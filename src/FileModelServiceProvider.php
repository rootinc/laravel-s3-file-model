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
                __DIR__ . '/../config/something.php' => config_path('something.php'),
            ], 'something-config');
        }
        else
        {
            $this->mergeConfigFrom(
                __DIR__ . '/../config/something.php', 'something'
            );
        }
    }
}