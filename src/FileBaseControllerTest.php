<?php

namespace RootInc\LaravelS3FileModel;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\File;
use App\User;

class FileBaseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function getUserForIndex()
    {
        return factory(User::class)->create();
    }

    protected function getUserForIndexError()
    {
        return factory(User::class)->create();
    }

    protected function getUserForCreate()
    {
        return factory(User::class)->create();
    }

    protected function getUserForUpdate()
    {
        return factory(User::class)->create();
    }

    protected function getUserForDelete()
    {
        return factory(User::class)->create();
    }

    /** @test */
    public function it_checks_if_index_work()
    {
        $files = factory(File::class, 4)->create();

        $response = $this->actingAs($this->getUserForIndex())->json('GET', route('api.files.index'));

        $response->assertStatus(200);
        $response->assertJson([
            "status" => "success",
            "payload" => [
                'files' => [
                    'data' => $files->sortBy('file_name')->values()->toArray()
                ]
            ]
        ]);
    }

    /** @test */
    public function it_checks_if_index_gives_error()
    {
        $files = factory(File::class, 4)->create();

        $response = $this->actingAs($this->getUserForIndexError())->json('GET', route('api.files.index'));

        $response->assertStatus(403);
        $response->assertJson([
            'status' => "error",
            'payload' => [
                'errors' => []
            ]
        ]);
    }

    /** @test */
    public function it_checks_create_works()
    {
        $response = $this->actingAs($this->getUserForCreate())->json('POST', route('api.files.store', [
            'file_name' => 'something cute.png',
            'file_type' => 'image/png',
            'file_data' => $this->get1x1RedPixelImage()
        ]));

        $file = File::first();

        $response->assertStatus(200);
        $response->assertJson([
            'status' => "success",
            'payload' => [
                'file' => $file->toArray()
            ]
        ]);
    }

    /** @test */
    public function it_checks_update_works()
    {
        $file = factory(File::class)->create();

        $response = $this->actingAs($this->getUserForUpdate())->json('PUT', route('api.files.update', [
            'file' => $file->id,
            'file_name' => 'something cute.png',
            'file_type' => 'image/png',
            'file_data' => $this->get1x1RedPixelImage()
        ]));

        $file->refresh();

        $response->assertStatus(200);
        $response->assertJson([
            'status' => "success",
            'payload' => [
                'file' => $file->toArray()
            ]
        ]);
    }

    /** @test */
    public function it_checks_delete_works()
    {
        $file = factory(File::class)->create();

        $response = $this->actingAs($this->getUserForDelete())->json('DELETE', route('api.files.destroy', [
            'file' => $file->id
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => "success",
            'payload' => []
        ]);
    }
}
