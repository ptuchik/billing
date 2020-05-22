<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Ptuchik\Billing\Constants\OrderStatus;
use Ptuchik\Billing\Factory;

/**
 * Class Orders
 */
class Orders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->increments('id');
                $table->nullableMorphs('reference');
                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
                $table->morphs('host');
                $table->integer('status')->default(Factory::getClass(OrderStatus::class)::PENDING);
                $table->string('action');
                $table->longText('params')->nullable();
                $table->timestamps();
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
        Schema::dropIfExists('orders');
    }
}
