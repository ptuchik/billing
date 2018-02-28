<?php

use Illuminate\Database\Migrations\Migration;

class Currencies extends Migration
{
    /**
     * Currencies table name
     * @var string
     */
    protected $table_name;

    /**
     * Create a new migration instance.
     */
    public function __construct()
    {
        $this->table_name = config('currency.drivers.database.table');
    }

    /**
     * Run the migrations.
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable($this->table_name)) {
            Schema::create($this->table_name, function ($table) {
                $table->increments('id')->unsigned();
                $table->string('name');
                $table->string('code', 10)->index();
                $table->string('symbol', 25);
                $table->string('format', 50);
                $table->string('exchange_rate', 100);
                $table->boolean('active')->default(true);
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
        Schema::drop($this->table_name);
    }
}
