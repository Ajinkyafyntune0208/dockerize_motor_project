<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeyWebserviceDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('webservice_request_response_data', function (Blueprint $table) {
            $table->bigInteger('enquiry_id')->change();
            $table->foreign('enquiry_id')->references('user_product_journey_id')->on('user_product_journey'); //->onUpdate('NO ACTION')->onDelete('NO ACTION');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('webservice_request_response_data', function (Blueprint $table) {
            //
        });
    }
}
