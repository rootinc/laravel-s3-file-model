<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table
                ->id();

            $table
                ->text('title')
                ->nullable()
                ->comment('Display pretty name of the file');

            $table
                ->text('file_name')
                ->comment('File name of the file');

            $table
                ->text('file_type')
                ->comment('MIME type format');

            $table
                ->text('location')
                ->comment('URL or path to file');

            $table
                ->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
}
