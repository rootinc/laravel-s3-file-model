<?php

namespace RootInc\LaravelS3FileModel;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;

use App\File;

class FileModelTest extends TestCase
{
    /** @test */
    public function it_has_the_proper_fields()
    {
        $file = factory(File::class)->create([
            "file_name" => "asdf.pdf",
            "title" => "fdsa",
            "file_type" => "application/pdf",
            "location" => "the/moon",
        ]);

        //check attributes are consistent
        $this->assertCount(7, $file->getAttributes());

        //check fields work
        $this->assertEquals("asdf.pdf", $file->file_name);
        $this->assertEquals("fdsa", $file->title);
        $this->assertEquals("application/pdf", $file->file_type);
        $this->assertEquals("the/moon", $file->location);
    }

    /** @test */
    public function getTitleAttribute_returns_title()
    {
        $file = factory(File::class)->create([
            'file_name' => "Rabbits.pdf",
            'title' => "Rabbits"
        ]);

        $this->assertEquals($file->title, "Rabbits");
    }

    /** @test */
    public function getFullUrlAttribute_returns_true_for_a_valid_file()
    {
        $file = factory(File::class)->create([
            'location' => config('filesystems.default') . '/somefile.jpg'
        ]);

        $this->assertEquals($file->fullUrl, Storage::disk()->url($file->location));
    }

    /** @test */
    public function exists_returns_true_with_valid_file()
    {
        //test file
        $file = FileModel::uploadAndCreateFileFromDataURI("cat.png", 'image/png', $this->get1x1RedPixelImage());

        $value = FileModel::exists($file->location);
        $this->assertTrue($value);
    }

    /** @test */
    public function exists_returns_false_with_invalid_file()
    {
        $file = new FileModel();

        $value = FileModel::exists("chickenasdf.jpg");
        $this->assertFalse($value);
    }

    /** @test */
    public function makeUploadFileFromDataURI_returns_uploaded_file()
    {
        //test file
        $uploadedFile = FileModel::makeUploadFileFromDataURI('dog.png', 'image/png', $this->get1x1RedPixelImage());

        $this->assertInstanceOf(UploadedFile::class, $uploadedFile);
    }

    /** @test */
    public function upload_uploads_a_file_to_s3()
    {
        //force to s3 for this test
        config(['filesystems.default' => 's3']);

        //test file
        $uploadedFile = FileModel::makeUploadFileFromDataURI('dog.png', 'image/png', $this->get1x1RedPixelImage());
        $upload_location = FileModel::upload($uploadedFile, null, false);

        $value = FileModel::exists($upload_location);
        $this->assertTrue($value);
    }

    /** @test */
    public function createFile_creates_a_file_model()
    {
        $name = "turtle";

        //test file (seperated out so we can do assertions)
        $uploadedFile = FileModel::makeUploadFileFromDataURI('dog.png', 'image/png', $this->get1x1RedPixelImage());
        $upload_location = FileModel::upload($uploadedFile, null, false);

        $file = FileModel::createFile($name, $uploadedFile, $upload_location);

        $this->assertEquals($file->file_name, $name);
        $this->assertEquals($file->file_type, $uploadedFile->getClientMimeType());
        $this->assertEquals($file->location, $upload_location);
    }

    /** @test */
    public function uploadAndCreateFileFromDataURI_creates_a_file_model()
    {
        //test file
        $file = FileModel::uploadAndCreateFileFromDataURI("rabbit.png", 'image/png', $this->get1x1RedPixelImage());

        $this->assertTrue(FileModel::exists($file->location));
    }

    /** @test */
    public function deleteUpload_deletes_a_file_from_s3()
    {
        //force to s3 for this test
        config(['filesystems.default' => 's3']);

        $name = "donkey";

        $file = FileModel::uploadAndCreateFileFromDataURI('dog.png', 'image/png', $this->get1x1RedPixelImage());

        FileModel::deleteUpload($file->location);

        $value = FileModel::exists($file->location);
        $this->assertFalse($value);
    }
}
