<?php

namespace jrosasr\LaravelBackup\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lbackup:db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera una copia de seguridad de la base de datos PostgreSQL y la guarda según la configuración del driver (local o Backblaze B2).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando el respaldo de la base de datos PostgreSQL...');

        $database = config('database.connections.pgsql.database');
        $username = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');
        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');

        // Obtener el driver de backup desde la configuración del paquete
        $backupDriver = config('backup.driver', 'local'); // Por defecto 'local'

        // ********************************************************************
        // VERIFICACIÓN CLAVE: Comprobar si la variable de entorno DB_DOCKER_CONTAINER_NAME está configurada.
        // Si está configurada, se intentará ejecutar pg_dump dentro de Docker.
        // De lo contrario, se asumirá que pg_dump está disponible en el host.
        // Se mantiene la referencia a B2 para la variable de docker, ya que es donde se espera que esté configurada.
        $dockerContainerName = config('filesystems.disks.b2.docker_container_name');
        $this->info("DB_DOCKER_CONTAINER_NAME: {$dockerContainerName}");
        $useDocker = !empty($dockerContainerName);
        // ********************************************************************

        $timestamp = Carbon::now()->format('Y-m-d_His');
        $sqlFileName = "backup_{$database}_{$timestamp}.sql";

        // Directorio temporal local donde se creará el .sql antes de subirlo
        // Este directorio es solo para el archivo temporal antes de moverlo al disco final.
        $tempLocalPath = storage_path('app/temp-backups');
        if (!is_dir($tempLocalPath)) {
            // Asegura que el directorio temporal exista con permisos de escritura.
            mkdir($tempLocalPath, 0775, true);
        }
        $fullSqlFilePath = "{$tempLocalPath}/{$sqlFileName}";

        $command = '';

        if ($useDocker) {
            $this->info("Detectado DB_DOCKER_CONTAINER_NAME. Ejecutando pg_dump dentro del contenedor Docker '{$dockerContainerName}'...");
            // Comando para ejecutar pg_dump dentro del contenedor Docker.
            // El host para pg_dump dentro del contenedor debe ser '127.0.0.1' o 'localhost'
            // ya que se conecta a la base de datos que se ejecuta en el mismo contenedor.
            $command = "docker exec -e PGPASSWORD='{$password}' {$dockerContainerName} pg_dump -h 127.0.0.1 -p {$port} -U {$username} -d {$database}";
        } else {
            $this->info("DB_DOCKER_CONTAINER_NAME no configurado. Ejecutando pg_dump directamente en el host...");
            // Comando para ejecutar pg_dump directamente en el host.
            // La salida de pg_dump se redirige al archivo SQL temporal.
            $command = "PGPASSWORD=\"{$password}\" pg_dump -h {$host} -p {$port} -U {$username} -d {$database}";
        }

        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(3600); // Aumenta el tiempo de espera si tu DB es grande (3600 segundos = 1 hora).
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Capturar la salida estándar de pg_dump (el contenido del backup SQL).
            // Esto es válido tanto si se ejecuta en Docker (stdout del exec) como en el host (stdout del pg_dump).
            $backupContent = $process->getOutput();

            // Escribir el contenido capturado en el archivo SQL local temporal.
            file_put_contents($fullSqlFilePath, $backupContent);

            $this->info("Copia de seguridad SQL creada localmente en: {$fullSqlFilePath}");

            // Lógica para guardar o subir el backup según el driver configurado.
            if ($backupDriver === 'b2') {
                $this->uploadToB2($fullSqlFilePath, $sqlFileName);
            } else {
                // Por defecto o si el driver no es 'b2', se guarda localmente.
                $this->storeLocally($fullSqlFilePath, $sqlFileName);
            }

            $this->info('Proceso de respaldo completado con éxito.');
            return Command::SUCCESS;

        } catch (ProcessFailedException $exception) {
            $this->error('Error al ejecutar el comando de respaldo: ' . $exception->getMessage());
            $this->error('Salida de error: ' . $process->getErrorOutput());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('Ocurrió un error general durante el proceso de respaldo: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            // Limpiar el directorio temporal si está vacío al finalizar.
            if (is_dir($tempLocalPath) && count(scandir($tempLocalPath)) == 2) {
                rmdir($tempLocalPath);
            }
            // Eliminar el archivo SQL local temporal después de subirlo/guardarlo.
            if (file_exists($fullSqlFilePath)) {
                unlink($fullSqlFilePath);
                $this->info("Archivo SQL local temporal eliminado.");
            }
        }
    }

    /**
     * Stores the backup file locally.
     *
     * @param string $fullSqlFilePath The full path to the temporary SQL file.
     * @param string $sqlFileName The name of the SQL file.
     * @return void
     */
    protected function storeLocally(string $fullSqlFilePath, string $sqlFileName): void
    {
        $this->info("Guardando la copia de seguridad localmente...");

        // Obtener la ruta de la configuración del paquete.
        $localPath = config('backup.local_path', 'app/private/backups');
        // Construir la ruta completa del archivo dentro del disco local.
        $diskFilePath = "{$localPath}/{$sqlFileName}";

        try {
            // Guardar el contenido del archivo temporal en el disco 'local'.
            $uploaded = Storage::disk('local')->put($diskFilePath, file_get_contents($fullSqlFilePath));

            if ($uploaded) {
                $this->info("Copia de seguridad guardada exitosamente en el disco local: {$diskFilePath}");
            } else {
                $this->error('Error al guardar la copia de seguridad localmente.');
            }
        } catch (\Exception $e) {
            $this->error('Error al guardar la copia de seguridad localmente: ' . $e->getMessage());
        }
    }

    /**
     * Uploads the backup file to Backblaze B2.
     *
     * @param string $fullSqlFilePath The full path to the temporary SQL file.
     * @param string $sqlFileName The name of the SQL file.
     * @return void
     */
    protected function uploadToB2(string $fullSqlFilePath, string $sqlFileName): void
    {
        $this->info("Subiendo la copia de seguridad a Backblaze B2...");

        // Obtener la ruta de la configuración del paquete.
        $b2Path = config('backup.b2_path', 'backups');
        // Construir la ruta completa del archivo dentro del disco B2.
        $diskFilePath = "{$b2Path}/{$sqlFileName}";

        try {
            // Subir el contenido del archivo temporal al disco 'b2'.
            $uploaded = Storage::disk('b2')->put($diskFilePath, file_get_contents($fullSqlFilePath));

            if ($uploaded) {
                $this->info("Copia de seguridad subida exitosamente a Backblaze B2: {$diskFilePath}");
            } else {
                $this->error('Error al subir la copia de seguridad a Backblaze B2. Revisa tus credenciales y configuración.');
            }
        } catch (\Exception $e) {
            $this->error('Error al subir la copia de seguridad a Backblaze B2: ' . $e->getMessage());
        }
    }
}
