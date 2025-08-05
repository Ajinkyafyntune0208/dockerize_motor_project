<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentCheckStatus;
use App\Models\UserProposal;
use App\Models\JourneyStage;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use Illuminate\Http\Request;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

class HdfcErgoPaymentStatusCheck implements ShouldQueue
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
    public function handle(Request $request)
    {
        $offset = $request->start_index; // start row index.
        $limit = $request->limit; // no of records to fetch/ get .
        
        $payment_request_response = DB::table('payment_request_response as prr')
                ->join('cv_journey_stages as cjs', 'cjs.user_product_journey_id', '=', 'prr.user_product_journey_id')
                ->where('prr.is_processed','=','N')
                //->whereNotIn('cjs.user_product_journey_id', PaymentCheckStatus::pluck('user_product_journey_id'))
                //->where('prr.status','!=',STAGE_NAMES['PAYMENT_SUCCESS'])
                //->whereNotIn('cjs.stage', [ STAGE_NAMES['POLICY_ISSUED']])
                ->where('prr.ic_id','=',11)
                ->select('prr.*','cjs.stage')
                ->orderBy('prr.id', 'DESC')
                //->orderBy('prr.id', 'ASC')
                ->offset($offset)
                ->limit($limit)
                ->get();
        foreach ($payment_request_response as $key => $data) 
        {
            $JourneyStage_data = JourneyStage::where('user_product_journey_id', $data->user_product_journey_id)->first();
                        
            $enquiryId = [
                'enquiryId' => customEncrypt($data->user_product_journey_id)
            ];
            //$pdf_response = httpRequestNormal('https://motor_api_uat.adityabirlainsurancebrokers.com/api/generatePdf', 'POST', $enquiryId)['response'];
            $pdf_response = httpRequestNormal(url('api/generatePdf'), 'POST', $enquiryId)['response'];
            $updated_journey_stage = JourneyStage::where('user_product_journey_id', $data->user_product_journey_id)->first();
            $updated_payment_status = PaymentRequestResponse::where('id', $data->id)->first();
            //$pdf_response = '';
            $status_data = [
                'payment_request_response_id'   => $data->id,
                'user_product_journey_id'   => $data->user_product_journey_id,
                'proposal_id'               => $data->user_proposal_id,
                'ic_id'                     => 11,
                'order_id'                  => $data->order_id,
                'existing_payment_status'   => $data->status,
                'updated_payment_status'    => $updated_payment_status->status,
                'existing_journey_stage'    => $data->stage,
                'updated_journey_stage'     => $JourneyStage_data->stage,
                'required_payment_data'     => $updated_journey_stage->stage,
                'pdf_status'                => json_encode($pdf_response),
                'response'                  => null,//$payment_respone->response,
                'created_at'                => date('Y-m-d H:i:s')
            ];
            DB::table('payment_check_status')->insert($status_data);
            PaymentRequestResponse::where('id', $data->id)
                    ->update(['is_processed'  => 'Y']);
        }
    }
}
