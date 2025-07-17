
# jrosasr/laravel-backup

Paquete de Laravel para automatizar y simplificar la creación de copias de seguridad de bases de datos, con almacenamiento local o en la nube. Ideal para desarrolladores y administradores que buscan una solución robusta y flexible, compatible con entornos Docker.

---

## Tabla de Contenidos

- [jrosasr/laravel-backup](#jrosasrlaravel-backup)
  - [Tabla de Contenidos](#tabla-de-contenidos)
  - [Características Principales](#características-principales)
  - [Compatibilidad](#compatibilidad)
    - [Bases de datos soportadas](#bases-de-datos-soportadas)
    - [Servicios de almacenamiento soportados](#servicios-de-almacenamiento-soportados)
  - [Versiones de Laravel Compatibles](#versiones-de-laravel-compatibles)
  - [Instalación](#instalación)
  - [Configuración](#configuración)
  - [Comandos](#comandos)
    - [1. Generar respaldo de la base de datos](#1-generar-respaldo-de-la-base-de-datos)
    - [2. Almacenar un respaldo existente en el almacenamiento configurado](#2-almacenar-un-respaldo-existente-en-el-almacenamiento-configurado)
  - [Soporte](#soporte)
  - [Licencia](#licencia)

---

## Características Principales

- **Respaldo de Base de Datos:** Copias de seguridad completas de tu base de datos.
- **Soporte Docker:** Ejecuta `pg_dump` dentro de un contenedor Docker si tu base de datos está dockerizada.
- **Almacenamiento Flexible:**
  - **Local:** Guarda los respaldos en el sistema de archivos del servidor.
  - **Backblaze B2:** Sube los respaldos a un bucket de Backblaze B2.
- **Configurable:** Elige el driver de almacenamiento (`local` o `b2`) fácilmente.

---

## Compatibilidad

### Bases de datos soportadas

| Base de datos   | Soportado |
|----------------|:---------:|
| PostgreSQL     | ✅        |
| MySQL          | ❌        |
| MongoDB        | ❌        |
| SQLite         | ❌        |

### Servicios de almacenamiento soportados

| Servicio             | Soportado |
|----------------------|:---------:|
| Backblaze B2         | ✅        |
| AWS S3               | ❌        |
| Google Cloud Storage | ❌        |
| Azure Blob Storage   | ❌        |

---

## Versiones de Laravel Compatibles

| Versión de Laravel   | Soportado |
|----------------------|:---------:|
| 10.x                 |    ✅     |
| 11.x                 |    ✅     |
| 12.x                 |    ✅     |

---

## Instalación

Instala el paquete vía Composer:

```bash
composer require jrosasr/laravel-backup
```

---

## Configuración

1. Publica el archivo de configuración:
   ```bash
   php artisan vendor:publish --tag=backup-config
   ```
   Esto copiará el archivo `backup.php` a tu directorio `config/`.

2. En tu archivo `.env`, define el driver de almacenamiento:
   ```env
   BACKUP_DRIVER=local # o BACKUP_DRIVER=b2
   ```

3. Si usas Backblaze B2, configura las credenciales en `.env`:
   ```env
   B2_ENDPOINT="https://s3.us-west-004.backblazeb2.com"    # Reemplaza con tu endpoint de B2
   B2_APPLICATION_KEY_ID="your_b2_application_key_id"
   B2_APPLICATION_KEY="your_b2_application_key"
   B2_REGION="us-west-004"
   B2_BUCKET_NAME="your_b2_bucket_name"
   DB_DOCKER_CONTAINER_NAME="your_docker_container_name"   # Opcional, si tu DB está en Docker
   ```

4. Ejemplo de configuración en `config/filesystems.php`:
   ```php
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
       'root' => storage_path('app/private'),
       'serve' => true,
       'throw' => false,
       'report' => false,
   ],
   ```

---

## Comandos

El paquete proporciona los siguientes comandos Artisan para gestionar tus copias de seguridad:

### 1. Generar respaldo de la base de datos

```bash
php artisan lbackup:db
```
Genera un archivo de respaldo de la base de datos en la ruta `storage/app/private/backups` y, según el driver configurado (`local` o `b2`), lo almacena o sube automáticamente.

### 2. Almacenar un respaldo existente en el almacenamiento configurado

```bash
php artisan lbackup:storage
```
Genera un archivo `zip` con los archivos ubicados en `storage/private` y `storage/public`, y toma el archivo de respaldo existente (por ejemplo, `backup_2025_01_01.zip`) para almacenarlo en el driver configurado (`local` o `b2`).

---

## Soporte

¿Tienes dudas, sugerencias o encontraste un bug? Abre un issue en el repositorio o contacta al autor.

---

## Licencia

MIT
