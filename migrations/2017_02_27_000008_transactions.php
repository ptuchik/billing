<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Ptuchik\Billing\Constants\TransactionStatus;
use Ptuchik\Billing\Constants\TransactionType;
use Ptuchik\Billing\Factory;

class Transactions extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->increments('id');
                $table->text('name')->nullable();
                $table->longText('data')->nullable();
                $table->unsignedInteger('purchase_id')->nullable();
                $table->foreign('purchase_id')->references('id')->on('purchases')->onUpdate('cascade')
                    ->onDelete('set null');
                $table->unsignedInteger('subscription_id')->nullable();
                $table->foreign('subscription_id')->references('id')->on('subscriptions')->onUpdate('cascade')
                    ->onDelete('set null');
                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
                $table->string('gateway')->nullable();
                $table->string('reference')->nullable();
                $table->tinyInteger('type')->default(Factory::getClass(TransactionType::class)::INCOME);
                $table->tinyInteger('status')->default(Factory::getClass(TransactionStatus::class)::SUCCESS);
                $table->string('message')->nullable();
                $table->decimal('price', 12, 2)->default(0.00);
                $table->decimal('discount', 12, 2)->default(0.00);
                $table->decimal('summary', 12, 2)->default(0.00);
                $table->string('currency', 10)->default(config('currency.default'));
                $table->longText('coupons')->nullable();
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
        Schema::dropIfExists('transactions');
    }
}
