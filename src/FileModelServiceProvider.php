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
                __DIR__ . '/../templates/FileControllerTest.php' => base_path('tests/Unit/FileControllerTest.php'),
            ], 'file-controller-test');

            $this->publishes([
                __DIR__ . '/../templates/FileController.php' => app_path('Http/Controllers/FileController.php'),
            ], 'file-controller');

            $this->publishes([
                __DIR__ . '/../templates/FileFactory.php' => database_path('factories/FileFactory.php'),
            ], 'file-factory');

            $this->publishes([
                __DIR__ . '/../templates/2020_03_12_152841_create_files_table.php' => database_path('migrations/2020_03_12_152841_create_files_table.php'),
            ], 'file-table');
        }
    }
}