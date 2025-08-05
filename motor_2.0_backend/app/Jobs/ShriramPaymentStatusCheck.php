<?php

namespace App\Jobs;

use App\Models\JourneyStage;
use App\Models\PaymentRequestResponse;
use App\Models\PolicyDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

class ShriramPaymentStatusCheck implements ShouldQueue
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
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->where('prr.is_processed', '=', 'N')
            ->where('prr.ic_id', '=', 33)
            ->select('up.pol_sys_id', 'prr.*', 'cjs.stage', )
            ->orderBy('prr.id', 'DESC')
            ->offset($offset)
            ->limit($limit)
            ->get();

        foreach ($payment_request_response as $key => $data) {
            $company_alias = "shriram";
            $like_str = "%" . $data->pol_sys_id . "%";
            $payment_response_data = DB::table('payment_response as pr')
                ->where('company_alias', '=', $company_alias)
                ->where('response', 'LIKE', $like_str)
                ->get();

            if (count($payment_response_data) > 0) {

                foreach ($payment_response_data as $key => $payment_respone) {

                    $payment_respone_array = (array) json_decode($payment_respone->response);
                    $JourneyStage_data = JourneyStage::where('user_product_journey_id', $data->user_product_journey_id)->first();
                    if (isset($payment_respone_array['ProposalSysID']) && $data->pol_sys_id == $payment_respone_array['ProposalSysID']) {

                        if (isset($payment_respone_array['Status']) && $payment_respone_array['Status'] == "SUCCESS") {
                            $payment_status = STAGE_NAMES['PAYMENT_SUCCESS'];
                            // if(strtolower($data->status) == strtolower(STAGE_NAMES['PAYMENT_INITIATED']))
                            // {
                            $updatePaymentResponse = [
                                'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                            ];

                            PaymentRequestResponse::where('id', $data->id)
                                ->update($updatePaymentResponse);

                            // }
                            $policy_data = [
                                'proposal_id' => $data->user_proposal_id,
                                'policy_number' => $payment_respone_array['PolicyNumber'],
                                'status' => 'SUCCESS',
                            ];
                            PolicyDetails::updateOrCreate(['proposal_id' => $data->user_proposal_id], $policy_data);

                            $enquiryId = [
                                'enquiryId' => customEncrypt($data->user_product_journey_id),
                            ];

                            $pdf_response = httpRequestNormal(url('api/generatePdf'), 'POST', $enquiryId)['response'];

                            $status_data = [
                                'payment_request_response_id' => $data->id,
                                'user_product_journey_id' => $data->user_product_journey_id,
                                'proposal_id' => $data->user_proposal_id,
                                'ic_id' => 33,
                                'order_id' => $data->order_id,
                                'existing_payment_status' => $data->status,
                                'updated_payment_status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                'existing_journey_stage' => $data->stage,
                                'updated_journey_stage' => $JourneyStage_data->stage,
                                'required_payment_data' => '',
                                'pdf_status' => json_encode($pdf_response),
                                'response' => $payment_respone->response,
                                'created_at' => date('Y-m-d H:i:s'),
                            ];
                            DB::table('payment_check_status')->insert($status_data);
                            break;
                        } else {
                            $payment_status = STAGE_NAMES['PAYMENT_FAILED'];
                            $status_data = [
                                'payment_request_response_id' => $data->id,
                                'user_product_journey_id' => $data->user_product_journey_id,
                                'proposal_id' => $data->user_proposal_id,
                                'ic_id' => 33,
                                'order_id' => $data->order_id,
                                'existing_payment_status' => $data->status,
                                'updated_payment_status' => STAGE_NAMES['PAYMENT_FAILED'],
                                'existing_journey_stage' => $data->stage,
                                'updated_journey_stage' => $JourneyStage_data->stage,
                                'required_payment_data' => '',
                                //'pdf_status'                => json_encode($pdf_response),
                                'response' => $payment_respone->response,
                                'created_at' => date('Y-m-d H:i:s'),
                            ];
                            DB::table('payment_check_status')->insert($status_data);
                        }
                    } else {
                        $status_data = [
                            'payment_request_response_id' => $data->id,
                            'user_product_journey_id' => $data->user_product_journey_id,
                            'proposal_id' => $data->user_proposal_id,
                            'ic_id' => 33,
                            'order_id' => $data->order_id,
                            'existing_payment_status' => $data->status,
                            'existing_journey_stage' => $data->stage,
                            //'response'                      => json_encode($payment_respone->response),
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        DB::table('payment_check_status')->insert($status_data);

                    }
                }
            } else {

                $status_data = [
                    'payment_request_response_id' => $data->id,
                    'user_product_journey_id' => $data->user_product_journey_id,
                    'proposal_id' => $data->user_proposal_id,
                    'ic_id' => 33,
                    'order_id' => $data->order_id,
                    'existing_payment_status' => $data->status,
                    'existing_journey_stage' => $data->stage,
                    //'response'                      => json_encode($payment_respone->response),
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                DB::table('payment_check_status')->insert($status_data);

            }

            PaymentRequestResponse::where('id', $data->id)
                ->update(['is_processed' => 'Y']);

        }
    }
}
