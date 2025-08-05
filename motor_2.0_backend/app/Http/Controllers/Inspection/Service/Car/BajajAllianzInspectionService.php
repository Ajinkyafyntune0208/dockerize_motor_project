<?php

namespace App\Http\Controllers\Inspection\Service\Car;

use Illuminate\Support\Facades\DB;
use App\Models\PolicyDetails;
use App\Models\JourneyStage;
use App\Models\UserProposal;
use App\Models\CvBreakinStatus;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';
class BajajAllianzInspectionService
{
    public static function inspectionConfirm($request)
    {
        $breakinDetails = CvBreakinStatus::with(['user_proposal','user_proposal.journey_stage'])
            ->where('cv_breakin_status.breakin_number', '=', trim($request->inspectionNo))
            ->first();
        // $product = getProductDataByIc($breakinDetails->master_policy_id);
        if($breakinDetails->user_proposal->vehicale_registration_number){
            $Reg_no = explode("-", $breakinDetails->user_proposal->vehicale_registration_number);
        }
        if ($breakinDetails) {
            $policy_details = PolicyDetails::where('proposal_id', $breakinDetails->user_proposal->user_proposal_id)->first();

            if ($policy_details) {
                $status = false;
                $message = 'Policy has already been generated for this inspection number';
            } else {
                $breakin_status_response = json_decode($breakinDetails->breakin_response, TRUE);
                if (isset($breakin_status_response['policyStatus']) && in_array($breakin_status_response['policyStatus'], ['PRE_INSPECTION_APPROVED', 'INCOMPLETE'])) {
                    $data = $breakinDetails->breakin_response;
                } else {
                    $breakin_details_input = [
                        'x:Header' => '',
                        'x:Body' => [
                            'bag:pinStatusWs' => [
                                'bag:pRegNoPart1'   => $Reg_no[0],
                                'bag:pRegNoPart2'   => $Reg_no[1],
                                'bag:pRegNoPart3'   => $Reg_no[2],
                                'bag:pRegNoPart4'   => $Reg_no[3],
                                'bag:pPinList_out' => [
                                    'typ:WeoRecStrings10User' => [
                                        'typ:stringval2'    => '',
                                        'typ:stringval3'    => '',
                                        'typ:stringval1'    => '',
                                        'typ:stringval6'    => '',
                                        'typ:stringval7'    => '',
                                        'typ:stringval4'    => '',
                                        'typ:stringval5'    => '',
                                        'typ:stringval8'    => '',
                                        'typ:stringval10'   => '',
                                        'typ:stringval9'    => ''
                                    ]
                                ],
                                'bag:pErrorMessage_out'     => '',
                                'bag:pErrorCode_out'     => ''
                            ]

                        ]
                    ];
                    $root = [
                        '_attributes' => [],
                    ];
                    $input_array = ArrayToXml::convert($breakin_details_input, $root, false, 'utf-8');
                    $input_array = str_replace('<root>', '', $input_array);
                    $input_array = str_replace('</root>', '', $input_array);
                    $url = config('constants.IcConstants.BAJAJ_ALLIANZ_BREAKIN_STATUS_CHECK_API');
                    $get_response = getWsData($url, $input_array, 'bajaj_allianz', [
                        'root_tag'     => 'x:Body',
                        'enquiryId' => $breakinDetails->user_proposal->user_product_journey_id,
                        'requestType'       => 'xml',
                        'section'     => 'car',
                        'method'      => 'Pin Status',
                        'requestMethod' => 'post',
                        'transaction_type' => 'proposal',
                        'headers' => [
                            'Content-Type' => 'application/soap+xml; charset="utf-8"',
                        ],
                        'container'    => '<x:Envelope xmlns:x="http://schemas.xmlsoap.org/soap/envelope/" xmlns:bag="http://com/bajajallianz/BagicMotorWS.wsdl" xmlns:typ="http://com/bajajallianz/BagicMotorWS.wsdl/types/"><x:Header/>#replace</x:Envelope>',
                        'request_data' => [
                            'proposal_id' => $breakinDetails->user_proposal->user_proposal_id,
                            'company'     => 'Bajaj Allianz General Insurance Co. Ltd.'
                        ],
                        'company' => 'Bajaj Allianz General Insurance Co. Ltd.'
                    ]);
                    // print_pre(customDecrypt($breakinDetails->user_product_journey_id));

                    $data = $get_response['response'];
                }
                if ($data) {
                    $data = XmlToArray::convert($data);
                }
               
                // $error = $data["env:Body"]["m:pinStatusWsResponse"]["pPinList_out"]["typ:WeoRecStrings10User"]["typ:stringval5"];
                // $errorMessage = (isset($error)) ? $error : "No records found";
                $no_records = is_array($data["env:Body"]["m:pinStatusWsResponse"] ?? null)
                    ? ($data["env:Body"]["m:pinStatusWsResponse"]["pErrorMessage_out"] ?? null)
                    : null;

                if (empty($no_records) || $no_records == "No of records found-0") {
                    $status = false;
                    $message = "No records found";
                } else {
                    if(isset($data["env:Body"]["m:pinStatusWsResponse"]) && !isset($data["env:Body"]["m:pinProcessWsResponse"])){
                        if(isset($data["env:Body"]["m:pinStatusWsResponse"]["pPinList_out"]["typ:WeoRecStrings10User"][0])) {
                            $data =  $data["env:Body"]["m:pinStatusWsResponse"]["pPinList_out"]["typ:WeoRecStrings10User"][0];
                        } else {
                            $data =  $data["env:Body"]["m:pinStatusWsResponse"]["pPinList_out"]["typ:WeoRecStrings10User"];
                        }
                    }
                    if (isset($data["typ:stringval2"])) {
                        if ($data["typ:stringval2"] == 'PIN_APPRD') {
                            $update_data = [
                                'breakin_response'      => $data,
                                'breakin_status'        => STAGE_NAMES['INSPECTION_APPROVED'],
                                'breakin_status_final'  => STAGE_NAMES['INSPECTION_APPROVED'],
                                'updated_at'            => date('Y-m-d H:i:s'),
                                'payment_url' =>  str_replace('quotes', 'proposal-page', $breakinDetails->user_proposal->journey_stage->proposal_url),
                                'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                                'payment_end_date'      => date('Y-m-d H:i:s', strtotime(' + 1 day'))

                            ];
                            DB::table('cv_breakin_status')
                                ->where('breakin_number', trim($request->inspectionNo))
                                ->update($update_data);

                            // $breakin_make_time = strtotime('18:00:00');

                            // if ($breakin_make_time > time()) {
                            //     $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
                            // } else {
                            //     $policy_start_date = date('Y-m-d', strtotime('+2 day', time()));
                            // }
                            // $policy_start_date = date('Y-m-d', strtotime('+1 day', time()));
                            // $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
                            UserProposal::where('user_product_journey_id', trim($breakinDetails->user_proposal->user_product_journey_id))
                                ->update([
                                    'is_inspection_done' => 'Y',
                                    // 'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                                    // 'policy_end_date' => date('d-m-Y', strtotime($policy_end_date))
                                ]);

                            $journey_stage = JourneyStage::where('user_product_journey_id', trim($breakinDetails->user_proposal->user_product_journey_id))
                                ->first();

                            $status = true;
                            $message = 'Your Vehicle Inspection is Done By bajaj allianz.';
                            updateJourneyStage([
                                'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                            ]);
                            return response()->json([
                                'status' => $status,
                                'msg'    => $message,
                                'data'   => [
                                    'enquiryId' => customEncrypt($breakinDetails->user_proposal->user_product_journey_id),
                                    'proposalNo' => $breakinDetails->user_proposal->proposal_no,
                                    'totalPayableAmount' => $breakinDetails->user_proposal->final_payable_amount,
                                    'proposalUrl' =>  str_replace('quotes', 'proposal-page', $journey_stage->proposal_url)
                                ]
                            ]);
                        } elseif (in_array($data["typ:stringval2"],['PGNR_ALTD','PGNR_PNDNG','PIN_ONHOLD','PIN_ISSD','PIN_CLS','DOC_PNDNG','PIN_AUTCLS']))
                        {
                            $update_data = [
                                'breakin_response' => $data,
                                'updated_at' => date('Y-m-d H:i:s')
                            ];

                            $status = false;
                            $message = 'Your Vehicle Inspection is ' . $data["typ:stringval5"];

                            updateJourneyStage([
                                'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                            ]);
                            $update_data = [
                                'breakin_response' => $data,
                                'breakin_status' => STAGE_NAMES['INSPECTION_PENDING'],
                                'breakin_status_final' => STAGE_NAMES['INSPECTION_PENDING'],
                                'updated_at' => date('Y-m-d H:i:s')
                            ];

                            DB::table('cv_breakin_status')
                                ->where('breakin_number', trim($request->inspectionNo))
                                ->update($update_data);
                        } else {
                            $update_data = [
                                'breakin_response' => $data,
                                'updated_at' => date('Y-m-d H:i:s')
                            ];

                            $status = false;
                            $message = 'Your Vehicle Inspection is ' . $data["typ:stringval5"];

                            updateJourneyStage([
                                'user_product_journey_id' => $breakinDetails->user_proposal->user_product_journey_id,
                                'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                            ]);

                            $update_data = [
                                'breakin_response' => $data,
                                'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                                'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                                'updated_at' => date('Y-m-d H:i:s')
                            ];

                            DB::table('cv_breakin_status')
                                ->where('breakin_number', trim($request->inspectionNo))
                                ->update($update_data);
                        }
                    } else {
                        $status = false;
                        $message = 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction';
                    }
                }
            }
            return response()->json([
                'status' => $status,
                'msg'    => $message
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Please Check Your Inspection Number'
            ]);
        }
    }
}
