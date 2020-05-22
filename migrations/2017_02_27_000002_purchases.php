<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Purchases extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('purchases')) {
            Schema::create('purchases', function (Blueprint $table) {
                $table->increments('id');
                $table->text('name')->nullable();
                $table->longText('data')->nullable();
                $table->morphs('host');
                $table->nullableMorphs('package');
                $table->nullableMorphs('reference');
                $table->boolean('active')->default(false);
                $table->longText('params')->nullable();
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
        Schema::dropIfExists('purchases');
    }
}
