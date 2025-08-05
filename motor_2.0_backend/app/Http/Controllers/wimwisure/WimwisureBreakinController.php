<?php

namespace App\Http\Controllers\wimwisure;

use App\Http\Controllers\Controller;
use App\Models\CvBreakinStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class WimwisureBreakinController extends Controller
{

    /**
     *
     * Breakin Id Generation function
     *
     * Optional Remark with #O
     *
     */

    public function WimwisureBreakinIdGen($payload)
    {
        try {

            $this->PayloadValidation($payload);

            $breakin_id_gen_request = [
                'callbackURL' => '', #O
                'chassisNumber' => $payload['chassis_number'], #O
                'customerEmail' => $payload['user_email'],
                'customerName' => $payload['user_name'],
                'customerPhoneNumber' => $payload['mobile_number'],
                'engineNumber' => $payload['engine_number'], #O
                // 'fuelType' => $payload['fuel_type'],
                'inspectionByWimwisure' => true,
                'inspectionNumber' => $payload['inspection_number'] ?? '', #O
                'inspectors' => [
                    $payload['mobile_number'] #O
                ],
                'notifyEmail' => [
                    $payload['user_email'] #O #AddbrokerMail
                ],
                'notifyPhone' => [
                    $payload['mobile_number'] #O #AddbrokerPhone
                ],
                'paidBy' => '', #O
                'purposeOfInspection' => 'break_in',
                'quoteNumber' => $payload['inspection_number'] ?? (string) $payload['enquiry_id'], #0
                'vehicleNumber' => str_replace('-', '', $payload['reg_number']),
                'vehicleType' => '4-wheeler'
            ];



            $broker_email = config('constants.wimwisure.BROKER_EMAIL_FOR_NOTIFICATION') ?? null;
            $broker_contact_number = config('constants.wimwisure.BROKER_CONTACT_NUMBER_FOR_NOTIFICATION') ?? 0;

            if ($broker_email && !empty($broker_email)) {
                array_push($breakin_id_gen_request['notifyEmail'], $broker_email);
            }

            if ($broker_contact_number && !empty($broker_contact_number)) {
                array_push($breakin_id_gen_request['notifyPhone'], $broker_contact_number);
            }

            $get_response = getWsData(config('constants.wimwisure.END_POINT_URL'), $breakin_id_gen_request, 'wimwisure', [
                'headers' => [
                    'X-WIM-TOKEN' => $payload['api_key']
                ],
                'enquiryId' => $payload['enquiry_id'],
                'requestMethod' => 'post',
                'section' => $payload['section'],
                'method' => 'Breakin-Generation',
                'transaction_type' => 'proposal',
            ]);
            $response = $get_response['response'];

            if ($response) {
                if(!empty($response->content))
                {
                    $data = json_decode($response['content'], true);

                    if (isset($response['status']) && $response['status'] != 200 && isset($data['Message'])) {
                        throw new \Exception($data['Message']);
                    }
                    return response()->json([
                        'status' => true,
                        'data' => $data
                    ]);
                }
                else
                {
                    $data =$response;

                    return response()->json([
                        'status' => true,
                        'data' => $data
                    ]);
                }


            }else{
                throw new \Exception('Something went wrong!');
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ]);
        }
    }


    public function WimwisureCheckInspection($payload)
    {
        try {
            $util_data = CvBreakinStatus::where('breakin_number', $payload->inspectionNo)->first();

            if (!$util_data) {
                throw new \Exception('Breakin Id does not exist', 404);
            }

            $proposal = UserProposal::where('user_proposal_id', $util_data->user_proposal_id)->first();

            if (!$proposal) {
                throw new \Exception('Proposal Data Missing...!', 404);
            }

            $end_point_url = config('constants.wimwisure.CHECK_INSPECTION_END_POINT_URL').'/'.$util_data->wimwisure_case_number;

            $get_response = getWsData($end_point_url, '', 'wimwisure', [
                'headers' => [
                    'X-WIM-TOKEN' => $payload->api_key  //$payload['api_key']
                ],
                'enquiryId' => $proposal->user_product_journey_id,
                'requestMethod' => 'get',
                'section' => $util_data->section,
                'method' => 'Check-Inspection-Status',
                'transaction_type' => 'proposal',
            ]);
            $response = $get_response['response'];

            if ($response) {
                $data = json_decode($response['content'], true);

                if (isset($response['status']) && $response['status'] != 200 && isset($data['Message'])) {
                    throw new \Exception($data['Message']);
                }
                
                $util_data->breakin_response = json_encode($data);
                $util_data->updated_at = date('Y-m-d H:i:s');
                $util_data->save();

                if(static::VerifyBreakinStatus($data['Remarks']))
                {
                    if ($data['Remarks'] == 'APPROVED') {
                        $journey_payload = DB::table('cv_journey_stages')->where('proposal_id', $util_data->user_proposal_id)->first();

                        $util_data->breakin_status = STAGE_NAMES['INSPECTION_APPROVED'];
                        $util_data->breakin_status_final = STAGE_NAMES['INSPECTION_APPROVED'];
                        $util_data->payment_url = $journey_payload->proposal_url;
                        $util_data->inspection_date = date('Y-m-d', strtotime($data['InspectionTime']));
                        $util_data->payment_end_date = date('Y-m-d', strtotime($data['InspectionTime'] . '+3 Day'));
                        $util_data->save();

                        $proposal->is_inspection_done = 'Y';
                        $proposal->save();

                        return [
                            'status' => true,
                            'msg' => 'Vehicle Inspection is Done By Wimwisure!',
                            'remarks' => $data['Remarks'],
                            'data' => [
                                'total_payable_amount' => $proposal->final_payable_amount,
                                'enquiryId' => $proposal->user_product_journey_id,
                                'proposalUrl' => $journey_payload->proposal_url
                            ]
                        ];
                    }elseif($data['Remarks'] == 'REJECTED'){
                        $util_data->breakin_status = STAGE_NAMES['INSPECTION_REJECTED'];
                        $util_data->breakin_status_final = STAGE_NAMES['INSPECTION_REJECTED'];
                        $util_data->inspection_date = date('Y-m-d', strtotime($data['InspectionTime']));
                        $util_data->save();

                        $proposal->is_inspection_done = 'Y';
                        $proposal->save();

                        return [
                            'status' => false,
                            'remarks' => $data['Remarks'],
                            'msg' => 'Vehicle Inspection is Rejected By Wimwisure',
                        ];
                    }else{
                        return [
                            'status' => false,
                            'remarks' => $data['Status'],
                            'msg' => 'Vehicle Inspection status is ' . $data['Status']
                        ];
                    }
                }else {
                    return [
                        'status' => false,
                        'remarks' => $data['Status'],
                        'msg' => 'Vehicle Inspection status is ' . $data['Status'] . (! is_null($data['Remarks']) ? ' - ' . $data['Remarks'] : '')
                    ];
                }


            }else{
                throw new \Exception('Something went wrong!');
            }

        } catch (\Exception $e) {
            // return response()->json([
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
            // ]);
        }
    }


    /**
     * verify Breakin Status
     */

    public static function VerifyBreakinStatus($payload)
    {
        if($payload == 'APPROVED' || $payload == 'REJECTED')
        {
            return true;
        }else {
            return false;
        }
    }

    /**
     * validation function
     *
     */

    private function PayloadValidation(&$payload){

        $required_tags = [
            'user_name', 'user_email', 'reg_number',
            'mobile_number', 'fuel_type', 'enquiry_id',
            'mobile_number', 'section', 'api_key'
        ];

        $optional_tags = [
            'chassis_number', 'engine_number'
        ];

        foreach ($required_tags as $key => $value) {
            if(isset($payload[$value]) === false || empty($payload[$value]) === true)
            {
                throw new \Exception($value.' tag/value is required');
            }
        }

        foreach ($optional_tags as $key => $value) {
            if(isset($payload[$value]) === false || empty($payload[$value]) === true)
            {
                $payload['chassis_number'] = '';
                $payload['engine_number'] = '';
            }
        }

    }


}
