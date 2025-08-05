<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use App\Models\GramcoverPostDataApi;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class GramcoverDataPushApiJob implements ShouldQueue
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
    public function handle()
    {

        if (config('constants.motorConstant.GRAMCOVER_DATA_PUSH_ENABLED') != 'Y') {
            return false;
        }
        // Gramcover Data Push Success Recorrds
        // $success_user_product_journey_ids = GramcoverPostDataApi::where('status', 'success')->orWhere('status', 'processing')->get('user_product_journey_id')->pluck(['user_product_journey_id'])->toArray();
        // Gramcover Data Push Success Recorrds

        // User Product Journeys Data From Journey Tables
        $reports = \App\Models\UserProductJourney::with([
            'quote_log',
            'corporate_vehicles_quote_request',
            'user_proposal',
            'user_proposal.policy_details',
            'user_proposal.breakin_status',
            'agent_details',
            'journey_stage',
            'sub_product',
            'addons'
        ])->whereNotIn('user_product_journey_id', function ($query) {
            $query->select('user_product_journey_id')->where('status', 'success')->orWhere('status', 'processing')->from('gramcover_post_data_apis');
        })->whereHas('agent_details', function ($query) {
            $query->whereNotNull('token');
        })->whereHas('journey_stage', function ($query) {
            $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
        })->get();
        foreach ($reports as $report) {
            
            error_log(now() . ': ' . $report->user_product_journey_id . "\n", 3, base_path(). '/storage/logs/GramcoverDataPush' . date('Y-m-d') . '.log');
            $user_product_journey_ids = \App\Models\GramcoverPostDataApi::where(function ($query) {
                return $query->where('status', 'success')->orWhere('status', 'processing');
            })->where(['user_product_journey_id' => $report->user_product_journey_id])->first(['user_product_journey_id']);
            
            if ($user_product_journey_ids) {
                error_log(now() . ': ' . $report->user_product_journey_id . "\n", 3, base_path() . '/storage/logs/GramcoverDataPushContinue' . date('Y-m-d') . '.log');
                continue;
            }

            $gramcover_post_data_api = \App\Models\GramcoverPostDataApi::create([
                'user_product_journey_id' => $report->user_product_journey_id,
                'status' => 'processing',
            ]);
            
            $temp = null;
            $temp = [
                "payment_request_response_id" => "",
                "lead_stage_id" => "",
                "payment_status" => "",
                "trace_id" => "",
                "proposal_id" => "",
                "proposal_no" => "",
                "payment_mode" => "",
                "vehicle_registration_number" => "",
                "company_alias" => "",
                "company_name" => "",
                'business_type' => "",
                "product_name" => $report['quote_log']['premium_json']['productName'] ?? "",
                "proposer_gender" => "",
                "proposer_name" => "",
                "proposer_mobile" => "",
                "proposer_emailid" => "",
                "proposal_date" => "",
                "primary_insured_gender" => "",
                "primary_insured_dob" => "",
                "primary_insured_name" => "",
                "primary_insured_mobile" => "",
                "primary_insured_emailid" => "",
                "proposer_dob" => "",
                "cover_amount" => "",
                "od_premium" => "",
                "tp_premium" => "",
                "premium_amount" => "",
                "base_premium" => "",
                "tax_amount" => "",
                "discount_amount" => "",
                "cpa_amount" => "",
                "cpa_policy_start_date" => "",
                "cpa_policy_end_date" => "",
                "addon_premium" => "",
                "previous_ncb" => "",
                "ncb_percentage" => "",
                "selected_addons" => "",
                "policy_start_date" => "",
                "policy_end_date" => "",
                "pincode" => "",
                "address" => "",
                "state" => "",
                "city" => "",
                "proposal_url" => "",
                "quote_url" => "",
                "lastupdated_time" => "",
                "transaction_stage" => "",
                "engine_number" => "",
                "chassis_number" => "",
                "policy_term" => "",
                "policy_type" => "",
                "previous_policy_number" => "",
                "previous_insurer" => "",
                "first_name" => "",
                "last_name" => "",
                "owner_type" => "",
                "is_financed" => "",
                "hypothecation_to" => "",
                "pg_response" => "",
                "sales_date" => "",
                "policy_status" => "",
                "transaction_date" => "",
                "policy_no" => "",
                "policy_doc_path" => "",
                "vehicle_make" => "",
                "vehicle_model" => "",
                "vehicle_version" => "",
                "vehicle_cubic_capacity" => "",
                "vehicle_fuel_type" => "",
                "vehicle_body_type" => "",
                "quote_id" => "",
                "section" => "",
                "sum_assured" => "",
                "insured_member_count" => "",
                "seller_name" => "",
                "seller_mobile" => "",
                "seller_email" => "",
                "seller_id" => "",
                "seller_type" => "",
                "pos_name" => "",
                "pos_mobile" => "",
                "pos_email" => "",
                "vehicle_registration_date" => "",
                "previous_policy_expiry_date" => "",
                "policy_period" => "",
                "zero_dep" => "",
                "od_discount" => "",
                "product_type" => "",
                "sub_product_type" => "",
                "nominee_dob" => "",
                "nominee_relationship" => "",
                "nominee_age" => "",
                "nominee_name" => "",
                "token" => "",
            ];

            $temp['lead_stage_id'] = $report->lead_stage_id ?? "";
            $temp['trace_id'] = $report->journey_id ?? "";
            if ($report->journey_stage) {
                $temp["transaction_stage"] = $report->journey_stage->stage ?? "";
                $temp["transaction_stage"] = $report->journey_stage->stage ?? "";
                $temp["proposal_url"] = $report->journey_stage->proposal_url ?? "";
                $temp["quote_url"] = $report->journey_stage->quote_url ?? "";
                $temp["lastupdated_time"] = $report->journey_stage->updated_at ? date('Y-m-d H:i:s', strtotime($report->journey_stage->updated_at)) : "";
            }
            if ($report->agent_details) {
                foreach ($report->agent_details as $key => $agent_detail) {
                    if (in_array($agent_detail->seller_type, ['E', 'P']) && $agent_detail->source == NULL) {
                        $temp["seller_name"] = $agent_detail->agent_name;
                        $temp["seller_mobile"] = $agent_detail->agent_mobile;
                        $temp["seller_email"] = $agent_detail->agent_email;
                        $temp["seller_id"] = $agent_detail->agent_id;
                        $temp["seller_type"] = $agent_detail->seller_type;
                        $temp["addhar_no"] = $agent_detail->aadhar_no;
                        $temp["pan_no"] = $agent_detail->pan_no;
                        $temp["token"] = $agent_detail->token;
                    }
                }
            }
            if (!empty($report->user_proposal) && (!empty($report->user_proposal->first_name) || !empty($report->user_proposal->last_name))) {
                $temp['proposer_name'] = $report->user_proposal->first_name . " " . $report->user_proposal->last_name;
            } else {
                $temp['proposer_name'] = $report->user_fname . " " . $report->user_lname;
            }
            $temp['proposer_mobile'] = !empty($report->user_mobile) ? $report->user_mobile : ($report->user_proposal->mobile_number ?? "");
            $temp['proposer_emailid'] = !empty($report->user_email) ? $report->user_email : ($report->user_proposal->email ?? "");
            if (!empty($report->corporate_vehicles_quote_request)) {
                $temp['vehicle_registration_number'] = !empty($report->corporate_vehicles_quote_request->vehicle_registration_no) ? $report->corporate_vehicles_quote_request->vehicle_registration_no : ($report->user_proposal->vehicale_registration_number ?? "");
                $temp['vehicle_manufacture_year'] = !empty($report->user_proposal->vehicle_manf_year) ? $report->user_proposal->vehicle_manf_year : $quote_details['quote_details']['manufacture_year'] ?? "";
            }
            if (!empty($report->quote_log)) {
                $temp['cover_amount'] = $report->quote_log->idv ?? "";
            }
            if (!empty($report->quote_log->quote_data)) {
                $quote_details = $report->quote_log->toArray();
                $temp['vehicle_make'] = $quote_details['quote_details']['manfacture_name'] ?? "";
                $temp['vehicle_model'] = $quote_details['quote_details']['model_name'] ?? "";
                $temp['vehicle_version'] = $quote_details['quote_details']['version_name'] ?? "";
                $temp['vehicle_cubic_capacity'] = $report->quote_log->premium_json['mmvDetail']['cubicCapacity'] ?? "";
                $temp['policy_type'] = $report->quote_log->premium_json['policyType'] ?? "";
                $temp['vehicle_registration_date'] = !empty($quote_details['quote_details']['vehicle_register_date']) ? $quote_details['quote_details']['vehicle_register_date'] : ($report->user_proposal->vehicale_registration_number ?? "");
                $temp['vehicle_fuel_type'] = $quote_details['premium_json']['fuelType'] ?? "";
                $temp['previous_policy_expiry_date'] = (isset($quote_details['quote_details']['previous_policy_expiry_date'])) ? $quote_details['quote_details']['previous_policy_expiry_date'] : "";

                if (!empty($quote_details['quote_details']['manufacture_year'])) {
                    $temp['vehicle_manufacture_year'] = !empty($report->user_proposal->vehicle_manf_year) ? $report->user_proposal->vehicle_manf_year : $quote_details['quote_details']['manufacture_year'] ?? "";
                }
                if ($temp['previous_policy_expiry_date'] == "New") {
                    $temp['previous_policy_expiry_date'] = "";
                }

                if (($report->quote_log->premium_json['mmvDetail']['cubicCapacity'] ?? 0) <= 1000) {
                    $vehicle_body_type = 'Hatchback';
                } else if (($report->quote_log->premium_json['mmvDetail']['cubicCapacity'] ?? 0) <= 1500 && ($report->quote_log->premium_json['mmvDetail']['seatingCapacity']) <= 5) {
                    $vehicle_body_type = 'Sedan';
                } else {
                    $vehicle_body_type = 'SUV';
                }
                $temp['vehicle_body_type'] = $vehicle_body_type;
                if (!empty($report->quote_log->premium_json)) {
                    $temp['company_alias'] = $quote_details['premium_json']['company_alias'] ?? "";
                    $temp['company_name'] = $quote_details['premium_json']['companyName'] ?? "";
                }
            }
            if (!empty($report->user_proposal)) {
                $temp['proposal_id'] = $report->user_proposal->user_proposal_id ?? "";
                $temp['proposal_no'] = $report->user_proposal->proposal_no ?? "";
                $temp['gender_name'] = $report->user_proposal->gender_name ?? "";
                $temp['proposer_gender'] = $report->user_proposal->gender_name ?? "";
                $temp['primary_insured_gender'] = $report->user_proposal->gender_name ?? "";
                $temp['primary_insured_dob'] = $report->user_proposal->dob ?? "";
                $temp['primary_insured_name'] = $report->user_proposal->first_name . " " . $report->user_proposal->last_name;
                $temp['primary_insured_mobile'] = $report->user_proposal->mobile_number ?? "";
                $temp['primary_insured_emailid'] = $report->user_proposal->email ?? "";
                $temp['proposer_dob'] = $report->user_proposal->dob ?? "";
                $temp['od_premium'] = $report->user_proposal->od_premium ?? "";
                $temp['tp_premium'] = $report->user_proposal->tp_premium ?? "";
                $temp['premium_amount'] = $report->user_proposal->final_payable_amount ?? "";
                $temp['base_premium'] = $report->user_proposal->total_premium ?? "";
                $temp['tax_amount'] = $report->user_proposal->service_tax_amount ?? "";
                $temp['discount_amount'] = $report->user_proposal->total_discount ?? "";
                $temp['cpa_amount'] = $report->user_proposal->cpa_premium ?? "";
                $temp['addon_premium'] = $report->user_proposal->addon_premium ?? "";
                $temp['proposal_date'] = $report->user_proposal->proposal_date ?? "";
                $temp['policy_start_date'] = $report->user_proposal->policy_start_date ?? "";
                $temp['policy_end_date'] = $report->user_proposal->policy_end_date ?? "";
                $temp['pincode'] = $report->user_proposal->pincode ?? "";
                $temp['address_line_1'] = $report->user_proposal->address_line1 ?? "";
                $temp['address_line_2'] = $report->user_proposal->address_line2 ?? "";
                $temp['address_line_3'] = $report->user_proposal->address_line3 ?? "";
                $temp['state'] = $report->user_proposal->state ?? "";
                $temp['city'] = $report->user_proposal->city ?? "";
                $temp['engine_number'] = $report->user_proposal->engine_number ?? "";
                $temp['chassis_number'] = $report->user_proposal->chassis_number ?? "";
                $temp['policy_term'] = "1" ?? "";
                $temp['previous_insurer'] = $report->user_proposal->insurance_company_name ?? "";
                $temp['previous_policy_number'] = $report->user_proposal->previous_policy_number ?? "";
                $temp['previous_insurance_company'] = $report->user_proposal->previous_insurance_company ?? "";
                $temp['prev_policy_expiry_date'] = $report->user_proposal->prev_policy_expiry_date ?? "";
                $temp['first_name'] = $report->user_proposal->first_name ?? "";
                $temp['last_name'] = $report->user_proposal->last_name ?? "";
                $temp['cpa_policy_start_date'] = $report->user_proposal->cpa_policy_fm_dt ?? "";
                $temp['cpa_policy_end_date'] = $report->user_proposal->cpa_policy_to_dt ?? "";
                $temp['nominee_dob'] = $report->user_proposal->nominee_dob ?? "";
                $temp['nominee_relationship'] = $report->user_proposal->nominee_relationship ?? "";
                $temp['nominee_age'] = $report->user_proposal->nominee_age ?? "";
                $temp['nominee_name'] = $report->user_proposal->nominee_name ?? "";
                //    $temp['tp_start_date'] = $report->user_proposal->tp_start_date ?? "";
                //    $temp['tp_end_date'] = $report->user_proposal->tp_end_date ?? "";
                //    $temp['tp_policy_number'] = $report->user_proposal->tp_insurance_number ?? "";
                //    $temp['tp_prev_company'] = $report->user_proposal->tp_insurance_company ?? "";
                $temp['breakin_number'] = $report->user_proposal->breakin_status->breakin_number ?? "";
                $temp['breakin_status'] = $report->user_proposal->breakin_status->breakin_status ?? "";

                if ($report->user_proposal->owner_type == "I") {
                    $temp['owner_type'] = "Individual" ?? "";
                } elseif ($report->user_proposal->owner_type == "C") {
                    $temp['owner_type'] = "Company" ?? "";
                }
                $temp['is_financed'] = $report->user_proposal->is_vehicle_finance ? true : false ?? "";
                $temp['hypothecation_to'] = $report->user_proposal->name_of_financer ?? "";
                $temp['sales_date'] = date('d-m-Y', strtotime($report->user_proposal->created_date)) ?? "";
                $temp['transaction_date'] = date('d-m-Y', strtotime($report->user_proposal->created_date)) ?? "";
                $temp['policy_no'] = !empty($report->user_proposal->policy_details) ? $report->user_proposal->policy_details->policy_number : "";
                $temp['policy_doc_path'] = !empty($report->user_proposal->policy_details) ? $report->user_proposal->policy_details->pdf_url : "";
                $temp['quote_id'] = $report->user_proposal->user_product_journey_id ?? "";
                $temp['section'] = $report['sub_product']['product_sub_type_code'] ?? ""; // product_type , section
                $temp['sum_assured'] = $report->user_proposal->idv ?? "";
                if ($report->user_proposal->policy_start_date != "" && $report->user_proposal->policy_end_date != "") {
                    $policy_peroid = \Carbon\Carbon::parse($report->user_proposal->policy_start_date)->diffInMonths(\Carbon\Carbon::parse($report->user_proposal->policy_end_date)->addDay());
                    if ($policy_peroid >= 5 && $policy_peroid <= 7) {
                        $temp['policy_period'] = '6 Months';
                    } elseif ($policy_peroid >= 2 && $policy_peroid <= 4) {
                        $temp['policy_period'] = '3 Months';
                    } elseif ($policy_peroid >= 11 && $policy_peroid <= 13) {
                        $temp['policy_period'] = '1 Year';
                    } else {
                        $temp['policy_period'] = $policy_peroid . ' Months';
                    }
                }
            }
            if ($report->quote_log) {
                $temp['prev_policy_type'] = $report->corporate_vehicles_quote_request->previous_policy_type ?? "";
                if ($temp['prev_policy_type'] == 'NEW') {
                    $temp['prev_policy_type'] = "";
                    $temp['tp_start_date'] = !empty($temp['tp_start_date']) ? \Carbon\Carbon::parse($temp['tp_start_date'])->format('d-m-Y') : \Carbon\Carbon::parse($temp['policy_start_date'])->format('d-m-Y');
                }
                $temp['business_type'] = !empty($report->user_proposal->business_type) ? $report->user_proposal->business_type : $report->corporate_vehicles_quote_request->business_type ?? ""; // from corporate table
                if ($temp['business_type'] == 'breakin') {
                    $temp['business_type'] = 'rollover';
                } else if ($temp['business_type'] == 'newbusiness') {
                    $temp['business_type'] = 'new';
                }
                // from corporate table
                $temp['zero_dep'] = in_array('zeroDepreciation', $report->quote_log->premium_json['applicableAddons'] ?? []) ? 'Yes' : 'No';
            }
            if (isset($report->quote_log->premium_json['company_alias']) && $report->quote_log->premium_json['company_alias'] == 'acko') {
                $temp['od_discount'] = '80';
            } elseif (isset($report->quote_log->premium_json['company_alias']) && $report->quote_log->premium_json['company_alias'] == 'icici_lombard') {
                $temp['od_discount'] = '80';
            } elseif (isset($report->quote_log->premium_json['company_alias']) && $report->quote_log->premium_json['company_alias'] == 'godigit') {
                $temp['od_discount'] = '75';
            } elseif (isset($report->quote_log->premium_json['company_alias']) && $report->quote_log->premium_json['company_alias'] == 'shriram') {
                $temp['od_discount'] = 'NA';
            }
            $temp['product_type'] = get_parent_code($report->corporate_vehicles_quote_request->product_id ?? null) ?? "";  //corporate_table product_id
            $temp['sub_product_type'] = $report['sub_product']['product_sub_type_code'] ?? "";
            if ($temp['product_type'] == 'CAR') {
                $temp['product_name'] = '4W';
                if (Carbon::parse($temp['vehicle_registration_date'])->addYear(3) >= now()) {
                    $temp['tp_start_date'] = Carbon::parse($temp['vehicle_registration_date'])->format('d-m-Y');
                    $temp['tp_end_date'] = Carbon::parse($temp['vehicle_registration_date'])->addYears(3)->subDay(1)->format('d-m-Y');
                }
                // $temp['tp_end_date'] = \Carbon\Carbon::parse($temp['policy_start_date'])->addYears(3)->subDay()->format('d-m-Y');
            } elseif ($temp['product_type'] == 'BIKE') {
                $temp['product_name'] = '2W';
                if (Carbon::parse($temp['vehicle_registration_date'])->addYear(5) >= now()) {
                    $temp['tp_start_date'] = Carbon::parse($temp['vehicle_registration_date'])->format('d-m-Y');
                    $temp['tp_end_date'] = Carbon::parse($temp['vehicle_registration_date'])->addYears(5)->subDay(1)->format('d-m-Y');
                }
                if ($temp['business_type'] == 'new') {
                    $temp['tp_end_date'] = \Carbon\Carbon::parse($temp['policy_start_date'])->addYears(5)->subDay(1)->format('d-m-Y');
                }
            } else {
                $temp['product_name'] = $temp['product_type'];
            }

            if (($temp['policy_type'] == 'Third Party' || $temp['policy_type'] == 'Third Party Breakin') && ($temp['business_type'] == 'rollover' || $temp['business_type'] == 'breakin' || $temp['business_type'] == 'new')) {
                $temp['ncb_percentage'] = "0";
                $temp['od_start_date'] = "";
                $temp['od_end_date'] = "";
                $temp['tp_start_date'] = $temp['policy_start_date'];
                $temp['tp_end_date'] = $temp['tp_end_date'] ?? $temp['policy_end_date'];
                $temp['ncb_percentage'] = '0';
                $temp['previous_ncb'] = '0'; // previous_ncb
            } else if ($temp['policy_type'] == 'Comprehensive' && ($temp['business_type'] == 'rollover' || $temp['business_type'] == 'breakin' || $temp['business_type'] == 'new')) {
                $temp['tp_start_date'] = $temp['policy_start_date'];
                $temp['tp_end_date'] = $temp['tp_end_date'] ?? $temp['policy_end_date'];
                $temp['od_start_date'] = $temp['policy_start_date'];
                $temp['od_end_date'] = $temp['policy_end_date'];
                $temp['ncb_percentage'] = !empty($report->user_proposal->applicable_ncb) ? $report->user_proposal->applicable_ncb : $quote_details['quote_details']['applicable_ncb'] ?? '';
                $temp['previous_ncb'] = ($temp['business_type'] != "new" ? ($quote_details['quote_details']['previous_ncb'] ?? '') : ""); // previous_ncb
            } else if ($temp['policy_type'] == 'Own Damage' && ($temp['business_type'] == 'rollover' || $temp['business_type'] == 'breakin' || $temp['business_type'] == 'Roll Over')) {
                $temp['tp_start_date'] = $report->user_proposal->tp_start_date ?? "";
                $temp['tp_end_date'] = $report->user_proposal->tp_end_date ?? "";
                $temp['previous_tp_start_date'] = $temp["tp_start_date"];
                $temp['previous_tp_end_date'] = $temp['tp_end_date'];
                $temp['od_start_date'] = $temp['policy_start_date'];
                $temp['od_end_date'] = $temp['policy_end_date'];
                $temp['previos_od_start_date'] = date('d-m-Y', strtotime('-1 year +1 day', strtotime($temp['prev_policy_expiry_date'])));;
                $temp['previos_od_end_date'] = $temp['prev_policy_expiry_date'];
                $temp['previous_od_policy_number'] = $temp['previous_policy_number'];
                $temp['previous_od_company'] = \Illuminate\Support\Facades\DB::table('previous_insurer_lists')->where('code', $temp['previous_insurance_company'])->first()->name; //$report->user_proposal->tp_insurance_company ?? "";;
                $temp['previous_policy_number'] = $report->user_proposal->tp_insurance_number ?? "";
                $temp['previous_tp_policy_number'] = $report->user_proposal->tp_insurance_number ?? "";
                $temp['previous_ic_name'] =  $temp['previous_tp_company'] = \Illuminate\Support\Facades\DB::table('previous_insurer_lists')->where('code', $report->user_proposal->tp_insurance_company)->first()->name; //$report->user_proposal->tp_insurance_company ?? "";
                $temp['ncb_percentage'] = !empty($report->user_proposal->applicable_ncb) ? $report->user_proposal->applicable_ncb : $quote_details['quote_details']['applicable_ncb'] ?? '';
                $temp['previous_ncb'] = $quote_details['quote_details']['previous_ncb'] ?? ''; // previous_ncb
                $temp['ncb_claim'] = !empty($report->user_proposal->is_claim) ? $report->user_proposal->is_claim : $quote_details['quote_details']['is_claim'] ?? '';
            }
            if (!empty($report->addons)) {
                if (isset($report->addons[0]->compulsory_personal_accident[0]['name']) && !is_null($report->addons[0]->compulsory_personal_accident[0]['name'])) {
                    $temp['cpa_policy_start_date'] = $temp['policy_start_date'] ?? "";
                    $temp['cpa_policy_end_date'] = \Carbon\Carbon::parse($temp['policy_start_date'] ?? '')->addYear(1)->subDay(1)->format('d-m-Y') ?? "";
                }
                foreach ($report->addons[0]->selected_addons as $key => $value) {
                    if (is_integer($key)) {
                        $temp['selected_addons'] .= $value['name'] . ',' ?? '';
                    } else {
                        $temp['selected_addons'] .= (isset($value[0]['name']) ? $value[0]['name'] . ',' : '');
                    }
                }
                // $temp['selected_addons'] = $report->addons[0]->selected_addons ?? '';
            }
            // $temp['token'] = empty($token) ? ($report->agent_details[0]->token ?? '') : $token;
            if ($temp['token'] != '') {
                $url_response = httpRequest('gramcover_push_data', $temp)['response'];
                $url_response = ((isset($url_response['status']) && isset($url_response['response'])) ? $url_response['response'] : $url_response);
                if(((isset($url_response['status']) && !isset($url_response['response']) && isset($url_response['result'])) && in_array($url_response['result'],['Invalid Token','Token Expired'])))
                {
                    $enquiry_id  = customDecrypt($temp['trace_id']);
                    $request_data = [
                        "seller_type" => $temp['seller_type'],
                        "seller_id" => $temp['seller_id'],
                        "user_product_journey_id" =>  $enquiry_id
                    ];
                    $response = httpRequestNormal(config('DASHBOARD_GET_AGENT_TOKEN'), 'POST', $request_data, [], [
                        'Content-Type' => 'application/json'
                    ], [], true, false);

                    $response_api = $response['response'] ?? [];

                    if(!empty($response_api))
                    {
                        $token_api = $response_api['data']['remote_token'] ?? '';
                        if(!empty($token_api))
                        {
                            $t_data = JwtTokenDecode($token_api);
                            if($t_data['status'] == true && !empty($t_data['token_data']['exp']))
                            {
                                $now = strtotime('now');
                                $t_time = $t_data['token_data']['exp'];
                                if($t_time > $now)
                                {
//                                        $all_agent_id = \App\Models\CvAgentMapping::select('agent_id')->where('user_product_journey_id',$enquiry_id)->pluck('agent_id')->toArray();
//                
//                                        \App\Models\CvAgentMapping::whereHas('journeyStage', function ($query) {
//                                            $query->whereIn('stage', [ STAGE_NAMES['POLICY_ISSUED']]);
//                                        })->whereIn('agent_id',$all_agent_id)
//                                        ->update(['token' => $token_api]);
                                        \App\Models\CvAgentMapping::where('user_product_journey_id', $enquiry_id)
                                          ->update(['token' => $token_api]);
                                }
                            }
                        }

                    }
                }
                $gramcover_post_data_api->update([
                    // 'user_product_journey_id' => $report->user_product_journey_id,
                    'token' => $temp['token'] ?? null,
                    'request' => $temp,
                    'response' => $url_response,
                    'status' => ((isset($url_response['status']) && $url_response['status'] == true) ? 'success' : 'failed'),
                ]);
            } else {
                $gramcover_post_data_api->update([
                    // 'user_product_journey_id' => $report->user_product_journey_id,
                    'token' => $temp['token'] ?? null,
                    'request' => $temp,
                    'response' => '{"result": "Token is required", "status": false}',
                    'status' => "failed",
                ]);
            }
            unset($temp);
        }
    }
}
