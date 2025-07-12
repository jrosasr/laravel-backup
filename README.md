# jrosasr/laravel-backup

## Propósito del Paquete

`jrosasr/laravel-backup` es un paquete de Laravel diseñado para simplificar y automatizar la creación de copias de seguridad de tu base de datos PostgreSQL. Permite generar respaldos de forma eficiente y flexible, ofreciendo la opción de almacenar estas copias de seguridad tanto localmente como en el servicio de almacenamiento en la nube Backblaze B2.

El paquete está pensado para desarrolladores y administradores de sistemas que necesitan una solución robusta para proteger sus datos de aplicaciones Laravel, con soporte para entornos Dockerizados.

## Características Principales

* **Respaldo de Base de Datos PostgreSQL:** Genera copias de seguridad completas de tu base de datos PostgreSQL.
* **Soporte Docker:** Capaz de ejecutar `pg_dump` directamente dentro de un contenedor Docker si tu base de datos se ejecuta en uno.
* **Almacenamiento Flexible:**
    * **Local:** Guarda las copias de seguridad directamente en el sistema de archivos de tu servidor (en la ruta configurada).
    * **Backblaze B2:** Sube automáticamente las copias de seguridad a un bucket de Backblaze B2 para almacenamiento en la nube seguro y escalable.
* **Configurable:** Permite definir el driver de almacenamiento (local o B2) a través de la configuración del paquete.

## Versiones de Laravel Compatibles

Este paquete es compatible con las siguientes versiones de Laravel:

* Laravel 10.x
* Laravel 11.x
* Laravel 12.x

## Instalación

Puedes instalar el paquete a través de Composer:

```
composer require jrosasr/laravel-backup
```

## Configuración del Paquete

Después de instalar el paquete, debes publicar su archivo de configuración para poder personalizarlo:

```
php artisan vendor:publish --tag=backup-config
```

Esto copiará el archivo backup.php a tu directorio config/.

### Configuración del Driver de Almacenamiento

En tu archivo .env, puedes especificar el driver de almacenamiento predeterminado:

```
BACKUP_DRIVER=local # o BACKUP_DRIVER=b2
```

Si eliges b2, asegúrate de configurar tus credenciales de Backblaze B2 en tu archivo .env y en config/filesystems.php:

#### Ejemplo de .env para B2:

```
B2_ENDPOINT=[https://s3.us-west-004.backblazeb2.com](https://s3.us-west-004.backblazeb2.com) # Reemplaza con tu endpoint de B2
B2_APPLICATION_KEY_ID=your_b2_application_key_id
B2_APPLICATION_KEY=your_b2_application_key
B2_REGION=us-west-004 # Reemplaza con tu región
B2_BUCKET_NAME=your_b2_bucket_name
DB_DOCKER_CONTAINER_NAME=your_docker_container_name # Opcional, si tu DB está en Docker
```

Ejemplo de config/filesystems.php (asegúrate de que el disco 'b2' esté configurado como 's3' driver):

```
'b2' => [
    'driver' => 's3',
    'endpoint' => env('B2_ENDPOINT'),
    'key' => env('B2_APPLICATION_KEY_ID'),
    'secret' => env('B2_APPLICATION_KEY'),
    'region' => env('B2_REGION'),
    'bucket' => env('B2_BUCKET_NAME'),
    'throw' => false,
    'docker_container_name' => env('DB_DOCKER_CONTAINER_NAME')
],
'local' => [
    'driver' => 'local',
    'root' => storage_path('app/private'), // Asegúrate de que esta ruta sea correcta
    'serve' => true,
    'throw' => false,
    'report' => false,
],
```

## Uso
Para generar una copia de seguridad de tu base de datos, simplemente ejecuta el siguiente comando Artisan:

```
php artisan lbackup:store
```

El paquete utilizará el driver especificado en tu variable BACKUP_DRIVER del .env para almacenar el backup.

