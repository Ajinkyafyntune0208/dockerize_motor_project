<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Payment\Services\Car\goDigitPaymentGateway;
use App\Models\PolicyDetails;
use App\Models\PaymentRequestResponse;

class GodigitPaymentPending implements ShouldQueue
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
        //Getting data of except Policy_issued, Policy issed But pdf not generated, Payment success
        $payment_request_response = DB::table('payment_request_response as prr')
            ->join('cv_journey_stages as cjs', 'cjs.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->where('prr.is_processed_payment_pending','=','N')
            ->where('prr.status','!=',STAGE_NAMES['PAYMENT_SUCCESS'])
            ->whereNotIn('cjs.stage', [ STAGE_NAMES['POLICY_ISSUED'],STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],STAGE_NAMES['PAYMENT_SUCCESS']])
            ->where('prr.ic_id','=',36)
            ->select('prr.*','cjs.stage')
            ->orderBy('prr.id', 'DESC')
            //->orderBy('prr.id', 'ASC')
            ->offset($offset)
            ->limit($limit)
            ->get();
        foreach ($payment_request_response as $key => $data) 
        {
            $payment_status_data = [
                'enquiryId'                     => $data->user_product_journey_id,
                'payment_request_response_id'   => $data->id,
                'order_id'                      => $data->order_id,
                'section'                       => ''
            ];
            
            $payment_response = goDigitPaymentGateway::checkPaymentPendingStatus((object) $payment_status_data);
            $payment_response = json_decode($payment_response,TRUE);
            $policyStatus = $payment_response['policyStatus'];
            $existing_policy_number = NULL;
            $policy_number = NULL;
            $pdf_link = NULL;
            $updated_stage = NULL;
            $pdf_response = NULL;
            $policy_status_response = NULL;
            $pdf_response  = NULL;
            if($policyStatus == 'INCOMPLETE')
            {
                $payment_status = STAGE_NAMES['PAYMENT_INITIATED'];
                $updated_stage  = STAGE_NAMES['PAYMENT_INITIATED'];
            }
            else if($policyStatus == 'DECLINED')
            {
                $payment_status = STAGE_NAMES['PAYMENT_FAILED'];
                $updated_stage  = STAGE_NAMES['PAYMENT_FAILED'];
            }
            else if($policyStatus == 'EFFECTIVE')
            {
                $updated_stage  = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                $payment_status = STAGE_NAMES['PAYMENT_SUCCESS']; 
                $policy_number = $payment_response['policyNumber'];
                $payment_status_data['applicationId'] = $payment_response['applicationId'];
                $pdf_response = goDigitPaymentGateway::pdfReponse((object) $payment_status_data);  
                $pdf_response = json_decode($pdf_response,TRUE);
                if(isset($pdf_response['schedulePathHC']))
                {
                    $updated_stage  = STAGE_NAMES['POLICY_ISSUED'];
                    $pdf_link = $pdf_response['schedulePathHC'];                    
                }
            }
            
            $PolicyDetails = PolicyDetails::where('proposal_id','=',$data->user_proposal_id)->get()->first();
            if(!empty($PolicyDetails))
            {                            
                $existing_policy_number = $PolicyDetails->policy_number;
            }
            $status_data = [
                'payment_request_response_id'   => $data->id,
                'enquiry_id'                    => customEncrypt($data->user_product_journey_id),
                'user_product_journey_id'       => $data->user_product_journey_id,
                'proposal_id'                   => $data->user_proposal_id,
                'ic_id'                         => $data->ic_id,
                'order_id'                      => $data->order_id,
                'existing_payment_status'       => $data->status,
                'updated_payment_status'        => $payment_status,
                'existing_journey_stage'        => $data->stage,
                'updated_journey_stage'         => $updated_stage,
                'existing_policy_number'        => $existing_policy_number,
                'updated_policy_number'         => $policy_number,
                'pdf_link'                      => $pdf_link,
                'payment_status_response'       => NULL,
                'policy_status_response'        => json_encode($payment_response),
                'pdf_response'                  => json_encode($pdf_response),
                'created_at'                    => date('Y-m-d H:i:s')
            ];
            DB::table('payment_pending_status')->insert($status_data);
            PaymentRequestResponse::where('id', $data->id)
                    ->update(['is_processed_payment_pending'  => 'Y']);
        }
    }
}
