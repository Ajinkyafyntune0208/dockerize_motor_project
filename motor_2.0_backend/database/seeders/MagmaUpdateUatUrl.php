<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MagmaUpdateUatUrl extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
            DB::update(DB::RAW('UPDATE config_settings AS b SET b.value=REPLACE(b.value,"https://uatpg.magma-hdi.co.in:444/","https://intuat.magmahdi.com/")'));
    }
}
