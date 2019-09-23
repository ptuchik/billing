<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Plans extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->increments('id');
                $table->string('icon')->nullable();
                $table->text('name')->nullable();
                $table->string('alias')->unique();
                $table->integer('visibility')->default(1);
                $table->integer('ordering')->default(1);
                $table->text('agreement')->nullable();
                $table->text('description')->nullable();
                $table->text('features_header')->nullable();
                $table->text('features')->nullable();
                $table->text('price')->nullable();
                $table->integer('trial_days')->default(0);
                $table->integer('billing_frequency')->default(0);
                $table->morphs('package');
                $table->longText('params')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plans');
    }
}
