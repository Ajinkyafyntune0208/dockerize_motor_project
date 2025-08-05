<?php

namespace App\Jobs;

use App\Models\JourneyStage;
use App\Models\PaymentRequestResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

require_once app_path() . '/Helpers/CarWebServiceHelper.php';

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);
class IffcoTokioPaymentStatusCheck implements ShouldQueue
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
            ->join('user_product_journey as j', 'j.user_product_journey_id', '=', 'prr.user_product_journey_id')
            ->where('prr.is_processed', '=', 'N')
            ->where('prr.ic_id', '=', 13)
            ->whereBetween(DB::raw('DATE(prr.created_at)'), ['2022-08-01', '2022-08-19'])
            ->select('prr.*', 'cjs.stage', 'j.product_sub_type_id')
            ->orderBy('prr.id', 'DESC')
            ->offset($offset)
            ->limit($limit)
            ->get();
        foreach ($payment_request_response as $key => $row) {
            if ($row->order_id != null) {
                $JourneyStage_data = JourneyStage::where('user_product_journey_id', $row->user_product_journey_id)->first();
                //check if the row is of car/bike
                $config_array = [
                    1 => [
                        'partnerCode' => config('constants.IcConstants.iffco_tokio.partnerCodeCar'),
                        'contractType' => 'PCP',
                        'productType' => 'CAR',
                    ],
                    2 => [
                        'partnerCode' => config('constants.IcConstants.iffco_tokio.partnerCodeBike'),
                        'contractType' => 'TWP',
                        'productType' => 'BIKE',
                    ],
                    'cv' => [
                        'partnerCode' => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE'),
                        'contractType' => 'CVI',
                        'productType' => 'CV',
                    ],
                ];
                $product_details = $config_array[(int) $row->product_sub_type_id] ?? $config_array['cv'];
                //prepare the request for payment status check
                $payment_status_check = [
                    'input' => [
                        'attribute1' => '',
                        'attribute2' => '',
                        'attribute3' => '',
                        'contractType' => $product_details['contractType'],
                        'messageId' => '',
                        'partnerCode' => $product_details['partnerCode'],
                        'uniqueQuoteId' => $row->order_id,
                    ],
                ];

                $get_response = getWsData(config('constants.IcConstants.iffco_tokio.ENDPOINT_PAYMENT_STATUS_CHECK'),
                    $payment_status_check,
                    'iffco_tokio',
                    [
                        'root_tag' => 'getPolicyStatus',
                        'enquiryId' => $row->user_product_journey_id,
                        'requestMethod' => 'post',
                        'container' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:prem="http://premiumwrapper.motor.itgi.com"><soapenv:Header /><soapenv:Body>#replace</soapenv:Body></soapenv:Envelope>',
                        'section' => $product_details['productType'],
                        'method' => 'Payment Status',
                        'company' => 'iffco_tokio',
                        'productName' => $product_details['productType'] . ' Insurance',
                        'transaction_type' => 'quote', // We need to store the logs in quote_webservice...
                    ]
                );
                $data = $get_response['response'];
                if ($data) {
                    $payment_response = XmlToArray::convert($data);
                    if (isset($payment_response['soapenv:Body']['getPolicyStatusResponse']['ns1:getPolicyStatusReturn'])) {
                        $payment_response = $payment_response['soapenv:Body']['getPolicyStatusResponse']['ns1:getPolicyStatusReturn'];
                        // Payment is done only when authFlag is 'Y'
                        if ($payment_response['ns1:authFlag'] == 'Y') {
                            // Mark all the Transactions as Payment Initiated
                            PaymentRequestResponse::where('user_product_journey_id', $row->user_product_journey_id)->update([
                                'active' => 0,
                                'status' => STAGE_NAMES['PAYMENT_INITIATED'],
                            ]);
                            // Then Mark single Transaction as Payment Success
                            PaymentRequestResponse::where([
                                'user_product_journey_id' => $row->user_product_journey_id,
                                'id' => $row->id,
                            ])->update([
                                'active' => 1,
                                'status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                            ]);
                            $enquiryId = [
                                'enquiryId' => customEncrypt($row->user_product_journey_id),
                            ];
                            $pdf_response = httpRequestNormal(url('api/generatePdf'), 'POST', $enquiryId)['response'];
                            $status_data = [
                                'payment_request_response_id' => $row->id,
                                'user_product_journey_id' => $row->user_product_journey_id,
                                'proposal_id' => $row->user_proposal_id,
                                'ic_id' => 13,
                                'order_id' => $row->order_id,
                                'existing_payment_status' => $row->status,
                                'updated_payment_status' => STAGE_NAMES['PAYMENT_SUCCESS'],
                                'existing_journey_stage' => $row->stage,
                                'updated_journey_stage' => $JourneyStage_data->stage,
                                'required_payment_data' => '',
                                'pdf_status' => json_encode($pdf_response),
                                'response' => $data,
                                'created_at' => date('Y-m-d H:i:s'),
                            ];
                            DB::table('payment_check_status')->insert($status_data);
                        } else {
                            $status_data = [
                                'payment_request_response_id' => $row->id,
                                'user_product_journey_id' => $row->user_product_journey_id,
                                'proposal_id' => $row->user_proposal_id,
                                'ic_id' => 13,
                                'order_id' => $row->order_id,
                                'existing_payment_status' => $row->status,
                                'existing_journey_stage' => $row->stage,
                                //'response'                      => json_encode($payment_respone->response),
                                'created_at' => date('Y-m-d H:i:s'),
                            ];
                            DB::table('payment_check_status')->insert($status_data);
                        }
                    } else {
                        $status_data = [
                            'payment_request_response_id' => $row->id,
                            'user_product_journey_id' => $row->user_product_journey_id,
                            'proposal_id' => $row->user_proposal_id,
                            'ic_id' => 13,
                            'order_id' => $row->order_id,
                            'existing_payment_status' => $row->status,
                            'existing_journey_stage' => $row->stage,
                            //'response'                      => json_encode($payment_respone->response),
                            'created_at' => date('Y-m-d H:i:s'),
                        ];
                        DB::table('payment_check_status')->insert($status_data);
                    }
                } else {
                    $status_data = [
                        'payment_request_response_id' => $row->id,
                        'user_product_journey_id' => $row->user_product_journey_id,
                        'proposal_id' => $row->user_proposal_id,
                        'ic_id' => 13,
                        'order_id' => $row->order_id,
                        'existing_payment_status' => $row->status,
                        'existing_journey_stage' => $row->stage,
                        //'response'                      => json_encode($payment_respone->response),
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                    DB::table('payment_check_status')->insert($status_data);
                }
                PaymentRequestResponse::where('id', $row->id)->update(['is_processed' => 'Y']);
            }
        }
    }
}
