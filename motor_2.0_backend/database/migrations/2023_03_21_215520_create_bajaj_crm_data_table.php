<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBajajCrmDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('bajaj_crm_data')) {
            Schema::create('bajaj_crm_data', function (Blueprint $table) {
                $table->id();
                $table->integer('user_product_journey_id')->nullable();
                $table->json('payload')->nullable();
                $table->enum('status', [0, 1, 2, 3])->nullable();
                $table->tinyInteger("attempt")->default(0);
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
        Schema::dropIfExists('bajaj_crm_data');
    }
}
