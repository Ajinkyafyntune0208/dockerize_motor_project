<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCvRelianceModalMasterTable extends Migration
{
    public function up()
    {
        Schema::create('cv_reliance_modal_master', function (Blueprint $table) {

		$table->text('Model_ID_PK')->nullable();
		$table->text('Make_id_pk')->nullable();
		$table->text('Make_Name')->nullable();
		$table->text('Model_Name')->nullable();
		$table->text('Variance')->nullable();
		$table->text('Veh_Type_Name')->nullable();
		$table->text('Veh_Sub_Type_Name')->nullable();
		$table->text('Wheels')->nullable();
		$table->text('Manufacturing_Year')->nullable();
		$table->text('Operated_By')->nullable();
		$table->text('CC')->nullable();
		$table->text('Unit_Name')->nullable();
		$table->text('Gross_Weight')->nullable();
		$table->text('Seating_Capacity')->nullable();
		$table->text('Carrying_Capacity')->nullable();
		$table->text('ModelStatus')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cv_reliance_modal_master');
    }
}