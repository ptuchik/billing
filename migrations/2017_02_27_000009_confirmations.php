<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
                $table->integer('type')
                    ->default(\Ptuchik\Billing\Factory::getClass(\Ptuchik\Billing\Constants\ConfirmationType::class)::PAID);
                $table->integer('device')->default(\Ptuchik\CoreUtilities\Constants\DeviceType::ALL);
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
