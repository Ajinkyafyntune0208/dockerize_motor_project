<?php

namespace App\Http\Controllers\Extra;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use App\Models\CvJourneyStages;
use Illuminate\Support\Str;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\LeadGenerationLogs;
use App\Models\UserProductJourney;
use App\Models\UserProposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UtilityApi extends Controller
{
    public function iciciLombardPosStatusUpdate()
    {
        if(!Schema::hasTable('icici_lombard_pos_mapping'))
        {
            return 
            [   'status' => false,
                'msg'   => 'Required Schema Not Exits'
            ];
        }
        $error_msg = config('ICICI_LOMBARD_POS_FAILED_MGS');
        $error_msg = explode(',',$error_msg);
        $return_data = [];
        foreach($error_msg as $key => $msg)
        {
            $result = DB::table('icici_lombard_pos_mapping as i')
                        ->where('status', 'success')
                        ->where('response', 'like', '%' . $msg . '%')
                        ->update(['agent_id' => 0 , 'status' => 'failure']);
            $return_data[$msg] = $result;
        }
        return $return_data;
    }

    public function changeJourneyStage(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'enquiryId' => 'required',
                'source'    => 'required',
                'reason'    => 'required',
                'stage'     => 'required',
                'cheksum'   => 'required',
                'agent_id'  => 'required'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if (is_numeric($request->enquiryId) && strlen($request->enquiryId) == 16) {
            $enquiryId = Str::substr($request->enquiryId, 8);
        } else {
            $enquiryId = customDecrypt($request->enquiryId);
        }

        $data_insert_id =  DB::table('cancelled_policy_logs')->insertGetId([
            'enquiry_id' => $enquiryId,
            'request' => json_encode($request->all()),
            'response' => NULL,
            'source' => $request->source,
            'reason' => $request->reason ?? null,
            'agent_id' => $request->agent_id ,
            'created_at' =>  now()->toDateTimeString(),
            'updated_at' =>  now()->toDateTimeString(),
        ]);

        if (!in_array($request->stage, explode(',',config('ALLOWED_STAGES_CHANGE_VALIDATION')))) {
            return
            [
                'status' => false,
                'msg'   => 'Invalid Stage'
            ];
        }

        if (!in_array($request->source, explode(',',config('ALLOWED_SOURCE_CHANGE_VALIDATION')))) {
            return
            [
                'status' => false,
                'msg'   => 'Invalid Source'
            ];
        }

        $cheksum = '@#$%^&878454545#@%$#@GJG$#$%^%$#45454544544^%%#$#%$^$$4HGG';
        
        if($request->cheksum != $cheksum)
        {
            return response()->json([
                "status" => false,
                "message" => "Invalid Cheksum",
            ]);
        }

        if(($request->agent_id ==  null || $request->agent_id ==  "null")  && !is_numeric($request->agent_id))
        {
            return response()->json([
                "status" => false,
                "message" => "Invalid Agent ID",
            ]);
        }

        if (is_numeric($data_insert_id)) {
            CvJourneyStages::where(
                'user_product_journey_id',
                $enquiryId
            )
            ->update([
                'stage' => 'Cancelled'
            ]);

            $response = [
                'enquiry_id' => $enquiryId,
                'status' => true,
                'msg'   => 'Stage Update SuccessFully',
            ];

            DB::table('cancelled_policy_logs')
                ->where('enquiry_id', $enquiryId)
                ->where('id', $data_insert_id)
                ->update([
                    'response' => json_encode($response, true)
                ]);

            return [
                'status' => true,
                'msg'   => 'Stage Update SuccessFully'
            ];
        } else {
            $response = [
                'enquiry_id' => $enquiryId,
                'status' => false,
                'msg'   => 'Stage Update Failed',
            ];

            DB::table('cancelled_policy_logs')
                ->where('enquiry_id', $enquiryId)
                ->update([
                    'response' => json_encode($response, true)
                ]);
                
            return [
                'status' => false,
                'msg'   => 'Stage Update Failed'
            ];
        }
    }
    public static function registrationNoCheck($request)
    {
        //$enquiry_id = (strlen($request->enquiryId) == 16 && is_numeric($request->enquiryId)) ? $request->enquiryId : customDecrypt($request->enquiryId);
        $enquiry_id = customDecrypt($request->enquiryId);
        $vehicle_registration_no =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)
                                    ->pluck('vehicle_registration_no')->first();
        // $user_proposal = DB::table('user_proposal')->where('user_product_journey_id', $enquiry_id)->first();
        // $corporate_table = DB::table('corporate_vehicles_quotes_request')->where('user_product_journey_id', $enquiry_id)->first();
        // $reg_no = '';
        //$data = [];
        // if (empty($user_proposal->vehicale_registration_number)) {
        //     return  [
        //         "status" => true
        //     ];
        // }
        // if (empty($corporate_table->vehicle_registration_no)) 
        // {
        //     return  [
        //         "status" => true
        //     ];
        // }
        // if($user_proposal->vehicale_registration_number == $corporate_table->vehicle_registration_no)
        // {
        //     $reg_no = $corporate_table->vehicle_registration_no ?? $user_proposal->vehicale_registration_number;
        // } 
        // else 
        // {
        //     $data = [
        //         "status" => false,
        //         "message" => 'Registration Number Not Present In DB',
        //     ];
        // }
        // if (!empty($reg_no) && $reg_no == ($request->registration_no ?? $request->vehicleRegistrationNo) ) 
        // {
        //     $data = [
        //         "status" => true,
        //     ];
        // } 
        // else 
        // {
        //     $data = [
        //         "status" => false,
        //         "message" => 'Registration Number Not Present In DB',
        //     ];
        // }
        // $status = true;
        // $message = null;
        $data = [
            "status" => true,
            "message" => null
        ];
        $vehicleRegistrationNo = NULL;
        if(!empty($request->registration_no))
        {
            $vehicleRegistrationNo = $request->registration_no;
        }
        else if(!empty($request->vehicleRegistrationNo))
        {
            $vehicleRegistrationNo = $request->vehicleRegistrationNo;
        }
        if (!empty($vehicle_registration_no) && !empty($vehicleRegistrationNo) )
        {
            if(str_replace("-","",$vehicle_registration_no) != str_replace("-","",$vehicleRegistrationNo))
            {
                $data = [
                    "status" => false,
                    "message" => 'Requested vehicle number is '.$vehicleRegistrationNo.' and Existing number is '. $vehicle_registration_no
                ];
            }
        }
        return $data;
    }
    public function correctionReportApi(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'enquiryId'             => 'required|numeric',
                'cheksum'               => 'required',
                'od_premium'            => 'required|numeric',
                'tp_premium'            => 'required|numeric',
                'addon_premium'         => 'required|numeric',
                'ncb_discount'          => 'required|numeric',
                'total_discount'        => 'required|numeric',          
                'total_premium'         => 'required|numeric',
                'service_tax_amount'    => 'required|numeric',
                'final_payable_amount'  => 'required|numeric',         
                'cpa_premium'           => 'required|numeric'
            ]
        );

        if ($validator->fails()) 
        {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if (is_numeric($request->enquiryId) && strlen($request->enquiryId) == 16) 
        {
            $enquiryId = Str::substr($request->enquiryId, 8);
        } 
        else 
        {
            $enquiryId = customDecrypt($request->enquiryId);
        }

        $data_found = UserProposal::where('user_product_journey_id', $enquiryId)->exists();
        if(!$data_found)
        {
            return response()->json([
                "status" => false,
                "message" => 'No data found',
            ]);
        }
        $is_data_updated = UserProposal::where('user_product_journey_id' , $enquiryId)
        ->update([
            'od_premium'            => $request->od_premium,
            'tp_premium'            => $request->tp_premium,
            'addon_premium'         => $request->addon_premium,
            'ncb_discount'          => $request->ncb_discount,
            'total_discount'        => $request->total_discount,       
            'total_premium'         => $request->total_premium,
            'service_tax_amount'    => $request->service_tax_amount,
            'final_payable_amount'  => $request->final_payable_amount,            
            'cpa_premium'           => $request->cpa_premium
        ]);
        return $return_data = [
            'status' => true,
            'message' => 'Data Updated'
        ];
    }
    public function ChassisEngineCheck(Request $request)
    {
        $business_type = $request->input('business_type');
        $engineNumber = $request->input('engine_number');
        $chassisNumber = $request->input('chassis_number');
        $enquiry_id = $request->input('enquiry_id');
        $userProposal = UserProposal::query()
            ->join('cv_journey_stages', 'user_proposal.user_product_journey_id', '=', 'cv_journey_stages.user_product_journey_id')
            ->where(function ($query) use ($engineNumber, $chassisNumber) {
                $query->where('engine_number', $engineNumber)
                    ->orWhere('chassis_number', $chassisNumber);
            })
            ->where(function ($query) {
                $query->where('cv_journey_stages.stage', STAGE_NAMES['POLICY_ISSUED'])
                ->orWhere('cv_journey_stages.stage', STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'])
                ->orWhere('cv_journey_stages.stage', STAGE_NAMES['PAYMENT_SUCCESS']);
            })
            ->orderBy('user_proposal.created_at', 'desc')
            ->first();

        if (!$userProposal) {
            return response()->json(['message' => 'No matching user proposal found.'], 404);
        }

        if ($business_type[0] == "newbusiness") {
            if ($userProposal) {
                return response()->json([
                    'status' => false,
                    'message' => 'Engine number or chassis number is already associated with a policy.',
                ]);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'Engine Number or Chassis Number is not there in the system.Proceed with policy creation',
                ]);
            }
        }
        $previousPolicyType =  UserProposal::join('corporate_vehicles_quotes_request', 'user_proposal.user_product_journey_id', '=', 'corporate_vehicles_quotes_request.user_product_journey_id')
            ->join('cv_journey_stages', 'cv_journey_stages.user_product_journey_id', '=', 'corporate_vehicles_quotes_request.user_product_journey_id')
        ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'corporate_vehicles_quotes_request.user_product_journey_id')
        ->join('master_policy', 'quote_log.master_policy_id', '=', 'master_policy.policy_id')
        ->join('master_premium_type', 'master_policy.premium_type_id', '=', 'master_premium_type.id')
        ->select(
                'master_premium_type.premium_type_code',
                'master_policy.business_type',
                'cv_journey_stages.stage',
                'user_proposal.policy_start_date',
                'user_proposal.policy_end_date',
                'user_proposal.tp_start_date',
                'user_proposal.tp_end_date',
                'corporate_vehicles_quotes_request.previous_policy_expiry_date',
                'user_proposal.user_product_journey_id',
                'master_policy.policy_id as master_policy_id',
                'master_premium_type.id'
            )
            ->where(function ($query) use ($userProposal) {
                $query->where('user_proposal.engine_number', $userProposal->engine_number)
                    ->orWhere('user_proposal.chassis_number', $userProposal->chassis_number);
            })
            ->whereIn('cv_journey_stages.stage', [
                STAGE_NAMES['POLICY_ISSUED'],
                STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                STAGE_NAMES['PAYMENT_SUCCESS']
            ])
            ->orderBy('user_proposal.user_product_journey_id', 'desc')
            ->get();
    $currentpolicyDrafted = UserProposal::join('corporate_vehicles_quotes_request', 'user_proposal.user_product_journey_id', '=', 'corporate_vehicles_quotes_request.user_product_journey_id')
        ->join('cv_journey_stages', 'cv_journey_stages.user_product_journey_id', '=', 'corporate_vehicles_quotes_request.user_product_journey_id')
        ->join('quote_log', 'quote_log.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        ->join('master_policy', 'quote_log.master_policy_id', '=', 'master_policy.policy_id')
        ->join('master_premium_type', 'master_policy.premium_type_id', '=', 'master_premium_type.id')
        ->select(
            'master_premium_type.premium_type_code',
            'master_policy.business_type',
            'cv_journey_stages.stage',
            'corporate_vehicles_quotes_request.previous_policy_expiry_date',
            'user_proposal.user_product_journey_id',
            'master_policy.policy_id as master_policy_id',
            'master_premium_type.id'
        )
            ->where('user_proposal.user_product_journey_id', $enquiry_id)
            ->whereIn('cv_journey_stages.stage', [
                STAGE_NAMES['PROPOSAL_DRAFTED'],STAGE_NAMES['PROPOSAL_ACCEPTED']
                ])
            ->orderBy('user_proposal.user_product_journey_id', 'desc')
            ->get();

        if ($currentpolicyDrafted->isNotEmpty()) {
            foreach ($currentpolicyDrafted as $currentPolicy) {
                $currentPolicyType = $currentPolicy->premium_type_code;
                $currentBusinessType = $currentPolicy->business_type;
                $currentStage = $currentPolicy->stage;
                $currentPolicyDate = $currentPolicy->previous_policy_expiry_date;
            }
        }
        if ($previousPolicyType->isNotEmpty()) {
            foreach ($previousPolicyType as $previousPolicy) {
                $previousPolicyType = $previousPolicy->premium_type_code;
                $previousBusinessType = $previousPolicy->business_type;
                $previousStage = $previousPolicy->stage;
                $previousPolicyDate = $previousPolicy->previous_policy_expiry_date;
                $policyStartDate = $previousPolicy->policy_start_date;
                $policyEndDate = $previousPolicy->policy_end_date;
                $tpEndDate = $previousPolicy->tp_end_date;
                $tpStartDate = $previousPolicy->tp_start_date;
                if ($currentpolicyDrafted->isNotEmpty()) {
                    foreach ($currentpolicyDrafted as $currentPolicy) {
                        if ($currentPolicyType === 'third_party') {
                            $policyStartDate = Carbon::parse($tpStartDate ?? $previousPolicy->policy_start_date);
                            $policyEndDate =  Carbon::parse($tpEndDate ?? $previousPolicy->policy_end_date);
                        } elseif ($currentPolicy->previous_policy_expiry_date === 'New') {
                            $currentPolicyDate = Carbon::now();;
                        } else {
                            $currentPolicyDate = Carbon::parse($currentPolicy->previous_policy_expiry_date);
                        }

                        $currentPolicyDate = Carbon::parse($currentPolicyDate);

                        if ($currentPolicyDate->eq(Carbon::parse($policyEndDate))) {
                            $isBetween = false;
                         } 
                         elseif($currentPolicyDate->lt($policyStartDate) || $currentPolicyDate->lt($policyEndDate) && $currentPolicy->business_type=='breakin') {
                            $isBetween = true;
                        }
                        else {
                            $isBetween = $currentPolicyDate->between(
                                Carbon::parse($policyStartDate),
                                Carbon::parse($policyEndDate)
                            );
                        }
                        if ($userProposal->engine_number === $engineNumber && $userProposal->chassis_number === $chassisNumber  && $isBetween &&  $this->isPolicyTypeCovered($previousPolicyType, $currentPolicyType) || ($previousPolicy->premium_type_code === $currentPolicy->premium_type_code &&
                        $previousPolicy->business_type === $currentPolicy->business_type)) {
                            return response()->json([
                                'status' => false,
                                'message' => 'Engine Number and Chassis Number is already associated with a policy.',
                                'data' => $userProposal,
                            ]);
                        }
                        elseif ($userProposal->engine_number === $engineNumber && $isBetween &&  $this->isPolicyTypeCovered($previousPolicyType, $currentPolicyType) || ($previousPolicy->premium_type_code === $currentPolicy->premium_type_code &&
                        $previousPolicy->business_type === $currentPolicy->business_type)) {
                            return response()->json([
                                'status' => false,
                                'message' => 'Engine Number is already associated with a policy.',
                                'data' => $userProposal,
                            ]);
                        }
                        elseif ($userProposal->chassis_number === $chassisNumber && $isBetween &&  $this->isPolicyTypeCovered($previousPolicyType, $currentPolicyType) || ($previousPolicy->premium_type_code === $currentPolicy->premium_type_code &&
                        $previousPolicy->business_type === $currentPolicy->business_type)) {
                            return response()->json([
                                'status' => false,
                                'message' => 'Chassis Number is already associated with a policy.',
                                'data' => $userProposal,
                            ]);
                        }
                        else {
                            return response()->json([
                                'status' => true,
                                'message' => 'Engine Number or Chassis Number is not there in the system.Proceed with policy creation',
                            ]);
                        }
                    }
                }
            }
            return response()->json([
                'status' => true,
                'message' => 'Engine Number or Chassis Number is not there in the system.Proceed with policy creation',
            ]);
        } else {

            return response()->json([
                'status' => true,
                'message' => 'Engine Number or Chassis Number is not there in the system.Proceed with policy creation',
            ]);
        }
    }

    private function isPolicyTypeCovered($previousPolicyType, $currentPolicyType)
    {
        $policyCoverage = [
            'comprehensive' => ['own_damage', 'third_party', 'comprehensive','breakin', 'own_damage_breakin', 'third_party_breakin','short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin'],
            'third_party' => ['third_party','comprehensive','third_party_breakin','breakin','short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin'],
            'own_damage' => ['own_damage', 'own_damage_breakin','breakin','short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin'],
            'breakin' => ['breakin','comprehensive','third_party','third_party_breakin','short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin'],
            'own_damage_breakin' => ['own_damage_breakin','comprehensive','own_damage','short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin'],
            'third_party_breakin' => ['third_party_breakin','third_party','comprehensive','short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin'],
            'short_term_3' => ['short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin','comprehensive','third_party','own_damage','breakin','own_damage_breakin','third_party_breakin'],
            'short_term_6' => ['short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin','comprehensive','third_party','own_damage','breakin','own_damage_breakin','third_party_breakin'],
            'short_term_3_breakin' => ['short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin','comprehensive','third_party','own_damage','breakin','own_damage_breakin','third_party_breakin'],
            'short_term_6_breakin' => ['short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin','comprehensive','third_party','own_damage','breakin','own_damage_breakin','third_party_breakin']
        ];
        return in_array($currentPolicyType, $policyCoverage[$previousPolicyType]);
    }
    
    public static function leadSoruceMapping($reg_no, $days, $newEnquiryId)
    {
        $dateThreshold = now()->subDays($days);
        $userProductJourneyId = CorporateVehiclesQuotesRequest::where('vehicle_registration_no', $reg_no)
            ->where('created_on', '>=', $dateThreshold)
            ->where('user_product_journey_id', '!=', $newEnquiryId)
            ->orderBy('created_on', 'desc')
            ->value('user_product_journey_id');

        if (empty($userProductJourneyId)) {
            return [
                'status' => false
            ];
        }
        $leadSource = UserProductJourney::where('user_product_journey_id', $userProductJourneyId)
            ->pluck('lead_source')
            ->first();

        $utm_request = LeadGenerationLogs::where('enquiry_id', $userProductJourneyId)
            ->where('method', 'payload received')
            ->latest()->value('request');
            
        if (!empty($utm_request)) {
            $utm_details = json_decode($utm_request, true)['utm'];
            $temp = [
                'broker_utm_source' => isset($utm_details['utm_source']) ? $utm_details['utm_source'] : null,
                'broker_utm_media' => isset($utm_details['utm_media']) ? $utm_details['utm_media'] : null,
                'broker_utm_campaign' => isset($utm_details['utm_campaign']) ? $utm_details['utm_campaign'] : null
            ];
        }

        if (!empty($leadSource)) {
            return [
                'status' => true,
                'lead_source' => $leadSource,
                'utm_source'  => $temp ?? null
            ];
        } else {
            return [
                'status' => false
            ];
        }
    }
}
