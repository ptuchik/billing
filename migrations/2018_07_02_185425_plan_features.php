<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PlanFeatures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('plan_features')) {
            Schema::create('plan_features', function (Blueprint $table) {
                $table->unsignedInteger('feature_id');
                $table->unsignedInteger('plan_id');
                $table->text('limit')->nullable();
                $table->foreign('feature_id')->references('id')->on('features')->onUpdate('cascade')->onDelete('cascade');
                $table->foreign('plan_id')->references('id')->on('plans')->onUpdate('cascade')->onDelete('cascade');
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
        if (Schema::hasTable('plan_features')) {
            Schema::dropIfExists('plan_features');
        }
    }
}
