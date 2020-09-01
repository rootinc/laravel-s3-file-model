<?php

namespace Tests\Feature;

use RootInc\LaravelS3FileModel\FileBaseControllerTest;

use App\User;

class FileControllerTest extends FileBaseControllerTest
{
    public function __construct()
    {
        $this->user_index = factory(User::class)->create();
        $this->user_index_error = factory(User::class)->create();
        $this->user_create = factory(User::class)->create();
        $this->user_update = factory(User::class)->create();
        $this->user_delete = factory(User::class)->create();
    }
}
