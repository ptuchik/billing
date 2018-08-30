<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Features extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table) {
                $table->increments('id');
                $table->text('title')->nullable();
                $table->longText('description')->nullable();
                $table->longText('params')->nullable();
                $table->string('package_type');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('features')) {
            Schema::dropIfExists('features');
        }
    }
}
