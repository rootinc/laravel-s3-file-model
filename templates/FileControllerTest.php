<?php

namespace Tests\Feature;

use RootInc\LaravelS3FileModel\FileBaseControllerTest;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\User;

class FileControllerTest extends FileBaseControllerTest
{
    use RefreshDatabase;

    protected $route_prefix = "api.files.";

    protected function getFileFactory($count=1, $create=true, $properties=[])
    {
        $files;

        $factory = factory(File::class, $count);
        if ($create)
        {
            $files = $factory->create();
        }
        else
        {
            $files = $factory->make();
        }

        $len = count($files);
        if ($len === 1)
        {
            return $files[0];
        }
        else if ($len === 0)
        {
            return null;
        }
        else
        {
            return $files;
        }
    }

    protected function getFirstFile()
    {
        return File::first();
    }

    protected function getUserForIndex()
    {
        return factory(User::class)->states("superadmin")->create();
    }

    protected function getUserForIndexError()
    {
        return factory(User::class)->states("user")->create();
    }

    protected function getUserForCreate()
    {
        return factory(User::class)->states("superadmin")->create();
    }

    protected function getUserForUpdate()
    {
        return factory(User::class)->states("superadmin")->create();
    }

    protected function getUserForDelete()
    {
        return factory(User::class)->states("superadmin")->create();
    }
}
