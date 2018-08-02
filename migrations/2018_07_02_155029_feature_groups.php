<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class FeatureGroups extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('feature_groups')) {
            Schema::create('feature_groups', function (Blueprint $table) {
                $table->increments('id');
                $table->text('title')->nullable();
                $table->longText('description')->nullable();
                $table->longText('params')->nullable();
                $table->integer('ordering')->default(1);
                $table->string('package_type');
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
        if (Schema::hasTable('feature_groups')) {
            Schema::dropIfExists('feature_groups');
        }
    }
}
