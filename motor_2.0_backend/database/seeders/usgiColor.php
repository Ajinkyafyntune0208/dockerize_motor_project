<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class usgiColor extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('usgi_color_masters')->insert([
            ['ColorCode' => 'M', 'ColorName' => 'Metallic', 'status' => 'A'],
            ['ColorCode' => 'NM', 'ColorName' => 'Non-Metallic', 'status' => 'A'],
            ['ColorCode' => 'O', 'ColorName' => 'Others', 'status' => 'A'],
        ]);
    }
}
