<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
                $table->integer('redeem')
                    ->default(\Ptuchik\Billing\Factory::getClass(\Ptuchik\Billing\Constants\CouponRedeemType::class)::INTERNAL);
                $table->boolean('prorate')->default(false);
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
