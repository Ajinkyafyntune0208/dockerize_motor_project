<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMasterMotorAddons extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('master_motor_addons', function (Blueprint $table) {
            $table->id();
            $table->integer('product_sub_type_id');
            $table->unsignedInteger('company_id');
            $table->string('company_name');
            $table->string('plan_name')->nullable();
            $table->enum('vehicle_segment',['premium','nonpremium'])->default('nonpremium');
            $table->string('vehicle_make')->default('other_make');
            $table->set('included',['road_side_assistance','zero_depreciation','key_replacement','engine_protector','ncb_protection','consumable','tyre_secure','return_to_invoice','loss_of_personal_belongings','emergency_medical_expenses'])->nullable();
            $table->string('road_side_assistance',10)->default('0-0-0');
            $table->string('zero_depreciation',10)->default('0-0-0');
            $table->string('key_replacement',10)->default('0-0-0');
            $table->string('engine_protector',10)->default('0-0-0');
            $table->string('ncb_protection',10)->default('0-0-0');
            $table->string('consumable',10)->default('0-0-0');
            $table->string('tyre_secure',10)->default('0-0-0');
            $table->string('return_to_invoice',10)->default('0-0-0');
            $table->string('loss_of_personal_belongings',10)->default('0-0-0');
            $table->string('emergency_medical_expenses',10)->default('0-0-0');
            $table->timestamps();
        });
        
        Schema::table('master_motor_addons', function ($table) {
            $table->foreign('company_id')->references('company_id')->on('master_company');
            $table->foreign('product_sub_type_id')->references('product_sub_type_id')->on('master_product_sub_type');
        });

        if (Schema::hasTable('master_motor_addons')) 
        {
            $icici_addons = [
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => "silver",
                    "vehicle_segment" => "nonpremium",
                    "vehicle_make" => "other_make",
                    "included" => "road_side_assistance,zero_depreciation,key_replacement,consumable",
                    "road_side_assistance" => "7-0-0",
                    "zero_depreciation" => "7-0-0",
                    "key_replacement" => "7-0-0",
                    "engine_protector" => "0-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "7-0-0",
                    "tyre_secure" => "0-0-0",
                    "return_to_invoice" => "0-0-0",
                    "loss_of_personal_belongings" => "0-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => "silver",
                    "vehicle_segment" => "premium",
                    "vehicle_make" => "other_make",
                    "included" => "road_side_assistance,zero_depreciation,key_replacement,consumable,loss_of_personal_belongings",
                    "road_side_assistance" => "7-0-0",
                    "zero_depreciation" => "7-0-0",
                    "key_replacement" => "7-0-0",
                    "engine_protector" => "0-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "7-0-0",
                    "tyre_secure" => "0-0-0",
                    "return_to_invoice" => "0-0-0",
                    "loss_of_personal_belongings" => "0-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => "gold",
                    "vehicle_segment" => "nonpremium",
                    "vehicle_make" => "other_make",
                    "included" => "road_side_assistance,zero_depreciation,key_replacement,consumable,tyre_secure,loss_of_personal_belongings",
                    "road_side_assistance" => "7-0-0",
                    "zero_depreciation" => "7-0-0",
                    "key_replacement" => "7-0-0",
                    "engine_protector" => "0-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "7-0-0",
                    "tyre_secure" => "3-0-0",
                    "return_to_invoice" => "0-0-0",
                    "loss_of_personal_belongings" => "0-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => "gold",
                    "vehicle_segment" => "premium",
                    "vehicle_make" => "other_make",
                    "included" => "road_side_assistance,zero_depreciation,key_replacement,consumable,tyre_secure,loss_of_personal_belongings",
                    "road_side_assistance" => "7-0-0",
                    "zero_depreciation" => "7-0-0",
                    "key_replacement" => "7-0-0",
                    "engine_protector" => "0-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "7-0-0",
                    "tyre_secure" => "5-0-0",
                    "return_to_invoice" => "0-0-0",
                    "loss_of_personal_belongings" => "0-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => "gold_plus",
                    "vehicle_segment" => "nonpremium",
                    "vehicle_make" => "other_make",
                    "included" => "road_side_assistance,zero_depreciation,key_replacement,engine_protector,consumable,loss_of_personal_belongings",
                    "road_side_assistance" => "7-0-0",
                    "zero_depreciation" => "7-0-0",
                    "key_replacement" => "7-0-0",
                    "engine_protector" => "5-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "7-0-0",
                    "tyre_secure" => "0-0-0",
                    "return_to_invoice" => "0-0-0",
                    "loss_of_personal_belongings" => "0-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => "gold_plus",
                    "vehicle_segment" => "premium",
                    "vehicle_make" => "other_make",
                    "included" => "road_side_assistance,zero_depreciation,key_replacement,engine_protector,consumable,loss_of_personal_belongings",
                    "road_side_assistance" => "7-0-0",
                    "zero_depreciation" => "7-0-0",
                    "key_replacement" => "7-0-0",
                    "engine_protector" => "5-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "7-0-0",
                    "tyre_secure" => "0-0-0",
                    "return_to_invoice" => "0-0-0",
                    "loss_of_personal_belongings" => "0-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => "platinum",
                    "vehicle_segment" => "nonpremium",
                    "vehicle_make" => "other_make",
                    "included" => "road_side_assistance,zero_depreciation,key_replacement,engine_protector,consumable,tyre_secure",
                    "road_side_assistance" => "7-0-0",
                    "zero_depreciation" => "7-0-0",
                    "key_replacement" => "7-0-0",
                    "engine_protector" => "5-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "7-0-0",
                    "tyre_secure" => "3-0-0",
                    "return_to_invoice" => "0-0-0",
                    "loss_of_personal_belongings" => "0-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => "platinum",
                    "vehicle_segment" => "premium",
                    "vehicle_make" => "other_make",
                    "included" => "road_side_assistance,zero_depreciation,key_replacement,engine_protector,consumable,tyre_secure",
                    "road_side_assistance" => "7-0-0",
                    "zero_depreciation" => "7-0-0",
                    "key_replacement" => "7-0-0",
                    "engine_protector" => "5-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "7-0-0",
                    "tyre_secure" => "5-0-0",
                    "return_to_invoice" => "0-0-0",
                    "loss_of_personal_belongings" => "0-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => NULL,
                    "vehicle_segment" => "premium",
                    "vehicle_make" => "other_make",
                    "included" => "",
                    "road_side_assistance" => "10-0-0",
                    "zero_depreciation" => "0-0-0",
                    "key_replacement" => "10-0-0",
                    "engine_protector" => "5-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "0-0-0",
                    "tyre_secure" => "5-0-0",
                    "return_to_invoice" => "5-0-0",
                    "loss_of_personal_belongings" => "10-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
                [
                    "product_sub_type_id" => 1,
                    "company_id" => 40,
                    "company_name" => "icici_lombard",
                    "plan_name" => NULL,
                    "vehicle_segment" => "premium",
                    "vehicle_make" => "other_make",
                    "included" => "",
                    "road_side_assistance" => "10-0-0",
                    "zero_depreciation" => "0-0-0",
                    "key_replacement" => "10-0-0",
                    "engine_protector" => "5-0-0",
                    "ncb_protection" => "0-0-0",
                    "consumable" => "0-0-0",
                    "tyre_secure" => "3-0-0",
                    "return_to_invoice" => "5-0-0",
                    "loss_of_personal_belongings" => "10-0-0",
                    "emergency_medical_expenses" => "0-0-0",
                    "created_at" => "2023-04-07 16:01:29",
                    "updated_at" => "2023-04-07 16:01:30",
                ],
            ];
            
            DB::table('master_motor_addons')->insert($icici_addons);
        }

        if (Schema::hasTable('master_motor_addons')) 
        {
            DB::table('config_settings')->updateOrInsert([
                'key'=>'DYNAMIC_MASTER_MOTOR_ADDON_TABLE_COLUMN_NAMES_REMOVING'
            ],
            [
                'label'=>'DYNAMIC_MASTER_MOTOR_ADDON_TABLE_COLUMN_NAMES_REMOVING',
                'value'=>'id,master_product_type,company_id,company_name,plan_name,vehicle_segment,vehicle_make,included,created_at,updated_at',
                'environment'=>'local'
            ]);
        }
        Schema::enableForeignKeyConstraints();
    }
    
    

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('master_motor_addons');
    }
}
