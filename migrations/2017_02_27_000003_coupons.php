<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Ptuchik\Billing\Constants\CouponRedeemType;
use Ptuchik\Billing\Factory;

class Coupons extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->string('code');
                $table->text('amount')->nullable();
                $table->boolean('percent')->default(false);
                $table->integer('redeem')->default(Factory::getClass(CouponRedeemType::class)::INTERNAL);
                $table->boolean('prorate')->default(false);
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
        Schema::dropIfExists('coupons');
    }
}
