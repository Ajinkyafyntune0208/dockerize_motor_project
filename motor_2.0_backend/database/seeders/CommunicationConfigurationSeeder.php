<?php

namespace Database\Seeders;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\Schema;

class CommunicationConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
       
        if (Schema::hasTable('communication_configuration')) {
            $data = [
                [
                    'page_name' => 'Pre_Quote',
                    'slug' => 'pre_quote',
                    'email_is_enable' => 1,
                    'email' => 0,
                    'sms_is_enable' => 1,
                    'sms' => 0,
                    'whatsapp_api_is_enable' => 1,
                    'whatsapp_api' => 0,
                    'whatsapp_redirection_is_enable' => 1,
                    'whatsapp_redirection' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'page_name' => 'Quote',
                    'slug' => 'quote',
                    'email_is_enable' => 1,
                    'email' => 0,
                    'sms_is_enable' => 1,
                    'sms' => 0,
                    'whatsapp_api_is_enable' => 1,
                    'whatsapp_api' => 0,
                    'whatsapp_redirection_is_enable' => 1,
                    'whatsapp_redirection' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'page_name' => 'Premium_Breakup',
                    'slug' => 'premium_breakup',
                    'email_is_enable' => 1,
                    'email' => 0,
                    'sms_is_enable' => 0,
                    'sms' => 0,
                    'whatsapp_api_is_enable' => 1,
                    'whatsapp_api' => 0,
                    'whatsapp_redirection_is_enable' => 1,
                    'whatsapp_redirection' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'page_name' => 'Compare',
                    'slug' => 'compare',
                    'email_is_enable' => 1,
                    'email' => 0,
                    'sms_is_enable' => 0,
                    'sms' => 0,
                    'whatsapp_api_is_enable' => 1,
                    'whatsapp_api' => 0,
                    'whatsapp_redirection_is_enable' => 1,
                    'whatsapp_redirection' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'page_name' => 'Proposal',
                    'slug' => 'proposal',
                    'email_is_enable' => 1,
                    'email' => 0,
                    'sms_is_enable' => 1,
                    'sms' => 0,
                    'whatsapp_api_is_enable' => 1,
                    'whatsapp_api' => 0,
                    'whatsapp_redirection_is_enable' => 1,
                    'whatsapp_redirection' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'page_name' => 'Payment_Success',
                    'slug' => 'payment_success',
                    'email_is_enable' => 1,
                    'email' => 0,
                    'sms_is_enable' => 1,
                    'sms' => 0,
                    'whatsapp_api_is_enable' => 1,
                    'whatsapp_api' => 0,
                    'whatsapp_redirection_is_enable' => 1,
                    'whatsapp_redirection' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'page_name' => 'Payment_Failure',
                    'slug' => 'payment_failure',
                    'email_is_enable' => 1,
                    'email' => 0,
                    'sms_is_enable' => 1,
                    'sms' => 0,
                    'whatsapp_api_is_enable' => 1,
                    'whatsapp_api' => 0,
                    'whatsapp_redirection_is_enable' => 1,
                    'whatsapp_redirection' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'page_name' => 'Breakin_Success',
                    'slug' => 'breakin_success',
                    'email_is_enable' => 1,
                    'email' => 0,
                    'sms_is_enable' => 1,
                    'sms' => 0,
                    'whatsapp_api_is_enable' => 1,
                    'whatsapp_api' => 0,
                    'whatsapp_redirection_is_enable' => 1,
                    'whatsapp_redirection' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

           
            foreach ($data as $record) {
                DB::table('communication_configuration')->updateOrInsert(
                    ['slug' => $record['slug']], // Unique identifier
                    array_merge($record, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );
            }
        }
    }
}