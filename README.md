# Laravel S3 File Model

Provides a File Model that supports direct uploads / downloads from S3 for a Laravel App.

## Installation

1. `composer require rootinc/laravel-s3-file-model`
2. run `php artisan vendor:publish --provider="RootInc\LaravelS3FileModel\S3FileModelProvider"` to install `something`
3. run `php artisan make:model File` to create a `File` model in the `app` directory
4. In the `File.php` file, update `Illuminate\Database\Eloquent\Model;` with `use RootInc\LaravelS3FileModel\S3FileModel;`
5. In the `File.php` file, update `class File extends Model` with `class File extends S3FileModel` :tada:

## Contributing

Thank you for considering contributing to the Laravel S3 File Model! To encourage active collaboration, we encourage pull requests, not just issues.

If you file an issue, the issue should contain a title and a clear description of the issue. You should also include as much relevant information as possible and a code sample that demonstrates the issue. The goal of a issue is to make it easy for yourself - and others - to replicate the bug and develop a fix.

## License

The Laravel S3 File Model is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
