<?php

namespace App\Http\Controllers;

use App\Models\CvAgentMapping;
use App\Models\DashboardPolicyUpdateLogs;
use App\Models\DashboardStageUpdateLogs;
use App\Models\PolicyChangeLog;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\PolicyUpdateLog;
use App\Models\UserProposal;
use Illuminate\Support\Facades\Storage;
use App\Models\JourneyStage;
use App\Models\MasterCompany;
use App\Models\MasterPremiumType;
use App\Models\CorporateVehiclesQuotesRequest;

class DashboardOnlineDataUpate extends Controller
{
    public  function PolicyDetailsUpdate(Request $request)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'section' => ['required', 'not_in:"null"'],
                'trace_id' => ['required', 'not_in:"null"'],
                'is_offline_entry' => ['required', 'boolean'],
                'policy_number' => ['required', 'not_in:"null"'],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'msg' => $validator->errors(),
            ], 400);
        }

        if ($request->section == 'motor') {


            $enquiryId = is_numeric($request->trace_id) && strlen($request->trace_id) == 16
                ? Str::substr($request->trace_id, 8)
                : customDecrypt($request->trace_id);


            $is_online_policy = UserProductJourney::where('user_product_journey_id', $enquiryId)
                ->where(function ($query) {
                    $query->where('lead_source', '!=', 'RENEWAL_DATA_UPLOAD')
                        ->orWhereNull('lead_source');
                })
                ->exists();  //retuen true or false


            //  if (($request->is_offline_entry && $is_online_policy)) {
            //     return response()->json([
            //         'status' => false,
            //         'msg' => 'policy source mismatch (offline / online)'
            //     ], 400);
            // }

            if (($request->is_offline_entry == 0 && !$is_online_policy) || ($request->is_offline_entry == 1 && $is_online_policy)) {
                return response()->json([
                    'status' => false,
                    'msg' => 'policy source mismatch (offline / online)'
                ]);
            }

            if ($is_online_policy && !$request->is_offline_entry) {   // online policy only

                $validator = Validator::make(
                    $request->all(),
                    [
                        'user_id' => ['required', 'not_in:"null"'],
                        'seller_id' => ['required', 'not_in:"null"'],
                        'seller_type' => ['required', 'not_in:"null"'],
                        'seller_name' => ['required', 'not_in:"null"'],
                        'seller_mobile' => ['required', 'not_in:"null"'],
                        'seller_email' => ['required', 'not_in:"null"'],
                        'seller_business_type' => ['required', 'not_in:"null"'],
                        'seller_business_code' => ['required', 'not_in:"null"'],
                        'screenshot_content' => ['required', 'string'],
                        'ss_type' => ['required', 'not_in:"null"']

                    ]
                );


                if ($validator->fails()) {
                    return response()->json([
                        'status' => false,
                        'msg' => $validator->errors(),
                    ], 400);
                }

                $oldData = CvAgentMapping::where('user_product_journey_id', $enquiryId)->first(['seller_type', 'user_name', 'agent_id', 'agent_name', 'agent_mobile', 'agent_email', 'agent_business_type', 'agent_business_code']);

                if (empty($oldData)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'No Seller Details Found.'
                    ], 400);
                }

                $oldDataArray = $oldData ? $oldData->toArray() : [];

                $file = $request->screenshot_content;
                $decoded_data = base64_decode($file);
                $file_extension = $request->ss_type;
                $file_name = $request->policy_number . '_' . uniqid() . '_update_seller_details.' . $file_extension;
                $directoryPath = "policy_details_update/trace_id/{$enquiryId}/{$file_name}";
                if ($decoded_data === false) {
                    return response()->json([
                        'status' => false,
                        'code' => 400,
                        'message' => 'Invalid screenshot data.',
                    ], 400);
                }
                Storage::put($directoryPath , $decoded_data);

                CvAgentMapping::where('user_product_journey_id', $enquiryId)
                    ->update([
                        'seller_type' => $request->seller_type,
                        'agent_id' => $request->seller_id,
                        'user_name' => $request->seller_username,
                        'agent_name' => $request->seller_name,
                        'agent_mobile' => $request->seller_mobile,
                        'agent_email' => $request->seller_email,
                        'agent_business_type' => $request->seller_business_type,
                        'agent_business_code' => $request->seller_business_code,
                    ]);

                $log =  PolicyChangeLog::create([
                    'trace_id' =>  $enquiryId,
                    'user_id' => $request->user_id,
                    'action_type' => 'update_seller_details',
                    'screenshot_url' => $directoryPath,
                    'policy_number' => $request->policy_number,
                    'source' => 'online',
                    'old_data' => json_encode($oldDataArray),
                    'new_data' => json_encode([
                        'seller_type' => $request->seller_type,
                        'agent_id' => $request->seller_id,
                        'user_name' => $request->seller_username,
                        'agent_name' => $request->seller_name,
                        'agent_mobile' => $request->seller_mobile,
                        'agent_email' => $request->seller_email,
                        'agent_business_type' => $request->seller_business_type,
                        'agent_business_code' => $request->seller_business_code,
                    ]),
                ]);

                $request_data =  [
                    'section' => 'motor',
                    'is_offline_entry' => 0,
                    'trace_id' => $request->trace_id,
                    'user_id' => $log->user_id,
                    'policy_number' => $log->policy_number,
                    'action_type' => $log->action_type,
                    'screenshot' => $log->screenshot_url,
                    'old_data' => json_encode(json_decode($log->old_data, true)),
                    'new_data' => json_encode(json_decode($log->new_data, true)),
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $log->updated_at->format('Y-m-d H:i:s'),
                ];


                DashboardPolicyUpdateLogs::create($request_data);
                
                \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
                    'enquiryId' => $enquiryId,
                ]), $enquiryId, false);

                return response()->json([
                    'status' => true,
                    'msg' => 'online policy details updated Successfully'
                ], 200);
            } elseif (!$is_online_policy && $request->is_offline_entry) {  // offline policy only



                $validator = Validator::make(
                    $request->all(),
                    [
                        'user_id' => ['required', 'not_in:"null"'],
                        'screenshot_content' => ['required', 'string'],
                        'ss_type' => ['required', 'not_in:"null"'],

                        'data' => 'required',
                        'data.proposer_name' => "nullable",
                        'data.proposer_mobile' => "nullable",
                        'data.proposer_emailid' => "nullable",
                        'data.policy_type' => "nullable",
                        'data.vehicle_registration_date' => "nullable",
                        'data.previous_policy_expiry_date' => "nullable",
                        'data.previous_policy_start_date' => "nullable",
                        'data.previous_ncb' => "nullable",
                        'data.ncb_percentage' => "nullable",
                        'data.vehicle_manufacture_year' => "nullable",
                        'data.ncb_claim' => "nullable",
                        'data.gender_name' => "nullable",
                        'data.proposer_gender' => "nullable",
                        'data.primary_insured_gender' => "nullable",
                        'data.primary_insured_dob' => "nullable",
                        'data.primary_insured_name' => "nullable",
                        'data.primary_insured_mobile' => "nullable",
                        'data.primary_insured_emailid' => "nullable",
                        'data.proposer_dob' => "nullable",
                        'data.policy_start_date' => "nullable",
                        'data.policy_end_date' => "nullable",
                        'data.pincode' => "nullable",
                        'data.address_line_1' => "nullable",
                        'data.address_line_2' => "nullable",
                        'data.address_line_3' => "nullable",
                        'data.state' => "nullable",
                        'data.city' => "nullable",
                        'data.engine_number' => "nullable",
                        'data.chassis_number' => "nullable",
                        'data.previous_insurer' => "nullable",
                        'data.previous_policy_number' => "nullable",
                        'data.first_name' => "nullable",
                        'data.last_name' => "nullable",
                        'data.cpa_policy_start_date' => "nullable",
                        'data.cpa_policy_end_date' => "nullable",
                        'data.nominee_dob' => "nullable",
                        'data.nominee_relationship' => "nullable",
                        'data.nominee_age' => "nullable",
                        'data.nominee_name' => "nullable",
                        'data.tp_start_date' => "nullable",
                        'data.tp_end_date' => "nullable",
                        'data.is_financed' => "nullable",
                        'data.hypothecation_to' => "nullable",
                        'data.zero_dep' => "nullable",
                        'data.transaction_stage' => "nullable",
                        'data.addhar_no' => "nullable",
                        'data.pan_no' => "nullable",

                        #additional fields
                        'data.ic_name_id' => "nullable",
                        'data.policy_type' => "nullable",
                        'data.policy_category_id' => "nullable"

                    ]
                );

                if ($validator->fails()) {
                    return response()->json([
                        'status' => false,
                        'msg' => $validator->errors(),
                    ], 400);
                }


                $oldData = CvAgentMapping::where('user_product_journey_id', $enquiryId)->select('seller_type', 'user_name', 'agent_id', 'agent_name', 'agent_mobile', 'agent_email', 'agent_business_type', 'agent_business_code')->first();


                $oldDataProposal = UserProposal::where('user_product_journey_id', $enquiryId)->select( 'first_name',
                'last_name',
                'mobile_number',
                'email',
                'vehicle_registration_no',
                'prev_policy_start_date',
                'prev_policy_expiry_date',
                "previous_ncb",
                'ncb_discount',
                'vehicle_manf_year',
                'is_claim',
                'proposal_no',
                'gender_name',
                'gender',
                'dob',
                'nominee_name',
                'nominee_age',
                'nominee_relationship',
                'nominee_dob',
                'policy_end_date',
                'policy_start_date',
                'pincode',
                'address_line1',
                'address_line2',
                'address_line3',
                'state',
                'city',
                'engine_number',
                'chassis_number',
                'previous_insurance_company',
                'previous_policy_number',
                'cpa_start_date',
                'cpa_end_date',
                'tp_start_date',
                'tp_end_date',
                'owner_type',
                'is_vehicle_finance',
                'ic_name',
                'vehicle_manf_year')->first();

                $corp_data = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->select('policy_type')->first();

                if (empty($oldData)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'seller details not found'
                    ], 400);
                }

                if (empty($oldDataProposal)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'proposal details not found'
                    ], 400);
                }

                if (empty($corp_data)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'corporate details not found'
                    ], 400);
                }

                $oldDataArray = $oldData ? $oldData->toArray() : [];
                $oldDataArray_1 = $oldDataProposal ? $oldDataProposal->toArray() : [];
                $oldDataArray_2 = $corp_data ? $corp_data->toArray() : [];
                $oldDataArrayFinal = array_merge($oldDataArray, $oldDataArray_1 , $oldDataArray_2 );

                $file = $request->screenshot_content;
                $decoded_data = base64_decode($file);
                $file_extension = $request->ss_type;
                $file_name = $request->policy_number . '_' . uniqid() . '_update_policy_details.' . $file_extension;
                $directoryPath = "policy_details_update/trace_id/{$enquiryId}/{$file_name}";
                if ($decoded_data === false) {
                    return response()->json([
                        'status' => false,
                        'code' => 400,
                        'message' => 'Invalid screenshot data.',
                    ], 400);
                }
                Storage::put($directoryPath , $decoded_data);

                $seller_data = [
                    'seller_type' => $request->seller_type,
                    'agent_id' => $request->seller_id,
                    'user_name' => $request->seller_username,
                    'agent_name' => $request->seller_name,
                    'agent_mobile' => $request->seller_mobile,
                    'agent_email' => $request->seller_email,
                    'agent_business_type' => $request->seller_business_type,
                    'agent_business_code' => $request->seller_business_code,
                ];

                $seller_data_update = array_filter($seller_data, fn($value) => !is_null($value));

                if (!empty($seller_data_update)) {

                    CvAgentMapping::where('user_product_journey_id', $enquiryId)
                    ->update($seller_data_update);
                }     

                $name = $request->input('data.proposer_name');

                if (!empty($name)) {
                    $name_part =  explode(" ", $name);
                    if (count($name_part) > 2) {
                        $first_name = $name_part[0] . " " . $name_part[1];
                        $last_name = $name_part[2];
                    } else {
                        $first_name = $name_part[0];
                        $last_name = $name_part[1];
                    }
                }

                $ic_id = $request->input('data.ic_name_id');
                if (!empty($ic_id)) {
                    $company_name = MasterCompany::where('company_id', $ic_id)->select('company_name')->first();
                }

                $data = [
                    'first_name' =>  $request->input('data.first_name') ?? ($name == null ? null : $first_name),
                    'last_name' =>  $request->input('data.last_name') ?? ($name == null ? null : $last_name),
                    'email' => $request->input('data.proposer_emailid') ?? $request->input('data.primary_insured_emailid'),
                    'mobile_number' => $request->input('data.proposer_mobile') ?? $request->input('data.primary_insured_mobile'),
                    'dob' => $request->input('data.proposer_dob') ?? $request->input('data.primary_insured_dob'),
                    'gender' => $request->input('data.proposer_gender'),
                    'gender_name' => $request->input('data.gender_name'),

                    'pincode' => $request->input('data.pincode'),
                    'address_line1' => $request->input('data.address_line_1'),
                    'address_line2' => $request->input('data.address_line_2'),
                    'state' => $request->input('data.state'),
                    'city' => $request->input('data.city'),

                    'engine_number' => $request->input('data.engine_number'),
                    'chassis_number' => $request->input('data.chassis_number'),

                    'previous_insurance_company' => $request->input('data.previous_insurer'),
                    'previous_policy_number' => $request->input('data.previous_policy_number'),

                    'policy_start_date' => $request->input('data.policy_start_date'),
                    'policy_end_date' => $request->input('data.policy_end_date'),
                    
                    'tp_start_date' => $request->input('data.tp_start_date'),
                    'tp_end_date' => $request->input('data.tp_end_date'),

                    'is_vehicle_finance' => $request->input('data.is_financed') === 'TRUE' ? '1' : '0',
                    // 'policy_type' => $request->input('data.policy_type'),
                    'previous_ncb' => $request->input('data.previous_ncb'),
                    'applicable_ncb' => $request->input('data.ncb_percentage'),
                    'is_claim' => $request->input('data.ncb_claim'),
                    'vehicle_manf_year' => $request->input('data.vehicle_manufacture_year'),

                    'cpa_start_date' => $request->input('data.cpa_policy_start_date'),
                    'cpa_end_date' => $request->input('data.cpa_policy_end_date'),

                    'nominee_name' => $request->input('data.nominee_name'),
                    'nominee_age' => $request->input('data.nominee_age'),
                    'nominee_relationship' => $request->input('data.nominee_relationship'),
                    'nominee_dob' => $request->input('data.nominee_dob'),

                    'ic_name' => $company_name ?? null,
                ];

                $data = array_filter($data, fn($value) => !is_null($value));

                $policy_id = $request->input('data.policy_type');

                if (!empty($policy_id)) {

                    $policy_type = MasterPremiumType::where('id', $policy_id)->pluck('premium_type_code')->first();

                    if (!empty($policy_type)) {

                        $corporate_data = [
                            'policy_type' => $policy_type
                        ];

                        CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)
                            ->update($corporate_data);
                    }
                    
                }

                if(!empty($data)){
                    UserProposal::where('user_product_journey_id', $enquiryId)
                    ->update($data);
                }

                $new_data = array_merge($seller_data_update, $data , $corporate_data ?? []);

                $log = PolicyChangeLog::create([
                    'trace_id' =>  $enquiryId,
                    'user_id' => $request->user_id,
                    'policy_number' => $request->policy_number,
                    'source' => 'offline',
                    'screenshot_url' => $directoryPath,
                    'action_type' => 'update_seller_details',
                    'old_data' => $oldDataArrayFinal,
                    'new_data' => $new_data
                ]);

                $request_data =  [
                    'section' => 'motor',
                    'is_offline_entry' => 1,
                    'trace_id' => $request->trace_id,
                    'user_id' => $log->user_id,
                    'policy_number' => $log->policy_number,
                    'action_type' => $log->action_type,
                    'screenshot' => $log->screenshot_url,
                    'old_data' => $log->old_data,
                    'new_data' => $log->new_data,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $log->updated_at->format('Y-m-d H:i:s'),
                ];

                DashboardPolicyUpdateLogs::create($request_data);

                \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
                    'enquiryId' => $enquiryId,
                ]), $enquiryId, false);

                return response()->json([
                    'status' => true,
                    'msg' => 'offline policy details updated Successfully'
                ], 200);
            }

            return response()->json([
                'status' => false,
                'msg' => 'something went wrong'
            ], 400);
        }
    }

    public  function PolicyStageUpdate(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'user_id' => ['required', 'not_in:"null"'],
                'section' => ['required', 'not_in:"null"'],
                'trace_id' => ['required', 'not_in:"null"'],
                'screenshot_content' => ['required', 'string'],
                'is_offline_entry' => ['required', 'boolean'],
                'policy_number' => ['required', 'not_in:"null"'],
                'ss_type' => ['required', 'not_in:"null"']
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ], 400);
        }

        if ($request->section == 'motor') {

            $enquiryId = is_numeric($request->trace_id) && strlen($request->trace_id) == 16
                ? Str::substr($request->trace_id, 8)
                : customDecrypt($request->trace_id);


            $is_online_policy = UserProductJourney::where('user_product_journey_id', $enquiryId)
                ->where(function ($query) {
                    $query->where('lead_source', '!=', 'RENEWAL_DATA_UPLOAD')
                        ->orWhereNull('lead_source');
                })
                ->exists();

            // if (($request->is_offline_entry && !$is_online_policy) || ($request->is_offline_entry && $is_online_policy)) {
            //     return response()->json([
            //         'status' => false,
            //         'msg' => 'policy source mismatch (offline / online)'
            //     ], 400);
            // }

            if (($request->is_offline_entry == 0 && !$is_online_policy) || ($request->is_offline_entry == 1 && $is_online_policy)) {
                return response()->json([
                    'status' => false,
                    'msg' => 'policy source mismatch (offline / online)'
                ]);
            }

            if ($is_online_policy && !$request->is_offline_entry) {

                $oldData = JourneyStage::where('user_product_journey_id', $enquiryId)->select('user_product_journey_id', 'stage')->first();

                if (empty($oldData)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'No journey data found.'
                    ], 400);
                }

                $oldDataArray = $oldData ? $oldData->toArray() : [];

                $file = $request->screenshot_content;
                $decoded_data = base64_decode($file);
                $file_extension = $request->ss_type;
                $file_name = $request->policy_number . '_' . uniqid() . '_update_stage_details.' . $file_extension;
                $directoryPath = "policy_stage_update/trace_id/{$enquiryId}/{$file_name}";
                if ($decoded_data === false) {
                    return response()->json([
                        'status' => false,
                        'code' => 400,
                        'message' => 'Invalid screenshot data.',
                    ], 400);
                }
                Storage::put($directoryPath , $decoded_data);

                // CvJourneyStages::where('user_product_journey_id', $enquiryId)
                //     ->update([
                //         'stage' => STAGE_NAMES['POLICY_CANCELLED'],
                //     ]);
                
                updateJourneyStage([
                    'user_product_journey_id' => $enquiryId,
                    'stage' => STAGE_NAMES['POLICY_CANCELLED']
                ]);

                $log =  PolicyUpdateLog::create([
                    'trace_id' => $enquiryId,
                    'user_id' => $request->user_id,
                    'policy_number' => $request->policy_number,
                    'action_type' => "update_policy_stage",
                    'source' => 'online',
                    'screenshot_url' => $directoryPath,
                    'old_data' => $oldDataArray,
                    'new_data' => [
                        'user_product_journey_id' => (int)$enquiryId,
                        'stage' => STAGE_NAMES['POLICY_CANCELLED'],
                    ]
                ]);

                $temp = [
                    'section' => 'motor',
                    'is_offline_entry' => 0,
                    'trace_id' => $request->trace_id,
                    'user_id' => $log->user_id,
                    'policy_number' => $log->policy_number,
                    'action_type' => $log->action_type,
                    'screenshot' => $log->screenshot_url,
                    'old_data' => $log->old_data,
                    'new_data' =>$log->new_data,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $log->updated_at->format('Y-m-d H:i:s'),
                ];

                DashboardStageUpdateLogs::create($temp);

                // \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
                //     'enquiryId' => $enquiryId,
                // ]), $enquiryId, false);

                return response()->json([
                    'status' => true,
                    'msg' => 'online policy stage updated Successfully'
                ], 200);
            } elseif (!$is_online_policy && $request->is_offline_entry) {

                $oldData = JourneyStage::where('user_product_journey_id', $enquiryId)->select('user_product_journey_id', 'stage')->first();

                if (empty($oldData)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'No journey data found.'
                    ], 400);
                }

                $oldDataArray = $oldData ? $oldData->toArray() : [];

                $file = $request->screenshot_content;
                $decoded_data = base64_decode($file);
                $file_extension = $request->ss_type;
                $file_name = $request->policy_number . '_' . uniqid() . '_update_stage_details.' . $file_extension;
                $directoryPath = "policy_stage_update/trace_id/{$enquiryId}/{$file_name}";
                if ($decoded_data === false) {
                    return response()->json([
                        'status' => false,
                        'code' => 400,
                        'message' => 'Invalid screenshot data.',
                    ], 400);
                }
                Storage::put($directoryPath, $decoded_data);

                // CvJourneyStages::where('user_product_journey_id', $enquiryId)
                //     ->update([
                //         'stage' => STAGE_NAMES['POLICY_CANCELLED'],
                //     ]);

                updateJourneyStage([
                    'user_product_journey_id' => $enquiryId,
                    'stage' => STAGE_NAMES['POLICY_CANCELLED']
                ]);

                $log =  PolicyUpdateLog::create([
                    'trace_id' =>  $enquiryId,
                    'user_id' => $request->user_id,
                    'policy_number' => $request->policy_number,
                    'action_type' => "update_policy_stage",
                    'source' => 'offline',
                    'screenshot_url' => $directoryPath,
                    'old_data' => $oldDataArray,
                    'new_data' => [
                        'user_product_journey_id' => $enquiryId,
                        'stage' => STAGE_NAMES['POLICY_CANCELLED'],
                    ]
                ]);

                $temp =  [
                    'section' => 'motor',
                    'is_offline_entry' =>  1,
                    'trace_id' => $request->trace_id,
                    'user_id' => $log->user_id,
                    'policy_number' => $log->policy_number,
                    'action_type' => $log->action_type,
                    'screenshot' => $log->screenshot_url,
                    'old_data' => $log->old_data,
                    'new_data' => $log->new_data,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $log->updated_at->format('Y-m-d H:i:s'),
                ];


                DashboardStageUpdateLogs::create($temp);

                // \App\Http\Controllers\KafkaController::ManualDataPush(new Request([
                //     'enquiryId' => $enquiryId,
                // ]), $enquiryId, false);

                return response()->json([
                    'status' => true,
                    'msg' => 'offline policy stage updated Successfully'
                ], 200);
            }

            return response()->json([
                'status' => false,
                'msg' => 'something went wrong'
            ], 400);
        }
    }
}
