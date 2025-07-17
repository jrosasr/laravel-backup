<?php

namespace jrosasr\LaravelBackup;

use Illuminate\Support\ServiceProvider;
use jrosasr\LaravelBackup\Console\Commands\BackupDatabase;
use jrosasr\LaravelBackup\Console\Commands\BackupStorage;

class BackupServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BackupDatabase::class,
                BackupStorage::class,
            ]);
        }
    }
}
