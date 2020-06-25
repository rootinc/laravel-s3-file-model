# Laravel S3 File Model

Provides a File Model that supports direct uploads / downloads from S3 for a Laravel App.

## Installation

1. `composer require rootinc/laravel-s3-file-model`
2. Run `php artisan vendor:publish --provider="RootInc\LaravelS3FileModel\FileModelServiceProvider"` to create `File` model in `app`, `FileTest` in `tests\Unit` and `FileFactory` in `database\factories`
3. Run `php artisan vendor:publish  --provider="Aws\Laravel\AwsServiceProvider` which adds `aws.php` in the `config` folder
4. In the `aws.php` file, change `'region' => env('AWS_REGION', 'us-east-1'),` to use `AWS_DEFAULT_REGION`
5. In `config\filesystems.php`, add key `'directory' => '', // root dir` to `public` and add key `'directory' => env('AWS_UPLOAD_FOLDER'),` to `s3`
6. :tada:

## Example Usage



## Contributing

Thank you for considering contributing to the Laravel S3 File Model! To encourage active collaboration, we encourage pull requests, not just issues.

If you file an issue, the issue should contain a title and a clear description of the issue. You should also include as much relevant information as possible and a code sample that demonstrates the issue. The goal of a issue is to make it easy for yourself - and others - to replicate the bug and develop a fix.

## License

The Laravel S3 File Model is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
