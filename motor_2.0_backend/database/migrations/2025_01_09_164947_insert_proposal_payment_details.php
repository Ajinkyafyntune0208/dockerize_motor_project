<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\CommunicationConfiguration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertProposalPaymentDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        CommunicationConfiguration::firstOrCreate(
            [
                'slug' => 'proposal_payment',
            ],
            [
                'page_name' => 'Proposal_Payment',
                'email_is_enable' => 1,
                'email' => 1,
                'sms_is_enable' => 1,
                'sms' => 1,
                'whatsapp_api_is_enable' => 1,
                'whatsapp_api' => 1,
                'whatsapp_redirection_is_enable' => 1,
                'whatsapp_redirection' => 1,
                'all_btn' => 1
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
