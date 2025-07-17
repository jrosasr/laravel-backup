<?php

namespace jrosasr\LaravelBackup\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;
use Carbon\Carbon;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

class BackupStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lbackup:storage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comprime en un zip todos los archivos de storage/private y storage/public, nombrando el archivo con la fecha actual.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '5G');
        set_time_limit(0);
        $this->info('Iniciando compresión de storage/private y storage/public...');

        $date = Carbon::now()->format('Y_m_d');
        $zipFileName = "lbackup_storage_{$date}.zip";
        $backupDriver = env('BACKUP_DRIVER', 'local');
        $localBackupDir = storage_path('backups');
        $zipFilePath = $backupDriver === 'local'
            ? $localBackupDir . "/{$zipFileName}"
            : storage_path("app/{$zipFileName}");

        $this->createZip($zipFilePath);

        if ($backupDriver === 'b2') {
            $this->uploadToB2($zipFilePath, $zipFileName, $date);
        }
        return Command::SUCCESS;
    }

    private function createZip($zipFilePath)
    {
        if (file_exists($zipFilePath)) {
            unlink($zipFilePath);
            $this->info("Archivo zip existente eliminado: {$zipFilePath}");
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath, ZipArchive::CREATE) !== true) {
            $this->error('No se pudo crear el archivo zip.');
            return false;
        }
        $folders = [
            storage_path('app/private'),
            storage_path('app/public'),
        ];
        foreach ($folders as $folder) {
            if (!is_dir($folder)) {
                $this->warn("El directorio no existe: {$folder}");
                continue;
            }
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(storage_path()) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        $this->info("Archivo zip creado exitosamente: {$zipFilePath}");
        return true;
    }

    private function uploadToB2($zipFilePath, $zipFileName, $date)
    {
        $b2FilePath = 'backup-files/' . $zipFileName;
        $this->info("Subiendo el archivo zip a Backblaze B2 (AWS SDK): {$b2FilePath}");
        $fileSize = filesize($zipFilePath);
        $useAwsSdk = $fileSize > 500 * 1024 * 1024;
        if ($useAwsSdk) {
            try {
                $s3 = new S3Client([
                    'version' => 'latest',
                    'region'  => env('B2_REGION'),
                    'endpoint' => env('B2_ENDPOINT'),
                    'credentials' => [
                        'key'    => env('B2_APPLICATION_KEY_ID'),
                        'secret' => env('B2_APPLICATION_KEY'),
                    ],
                    'use_path_style_endpoint' => true,
                ]);
                $partSize = 8 * 1024 * 1024;
                $totalParts = ceil($fileSize / $partSize);
                $progressBar = $this->output->createProgressBar($totalParts);
                $progressBar->setFormat('Subiendo: [%bar%] %percent:3s%% (%current%/%max%)');
                $currentPart = 0;
                $uploader = new \Aws\S3\MultipartUploader($s3, $zipFilePath, [
                    'bucket' => env('B2_BUCKET_NAME'),
                    'key'    => $b2FilePath,
                    'part_size' => $partSize,
                    'before_upload' => function () use (&$currentPart, $progressBar) {
                        $currentPart++;
                        $progressBar->advance();
                        $progressBar->display();
                    },
                ]);
                $uploader->upload();
                $progressBar->finish();
                $this->info("\nArchivo zip subido exitosamente a Backblaze B2 con AWS SDK: {$b2FilePath}");
                unlink($zipFilePath);
                $this->info("Archivo zip local eliminado tras subir a B2.");
            } catch (\Exception $e) {
                $this->error('Error al subir el archivo zip con AWS SDK a Backblaze B2: ' . $e->getMessage());
                return false;
            }
        } else {
            $uploaded = Storage::disk('b2')->put($b2FilePath, fopen($zipFilePath, 'r'));
            if ($uploaded) {
                $this->info("Archivo zip subido exitosamente a Backblaze B2: {$b2FilePath}");
                unlink($zipFilePath);
                $this->info("Archivo zip local eliminado tras subir a B2.");
            } else {
                $this->error('Error al subir el archivo zip a Backblaze B2. Revisa tus credenciales y configuración.');
                return false;
            }
        }
        $this->deleteOldBackups($date);
        return true;
    }

    private function deleteOldBackups($date)
    {
        $this->info('Eliminando archivos zip antiguos en Backblaze B2...');
        $files = Storage::disk('b2')->files('backup-files');
        $keep = [
            "lbackup_storage_{$date}.zip",
            "lbackup_storage_" . Carbon::yesterday()->format('Y_m_d') . ".zip"
        ];
        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/^lbackup_storage_\d{4}_\d{2}_\d{2}\.zip$/', $basename) && !in_array($basename, $keep)) {
                Storage::disk('b2')->delete($file);
                $this->info("Archivo zip eliminado de B2: {$file}");
            }
        }
    }
}
