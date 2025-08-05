<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvHdfcErgoRtoLocationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cv_hdfc_ergo_rto_location', function (Blueprint $table) {
            $table->text('Num_Country_Code')->nullable();
            $table->text('Num_State_Code')->nullable();
            $table->text('Txt_Rto_Location_code')->nullable();
            $table->text('Txt_Rto_Location_desc')->nullable();
            $table->text('Num_Vehicle_Class_code')->nullable();
            $table->text('Txt_Registration_zone')->nullable();
            $table->text('Txt_Status')->nullable();
            $table->text('Num_Vehicle_Subclass_Code')->nullable();
            $table->text('EMG_COV')->nullable();
            $table->text('IsActive')->nullable();
            $table->text('IsActive_For_GCV')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cv_hdfc_ergo_rto_location');
    }
}
