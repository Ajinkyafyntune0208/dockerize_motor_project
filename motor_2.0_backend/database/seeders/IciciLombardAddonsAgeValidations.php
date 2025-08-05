<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IciciLombardAddonsAgeValidations extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        if (Schema::hasTable('icici_lombard_addons_validation')) 
        {
            DB::table('icici_lombard_addons_validation')->insert(
                [
                    [
                        "name" => "zero_dep",
                        "premium_vehicle_age" => 5,
                        "non_premium_vehicle_age" => 5,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                    [
                        "name" => "road_side_assistance",
                        "premium_vehicle_age" => 10,
                        "non_premium_vehicle_age" => 10,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                    [
                        "name" => "consumable",
                        "premium_vehicle_age" => 7,
                        "non_premium_vehicle_age" => 7,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                    [
                        "name" => "key_replacement",
                        "premium_vehicle_age" => 10,
                        "non_premium_vehicle_age" => 10,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                    [
                        "name" => "engine_protector",
                        "premium_vehicle_age" => 5,
                        "non_premium_vehicle_age" => 5,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                    [
                        "name" => "ncb_protection",
                        "premium_vehicle_age" => 0,
                        "non_premium_vehicle_age" => 0,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                    [
                        "name" => "tyre_secure",
                        "premium_vehicle_age" => 5,
                        "non_premium_vehicle_age" => 3,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                    [
                        "name" => "return_to_invoice",
                        "premium_vehicle_age" => 5,
                        "non_premium_vehicle_age" => 5,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                    [
                        "name" => "loss_of_belongings",
                        "premium_vehicle_age" => 10,
                        "non_premium_vehicle_age" => 10,
                        "created_at" => now(),
                        "updated_at" => now()
                    ],
                ]
            );
        }

    }
}
