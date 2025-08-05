<?php

namespace App\Http\Controllers\LiveCheck;

use App\Http\Controllers\Controller;
use App\Models\CvBreakinStatus;
use App\Models\User;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\QuoteLog;
use Illuminate\Support\Carbon;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class LivechekBreakinController extends Controller
{

    /*  All constant defination
    $EndPointUrl = 'http://newapi.test.livechek.com/api/reports/';
    $AppKey = 'ACIBKNqTj9kW6x0aInAaqF';
    $AppId = '5b31e4ebceaaee0a8f4f53c7';
    $CompanyId = '5af7d41259dbf77d08d1f35a';
    $BranchId = '5af7d57059dbf77d08d1f363';
    $AppUserId = '9999888366'; */

    # breakin id Generation
    public function LiveChekBreakin($payload)
    {
        try {
            
            $this->validateData($payload);
            $appId = $companyId = $branchId = $appUserId = $App_key = NULL;
            if($payload['ic_name'] == 'iffco_tokio')
            {
                $appId      =  config('constants.IcConstants.iffco_tokio.IFFCO_TOKIO_LIVE_CHEK_APP_ID');
                $companyId  =  config('constants.IcConstants.iffco_tokio.IFFCO_TOKIO_LIVE_CHEK_COMPANY_ID');
                $branchId   =  config('constants.IcConstants.iffco_tokio.IFFCO_TOKIO_LIVE_CHEK_BRANCH_ID');
                $appUserId  =  config('constants.IcConstants.iffco_tokio.IFFCO_TOKIO_LIVE_CHEK_APP_USER_ID');             
                $App_key    =  config('constants.IcConstants.iffco_tokio.IFFCO_TOKIO_LIVE_CHEK_APP_KEY');             
            }
            $live_chek_request = [
                'appId'     => $appId,
                'refId'     => time(),
                'companyId' => [
                    $companyId
                ],
                'branchId' => [
                    $branchId
                ],
                'appUserId'         => $appUserId,
                'name'              => $payload['user_name'],
                'mobileNumber'      => $payload['mobile_name'],
                'Email'             => $payload['user_email'],
                'regNumber'         => str_replace('-', '', $payload['reg_number']),
                'vehicleCategory'   => $payload['vehicle_category'],
                'make'              => $payload['veh_manuf']. $payload['veh_model'],
                'brand'             => $payload['veh_variant'],
                'fuelType'          => $payload['fuel_type'],
                'modelYear'         => $payload['model_year'],
                'address'           => $payload['address'],
                'city'              => $payload['city']
            ];
            $get_response = getWsData(config('constants.IcConstants.live_chek.LIVE_CHEK_END_POINT_URL'), $live_chek_request, 'live_chek', [
                'headers' => [
                    'Content-Type' => 'Application/json',
                    'App-key' => $App_key
                ],
                'enquiryId' => $payload['enquiry_id'],
                'requestMethod' => 'post',
                'section' => $payload['section'],
                'method' => 'BreakinGeneration',
                'transaction_type' => 'proposal',
            ]);
            $response = $get_response['response'];

            if ($response) {
                $data = json_decode($response, true);
                if (static::statusCodeCheck($data)) {
                    if (isset($data['data']) === true) {
                        return [
                            'status' => true,
                            'data' => $data['data']
                        ];
                    }else{
                        return [
                            'status' => true,
                            'data' => $data
                        ];
                    }
                }else {
                    throw new \Exception('Something Went wrong while creating breakin Id');
                }
            }else{
                throw new \Exception('Something Went wrong while creating breakin Id');
            }


        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
        }

    }

    # validation function
    private function validateData($payload){

        $tags = ['user_name', 'user_email', 'reg_number', 'veh_manuf', 'veh_model', 'mobile_name', 'vehicle_category', 'fuel_type', 'model_year', 'address', 'city', 'enquiry_id', 'section'];

        foreach ($tags as $key => $value) {
            if(isset($payload[$value]) === false || empty($payload[$value]) === true)
            {
                throw new \Exception($value.' tag/value is required');
            }
        }

        $categories = [
            'car', 'bike', 'scooty', 'truck', 'mini truck', 'tractor', 'bus', 'mini bus', '3-wheeler passenger', '3-wheeler cargo', 'earth-moving vehicle'];

       if (!in_array($payload['vehicle_category'], $categories)) {
            throw new \Exception('Vehicle category provided is incorrect');
       }

    }

    # Verify service status
    static function statusCodeCheck($payload)
    {
        if (isset($payload['status']['code']) === true && $payload['status']['code'] === 200) {
            return true;
        }else{
            return false;
        }
    }

    # To check breakin inspection status
    static function inspectionConfirm($payload)
    {
        try {
            $inspectionNo = $payload['inspectionNo'];
            $verifiedResult = static::verifyBreakinData($inspectionNo);
            
            //$InspectionCheckEndPointUrl = 'http://newapi.test.livechek.com/api/reports/'.$inspectionNo.'/status';
            $InspectionCheckEndPointUrl = config('constants.IcConstants.live_chek.LIVE_CHEK_END_POINT_URL').$inspectionNo.'/status';
            // $InspectionCheckEndPointUrl = 'http://newapi.test.livechek.com/api/reports/'.'1643797557'.'/status';
            $App_key = NULL;
            if($payload['ic_name'] == 'iffco_tokio')
            {
               $App_key  =  config('constants.IcConstants.iffco_tokio.IFFCO_TOKIO_LIVE_CHEK_APP_KEY');
            }
            else if($payload['ic_name'] == 'future_generali')
            {
                $App_key  =  config('constants.IcConstants.future_generali.APP_KEY_FG_LIVE_CHECK');
            }
            $get_response = getWsData($InspectionCheckEndPointUrl, '', 'live_chek', [
                'headers' => [
                    'Content-Type' => 'Application/json',
                    'App-key' => $App_key
                ],
                'enquiryId' => $verifiedResult['data']['enquiryId'],
                'requestMethod' => 'get',
                'section' => $verifiedResult['data']['section'],
                'method' => 'CheckInspectionStatus',
                'transaction_type' => 'proposal',
            ]);
            $response = $get_response['response'];

            if ($response) {
                $data = json_decode($response, true);
                if (static::statusCodeCheck($data)) {
                    if (isset($data['data']['result'][0]['refId']) === true) {

                        if(isset($data['data']['result'][0]['status']) === true && static::verifyBreakinStatus($data['data']['result'][0]['status']))
                        {
                            $breakinStatusUpdate = CvBreakinStatus::where('user_proposal_id', $verifiedResult['data']['user_proposal_id'])
                            ->where('breakin_number', $inspectionNo);
                            $user_proposal = UserProposal::where('user_proposal_id', $verifiedResult['data']['user_proposal_id'])->first();

                            if ($data['data']['result'][0]['status'] == 'accepted') {
                                $journey_payload = DB::table('cv_journey_stages')->where('proposal_id', $verifiedResult['data']['user_proposal_id'])->first();

                                if($payload['ic_name'] == 'future_generali')
                                {
                                    $payment_end_date = date('Y-m-d 23:59:59', strtotime(date('Y-m-d', strtotime($data['data']['result'][0]['updatedAt'])). 'today midnight +1 Day'));

                                    $policy_start_date = date('d-m-Y', strtotime("+1 day"));
                                    $policy_end_date = date('d-m-Y', strtotime(date('d-m-Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))))));
                                    $user_proposal->policy_start_date = $policy_start_date;
                                    $user_proposal->policy_end_date = $policy_end_date;
                                }else
                                {
                                    $payment_end_date = date('Y-m-d H:i:s', strtotime($data['data']['result'][0]['updatedAt']. '+3 Day'));
                                }

                                $breakinStatusUpdate->update([
                                    'breakin_status' => STAGE_NAMES['INSPECTION_APPROVED'],
                                    'breakin_status_final' => STAGE_NAMES['INSPECTION_APPROVED'],
                                    'payment_url' => $journey_payload->proposal_url,
                                    'breakin_response' => json_encode($data),
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'inspection_date' => date('Y-m-d', strtotime($data['data']['result'][0]['updatedAt'])),
                                    'breakin_id' => $data['data']['result'][0]['refId'] ?? null,
                                    'payment_end_date' => $payment_end_date
                                ]);

                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['INSPECTION_ACCEPTED']
                                ]);

                                $user_proposal->is_inspection_done = 'Y';
                                $user_proposal->save();

                                if(config('constants.IcConstants.iffco_tokio.IS_BREAKIN_INSPECTION_DATE_CHANGES_AVAILABLE') == 'Y')
                                {
                                    self::iffcoLiveCheck($user_proposal, $data, $inspectionNo);
                                }

                                return response()->json([
                                    'status' => true,
                                    'msg' => 'Vehicle Inspection is Done By Live Chek!',
                                    'data' => [
                                        'total_payable_amount' => $user_proposal->final_payable_amount,
                                        'enquiryId' => $user_proposal->user_product_journey_id,
                                        'proposalUrl' => $journey_payload->proposal_url
                                    ]
                                ]);
                            }elseif ($data['data']['result'][0]['status'] == 'rejected') {
                                $breakinStatusUpdate->update([
                                    'breakin_status' => STAGE_NAMES['INSPECTION_REJECTED'],
                                    'breakin_status_final' => STAGE_NAMES['INSPECTION_REJECTED'],
                                    'breakin_response' => json_encode($data),
                                    'updated_at' => date('Y-m-d H:i:s'),
                                    'inspection_date' => date('Y-m-d', strtotime($data['data']['result'][0]['updatedAt']))
                                ]);
                                $user_proposal->is_inspection_done = 'Y';
                                $user_proposal->save();

                                updateJourneyStage([
                                    'user_product_journey_id' => $user_proposal->user_product_journey_id,
                                    'stage' => STAGE_NAMES['INSPECTION_REJECTED']
                                ]);

                                return response()->json([
                                    'status' => false,
                                    'msg' => 'Vehicle Inspection is Rejected By Live Chek',
                                ]);
                            }

                            throw new \Exception('Invalid Case!');


                        }else {
                            throw new \Exception('Inspection Pending from Insurance Company!');
                        }

                    }else{
                       throw new \Exception('Something Went wrong!');
                    }
                }else {
                    throw new \Exception('Something Went wrong!');
                }
            }else {
                throw new \Exception('Something Went wrong!');
            }

        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'error_trace' => basename($e->getFile()) . ' : ' .$e->getLine(),
            ];
        }

    }

    # verify breakin id exist or not
    static function verifyBreakinData($breakin_no)
    {
        $util_data = CvBreakinStatus::where('breakin_number', $breakin_no)->first();

        if (!$util_data) {
            throw new \Exception('Breakin Id does not exist', 404);
        }else{

            $user_proposal = UserProposal::where('user_proposal_id', $util_data->user_proposal_id)->first();

            return [
                'status' => true,
                'data' => [
                    'enquiryId' => $user_proposal->user_product_journey_id,
                    'section' => $util_data->section,
                    'user_proposal_id' => $util_data->user_proposal_id
                ]
            ];
        }

    }

    # verify breakin final status and return boolean value
    static function verifyBreakinStatus($breakin_status)
    {
        if($breakin_status == 'company-approved' || $breakin_status == 'inspectionStarted' || $breakin_status == 'in-process')
        {
            return false;
        }elseif ($breakin_status == 'rejected' || $breakin_status == 'accepted') {
           return true;
        }
    }

    static function iffcoLiveCheck($proposal, $breakinResponse, $inspectionNo)
    {
        $breakinStatusUpdate = CvBreakinStatus::where('user_proposal_id', $proposal->user_proposal_id)
        ->where('breakin_number', $inspectionNo);

        $breakinStatusUpdate->update([
            'breakin_response' => json_encode($breakinResponse),
            'updated_at' => date('Y-m-d H:i:s'),
            'inspection_date' => Carbon::parse($breakinResponse['data']['result'][0]['updatedAt'])->setTimezone('Asia/Calcutta')->format('Y-m-d H:i:s'),
            'payment_end_date' => Carbon::parse($breakinResponse['data']['result'][0]['updatedAt'])->addDay(3)->setTimezone('Asia/Calcutta')->format('Y-m-d H:i:s')
        ]);

        $quote_log_data = DB::table('quote_log as ql')
        ->join('master_policy as mp', 'mp.policy_id', '=', 'ql.master_policy_id')
        ->join('master_company as mc', 'mc.company_id', '=', 'ql.ic_id')
        ->join('master_premium_type as mpt', 'mpt.id', '=', 'mp.premium_type_id')
        ->where('user_product_journey_id', $proposal->user_product_journey_id)
        ->select('ql.product_sub_type_id as product_sub_type_id', 'ql.ic_id', 'mc.company_alias', 'mp.premium_type_id as premium_type_id', 'ql.user_product_journey_id', 'mpt.premium_type_code as premium_type')
        ->first();

        if($quote_log_data->company_alias == 'iffco_tokio' && $quote_log_data->product_sub_type_id == 6)
        {
            $policy_start_date = Carbon::parse(date('d-m-Y'));

            if ($quote_log_data->premium_type == 'short_term_3_breakin') {
                $policy_end_date = Carbon::parse($policy_start_date)->addMonth(3)->subDay(1);
            } elseif ($quote_log_data->premium_type == 'short_term_6_breakin') {
                $policy_end_date = Carbon::parse($policy_start_date)->addMonth(6)->subDay(1);
            } else {
                $policy_end_date = Carbon::parse($policy_start_date)->addyear(1)->subDay(1);
            }
            
            UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'is_inspection_done' => 'Y',
                    'policy_start_date' => $policy_start_date->format('d-m-Y'),
                    'policy_end_date' => $policy_end_date->format('d-m-Y'),
            ]);
        }
    }

}
