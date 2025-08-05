<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTataAigModelMasterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tata_aig_model_master', function (Blueprint $table) {
            $table->string('vehicleclasscode', 50)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->string('vehiclemodelcode', 50);
            $table->string('vehiclemodel', 100)->nullable();
            $table->string('numberofwheels', 50)->nullable();
            $table->string('cubiccapacity', 50)->nullable();
            $table->string('grossvehicleweight', 50)->nullable();
            $table->string('seatingcapacity', 50)->nullable();
            $table->string('carryingcapacity', 50)->nullable();
            $table->string('tabrowindx', 50)->nullable();
            $table->string('dat_start_date', 100)->nullable();
            $table->string('date_end_date', 100)->nullable();
            $table->string('bodytypecode', 50)->nullable();
            $table->string('vahiclemodelstatus', 100)->nullable();
            $table->string('num_insert_trans_id', 50)->nullable();
            $table->string('num_modify_trans_id', 50)->nullable();
            $table->string('dat_insert_date', 100)->nullable();
            $table->string('dat_modify_date', 100)->nullable();
            $table->string('txt_obsolete_flag', 100)->nullable();
            $table->string('num_vehicle_subclass_code', 50)->nullable();
            $table->string('active_flag', 100)->nullable();
            $table->string('txt_model_cluster', 100)->nullable();
            $table->string('numberodaxle', 50)->nullable();
            $table->string('txt_fuelcode', 50)->nullable();
            $table->string('txt_fuel', 100)->nullable();
            $table->string('txt_segmentcode', 100)->nullable();
            $table->string('txt_segmenttype', 100)->nullable();
            $table->string('txt_varient', 100)->nullable();
            $table->string('manufacturercode', 50)->nullable();
            $table->string('txt_status_flag', 100)->nullable();
            $table->string('make', 100)->nullable();
            $table->string('txt_tacmakecode', 100)->nullable();
            $table->string('txt_alt_fuel_type', 100)->nullable();
            $table->string('num_veh_age', 50)->nullable();
            $table->string('num_veh_id', 50)->nullable();
            $table->string('num_exshwrm_prce', 50)->nullable();
            $table->string('num_variation', 50)->nullable();
            $table->string('txt_door', 10)->nullable();
            $table->string('txt_declined', 10)->nullable();
            $table->string('txt_referred', 10)->nullable();
            $table->string('txt_theft_target', 10)->nullable();
            $table->string('txt_auto_tran', 10)->nullable();
            $table->string('txt_abs', 10)->nullable();
            $table->string('txt_air_bag', 10)->nullable();
            $table->string('txt_userid', 100)->nullable();
            $table->string('txt_ip_address', 100)->nullable();
            $table->string('num_hp', 50)->nullable();
            $table->string('txt_alt_varient', 100)->nullable();
            $table->string('txt_alt_manufacturer', 100)->nullable();
            $table->string('txt_alt_vehiclemodel', 100)->nullable();
            $table->string('txt_vehicle_ref_no', 100)->nullable();
            $table->string('txt_excess_group_cd', 100)->nullable();
            $table->string('txt_vehicle_type', 100)->nullable();
            $table->string('num_parent_model_code', 50)->nullable();
            $table->string('num_level', 50)->nullable();
            $table->text('bodytype_desc')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tata_aig_model_master');
    }
}
