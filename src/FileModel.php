<?php

namespace RootInc\LaravelS3FileModel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Aws\Laravel\AwsFacade as AWS;

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

    protected static function authorizeS3Upload($relative_path, $acl = 'private')
    {
        $filesystem_driver = config('filesystems.default');
        if ($filesystem_driver !== 's3')
        {
            return null;
        }

        $s3Client = AWS::createClient($filesystem_driver);

        $cmd = $s3Client->getCommand('PutObject', [
            'Bucket' => getenv('AWS_BUCKET'),
            'Key' => getenv('AWS_UPLOAD_FOLDER') . $relative_path,
            'ACL' => $acl
        ]);

        $request = $s3Client->createPresignedRequest($cmd, '+24 hours');

        return $request->getUri();
    }

    public function downloadUrl()
    {
        $filesystem_driver = config('filesystems.default');
        if ($filesystem_driver !== 's3')
        {
            return null;
        }

        $s3Client = AWS::createClient($filesystem_driver);
        $fileName = $this->file_name;

        $command = $s3Client->getCommand('GetObject', [
            'Bucket' => getenv('AWS_BUCKET'),
            'Key' => $this->location,
            'ResponseContentDisposition' => "attachment; filename=$fileName",
        ]);

        return $s3Client->createPresignedRequest($command, '+5 minutes')->getUri()->__toString();
    }
}