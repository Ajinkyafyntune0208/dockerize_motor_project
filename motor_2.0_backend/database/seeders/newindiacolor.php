<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class newindiacolor extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('new_india_color_masters')->insert([
            ['color_code' => 'AGMETALLIC',   'color' =>   'A G Metallic'],
            ['color_code' => 'ARTIC SIL',    'color' =>   'Artic Silver'],
            ['color_code' => 'Beige',        'color' =>   'Beige'],
            ['color_code' => 'BLACK',        'color' =>   'Black'],
            ['color_code' => 'BLUE',         'color' =>   'Blue'],
            ['color_code' => 'BROWN',        'color' =>   'Brown'],
            ['color_code' => 'CHAMPAIGNE',   'color' =>   'Champaigne'],
            ['color_code' => 'DARK RED',     'color' =>   'DARK RED'],
            ['color_code' => 'DARKBLUE',     'color' =>   'Dark Blue'],
            ['color_code' => 'DARKGREEN',    'color' =>   'Dark Green'],
            ['color_code' => 'FORESTDEW',    'color' =>   'FOREST DEW'],
            ['color_code' => 'GOLD',         'color' =>   'Gold'],
            ['color_code' => 'GRAPHINE',     'color' =>   'GRAPHINE'],
            ['color_code' => 'GREEN',        'color' =>   'GREEN'],
            ['color_code' => 'GREY',         'color' =>   'Grey'],
            ['color_code' => 'IVORY',        'color' =>   'IVORY'],
            ['color_code' => 'LEMON YELL',   'color' =>   'LEMON YELLOW'],
            ['color_code' => 'LIGHTBLUE',    'color' =>   'Light Blue'],
            ['color_code' => 'LIGHTGREEN',   'color' =>   'Light Green'],
            ['color_code' => 'MAROON',       'color' =>   'Maroon'],
            ['color_code' => 'MAUVE',        'color' =>   'MAUVE'],
            ['color_code' => 'ORANGE',       'color' =>   'Orange'],
            ['color_code' => 'OTHER',        'color' =>   'OTHER COLOR'],
            ['color_code' => 'PEACH',        'color' =>   'PEACH'],
            ['color_code' => 'PEARL',        'color' =>   'Pearl White'],
            ['color_code' => 'PINK',         'color' =>   'PINK'],
            ['color_code' => 'PLATINUM',     'color' =>   'PLATINUM'],
            ['color_code' => 'PURPLE',       'color' =>   'PURPLE'],
            ['color_code' => 'REAL EARTH',   'color' =>   'REAL EARTH'],
            ['color_code' => 'RED',          'color' =>   'Red'],
            ['color_code' => 'SANDAL',       'color' =>   'SANDAL'],
            ['color_code' => 'SILVER',       'color' =>   'SILVER'],
            ['color_code' => 'SIM RED',      'color' =>   'SIMPSON RED'],
            ['color_code' => 'SUPER WITE',   'color' =>   'Super White'],
            ['color_code' => 'VIOLET',       'color' =>   'VIOLET'],
            ['color_code' => 'WARM SLVR',    'color' =>   'Warm Silver'],
            ['color_code' => 'WHITE',        'color' =>   'White'],
            ['color_code' => 'YELLOW',       'color' =>   'Yellow'],
        ]);
    }
}
