<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class GiftedCoupons extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('gifted_coupons')) {
            Schema::create('gifted_coupons', function (Blueprint $table) {
                $table->increments('id');
                $table->string('coupon_code')->nullable();
                $table->unsignedInteger('coupon_id')->nullable();
                $table->string('plan_alias')->nullable();
                $table->nullableMorphs('host');
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
        Schema::dropIfExists('gifted_coupons');
    }
}
