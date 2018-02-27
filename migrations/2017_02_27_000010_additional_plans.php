<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AdditionalPlans extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('additional_plans')) {
            Schema::create('additional_plans', function (Blueprint $table) {
                $table->unsignedInteger('plan_id');
                $table->foreign('plan_id')->references('id')->on('plans')->onUpdate('cascade')->onDelete('cascade');
                $table->unsignedInteger('additional_plan_id');
                $table->foreign('additional_plan_id')->references('id')->on('plans')->onUpdate('cascade')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('additional_plans');
    }
}
