<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;


class UniversalSompoBikeAddonConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // DB::table('universal_sompo_bike_addon_configuration')->insert(


        // );
        if (Schema::hasTable('universal_sompo_bike_addon_configuration')) 
        {
            DB::table('universal_sompo_bike_addon_configuration')->truncate();
            DB::table('universal_sompo_bike_addon_configuration')->insert([
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "0-1",
                        "zero_dep" => "True",
                        "engine_protector" => "True",
                        "return_to_invoice" => "True",
                        "road_side_assistance" => "True",
                        "consumable" => "True",
                    ),
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "1-2",
                        "zero_dep" => "True",
                        "engine_protector" => "True",
                        "return_to_invoice" => "True",
                        "road_side_assistance" => "True",
                        "consumable" => "True",
                    ),
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "2-3",
                        "zero_dep" => "True",
                        "engine_protector" => "True",
                        "return_to_invoice" => "True",
                        "road_side_assistance" => "True",
                        "consumable" => "True",
                    ),
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "3-4",
                        "zero_dep" => "True",
                        "engine_protector" => "True",
                        "return_to_invoice" => "True",
                        "road_side_assistance" => "True",
                        "consumable" => "True",
                    ),
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "4-5",
                        "zero_dep" => "True",
                        "engine_protector" => "True",
                        "return_to_invoice" => "True",
                        "road_side_assistance" => "True",
                        "consumable" => "True",
                    ),
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "5-6",
                        "zero_dep" => "False",
                        "engine_protector" => "False",
                        "return_to_invoice" => "False",
                        "road_side_assistance" => "True",
                        "consumable" => "False",
                    ),
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "6-7",
                        "zero_dep" => "False",
                        "engine_protector" => "False",
                        "return_to_invoice" => "False",
                        "road_side_assistance" => "True",
                        "consumable" => "False",
                    ),
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "7-10",
                        "zero_dep" => "False",
                        "engine_protector" => "False",
                        "return_to_invoice" => "False",
                        "road_side_assistance" => "True",
                        "consumable" => "False",
                    ),
                    array(
                        "vehicle_make" => "bike",
                        "age_range" => "10-15",
                        "zero_dep" => "False",
                        "engine_protector" => "False",
                        "return_to_invoice" => "False",
                        "road_side_assistance" => "True",
                        "consumable" => "False",
                    )
            ]);
        }
    }
}
