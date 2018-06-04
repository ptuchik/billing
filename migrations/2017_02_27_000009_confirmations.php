<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Ptuchik\Billing\Constants\ConfirmationType;
use Ptuchik\Billing\Factory;
use Ptuchik\CoreUtilities\Constants\DeviceType;

class Confirmations extends Migration
{
    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('confirmations')) {
            Schema::create('confirmations', function (Blueprint $table) {
                $table->increments('id');
                $table->string('icon')->nullable();
                $table->text('title')->nullable();
                $table->longText('body')->nullable();
                $table->text('button')->nullable();
                $table->text('url')->nullable();
                $table->integer('type')->default(Factory::getClass(ConfirmationType::class)::PAID);
                $table->integer('device')->default(DeviceType::ALL);
                $table->nullableMorphs('package');
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
        Schema::dropIfExists('confirmations');
    }
}
