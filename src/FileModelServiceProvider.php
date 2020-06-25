<?php

namespace RootInc\LaravelS3FileModel;

use Illuminate\Support\ServiceProvider;

class FileModelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole())
        {
            $this->publishes([
                __DIR__ . '/../templates/File.php' => app_path('File.php'),
            ], 'file');

            $this->publishes([
                __DIR__ . '/../templates/FileTest.php' => base_path('tests/Unit/FileTest.php'),
            ], 'file-test');

            $this->publishes([
                __DIR__ . '/../templates/FileFactory.php' => database_path('factories/FileFactory.php'),
            ], 'file-factory');
        }
        else
        {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/something.php', 'something'
            );
        }
    }
}