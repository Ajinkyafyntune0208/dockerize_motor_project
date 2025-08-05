<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FastlaneVehicleDescriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('fastlane_vehicle_description')->insert([
            ['section' => 'bike', 'description' => 'Adapted Vehicle(2WIC)'],
            ['section' => 'bike', 'description' => 'Adapted Vehicle(2WN)'],
            ['section' => 'bike', 'description' => 'Adapted Vehicle(4WIC)'],
            ['section' => 'car', 'description' => 'Adapted Vehicle(LMV)'],
            ['section' => 'car', 'description' => 'Camper Van / Trailer (Private Use)'],
            ['section' => 'car', 'description' => 'Camper Van / Trailer (Private Use)(LMV)'],
            ['section' => 'car', 'description' => 'L.M.V. (CAR)'],
            ['section' => 'car', 'description' => 'L.M.V. (CAR)(LMV)'],
            ['section' => 'car', 'description' => 'L.M.V.(JEEP/GYPSY)(LMV)'],
            ['section' => 'bike', 'description' => 'M-Cycle/Scooter'],
            ['section' => 'bike', 'description' => 'M-Cycle/Scooter(2WN)'],
            ['section' => 'bike', 'description' => 'M-Cycle/Scooter-With Side Car'],
            ['section' => 'bike', 'description' => 'M-Cycle/Scooter-With Side Car(2WN)'],
            ['section' => 'bike', 'description' => 'Moped'],
            ['section' => 'bike', 'description' => 'Moped(2WN)'],
            ['section' => 'bike', 'description' => 'MOPED(SCR)'],
            ['section' => 'bike', 'description' => 'Mopeds and Motorised Cycle'],
            ['section' => 'car', 'description' => 'Motor Car'],
            ['section' => 'car', 'description' => 'Motor Car(LMV)'],
            ['section' => 'bike', 'description' => 'MOTOR CYCLE'],
            ['section' => 'bike', 'description' => 'MOTOR CYCLE(SCR)'],
            ['section' => 'bike', 'description' => 'Motorised Cycle (CC > 25cc)'],
            ['section' => 'bike', 'description' => 'Motorised Cycle (CC > 25cc)(2WN)'],
            ['section' => 'car', 'description' => 'Omni Bus (Private Use)'],
            ['section' => 'car', 'description' => 'Omni Bus (Private Use)(LMV)'],
            ['section' => 'car', 'description' => 'Omni Bus (Private Use)(OTH)'],
            ['section' => 'car', 'description' => 'Omni Bus(LMV)'],
            ['section' => 'car', 'description' => 'Private Service Vehicle (Individual Use)'],
            ['section' => 'car', 'description' => 'Private Service Vehicle (Individual Use)(LMV)'],
            ['section' => 'car', 'description' => 'Private Service Vehicle(LMV)'],
            ['section' => 'car', 'description' => 'Quadricycle (Private)'],
            ['section' => 'car', 'description' => 'Quadricycle (Private)(LMV)'],
            ['section' => 'bike', 'description' => 'SCOOTER(SCR)'],
            ['section' => 'car', 'description' => 'Three Wheeler (Personal)'],
            ['section' => 'car', 'description' => 'Three Wheeler (Personal)(3WN)'],
            ['section' => 'bike', 'description' => 'Motorised Cycle '],
        ]);
    }
}
