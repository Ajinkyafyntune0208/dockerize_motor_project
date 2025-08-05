<?php

namespace Database\Seeders;

use App\Models\S3LogTablesModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        S3LogTablesModel::insert([
            [
                'table_name' => 'third_party_api_request_responses',
                'created_at' => now()
            ],
            [
                'table_name' => 'quote_webservice_request_response_data',
                'created_at' => now()
            ],
            [
                'table_name' => 'webservice_request_response_data',
                'created_at' => now()
            ],
            [
                'table_name' => 'mail_logs',
                'created_at' => now()
            ],
        ]);
    }
}
