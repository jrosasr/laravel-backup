<?php

namespace jrosasr\LaravelBackup;

use Illuminate\Support\ServiceProvider;
use jrosasr\LaravelBackup\Console\Commands\BackupDatabase;


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
            ]);
        }
    }
}
