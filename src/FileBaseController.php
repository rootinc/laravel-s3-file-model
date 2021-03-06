<?php

namespace RootInc\LaravelS3FileModel;

use App\Http\Controllers\Controller;
use App\File;

use ReflectionClass;

use Illuminate\Http\Request;

class FileBaseController extends Controller
{
	protected static function getFileModel()
	{
		$rc = new ReflectionClass(File::class);
    return $rc->newInstance();
	}

	public function index(Request $request)
	{
		$search = $request->input('search') ? $request->input('search') : "";

		$files = static::getFileModel()->where(function($q) use ($search)  {
			$q->where('file_name', 'ILIKE', "%$search%")
				->orWhere('title', 'ILIKE', "%$search%");
		})
			->orderBy('file_name')
			->paginate();

		return response()->json([
			'status' => "success",
			'payload' => [
				'files' => $files,
			]
		]);
	}

	/**
	 * Creates and uploads a file
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function store(Request $request)
	{
		$file_name = $request->input('file_name');
		$file_type = $request->input('file_type');
		$file_data = $request->input('file_data');

		$relative_directory = $request->input('directory') ?? null;
		$public = $request->input('public') ?? false;

		//this is set up with two distinct routes so that the FileUploader component can still call the `store / post` method on the file object
		//in the cloud route, we are doing a direct upload to s3
		//in the other route, we are doing a an upload to the server, then to s3
		if (!$file_data)
		{
			$data = static::getFileModel()->s3CreateUpload(static::getFileModel(), $file_name, $file_type, $relative_directory, $public);
			$file = $data['file'];
			$upload_url = $data['upload_url'];

			$file->refresh();

			return response()->json([
				'status' => "success",
				'payload' => [
					'file' => $file,
					'upload_url' => $upload_url,
				]
			]);
		}
		else
		{
			$file = static::getFileModel()->uploadAndCreateFileFromDataURI($file_name, $file_type, $file_data, $relative_directory, $public);
			$file->refresh();

			return response()->json([
				'status' => "success",
				'payload' => [
					'file' => $file
				]
			]);
		}
	}

	/**
	 * Updates and uploads replacement file
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function update(Request $request, $file_id)
	{
		$file = static::getFileModel()->findOrFail($file_id);

		//this is set up with two distinct routes so that the FileUploader component can still call the `update / put` method on the file object
		//in the title route, we only need to update the title of the file
		//in the other route, we are doing a replacement file
		if ($request->input('title'))
		{
			$file->title = $request->input('title');
			$file->save();

			return response()->json([
				'status' => "success",
				'payload' => [
					'file' => $file
				]
			]);
		}
		else
		{
			static::getFileModel()->deleteUpload($file->location);

			$file_name = $request->input('file_name');
			$file_type = $request->input('file_type');
			$file_data = $request->input('file_data');

			$relative_directory = $request->input('directory') ?? null;
			$public = $request->input('public') ?? false;

			//this is set up with two distinct routes so that the FileUploader component can still call the `update / put` method on the file object
			//in the cloud route, we are doing a direct upload to s3
			//in the other route, we are doing a an upload to the server, then to s3
			if (!$file_data)
			{
				$data = static::getFileModel()->s3CreateUpload($file, $file_name, $file_type, $relative_directory, $public);
				$file = $data['file'];
				$upload_url = $data['upload_url'];

				$file->refresh();

				return response()->json([
					'status' => "success",
					'payload' => [
						'file' => $file,
						'upload_url' => $upload_url,
					]
				]);
			}
			else
			{
				$uploadedFile = static::getFileModel()->makeUploadFileFromDataURI($file_name, $file_type, $file_data);
				$upload_location = static::getFileModel()->upload($uploadedFile, $relative_directory, $public);

				$file->file_name = $file_name;
				$file->file_type = $uploadedFile->getClientMimeType();
				$file->location = $upload_location;

				$file->save();

				return response()->json([
					'status' => "success",
					'payload' => [
						'file' => $file
					]
				]);
			}
		}
	}

	/**
	 * Deletes and deletes uploaded file
	 *
	 * @param \Illuminate\Http\Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function destroy(Request $request, $file_id)
	{
		$file = static::getFileModel()->findOrFail($file_id);

		static::getFileModel()->deleteUpload($file->location);

		$file->delete();

		return response()->json([
			'status' => "success",
			'payload' => []
		]);
	}
}