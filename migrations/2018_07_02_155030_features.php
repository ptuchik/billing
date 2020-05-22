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
                $table->integer('ordering')->default(1);
                $table->string('package_type');
                $table->unsignedInteger('group_id')->nullable();
                $table->foreign('group_id')->references('id')->on('feature_groups')->onUpdate('cascade')
                    ->onDelete('set null');
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
