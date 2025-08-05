<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class file_ic_config extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $company_alias_list = DB::table('master_company')
                    ->whereNotNull('company_alias')
                    ->select('company_alias')->get()
                    ->toArray();
        $file_ic_config_array = [];
        foreach($company_alias_list as $company_alias)
        {
            $file_ic_config = [
                'ic'                    => $company_alias->company_alias,
                'maxFileSize'           => '2*1024*1024',
                'acceptedExtensions'    => ['.jpeg','.png']
            ];
            $file_ic_config_array[] = $file_ic_config;
        }
        $theme_configs_data = DB::table('theme_configs')    
                    ->select('id','broker_config')
                    ->get()
                    ->toArray();
        if(isset($theme_configs_data[0]))
        {
            $id = $theme_configs_data[0]->id;
            $theme_configs_data = json_decode($theme_configs_data[0]->broker_config,true);
            $theme_configs_data['file_ic_config'] = $file_ic_config_array;
            DB::table('theme_configs')->where('id', $id)
                ->update(['broker_config' => json_encode($theme_configs_data)]);
        }
        
    }
}
