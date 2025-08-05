<?php

namespace App\Jobs;

use App\Http\Controllers\ProposalReportController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DashboardDataPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    protected $enquiry_id;
    public function __construct($eq_id)
    {
        $this->enquiry_id = $eq_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED') != 'Y') {
            return 0;
        }
        if($this->enquiry_id == NULL)
        {
            return 0;
        }

        if(is_numeric($this->enquiry_id) )
        {
            switch( strlen($this->enquiry_id) )
            {
                case 16:
                    $this->enquiry_id = ltrim(substr($this->enquiry_id, 8),0);
                break;
            }
        }
        else
        {
            $this->enquiry_id = customDecrypt($this->enquiry_id);
        }
        $encryptedId = customEncrypt($this->enquiry_id);
        $request['enquiry_id'] = $encryptedId;
        $request['update_renewal_data'] = 'Y';
        $request['skip_secret_token'] = true;        
        
        // For ACE broker, We need to push only seller_type E policies.
        if (config('constants.motorConstant.SMS_FOLDER') == 'ace') {
            $request['seller_type'] = 'E';
        }

        $dashboardData = ProposalReportController::proposalReports(new \Illuminate\Http\Request($request))->getOriginalContent();

        if (empty($dashboardData['data'][0] ?? [])) {
            return false;
        }
        $allSections = [
            1 => 'CAR',
            2 => 'BIKE',
            3 => 'MISC',
            4 => 'GCV',
            8 => 'PCV',
        ];

        $userData = \App\Models\UserProductJourney::find($this->enquiry_id);
        $userSection = $userData?->sub_product?->parent_product_sub_type_id ?? '';
        $sixteen_digit_trace_id = $userData?->journey_id ?? '';

        $dashboardData['data'][0]['section'] = $allSections[$userSection] ?? '';

        // If the section is empty it will create duplicate record for the same trace ID.
        if (empty($dashboardData['data'][0]['section'])) {
            return;
        }
        // $updateOrCreate['section'] = $dashboardData['data'][0]['section'];
        if (config('enquiry_id_encryption') == 'Y') {
            // $updateOrCreate['trace_id'] = $dashboardData['data'][0]['trace_id'];
        } else {
            // $dashboardData['data'][0]['enquiry_id'] = $sixteen_digit_trace_id;
        }
        // Dashboard team needs enquiry_id in all case i.e Encryption is On or Off - 15-04-2024
        $dashboardData['data'][0]['enquiry_id'] = $sixteen_digit_trace_id;

        // If a policy is punched and it is a renewal journey, then update the original old data journey's data also in MongoDB
        // It will have the renewal_journeys tags in it
        if (strtolower($dashboardData['data'][0]['transaction_stage']) == strtolower( STAGE_NAMES['POLICY_ISSUED'] ) && $dashboardData['data'][0]['is_renewal'] == 'Y') {
            DashboardDataPush::dispatch($userData->old_journey_id)->onQueue(env('DASHBOARD_PUSH_QUEUE_NAME'));
        }

        $dashboard = new \App\Helpers\Mongo\Models\DashboardTransaction();
        $dashboard->updateOrCreate(
            ['trace_id' => $dashboardData['data'][0]['trace_id']],
            $dashboardData['data'][0]
        );

        //Check if the record exists
        // $existing = \App\Models\DashboardDataPushModel::select('trace_id')
        //     ->where('trace_id', $dashboardData['data'][0]['trace_id'])
        //     ->first();

        // if ($existing) {
        //     $existing->update($dashboardData['data'][0]);
        // } else {
        //     \App\Models\DashboardDataPushModel::create($dashboardData['data'][0]);
        // }
    }
}
