

# jrosasr/laravel-backup

Laravel package to automate and simplify database backup creation, with local or cloud storage. Ideal for developers and administrators seeking a robust and flexible solution, compatible with Docker environments.

---

## Table of Contents

- [jrosasr/laravel-backup](#jrosasrlaravel-backup)
  - [Table of Contents](#table-of-contents)
  - [Main Features](#main-features)
  - [Compatibility](#compatibility)
    - [Supported Databases](#supported-databases)
    - [Supported Storage Services](#supported-storage-services)
  - [Supported Laravel Versions](#supported-laravel-versions)
  - [Installation](#installation)
  - [Configuration](#configuration)
  - [Commands](#commands)
    - [1. Generate a database backup](#1-generate-a-database-backup)
    - [2. Store an existing backup in the configured storage](#2-store-an-existing-backup-in-the-configured-storage)
  - [Support](#support)
  - [License](#license)

---

## Main Features

- **Database Backup:** Full backups of your database.
- **Docker Support:** Runs `pg_dump` inside a Docker container if your database is dockerized.
- **Flexible Storage:**
  - **Local:** Stores backups on the server's filesystem.
  - **Backblaze B2:** Uploads backups to a Backblaze B2 bucket.
- **Configurable:** Easily choose the storage driver (`local` or `b2`).

---

## Compatibility

### Supported Databases

| Database     | Supported |
|--------------|:---------:|
| PostgreSQL   | ✅        |
| MySQL        | ❌        |
| MongoDB      | ❌        |
| SQLite       | ❌        |

### Supported Storage Services

| Service              | Supported |
|----------------------|:---------:|
| Backblaze B2         | ✅        |
| AWS S3               | ❌        |
| Google Cloud Storage | ❌        |
| Azure Blob Storage   | ❌        |

---

## Supported Laravel Versions

| Laravel Version   | Supported |
|-------------------|:---------:|
| 10.x              |    ✅     |
| 11.x              |    ✅     |
| 12.x              |    ✅     |

---

## Installation

Install the package via Composer:

```bash
composer require jrosasr/laravel-backup
```

---

## Configuration

1. Publish the configuration file:
   ```bash
   php artisan vendor:publish --tag=backup-config
   ```
   This will copy the `backup.php` file to your `config/` directory.

2. In your `.env` file, set the storage driver:
   ```env
   BACKUP_DRIVER=local # or BACKUP_DRIVER=b2
   ```

3. If you use Backblaze B2, set the credentials in `.env`:
   ```env
   B2_ENDPOINT="https://s3.us-west-004.backblazeb2.com"    # Replace with your B2 endpoint
   B2_APPLICATION_KEY_ID="your_b2_application_key_id"
   B2_APPLICATION_KEY="your_b2_application_key"
   B2_REGION="us-west-004"
   B2_BUCKET_NAME="your_b2_bucket_name"
   DB_DOCKER_CONTAINER_NAME="your_docker_container_name"   # Optional, if your DB is in Docker
   ```

4. Example configuration in `config/filesystems.php`:
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

## Commands

The package provides the following Artisan commands to manage your backups:

### 1. Generate a database backup

```bash
php artisan lbackup:db
```
Generates a database backup file in the `storage/app/private/backups` path and, depending on the configured driver (`local` or `b2`), stores or uploads it automatically.

### 2. Store an existing backup in the configured storage

```bash
php artisan lbackup:storage
```
Creates a `zip` file with the files located in `storage/private` and `storage/public`, and takes the existing backup file (e.g., `backup_2025_01_01.zip`) to store it in the configured driver (`local` or `b2`).

---

## Support

Have questions, suggestions, or found a bug? Open an issue in the repository.

---

## License

MIT
