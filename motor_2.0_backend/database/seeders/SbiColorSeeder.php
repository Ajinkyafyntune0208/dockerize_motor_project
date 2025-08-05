<?php

namespace Database\Seeders;

use App\Models\ProposalFields;


use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SbiColorSeeder extends Seeder
{
	/**
		* Run the database seeds.
		*
		* @return void
		*/
	public function run()
	{
		DB::table('sbi_colors')->insert([
			['sbi_color' => 'black'],
			['sbi_color' => 'blue'],
			['sbi_color' => 'yellow'],
			['sbi_color' => 'ivory'],
			['sbi_color' => 'red'],
			['sbi_color' => 'white'],
			['sbi_color' => 'green'],
			['sbi_color' => 'purple'],
			['sbi_color' => 'violet'],
			['sbi_color' => 'maroon'],
			['sbi_color' => 'silver'],
			['sbi_color' => 'gold'],
			['sbi_color' => 'beige'],
			['sbi_color' => 'orange'],
			['sbi_color' => 'still grey']
		]);
	}
}
