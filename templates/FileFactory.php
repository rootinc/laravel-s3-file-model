<?php

use App\File;
use bheller\ImagesGenerator\ImagesGeneratorProvider;
use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(File::class, function (Faker $faker) {
    $name = $faker->word;
    $file_extension = $faker->fileExtension;

    return [
        'file_name' => $name . '.' . $file_extension,
        'title' => null,
        'file_type' => $faker->mimeType,
        'location' =>
            !empty(File::getDirectory())
                ? File::getDirectory() . '/' . $faker->uuid . '.' . $file_extension
                : $faker->uuid . '.' . $file_extension,
    ];
});

$factory->state(File::class, 'image', function(Faker $faker){

    $image_data = buildAndUploadImage();

    return [
        'file_name' => $image_data['file_name'],
        'title' => $image_data['title'],
        'file_type' => $image_data['file_type'],
        'location' => $image_data['location'],
    ];
});

$factory->state(File::class, 'thumbnail', function(Faker $faker){

    $image_data = buildAndUploadImage(300, 200);

    return [
        'file_name' => $image_data['file_name'],
        'title' => $image_data['title'],
        'file_type' => $image_data['file_type'],
        'location' => $image_data['location'],
    ];
});

// Only declare this once
if(!function_exists('buildAndUploadImage')) {
    function buildAndUploadImage($width = 600, $height = 400) {
        $faker = \Faker\Factory::create();
        // Add ImageGenerator Provider
        $faker->addProvider(new ImagesGeneratorProvider($faker));

        $file_type = 'jpg';
        $file_name = $faker->word . "." . $file_type;
        $text = $faker->catchPhrase;
        $text_color = $faker->hexColor;
        $bg_color = $faker->hexColor;
        $file_mime_type = 'image/jpeg';

        // Make temp file
        // @todo: this should write to temp dir, not public dir
        $image_path = $faker->imageGenerator(storage_path('app/public'), $width, $height, $file_type, true, $text, $text_color, $bg_color);

        // Get temp file data
        $file_contents = file_get_contents($image_path);
        $data = base64_encode($file_contents);
        $uri = "data:{$file_mime_type};base64,".$data;

        // Do upload
        $uploadedFile = File::makeUploadFileFromDataURI($file_name, $file_mime_type, $uri);
        $upload_location = File::upload($uploadedFile, null, true);

        // Remove temp file
        unlink($image_path);

        return [
            'file_name' => $file_name,
            'title' => null,
            'file_type' => $file_mime_type,
            'location' => $upload_location,
        ];
    }
}