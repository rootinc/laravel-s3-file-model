<?php

namespace RootInc\LaravelS3FileModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Aws\Laravel\AwsFacade as AWS;
use Psr\Http\Message\RequestInterface;

class FileModel extends Model
{
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'files';

	protected $guarded = [];

	protected $appends = [
		'fullUrl'
	];

	/**
	 * Allows calling $this->fullUrl
	 *
	 * @return string
	 */
	public function getFullUrlAttribute()
	{
		return Storage::disk(config('filesystems.default'))->url($this->location);
	}

	/**
	 * Since migration has been added with nullable field, we get the title from file_name if title is null
	 *
	 * @return string
	 */
	public function getTitleAttribute()
	{
		if ($this->attributes['title'] === null)
		{
			return $this->file_name;
		}
		else
		{
			return $this->attributes['title'];
		}
	}

	// STATIC HELPERS

	/**
	 * Makes an uploadedFile object.  Used for FilesTableSeeder as well
	 *
	 * @param string $file_name file name of the file to get extension from
	 * @param string $file_type file mime type of the file
	 * @param string $data_URI all the data
	 * @param string|null $relative_directory the directory where the file goes relative to UPLOAD_DIRECTORY
	 * @param boolean $public permission of the file
	 * @return \Illuminate\Http\UploadedFile
	 */
	public static function uploadAndCreateFileFromDataURI($file_name, $file_type, $data_URI, $relative_directory = null, $public = false)
	{
		$uploadedFile = self::makeUploadFileFromDataURI($file_name, $file_type, $data_URI);
		$upload_location = self::upload($uploadedFile, $relative_directory, $public);
		return self::createFile($file_name, $uploadedFile, $upload_location);
	}

	/**
	 * Makes an uploadedFile object.  Used for FilesTableSeeder as well
	 *
	 * @param string $file_name file name of the file to get extension from
	 * @param string $file_type file mime type of the file
	 * @param string $data_URI all the data
	 * @return \Illuminate\Http\UploadedFile
	 */
	public static function makeUploadFileFromDataURI($file_name, $file_type, $data_URI)
	{
		$data = explode(',', $data_URI)[1];

		file_put_contents('temp', base64_decode($data));

		$ext = pathinfo($file_name, PATHINFO_EXTENSION);

		return new UploadedFile(
			'temp',
			uniqid() . '.' . $ext,
			$file_type,
			null,
			false
		);
	}

	/**
	 * Add File model to DB
	 *
	 * @param string $file_name file_name of the file
	 * @param UploadedFile $file
	 * @param string $file_path relative to filesystem root. For S3 the bucket is the filesystem root.
	 * @return mixed
	 */
	public static function createFile($file_name, UploadedFile $file, $file_path)
	{
		// Add to database
		return self::create([
			'file_name' => $file_name,
			'file_type' => $file->getClientMimeType(),
			'location' => $file_path
		]);
	}

	/**
	 * Base upload method.
	 *
	 * @param UploadedFile $file
	 * @param string|null $relative_directory
	 * @param bool $public
	 * @return mixed
	 */
	public static function upload(UploadedFile $file, $relative_directory = null, $public = false)
	{
		$filesystem_driver = config('filesystems.default');

		$disk = Storage::disk($filesystem_driver);

		$directory_key = "filesystems.disks.${filesystem_driver}.directory";
		$directory = config($directory_key) . ($relative_directory
				? $relative_directory
				: '');

		// Public or private
		$visibility = $public ? 'public' : 'private';

		return $disk->putFileAs($directory, $file, $file->getClientOriginalName(), $visibility);
	}

	/**
	 * Delete an upload from the filesystem
	 *
	 * @param string $file_location
	 * @return bool
	 */
	public static function deleteUpload($file_location)
	{
		$disk = Storage::disk(config('filesystems.default'));

		return $disk->delete($file_location);
	}

	/**
	 * Check that a file exists
	 *
	 * @param $location
	 * @return bool
	 */
	public static function exists($location)
	{
		$disk = Storage::disk(config('filesystems.default'));

		return $disk->exists($location);
	}

	//S3 METHODS ONLY

	/**
	 * Creates File model and gets authorization to stream directly to S3
	 *
	 * @param FileModel $file
	 * @param string $file_name
	 * @param string $file_type
	 * @param null $relative_directory - directory withing your bucket's upload directory to store to
	 * @param bool $public
	 * @return array
	 * @throws \Exception
	 */
	public static function s3CreateUpload(FileModel $file, $file_name, $file_type, $relative_directory = null, $public = false)
	{
		$filesystem_driver = config('filesystems.default');
		if ($filesystem_driver !== 's3')
		{
			throw new \Exception("s3 is not the filesystem driver");
		}

		$ext = pathinfo($file_name, PATHINFO_EXTENSION);

		$directory =  config('filesystems.disks.s3.directory') . ($relative_directory
				? $relative_directory
				: '');

		$file->location = $directory . "/" . uniqid() . "." . $ext;
		$file->file_name = $file_name;
		$file->file_type = $file_type;

		$file->save();

		$upload_url = $file->s3AuthorizeUploadUrl($public);

		return compact('file', 'upload_url');
	}

	/**
	 * Builds authorization to stream to S3
	 *
	 * @param bool $public
	 * @param string $timing
	 * @return string - URI formatted
	 * @throws \Exception
	 */
	public function s3AuthorizeUploadUrl($public = false, $timing = '+24 hours')
	{
		$filesystem_driver = config('filesystems.default');
		if ($filesystem_driver !== 's3')
		{
			throw new \Exception("s3 is not the filesystem driver");
		}

		// Public or private
		$visibility = $public ? 'public-read' : 'private';

		$s3Client = AWS::createClient($filesystem_driver);

		$data = [
			'Bucket' => config('filesystems.disks.s3.bucket'),
			'Key' => $this->location,
			'ACL' => $visibility,
		];

		$cmd = $s3Client->getCommand('PutObject', $data);

		return $s3Client->createPresignedRequest($cmd, $timing)->getUri()->__toString();
	}

	/**
	 * @param bool $attachment
	 * @param string $timing
	 * @return string - URI formatted
	 * @throws \Exception
	 */
	public function s3AuthorizeDownloadUrl($attachment = false, $timing = '+5 minutes')
	{
		$filesystem_driver = config('filesystems.default');
		if ($filesystem_driver !== 's3')
		{
			throw new \Exception("s3 is not the filesystem driver");
		}

		$s3Client = AWS::createClient($filesystem_driver);
		$fileName = $this->file_name;

		$data = [
			'Bucket' => config('filesystems.disks.s3.bucket'),
			'Key' => $this->location,
			'ResponseContentDisposition' => "attachment; filename=$fileName",
		];

		if (!$attachment)
		{
			unset($data['ResponseContentDisposition']);
		}

		$cmd = $s3Client->getCommand('GetObject', $data);

		return $s3Client->createPresignedRequest($cmd, $timing)->getUri()->__toString();
	}
}
