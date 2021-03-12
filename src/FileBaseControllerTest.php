<?php

namespace RootInc\LaravelS3FileModel;

use Tests\TestCase;

use App\File;
use App\User;

class FileBaseControllerTest extends TestCase
{
	protected $route_prefix = "api.files.";

	protected function getFileFactory($count=1, $create=true, $properties=[])
	{
		$files;

		$factory = factory(File::class, $count);
		if ($create)
		{
			$files = $factory->create($properties);
		}
		else
		{
			$files = $factory->make($properties);
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
		$files = $this->getFileFactory(4);

		$response = $this->actingAs($this->getUserForIndex())->json('GET', route($this->route_prefix . 'index'));

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
		$files = $this->getFileFactory(4);

		$response = $this->actingAs($this->getUserForIndexError())->json('GET', route($this->route_prefix . 'index'));

		$response->assertStatus(403);
		$response->assertJson([
			'status' => "error",
			'payload' => [
				'errors' => []
			]
		]);
	}

	/** @test */
	public function it_checks_create_works_with_default_filesystem()
	{
		$response = $this->actingAs($this->getUserForCreate())->json('POST', route($this->route_prefix . 'store', [
			'file_name' => 'something cute.png',
			'file_type' => 'image/png',
			'file_data' => $this->get1x1RedPixelImage()
		]));

		$file = $this->getFirstFile();

		$response->assertStatus(200);
		$response->assertJson([
			'status' => "success",
			'payload' => [
				'file' => $file->toArray()
			]
		]);
	}

	/** @test */
	public function it_checks_create_works_with_s3_filesystem()
	{
		//force to s3 for this test
		config(['filesystems.default' => 's3']);

		$file = $this->getFileFactory(1, false);

		$response = $this->actingAs($this->getUserForCreate())->json('POST', route($this->route_prefix . 'store', [
			'file_name' => $file['file_name'],
			'file_type' => $file['file_type'],
		]));

		$response->assertStatus(200);
		$response->assertJson([
			'status' => "success",
			'payload' => [
				'file' => [
					'file_name' => $file['file_name'],
					'file_type' => $file['file_type']
				],
				// Not checking "upload_url" since it is dynamic nonsense hash
			]
		]);
	}

	/** @test */
	public function it_checks_update_works_for_default_filesystem()
	{
		$file = $this->getFileFactory();

		$response = $this->actingAs($this->getUserForUpdate())->json('PUT', route($this->route_prefix . 'update', [
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
	public function it_checks_update_works_with_s3_filesystem()
	{
		//force to s3 for this test
		config(['filesystems.default' => 's3']);

		$original_file = $this->getFileFactory();
		$update_file = $this->getFileFactory(1, false);

		$response = $this->actingAs($this->getUserForCreate())->json('PUT', route($this->route_prefix . 'update', [
			'file' => $original_file->id,
			'file_name' => $update_file['file_name'],
			'file_type' => $update_file['file_type'],
		]));

		$response->assertStatus(200);
		$response->assertJson([
			'status' => "success",
			'payload' => [
				'file' => [
					'id' => $original_file->id,
					'file_name' => $update_file['file_name'],
					'file_type' => $update_file['file_type']
				],
				// Not checking "upload_url" since it is dynamic nonsense hash
			]
		]);
	}

	/** @test */
	public function it_checks_delete_works()
	{
		$file = $this->getFileFactory();

		$response = $this->actingAs($this->getUserForDelete())->json('DELETE', route($this->route_prefix . 'destroy', [
			'file' => $file->id
		]));

		$response->assertStatus(200);
		$response->assertJson([
			'status' => "success",
			'payload' => []
		]);
	}
}
