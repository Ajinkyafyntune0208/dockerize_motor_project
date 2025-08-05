<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;

class FetchAckoCkycStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $matchThese = ['ic_name' => 'Acko General Insurance Ltd', 'is_ckyc_verified' => 'N'];
        $proposal_datas = UserProposal::where($matchThese)->first();

        foreach ($proposal_datas as $proposal_data) 
        {

            $product_name = DB::table('master_product')
            ->select('master_product.product_name')
            ->join('quote_log','quote_log.master_policy_id','=','master_product.master_policy_id')
            ->where('quote_log.user_product_journey_id', $proposal_data->user_product_journey_id)
            ->get();

            $kyc_request = [
                'proposal_id' => $proposal_data->proposal_no
            ];

            $get_response = getWsData(config('constants.IcConstants.acko.ACKO_PROPOSAL_STATUS_URL'), $kyc_request, 'acko',
            [
                'enquiryId'     => $proposal_data->user_product_journey_id,
                'requestMethod' => 'post',
                'section'       => strtoupper($proposal_data->product_type),
                'productName'   => $product_name,
                'company'       => 'acko',
                'method'        => 'GET PROPOSAL STATUS',
                'transaction_type' => 'proposal'
            ]);

            $kyc_response = $get_response['response'];
            $kyc_result = json_decode($kyc_response, TRUE);
            $kyc_status_to_be_updated = 'N';
            if($kyc_result['result']['kyc']['status'] == 'KYC_SUCCESS')
            {
                $kyc_status_to_be_updated = 'Y'; 
            }
            UserProposal::where('proposal_no', $proposal_data->proposal_no)
            ->update([
                'is_ckyc_verified' =>  $kyc_status_to_be_updated
            ]);
        }
    }
}
