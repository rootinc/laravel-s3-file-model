<?php

namespace Tests\Feature;

use RootInc\LaravelS3FileModel\FileBaseControllerTest;

use App\User;

class FileControllerTest extends FileBaseControllerTest
{
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
