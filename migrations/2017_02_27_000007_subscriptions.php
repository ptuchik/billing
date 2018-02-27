<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Subscriptions extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->increments('id');
                $table->text('name')->nullable();
                $table->string('alias')->nullable();
                $table->text('params')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->unsignedInteger('purchase_id');
                $table->decimal('price', 12, 2)->default(0.00);
                $table->string('currency', 10)->default(config('currency.default'));
                $table->longText('coupons')->nullable();
                $table->longText('addons')->nullable();
                $table->integer('billing_frequency')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('next_billing_date')->nullable();
                $table->timestamps();
                $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('set null');
                $table->foreign('purchase_id')->references('id')->on('purchases')->onUpdate('cascade')
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
        Schema::dropIfExists('subscriptions');
    }
}
