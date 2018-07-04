<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PackagesFeatures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('packages_features')) {
            Schema::create('packages_features', function (Blueprint $table) {
                $table->unsignedInteger('feature_id');
                $table->unsignedInteger('package_id');
                $table->string("package_type");
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
        if (Schema::hasTable('packages_features')) {
            Schema::dropIfExists('packages_features');
        }
    }
}
