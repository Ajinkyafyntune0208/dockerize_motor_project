<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FgCarAddonConfiguration extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('fg_car_addon_configuration')->insert([
            ['id' => 1, 'addon_combination' => 'Zero Dep + Consumable + Engine Protector + Tyre + RTI','cover_code' => 'ZCETR' ,'age' => '3' ,'with_zd' => 'Y'],

            ['id' => 2, 'addon_combination' => 'Zero Dep + Consumable + Engine Protector + Tyre','cover_code' => 'ZDCET' ,'age' => '5' ,'with_zd' => 'Y'],

            ['id' => 3, 'addon_combination' => 'Zero Dep + Consumable + Tyre','cover_code' => 'ZDCNT' ,'age' => '5' ,'with_zd' => 'Y'],

            ['id' => 4, 'addon_combination' => 'NCB Protection', 'cover_code' =>'STNCB' ,'age' => '5' ,'with_zd' => 'N'],

            ['id' => 5, 'addon_combination' => 'Zero Dep + Consumable + Engine Protector','cover_code' => 'ZDCNE' ,'age' => '7' ,'with_zd' => 'Y'],

            ['id' => 6, 'addon_combination' => 'Zero Dep + Consumable' ,'cover_code'=> 'ZDCNS' ,'age' => '7' ,'with_zd' => 'Y'],

            ['id' => 7, 'addon_combination' => 'Zero Depreciation','cover_code' => 'STZDP' ,'age' => '7' ,'with_zd' => 'Y'],

            ['id' => 8, 'addon_combination' => 'Standalone RSA','cover_code' => 'STRSA' ,'age' => '7' ,'with_zd' => 'N'],

            ['id' => 9, 'addon_combination' => 'RSA + Personal Belonging + Key Loss','cover_code' => 'RSPBK' ,'age' => '7' ,'with_zd' => 'N'],

            ['id' => 10, 'addon_combination' => '','cover_code' => '' ,'age' => '8' ,'with_zd' => 'N'],
        ]);
    }
}
