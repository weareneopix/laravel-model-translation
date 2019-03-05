<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTranslationsTable extends Migration
{
    /**
     * Run the migrations and create the translations table.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id');

            $table->string('language');
            $table->string('translatable_type');
            $table->string('translatable_id');

            $table->string('name');
            $table->string('value');
        });
    }

    /**
     * Reverse the migrations and drop the translations table.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('translations');
    }
}
