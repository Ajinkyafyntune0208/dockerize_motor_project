<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShriramCkycSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if(env('APP_ENV') == 'local') {
            DB::table('config_settings')->updateOrInsert([
                'key'=>'constants.motor.shriram.QUOTE_URL_JSON'
            ],
            [
                'label'=>'QUOTE_URL_JSON',
                'value'=>'https://novauat.shriramgi.com/UATWebAggrNAPI/PolicyGeneration.svc/RestService/GetQuote',
                'environment'=>'local'
            ]);

            DB::table('config_settings')->updateOrInsert([
                'key'=>'constants.motor.shriram.PROPOSAL_URL_JSON'
            ],
            [
                'label'=>'PROPOSAL_URL_JSON',
                'value'=>'https://novauat.shriramgi.com/UATWebAggrNAPI/PolicyGeneration.svc/RestService/GenerateProposal',
                'environment'=>'local'
            ]);

            DB::table('config_settings')->updateOrInsert([
                'key'=>'constants.IcConstants.shriram.SHRIRAM_PCV_JSON_QUOTE_URL'
            ],
            [
                'label'=>'SHRIRAM_PCV_JSON_QUOTE_URL',
                'value'=>'https://novauat.shriramgi.com/UATWebAggrNAPI/PolicyGeneration.svc/RestService/GetQuote',
                'environment'=>'local'
            ]);

            DB::table('config_settings')->updateOrInsert([
                'key'=>'constants.IcConstants.shriram.SHRIRAM_PROPOSAL_SUBMIT_URL_JSON'
            ],
            [
                'label'=>'SHRIRAM_PROPOSAL_SUBMIT_URL_JSON',
                'value'=>'https://novauat.shriramgi.com/UATWebAggrNAPI/PolicyGeneration.svc/RestService/GeneratePCCVProposal',
                'environment'=>'local'
            ]);
        }
        
    }
}
