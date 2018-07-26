<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PackageFeatures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('package_features')) {
            Schema::create('package_features', function (Blueprint $table) {
                $table->unsignedInteger('feature_id');
                $table->morphs('package');
                $table->foreign('feature_id')->references('id')->on('features')->onUpdate('cascade')->onDelete('cascade');
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
        if (Schema::hasTable('package_features')) {
            Schema::dropIfExists('package_features');
        }
    }
}
