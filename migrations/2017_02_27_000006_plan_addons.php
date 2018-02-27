<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PlanAddons extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('plan_addons')) {
            Schema::create('plan_addons', function (Blueprint $table) {
                $table->unsignedInteger('plan_id');
                $table->unsignedInteger('coupon_id');
                $table->foreign('plan_id')->references('id')->on('plans')->onUpdate('cascade')->onDelete('cascade');
                $table->foreign('coupon_id')->references('id')->on('coupons')->onUpdate('cascade')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plan_addons');
    }
}
