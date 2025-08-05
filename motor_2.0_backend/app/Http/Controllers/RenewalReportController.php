<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\MasterProductSubType;
use App\Models\ProposalReportsRequests;
use Facade\FlareClient\Stacktrace\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class RenewalReportController extends Controller
{
    public static function renewalReports(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'seller_type' => ['nullable', 'in:E,P,U,Partner,b2c'],
            'seller_id' => [Rule::requiredIf(function () use ($request) {
                return in_array($request->sellerType, ['E', 'P', 'U', 'Partner']);
            }), 'array'],
            'transaction_stage' => ['nullable', 'array'],
            'product_type' => ['nullable'],
            'enquiry_id' => ['nullable'],
            'proposal_no' => ['nullable'],
            'policy_no' => ['nullable'],
            'company_alias' => ['nullable'],
            'from_time' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'to_time' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'policy_expiry_to' => ['nullable'],
            'policy_expiry_from' => ['nullable']
        ]);

        $reports = UserProductJourney::with([
            'quote_log.master_policy.premium_type',
            'corporate_vehicles_quote_request.product_sub_type.parent',
            'user_proposal.policy_details',
            'user_proposal.breakin_status',
            'agent_details',
            'journey_stage',
            'sub_product',
            'addons',
            'link_delivery_status',
            'payment_response_success',
        ]);

        # We Only Need Policy Issued Here
        $transactionStage = [ STAGE_NAMES['POLICY_ISSUED']];
        
        if (!empty($request->transaction_stage) && in_array(STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], $request->transaction_stage) && in_array(STAGE_NAMES['POLICY_ISSUED'], $request->transaction_stage) && (count($request->transaction_stage) > 0 &&  count($request->transaction_stage) < 3)) {
            $reports = $reports->when(!empty($request->transaction_stage), function ($query) {
                $query->whereHas('payment_response_success', function ($query) {
                    $query->where('updated_at', '>=', date('Y-m-d 00:00:00', strtotime(request()->from)))
                        ->where('updated_at', '<', date('Y-m-d 23:59:59', strtotime(request()->to)));
                });
            });
        } else {
            $reports = $reports->when(!empty($request->from && $request->to), function ($query) {
                $query->whereHas('journey_stage', function ($query) {
                    $query->whereBetween('updated_at', [
                        Carbon::parse(request()->from)->startOfDay(),
                        Carbon::parse(request()->to)->endOfDay(),
                    ]);
                });
            });
        }

        $reports = $reports->when(!empty(request()->policy_expiry_from) && !empty(request()->policy_expiry_to), function ($query) {
            $policy_expiry_from = now()->diffInDays(Carbon::parse(request()->policy_expiry_from));
            $policy_expiry_to = now()->diffInDays(Carbon::parse(request()->policy_expiry_to));

            $query->whereHas('user_proposal', function (Builder $query) use ($policy_expiry_from, $policy_expiry_to) {
                $query->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') < CURDATE() + INTERVAL {$policy_expiry_to} DAY AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') > CURDATE() + INTERVAL {$policy_expiry_from} DAY");
            });
        });

        $reports = $reports->when(!empty($transactionStage), function ($query) use($transactionStage) {
            $query->whereHas('journey_stage', function ($query) use($transactionStage) {
                $query->whereIn('stage', $transactionStage);
            });
        });

        $reports = $reports->orderBy('created_on', 'desc');

        $reports->when(!empty($request->enquiry_id), function ($query) {
            $query->where('user_product_journey_id', customDecrypt(request()->enquiry_id));
        });

        $reports->when(!empty($request->corp_id), function ($query) {
            $query->whereIn('corporate_id', is_array(request()->corp_id) ? request()->corp_id : [request()->corp_id]);
        });

        $reports->when(!empty($request->domain_id), function ($query) {
            $query->whereIn('domain_id', is_array(request()->domain_id) ? request()->domain_id : [request()->domain_id]);
        });

        $reports = $reports->when(!empty($request->proposal_no), function ($query) {
            $query->whereHas('user_proposal', function (Builder $query) {
                $query->where('proposal_no', request()->proposal_no);
            });
        });

        $reports = $reports->when(!empty($request->policy_no), function ($query) {
            $query->whereHas('user_proposal.policy_details', function (Builder $query) {
                $query->where('policy_number', request()->policy_no);
            });
        });

        $reports = $reports->when(!empty($request->seller_type), function ($query) {
            $query->whereHas('agent_details', function (Builder $query) {
                if (request()->seller_type == 'b2c') {
                    $query->where('seller_type', 'U')/* ->whereNotIn('seller_type', ['E', 'P']) */;
                } else {
                    $query->where('seller_type', request()->seller_type);
                }
                // $query->where('seller_type', request()->seller_type);
            });
        });

        $reports = $reports->when(!empty($request->combined_seller_ids), function ($query) {
            $query->whereHas('agent_details', function (Builder $query) /* use($key, $value) */ {
                $i = 0;
                foreach (request()->combined_seller_ids as $key => $value) {
                    $key = ($key == 'b2c') ? 'U' : $key;
                    if ($i == 0)
                        if (empty($value))
                            $query->where('seller_type', $key);
                        else
                            $query->where('seller_type', $key)->whereIn('agent_id', $value);
                    else
                        if (empty($value))
                        $query->orWhere('seller_type', $key);
                    else
                        $query->orWhere('seller_type', $key)->whereIn('agent_id', $value);
                    $i++;
                }
            });
        });

        $reports = $reports->when(!empty($request->seller_id), function ($query) {
            $query->whereHas('agent_details', function (Builder $query) {
                $query->whereIn('agent_id', request()->seller_id);
            });
        });

        if (is_array(request()->product_type)) {
            $master_product_sub_type = MasterProductSubType::whereIn('parent_id', request()->product_type ?? [])->get()->pluck('product_sub_type_id')->toArray();
        } else {
            $master_product_sub_type = MasterProductSubType::where('parent_id', request()->product_type ?? '')->get()->pluck('product_sub_type_id')->toArray();
        }

        $reports = $reports->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
            $query->whereHas('corporate_vehicles_quote_request', function ($query) use ($master_product_sub_type) {
                if (!empty($master_product_sub_type)) {
                    $query->whereIn('product_id', $master_product_sub_type);
                } else {
                    $query->where('product_id', request()->product_type);
                }
            });
        });

        if (isset($request->limit) && isset($request->offset)) {
            $reports = $reports->skip($request->offset)->take($request->limit);
        }

        if ($request->pagination == true) {
            $reportsPagination = $reports->paginate(((isset($request->perPageRecords) && $request->perPageRecords > 0 && $request->perPageRecords != null)  ? $request->perPageRecords : 100));
            $paginationData = [
                'per_page' => $reportsPagination->perPage(),
                'current_page' => $reportsPagination->currentPage(),
                'prev_page_page' => $reportsPagination->previousPageUrl(),
                'next_page_page' => $reportsPagination->nextPageUrl(),
                'total' => $reportsPagination->total(),
                'last_page' => $reportsPagination->lastPage(),
                // 'links' => $reportsPagination->getUrlRange(1, $reportsPagination->lastPage())
            ];
            $reports = $reportsPagination;
        } else {
            $reports = $reports->get();
        }

        $combined_seller_types = array_keys($request->combined_seller_ids ?? []);
        $allDate = [];
        foreach ($reports as $report) {
            $temp = [];
            $temp['trace_id'] = "";
            $temp['proposer_name'] = "";
            $temp['proposer_mobile'] = "";
            $temp['proposer_emailid'] = "";
            $temp['vehicle_registration_number'] = "";
            $temp['journey_type'] = "";
            $temp['cover_amount'] = "";
            $temp['product_name'] = "";
            $temp['vehicle_make'] = "";
            $temp['vehicle_model'] = "";
            $temp['vehicle_version'] = "";
            $temp['vehicle_cubic_capacity'] = "";
            $temp['vehicle_fuel_type'] = "";
            $temp['vehicle_seating_capacity'] = "";
            $temp['vehicle_built_up'] = "";
            $temp['vehicle_gvw'] = "";
            $temp['policy_type'] = "";
            $temp['vehicle_registration_date'] = "";
            $temp['previous_policy_expiry_date'] = "";
            $temp['previous_ncb'] = "";
            $temp['ncb_percentage'] = "";
            $temp['vehicle_manufacture_year'] = "";
            $temp['ncb_claim'] = "";
            $temp['vehicle_body_type'] = "";
            $temp['company_alias'] = "";
            $temp['company_name'] = "";
            $temp['proposal_no'] = "";
            $temp['gender_name'] = "";
            $temp['proposer_gender'] = "";
            $temp['primary_insured_gender'] = "";
            $temp['primary_insured_dob'] = "";
            $temp['primary_insured_name'] = "";
            $temp['primary_insured_mobile'] = "";
            $temp['primary_insured_emailid'] = "";
            $temp['proposer_dob'] = "";
            $temp['od_premium'] = "";
            $temp['tp_premium'] = "";
            $temp['premium_amount'] = "";
            $temp['base_premium'] = "";
            $temp['tax_amount'] = "";
            $temp['ncb_discount'] = "";
            $temp['discount_amount'] = "";
            $temp['cpa_amount'] = "";
            $temp['addon_premium'] = "";
            $temp['proposal_date'] = "";
            $temp['policy_start_date'] = "";
            $temp['policy_end_date'] = "";
            $temp['pincode'] = "";
            $temp['address_line_1'] = "";
            $temp['address_line_2'] = "";
            $temp['address_line_3'] = "";
            $temp['state'] = "";
            $temp['city'] = "";
            $temp['engine_number'] = "";
            $temp['chassis_number'] = "";
            $temp['policy_term'] = "";
            $temp['previous_insurer'] = "";
            $temp['previous_policy_number'] = "";
            $temp['first_name'] = "";
            $temp['last_name'] = "";
            $temp['cpa_policy_start_date'] = "";
            $temp['cpa_policy_end_date'] = "";
            $temp['nominee_dob'] = "";
            $temp['nominee_relationship'] = "";
            $temp['nominee_age'] = "";
            $temp['nominee_name'] = "";
            $temp['tp_start_date'] = "";
            $temp['tp_end_date'] = "";
            $temp['tp_policy_number'] = "";
            $temp['tp_prev_company'] = "";
            $temp['breakin_number'] = "";
            $temp['breakin_status'] = "";
            $temp['owner_type'] = "";
            $temp['is_financed'] = "";
            $temp['hypothecation_to'] = "";
            $temp['sales_date'] = "";
            $temp['transaction_date'] = "";
            $temp['policy_no'] = "";
            $temp['policy_doc_path'] = "";
            $temp['sum_assured'] = "";
            $temp['policy_period'] = "";
            $temp['business_type'] = "";
            $temp['prev_policy_type'] = "";
            $temp['zero_dep'] = "";
            $temp['od_discount'] = "";
            $temp['section'] = "";
            $temp['product_type'] = "";
            $temp['product_type'] = "";
            $temp['sub_product_type'] = "";
            $temp['selected_addons'] = "";
            $temp['selected_additional_covers'] = "";
            $temp['selected_accessories'] = "";
            $temp['selected_discounts'] = "";
            $temp['cpa_policy_end_date'] = "";
            $temp['business_type'] = "";
            $temp["transaction_stage"] = "";
            $temp["proposal_url"] = "";
            $temp["quote_url"] = "";
            $temp["lastupdated_time"] = "";
            $temp["seller_name"] = "";
            $temp["seller_mobile"] = "";
            $temp["seller_email"] = "";
            $temp["seller_id"] = "";
            $temp["seller_type"] = "";
            $temp["addhar_no"] = "";
            $temp["pan_no"] = "";
            $temp["pos_code"] = "";
            $temp["payment_order_id"] = "";
            $temp["payment_status"] = "";
            $temp["payment_time"] = "";
            $temp["domain_id"] = $report->domain_id;
            $temp["corp_id"] = $report->corporate_id;
            $temp["source"] = "";
            $temp["sub_source"] = $report->sub_source ?? "";
            $temp["campaign_id"] = $report->campaign_id ?? "";
            $temp["branch_code"] = "";
            $temp["user_id"] = "";
            $temp['trace_id'] = $report->journey_id ?? "";
            if ($report->journey_stage) {
                $temp["transaction_stage"] = $report->journey_stage->stage ?? "";
                $temp["transaction_stage"] = $report->journey_stage->stage ?? "";
                $temp["proposal_url"] = $report->journey_stage->proposal_url ?? "";
                $temp["quote_url"] = $report->journey_stage->quote_url ?? "";
                $temp["lastupdated_time"] = $report->journey_stage->updated_at ? date('Y-m-d H:i:s', strtotime($report->journey_stage->updated_at)) : "";
            }
            if ($report->agent_details) {
                if ($report->agent_details->count() > 1) {
                    if (!empty($request->seller_type)) {
                        if ($request->seller_type == 'b2c') {
                            continue;
                        }
                        $agent_details = $report->agent_details->where('seller_type', $request->seller_type);
                    }
                    if (!empty($combined_seller_types)) {
                        $agent_details = $report->agent_details->whereIn('seller_type', $combined_seller_types);
                    }
                } else {
                    $seller_type = $request->seller_type == 'b2c' ? 'U' : $request->seller_type;
                    if (empty($seller_type))
                        $agent_details = $report->agent_details;
                    else
                        $agent_details = $report->agent_details->where('seller_type', $seller_type);
                }
                foreach (/* report-> */$agent_details as $key => $agent_detail) {
                    // if (in_array($agent_detail->seller_type, ['E', 'P', 'Partner']) && $request->seller_type != 'U') {
                    $temp["seller_name"] = $agent_detail->agent_name;
                    $temp["seller_mobile"] = $agent_detail->agent_mobile;
                    $temp["seller_email"] = $agent_detail->agent_email;
                    $temp["seller_id"] = $agent_detail->agent_id;
                    $temp["seller_type"] = $agent_detail->seller_type;
                    $temp["addhar_no"] = $agent_detail->aadhar_no;
                    $temp["pan_no"] = $agent_detail->pan_no;
                    $temp["branch_code"] = $agent_detail->branch_code ?? "";
                    $temp["user_id"] = $agent_detail->user_id ?? "";
                    if ($agent_detail->seller_type == 'P') {
                        $temp["rm_code"] = $agent_detail->pos_key_account_manager ?? "";
                    } else {
                        $temp["rm_code"] = $agent_detail->user_id ?? "";
                    }
                    // $temp["pos_code"] = '';
                    // } elseif (in_array($agent_detail->seller_type, ['U']) && $request->seller_type == 'U') {
                    //     $temp["seller_name"] = $agent_detail->agent_name;
                    //     $temp["seller_mobile"] = $agent_detail->agent_mobile;
                    //     $temp["seller_email"] = $agent_detail->agent_email;
                    //     $temp["seller_id"] = $agent_detail->agent_id;
                    //     $temp["seller_type"] = $agent_detail->seller_type;
                    //     $temp["Seller_addhar_no"] = $agent_detail->aadhar_no;
                    //     $temp["seller_pan_no"] = $agent_detail->pan_no;
                    // }
                }
            }
            if (!empty($report->user_proposal) && (!empty($report->user_proposal->first_name) || !empty($report->user_proposal->last_name))) {
                if (isset($report->corporate_vehicles_quote_request->vehicle_owner_type) && $report->corporate_vehicles_quote_request->vehicle_owner_type == 'I') {
                    $temp['proposer_name'] =  $report->user_proposal->first_name . " " . $report->user_proposal->last_name;
                } else {
                    $temp['proposer_name'] =  $report->user_proposal->first_name/*  . " " . $report->user_proposal->last_name */;
                }
            } else {
                $temp['proposer_name'] =  $report->user_fname . " " . $report->user_lname;
            }
            if ($report->quote_log) {
                $temp['od_premium'] = $report->quote_log->od_premium ?? "";
                $temp['tp_premium'] = $report->quote_log->tp_premium ?? "";
                $temp['premium_amount'] = $report->quote_log->final_premium_amount ?? "";
                $temp['base_premium'] = $report->quote_log->premium_json['basicPremium'] ?? "";
                $temp['tax_amount'] = $report->quote_log->service_tax ?? "";
                $temp['ncb_discount'] = $report->quote_log->revised_ncb;
                $temp['discount_amount'] = $report->quote_log->total_discount ?? "";
                $temp['company_name'] = $report->quote_log->premium_json['companyName'] ?? "";
            }
            // $temp['proposer_mobile'] = !empty($report->user_mobile) ? $report->user_mobile : ($report->user_proposal->mobile_number ?? "");
            $temp['proposer_mobile'] =  empty($report->user_proposal->mobile_number) ? ($report->user_mobile ?? "") : ($report->user_proposal->mobile_number ?? "");
            $temp['proposer_emailid'] = !empty($report->user_email) ? $report->user_email : ($report->user_proposal->email ?? "");
            if (!empty($report->corporate_vehicles_quote_request)) {
                $temp['rto_code'] = $report->corporate_vehicles_quote_request->rto_code ?? "";
                $temp['vehicle_registration_number'] = !empty($report->user_proposal->vehicale_registration_number) ? $report->user_proposal->vehicale_registration_number : ($report->corporate_vehicles_quote_request->vehicle_registration_no ?? $temp['rto_code']);
                $temp["source"] = $temp['journey_type'] = $report->corporate_vehicles_quote_request->journey_type ?? "";
            }
            if (!empty($report->quote_log)) {
                $temp['cover_amount'] = $report->quote_log->idv ?? "";
            }
            if (!empty($report->quote_log->quote_data)) {
                $temp['product_name'] = $report['quote_log']['premium_json']['productName'] ?? "";
                $quote_details = $report->quote_log;
                $quote_data = json_decode($quote_details['quote_data'], TRUE);

                $temp['vehicle_make'] = $quote_details->premium_json['mmvDetail']['manfName'] ?? ($quote_data['manfacture_name'] ?? "");
                $temp['vehicle_model'] = $quote_details->premium_json['mmvDetail']['modelName'] ?? ($quote_data['model_name'] ?? "");
                $temp['vehicle_version'] = $quote_details->premium_json['mmvDetail']['versionName'] ?? ($quote_data['version_name'] ?? "");
                $temp['vehicle_cubic_capacity'] = $quote_details->premium_json['mmvDetail']['cubicCapacity'] ?? "";
                $temp['vehicle_fuel_type'] = $quote_details->premium_json['mmvDetail']['fuelType'] ?? ($quote_data['fuel_type'] ?? "");
                $temp['vehicle_seating_capacity'] = $quote_details->premium_json['mmvDetail']['seatingCapacity'] ?? "";
                $temp['vehicle_built_up'] = $mmv_details['data']['version']['vehicle_built_up'] ?? "";
                $temp['vehicle_gvw'] = $quote_details->premium_json['mmvDetail']['grossVehicleWeight'] ?? "";

                if (isset($report->quote_log->master_policy->premium_type->premium_type) && $report->quote_log->master_policy->premium_type->premium_type != NULL) {
                    if (in_array($report->quote_log->master_policy->premium_type->premium_type, ['Third Party', 'Third Party Breakin'])) {
                        $temp['policy_type'] = "Third Party";
                    } elseif ($report->quote_log->master_policy->premium_type->premium_type == 'Own Damage') {
                        $temp['policy_type'] = "Own Damage";
                    } else {
                        $temp['policy_type'] = "Comprehensive";
                    }
                }

                $temp['vehicle_registration_date'] = !empty($report->corporate_vehicles_quote_request->vehicle_register_date) ? $report->corporate_vehicles_quote_request->vehicle_register_date : "";
                $temp['vehicle_registration_date'] = report_date($temp['vehicle_registration_date']);
                $temp['previous_policy_expiry_date'] = (isset($quote_details['quote_details']['previous_policy_expiry_date'])) ? $quote_details['quote_details']['previous_policy_expiry_date'] : "";
                $temp['previous_policy_expiry_date'] = report_date($temp['previous_policy_expiry_date']);
                $temp['previous_ncb'] = $quote_details['quote_details']['previous_ncb'] ?? ''; // previous_ncb
                $temp['ncb_percentage'] = !empty($report->user_proposal->applicable_ncb) ? $report->user_proposal->applicable_ncb : $quote_details['quote_details']['applicable_ncb'] ?? '';
                $temp['vehicle_manufacture_year'] = !empty($report->user_proposal->vehicle_manf_year) ? $report->user_proposal->vehicle_manf_year : $report->corporate_vehicles_quote_request->manufacture_year ?? "";
                $temp['ncb_claim'] = !empty($report->user_proposal->is_claim) ? $report->user_proposal->is_claim : $quote_details['quote_details']['is_claim'] ?? '';
                if (($report->quote_log['premium_json']['mmvDetail']['cubicCapacity'] ?? 0) <= 1000) {
                    $vehicle_body_type = 'Hatchback';
                } else if (($report->quote_log['premium_json']['mmvDetail']['cubicCapacity'] ?? 0) <= 1500 && ($report->quote_log['premium_json']['mmvDetail']['seatingCapacity']) <= 5) {
                    $vehicle_body_type = 'Sedan';
                } else {
                    $vehicle_body_type = 'SUV';
                }
                $temp['vehicle_body_type'] = $vehicle_body_type;
                if (!empty($report->quote_log->premium_json)) {
                    $temp['company_alias'] = $quote_details->premium_json['company_alias'] ?? "";
                }
            }
            if (!empty($report->user_proposal)) {
                $temp['company_name'] = $report->user_proposal->ic_name ?? "";
                $temp['proposal_no'] = $report->user_proposal->proposal_no ?? "";
                $temp['gender_name'] = $report->user_proposal->gender_name ?? "";
                $temp['proposer_gender'] = $report->user_proposal->gender_name ?? "";
                $temp['primary_insured_gender'] = $report->user_proposal->gender_name ?? "";
                $temp['primary_insured_dob'] = report_date($report->user_proposal->dob);
                $temp['primary_insured_name'] = $report->user_proposal->first_name . " " . $report->user_proposal->last_name;
                $temp['primary_insured_mobile'] = $report->user_proposal->mobile_number ?? "";
                $temp['primary_insured_emailid'] = $report->user_proposal->email ?? "";
                $temp['proposer_dob'] = report_date($report->user_proposal->dob);
                $temp['od_premium'] = $report->user_proposal->od_premium ?? "";
                $temp['tp_premium'] = $report->user_proposal->tp_premium ?? "";
                $temp['premium_amount'] = $report->user_proposal->final_payable_amount ? $report->user_proposal->final_payable_amount : $report->quote_log->final_premium_amount ?? "";
                $temp['base_premium'] = $report->user_proposal->total_premium ?? "";
                $temp['tax_amount'] = $report->user_proposal->service_tax_amount ?? "";
                $temp['ncb_discount'] = $report->user_proposal->ncb_discount;
                $temp['discount_amount'] = $report->user_proposal->total_discount ?? "";
                $temp['cpa_amount'] = $report->user_proposal->cpa_premium ?? "";
                $temp['addon_premium'] = $report->user_proposal->addon_premium ?? "";
                $temp['proposal_date'] = $report->user_proposal->proposal_date ?? "";
                $temp['policy_start_date'] = report_date($report->user_proposal->policy_start_date, NULL, 'Y-m-d');
                $temp['policy_end_date'] =   report_date($report->user_proposal->policy_end_date, NULL, 'Y-m-d');
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
                $temp['first_name'] = $report->user_proposal->first_name ?? "";
                $temp['last_name'] = $report->user_proposal->last_name ?? "";
                $temp['cpa_policy_start_date'] = report_date($report->user_proposal->cpa_policy_fm_dt, NULL, 'Y-m-d H:i:s');
                $temp['cpa_policy_end_date'] =  report_date($report->user_proposal->cpa_policy_to_dt, NULL, 'Y-m-d H:i:s');
                $temp['nominee_dob'] = Carbon::parse($report->user_proposal->nominee_dob)->format('Y-m-d') ?? "";
                $temp['nominee_relationship'] = $report->user_proposal->nominee_relationship ?? "";
                $temp['nominee_age'] = $report->user_proposal->nominee_age ?? "";
                $temp['nominee_name'] = $report->user_proposal->nominee_name ?? "";
                $temp['tp_start_date'] =  report_date($report->user_proposal->tp_start_date, NULL, 'Y-m-d H:i:s');
                $temp['tp_end_date'] = report_date($report->user_proposal->tp_end_date, NULL, 'Y-m-d H:i:s');
                $temp['tp_policy_number'] = $report->user_proposal->tp_insurance_number ?? "";
                $temp['tp_prev_company'] = $report->user_proposal->tp_insurance_company ?? "";
                $temp['breakin_number'] = $report->user_proposal->breakin_status->breakin_number ?? "";
                $temp['breakin_status'] = $report->user_proposal->breakin_status->breakin_status ?? "";

                if ($report->user_proposal->owner_type == "I") {
                    $temp['owner_type'] = "Individual" ?? "";
                } elseif ($report->user_proposal->owner_type == "C") {
                    $temp['owner_type'] = "Company" ?? "";
                }
                $temp['is_financed'] = $report->user_proposal->is_vehicle_finance ? "true" : "false" ?? "";
                $temp['hypothecation_to'] = $report->user_proposal->name_of_financer ?? "";
                $temp['sales_date'] = date('Y-m-d', strtotime($report->user_proposal->created_date)) ?? "";
                $temp['transaction_date'] = date('Y-m-d', strtotime($report->user_proposal->created_date)) ?? "";
                $temp['policy_no'] = !empty($report->user_proposal->policy_details) ? $report->user_proposal->policy_details->policy_number : "";
                $temp['policy_doc_path'] = !empty($report->user_proposal->policy_details) ? $report->user_proposal->policy_details->pdf_url : "";
                $temp['sum_assured'] = $report->user_proposal->idv ?? "";
                if ($report->user_proposal->policy_start_date != "" && $report->user_proposal->policy_end_date != "") {
                    $policy_peroid = Carbon::parse($report->user_proposal->policy_start_date)->diffInMonths(Carbon::parse($report->user_proposal->policy_end_date)->addDay());
                    if ($policy_peroid >= 5 && $policy_peroid <= 7) {
                        $temp['policy_period'] = '6 Months';
                    } elseif ($policy_peroid >= 2 && $policy_peroid <= 4) {
                        $temp['policy_period'] = '3 Months';
                    } elseif ($policy_peroid >= 11 && $policy_peroid <= 13) {
                        $temp['policy_period'] = '1 Year';
                    } else {
                        $temp['policy_period'] = $policy_peroid . ' Months';
                    }

                    if (isset($report->quote_log->master_policy->premium_type->premium_type)) {
                        if (in_array($report->quote_log->master_policy->premium_type->premium_type, ['Short Term - 3 Months', 'Short Term - 3 Months - Breakin'])) {
                            $temp['policy_period'] = '3 Months';
                        } else if (in_array($report->quote_log->master_policy->premium_type->premium_type, ['Short Term - 6 Months', 'Short Term - 6 Months - Breakin'])) {
                            $temp['policy_period'] = '6 Months';
                        }
                    }
                }
            }
            if ($report->quote_log) {
                $temp['business_type'] = !empty($report->user_proposal->business_type) ? $report->user_proposal->business_type : $report->corporate_vehicles_quote_request->business_type ?? ""; // from corporate table
                $temp['prev_policy_type'] = $report->corporate_vehicles_quote_request->previous_policy_type ?? "";
                $temp['zero_dep'] = in_array('zeroDepreciation', $report->quote_log['premium_json']['applicableAddons'] ?? []) ? 'Yes' : 'No';
            }
            if (isset($report->quote_log['premium_json']['company_alias']) && $report->quote_log['premium_json']['company_alias'] == 'acko') {
                $temp['od_discount'] = '80';
            } elseif (isset($report->quote_log['premium_json']['company_alias']) && $report->quote_log['premium_json']['company_alias'] == 'icici_lombard') {
                $temp['od_discount'] = '80';
            } elseif (isset($report->quote_log['premium_json']['company_alias']) && $report->quote_log['premium_json']['company_alias'] == 'godigit') {
                $temp['od_discount'] = '75';
            } elseif (isset($report->quote_log['premium_json']['company_alias']) && $report->quote_log['premium_json']['company_alias'] == 'shriram') {
                $temp['od_discount'] = 'NA';
            }
            $temp['section'] = \Illuminate\Support\Str::upper(isset($report['sub_product']['product_sub_type_code']) ? $report['sub_product']['product_sub_type_code'] : "") ?? ""; // product_type , section
            // $temp['product_type'] = \Illuminate\Support\Str::lower(get_parent_code($report->corporate_vehicles_quote_request->product_id ?? null)) ?? "";  //corporate_table product_id
            $temp['product_type'] = \Illuminate\Support\Str::lower($report->corporate_vehicles_quote_request->product_sub_type->parent->product_sub_type_code ?? null) ?? "";  //corporate_table product_id
            $temp['sub_product_type'] = \Illuminate\Support\Str::lower($report['sub_product']['product_sub_type_code'] ?? '') ?? "";
            $temp['selected_addons'] = "";
            if (!empty($report->addons[0])) {
                if (isset($report->addons[0]->compulsory_personal_accident[0]['name']) && !is_null($report->addons[0]->compulsory_personal_accident[0]['name'])) {
                    $temp['cpa_policy_start_date'] = report_date($temp['policy_start_date'], NULL, 'Y-m-d H:i:s');
                    $temp['cpa_policy_end_date']  =  Carbon::parse($temp['policy_start_date'] ?? '')->addYear(1)->subDay(1)->format('Y-m-d H:i:s') ?? "";
                }
                if (is_array($report->addons[0]->selected_addons)) {
                    foreach ($report->addons[0]->selected_addons as $key => $value) {
                        if (is_integer($key)) {
                            $temp['selected_addons'] .= (isset($value['name']) ? $value['name'] . ', ' : '');
                        } else {
                            $temp['selected_addons'] .= (isset($value[0]['name']) ? $value[0]['name'] . ', ' : '');
                        }
                    }
                }
                if (is_array($report->addons[0]->additional_covers)) {
                    foreach ($report->addons[0]->additional_covers/* accessories */ as $key => $value) {
                        $temp['selected_additional_covers'] .= (isset($value['name']) ? $value['name'] . ', ' : '');
                    }
                }
                if (is_array($report->addons[0]->accessories)) {
                    foreach ($report->addons[0]->accessories as $key => $value) {
                        $temp['selected_accessories'] .= (isset($value['name']) ? $value['name'] . ', ' : '');
                    }
                }

                if (is_array($report->addons[0]->discounts)) {
                    foreach ($report->addons[0]->discounts as $key => $value) {
                        $temp['selected_discounts'] .= (isset($value['name']) ? $value['name'] . ', ' : '');
                    }
                }
                // $temp['selected_addons'] = $report->addons[0]->selected_addons ?? '';
            }
            if ($temp['policy_type'] == 'Third Party Breakin' || $temp['policy_type'] == 'Third Party') {
                $temp['previous_ncb'] = "NA";
                $temp['ncb_percentage'] = "NA";
            }
            if (!empty($report->payment_response_success)) {
                $temp["payment_order_id"] = $report->payment_response_success->order_id ?? "";
                $temp["payment_status"] = $report->payment_response_success->status ?? "";
                $temp["payment_time"] = $report->payment_response_success->created_at ?? "";
            }
            if (!empty($report->link_delivery_status)) {
                if ($report->link_delivery_status->status == "delivered") {
                    $temp["link_delivery"] = report_date($report->link_delivery_status->created_at , NULL, 'd-m-Y h:i:s A');
                    $temp["link_clicked"] = '';
                } else {
                    $temp["link_delivery"] = report_date($report->link_delivery_status->created_at,NULL,'d-m-Y h:i:s A' );
                    $temp["link_clicked"] = report_date($report->link_delivery_status->updated_at, NULL, 'd-m-Y h:i:s A');
                }
            }

            array_push($allDate, $temp);
        }

        list($status, $msg, $data) = $allDate
            ? [true, 'result found', $allDate]
            : [false, 'no result found', ""];
        
        if (!empty($request->company_alias))
            $data =  collect($data)->where('company_alias', $request->company_alias)->toArray();

        return response()->json([
            "status" => $status,
            "msg" => $msg,
            "data" => $data,
            'pagination' => $paginationData ?? [],
        ]);
    }
}
