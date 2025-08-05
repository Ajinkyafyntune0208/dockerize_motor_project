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
use App\Http\Controllers\Payment\Services\Car\iciciLombardPaymentGateway;
use App\Models\PolicyDetails;
use App\Models\PaymentRequestResponse;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use App\Models\MasterPolicy;
use Illuminate\Support\Facades\Storage;

class IciciLombardPaymentPending implements ShouldQueue
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
            //->whereIn('cjs.stage', [ STAGE_NAMES['POLICY_ISSUED']])
            ->where('prr.ic_id','=',40)
            ->select('prr.*','cjs.stage')
            ->orderBy('prr.id', 'DESC')
            //->orderBy('prr.id', 'ASC')
            ->offset($offset)
            ->limit($limit)
            ->get();
//        print_r($payment_request_response);
//        die;
        foreach ($payment_request_response as $key => $data) 
        {
            $quote_data = QuoteLog::where('user_product_journey_id', $data->user_product_journey_id)
                                    ->select('product_sub_type_id','master_policy_id')
                                    ->first();
            $section = get_parent_code($quote_data->product_sub_type_id);
            $productData = getProductDataByIc($quote_data->master_policy_id)->product_name ?? NULL;
  
            $payment_status_data = [
                'enquiryId'                     => $data->user_product_journey_id,
                'payment_request_response_id'   => $data->id,
                'order_id'                      => $data->order_id,
                'section'                       => $section,
                'product_name'                  => $productData,
                'policy_id'                     => $quote_data->master_policy_id
            ];
            $payment_response = iciciLombardPaymentGateway::checkTransationStatus((object) $payment_status_data);
            $payment_response = json_decode($payment_response,TRUE);
            $existing_policy_number = NULL;
            $policy_number = NULL;
            $policyGeneration = $pdf_link = NULL;
            $pdf_response  = NULL;
            $payment_response_status = $payment_response['Status'] ?? NULL;
            $payment_response_AuthCode = $payment_response['AuthCode'] ?? NULL;
            $payment_status = NULL;
            $updated_stage  = NULL;
            if($payment_response_status == 2)
            {
                $payment_status = STAGE_NAMES['PAYMENT_INITIATED'];
                $updated_stage  = STAGE_NAMES['PAYMENT_INITIATED'];
            }
            else if($payment_response_status == 1)
            {
                $payment_status = STAGE_NAMES['PAYMENT_FAILED'];
                $updated_stage  = STAGE_NAMES['PAYMENT_FAILED'];
            }
            else if($payment_response_status == 0 && $payment_response_AuthCode != NULL)
            {
                $updated_stage  = STAGE_NAMES['PAYMENT_SUCCESS'];
                $payment_status = STAGE_NAMES['PAYMENT_SUCCESS']; 
                $userDetails = UserProposal::where('user_product_journey_id', $data->user_product_journey_id)
                    ->select('unique_proposal_id')
                    ->first();
                $premium_type_id = MasterPolicy::where('policy_id', $quote_data->master_policy_id)->first();
                $premium_type = DB::table('master_premium_type')
                    ->where('id', $premium_type_id->premium_type_id)
                    ->pluck('premium_type_code')
                    ->first();
                $payment_status_data['CorrelationId']   = $userDetails->unique_proposal_id;
                $payment_status_data['AuthCode']        = $payment_response['AuthCode'];
                $payment_status_data['PGtransactionId'] = $payment_response['PGtransactionId'];
                $payment_status_data['MerchantId']      = $payment_response['MerchantId'];
                $payment_status_data['PaymentAmount']   = $payment_response['Amount'];
                $payment_status_data['CustomerID']      = $data->customer_id;
                $payment_status_data['ProposalNo']      = $data->proposal_no;
                $payment_status_data['payment_date']    = $data->created_at;
                $payment_status_data['premium_type']    = $premium_type;
                
                $policyGeneration = iciciLombardPaymentGateway::policyGeneration((object) $payment_status_data);
                
                $policyGeneration = json_decode($policyGeneration,true);
      
                //print_r($policyGeneration);
                //if(isset($generatedPolicy['status']) && $generatedPolicy['status'] == true && $generatedPolicy['statusMessage'] == 'Success') 
                if(isset($policyGeneration['status']) && $policyGeneration['status'] == true && $policyGeneration['statusMessage'] == 'Success') 
                {
                    $updated_stage  = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
                    $policy_number = $policyGeneration['paymentTagResponse']['paymentTagResponseList'][0]['policyNo'];
                    $payment_status_data['policy_number'] = $policy_number;
                    //pdf service call
                    $pdf_response = iciciLombardPaymentGateway::pdfGeneration((object) $payment_status_data);
                    if (preg_match("/^%PDF-/", $pdf_response)) 
                    {
                        $updated_stage  = STAGE_NAMES['POLICY_ISSUED'];
                        $file_name = 'policyDocs/pending_policy/icici_lombard/'. customEncrypt($data->user_product_journey_id).'.pdf';
                        if(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->exists($file_name))
                        {
                           Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->delete($file_name);
                        }
                        Storage::put($file_name,$pdf_response);
                        $pdf_link = Storage::url($file_name);                    
                    }                 
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
                'payment_status_response'       => json_encode($payment_response),
                'policy_status_response'        => json_encode($policyGeneration),
                'pdf_response'                  => $pdf_response,
                'created_at'                    => date('Y-m-d H:i:s')
            ];
            DB::table('payment_pending_status')->insert($status_data);
            PaymentRequestResponse::where('id', $data->id)
                    ->update(['is_processed_payment_pending'  => 'Y']);
        }
    }
}
