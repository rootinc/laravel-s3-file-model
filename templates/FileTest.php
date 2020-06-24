<?php

namespace Tests\Unit;

use RootInc\LaravelS3FileModel\FileModelTest;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\File;

class FileTest extends FileModelTest
{
    use RefreshDatabase;

    
}
