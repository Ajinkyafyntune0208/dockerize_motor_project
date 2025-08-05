<?php

namespace App\Helpers;

use App\Http\Controllers\PremiumDetailController;
use App\Models\LeadGenerationLogs;
use App\Models\MasterCompany;
use App\Models\MasterProductSubType;
use App\Models\PaymentRequestResponse;
use App\Models\RenewalDataMigrationStatus;
use App\Models\UserProductJourney;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Reports
{
    public function proposalReports(Request $request)
    {
        $renewalWhatsappArray = [];
        if (Schema::hasTable('communication_logs_5')) {
            $renewalWhatsappArray = DB::table('communication_logs_5 as c')
                ->where('c.service_type', 'WHATSAPP')
                ->distinct()
                ->pluck('c.user_product_journey_id')
                ->toArray();
        }

        $chassisNumberArray = [];
        $vehicleRegNumberArray = [];
        if (
            config('constants.motorConstant.SMS_FOLDER') == 'tmibasl' &&
            (isset($request->username) || isset($request->vehicles))
        ) {
            $status = true;
            $msg = $errorSpecific = null;
            if (empty($request->lead_source)) {
                $status = false;
                $msg = $errorSpecific = 'Lead Source Required';
            } elseif (empty($request->username) && empty($request->vehicles)) {
                $status = false;
                $msg = $errorSpecific = 'Either username or vehicles value is required';
            }

            if (!empty($request->vehicles) && is_array($request->vehicles)) {
                $vehicles = $request->vehicles;
                $chassisNumberArray = array_filter(array_column($vehicles, 'chassis_number'));
                $vehicleRegNumberArray = array_filter(array_column($vehicles, 'vehicle_reg_number'));
                if (empty($chassisNumberArray) && empty($vehicleRegNumberArray)) {
                    $status = false;
                    $msg = $errorSpecific = 'Either Chassis or Registration Number is required';
                }
            }
            if (!$status) {
                return response()->json([
                    'status' => false,
                    'msg'    => $msg,
                    'errorSpecific' => $errorSpecific
                ]);
            }
        }

        $startTime = microtime(true);

        $reports = UserProductJourney::with([
            'quote_log' => function ($query) {
                return $query->select([
                    'user_product_journey_id',
                    'idv',
                    'final_premium_amount',
                    'premium_json',
                    'od_premium',
                    'tp_premium',
                    'service_tax',
                    'revised_ncb',
                    'quote_data',
                    'master_policy_id',
                    'ic_alias'
                ]);
            },
            'quote_log.master_policy' => function ($query) {
                return $query->with(['master_product' => function ($query1) {
                    return $query1->select(['product_id']);
                }])->select(['policy_id', 'premium_type_id']);
            },
            'quote_log.master_policy.premium_type' => function ($query) {
                return $query->select(['id', 'premium_type']);
            },
            'corporate_vehicles_quote_request' => function ($query) {
                return $query->select([
                    'user_product_journey_id',
                    'product_id',
                    'vehicle_owner_type',
                    'rto_code',
                    'vehicle_registration_no',
                    'journey_type',
                    'vehicle_register_date',
                    'manufacture_year',
                    'business_type',
                    'previous_policy_type',
                    'is_renewal',
                    'rollover_renewal',
                    'rto_city',
                    'policy_type',
                    'version_id'
                ]);
            },
            'corporate_vehicles_quote_request.product_sub_type' => function ($query) {
                return $query->select([
                    'parent_product_sub_type_id',
                    'product_sub_type_id'
                ]);
            },
            'corporate_vehicles_quote_request.product_sub_type.parent' => function ($query) {
                return $query->select([
                    'parent_product_sub_type_id',
                    'product_sub_type_id',
                    'product_sub_type_code'
                ]);
            },
            'user_proposal' => function ($query) {
                return $query->select([
                    'user_proposal_id',
                    'user_product_journey_id',
                    'first_name',
                    'last_name',
                    'mobile_number',
                    'email',
                    'vehicale_registration_number',
                    'applicable_ncb',
                    'vehicle_manf_year',
                    'is_claim',
                    'ic_name',
                    'proposal_no',
                    'gender_name',
                    'dob',
                    'od_premium',
                    'tp_premium',
                    'final_payable_amount',
                    'total_premium',
                    'service_tax_amount',
                    'ncb_discount',
                    'total_discount',
                    'cpa_premium',
                    'addon_premium',
                    'proposal_date',
                    'policy_start_date',
                    'policy_end_date',
                    'pincode',
                    'address_line1',
                    'address_line2',
                    'address_line3',
                    'state',
                    'city',
                    'engine_number',
                    'chassis_number',
                    'insurance_company_name',
                    'previous_policy_number',
                    'cpa_policy_fm_dt',
                    'cpa_policy_to_dt',
                    'nominee_dob',
                    'nominee_relationship',
                    'nominee_age',
                    'nominee_name',
                    'tp_start_date',
                    'tp_end_date',
                    'tp_insurance_number',
                    'tp_insurance_company',
                    'owner_type',
                    'is_vehicle_finance',
                    'name_of_financer',
                    'created_date',
                    'idv',
                    'business_type',
                    'prev_policy_expiry_date',
                    'prev_policy_start_date',
                    'is_ckyc_verified',
                    'previous_ncb',
                    'gender',
                    'ic_id',
                    'ckyc_meta_data',
                    'ckyc_number',
                    'ckyc_reference_id',
                    'is_ckyc_verified',
                    'is_ckyc_details_rejected',
                    'ckyc_type',
                    'ckyc_type_value',
                    'ckyc_extras',
                    'updated_at',
                    'created_at'
                ]);
            },

            'user_proposal.policy_details' => function ($query) {
                return $query->select([
                    'proposal_id',
                    'policy_number',
                    'pdf_url'
                ]);
            },
            'user_proposal.breakin_status' => function ($query) {
                return $query->select([
                    'user_proposal_id',
                    'breakin_number',
                    'breakin_status',
                    'inspection_date'
                ]);
            },
            'agent_details' => function ($query) {
                if (config('constants.motorConstant.SMS_FOLDER') == 'ace') {
                    $query = $query->where(function ($query) {
                        $query->where('agent_id', 'NOT LIKE', '%-%')->orWhereNULL('agent_id');
                    });
                }
                return $query->select([
                    'user_product_journey_id',
                    'agent_name',
                    'agent_mobile',
                    'agent_email',
                    'agent_id',
                    'seller_type',
                    'aadhar_no',
                    'pan_no',
                    'branch_code',
                    'user_id',
                    'agent_business_type',
                    'agent_business_code',
                    'user_name',
                    'pos_key_account_manager',
                    'branch_name',
                    'channel_id',
                    'channel_name',
                    'region_name',
                    'region_id',
                    'zone_id',
                    'zone_name',
                    'source_type',
                    'employee_pos_id',
                    'agent_pos_id'
                ]);
            },
            'journey_stage' => function ($query) {
                return $query->select([
                    'user_product_journey_id',
                    'stage',
                    'proposal_url',
                    'quote_url',
                    'updated_at'
                ]);
            },
            'sub_product' => function ($query) {
                return $query->select([
                    'product_sub_type_code',
                    'product_sub_type_id'
                ]);
            },
            'addons' => function ($query) {
                return $query->select([
                    'user_product_journey_id',
                    'compulsory_personal_accident',
                    'applicable_addons',
                    'additional_covers',
                    'accessories',
                    'discounts'
                ]);
            },
            'link_delivery_status' => function ($query) {
                return $query->select([
                    'user_product_journey_id',
                    'status',
                    'created_at',
                    'updated_at',
                ]);
            },
            'payment_response_success' => function ($query) {
                return $query->select([
                    'user_product_journey_id',
                    'order_id',
                    'status',
                    'created_at',
                    'updated_at'
                ]);
            },
            'finsall_payment_details' => function ($query) {
                return $query->select([
                    'user_product_journey_id',
                    'is_payment_finsall'
                ]);
            },
            'premium_details' => function ($query) {
                return $query->select([
                    'user_product_journey_id',
                    'details',
                    'commission_details',
                    'payin_details'
                ]);
            }
        ]);

        $reports = $reports->when(!empty($request->lead_source), function ($query) use ($request) {
            $query->where('lead_source', $request->lead_source);
        });

        $customPagination = false;
        if (
            !empty($request->policy_expiry_date_from) &&
            !empty($request->policy_expiry_date_to) && !empty($request->pagination) &&
            !empty($request->perPageRecords)
        ) {
            $customPagination = true;
        }

        if (
            !empty($request->transaction_stage) &&
            in_array(STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], $request->transaction_stage) &&
            in_array(STAGE_NAMES['POLICY_ISSUED'], $request->transaction_stage) &&
            (count($request->transaction_stage) > 0 &&  count($request->transaction_stage) < 3)
        ) {
            $reports = $reports->when(!empty($request->transaction_stage), function ($query) use ($request) {
                $query->whereHas('payment_response_success', function ($query) use ($request) {
                    $query->where('updated_at', '>=', date('Y-m-d 00:00:00', strtotime($request->from)))
                        ->where('updated_at', '<', date('Y-m-d 23:59:59', strtotime($request->to)));
                });
            });
        } else {
            $reports = $reports->when(!empty($request->from && $request->to), function ($query) use ($request) {
                $query->whereHas('journey_stage', function ($query) use ($request) {
                    $query->whereBetween('updated_at', [
                        Carbon::parse($request->from)->startOfDay(),
                        Carbon::parse($request->to)->endOfDay(),
                    ]);
                });
            });
        }
        $reports = $reports->when(!empty($request->from_time && $request->to_time), function ($query) use ($request) {
            $query->whereHas('journey_stage', function ($query) use ($request) {
                $query->whereBetween('updated_at', [
                    Carbon::parse($request->from_time),
                    Carbon::parse($request->to_time),
                ]);
            });
        });
        $reports = $reports->when(!empty($request->transaction_stage), function ($query) use ($request) {
            $query->whereHas('journey_stage', function ($query) use ($request) {
                $query->whereIn('stage', $request->transaction_stage);
            });
        });


        $orderBy = "created_on";
        $order = strtoupper((strtoupper($request->order ?? "") == "ASC") ? "ASC" : "DESC");

        $reports = $reports->orderBy($orderBy, $order);

        $reports->when(!empty($request->enquiry_id), function ($query) use ($request) {
            $query->where('user_product_journey_id', customDecrypt($request->enquiry_id));
        });

        $reports->when(!empty($request->corp_id), function ($query) use ($request) {
            $query->whereIn('corporate_id', is_array($request->corp_id) ? $request->corp_id : [$request->corp_id]);
        });
        $reports->when(!empty($request->domain_id), function ($query) use ($request) {
            $query->whereIn('domain_id', is_array($request->domain_id) ? $request->domain_id : [$request->domain_id]);
        });

        $reports = $reports->when(!empty($request->proposal_no), function ($query) use ($request) {
            $query->whereHas('user_proposal', function (Builder $query) use ($request) {
                $query->where('proposal_no', $request->proposal_no);
            });
        });


        // from mobile and email (mobile || email)
        $request_mobile = $request->mobile;
        $request_email = $request->email;
        if (!empty($request->mobile) && !empty($request->email)) {
            $reports = $reports->when((!empty($request_mobile) || !empty($request_email)),
                function ($query) use ($request_mobile, $request_email) {
                    $query->whereHas('user_proposal', function (Builder $query) use ($request_mobile, $request_email) {
                        $query->where('mobile_number', $request_mobile)
                            ->orWhere('email', $request_email);
                    });
                }
            );
        } elseif (!empty($request->mobile)) {
            //from mobile field
            $reports = $reports->when(!empty($request->mobile), function ($query) use ($request) {
                $query->whereHas('user_proposal', function (Builder $query) use ($request) {
                    $query->where('mobile_number', $request->mobile);
                });
            });
        } elseif (!empty($request->email)) {
            // from email id
            $reports = $reports->when(!empty($request->email), function ($query) use ($request) {
                $query->whereHas('user_proposal', function (Builder $query) use ($request) {
                    $query->where('email', $request->email);
                });
            });
        }


        $reports = $reports->when(!empty($request->engine_number), function ($query) use ($request) {
            $query->whereHas('user_proposal', function (Builder $query) use ($request) {
                $query->where('engine_number', $request->engine_number);
            });
        });
        if (!empty($chassisNumberArray) || !empty($vehicleRegNumberArray)) {
            $reports = $reports->when((!empty($chassisNumberArray) || !empty($vehicleRegNumberArray)),
                function ($query) use ($chassisNumberArray, $vehicleRegNumberArray) {
                    $query->whereHas('user_proposal', function (Builder $query) use ($chassisNumberArray, $vehicleRegNumberArray) {
                        $query->whereIn('chassis_number', $chassisNumberArray)
                            ->orWhereIn('vehicale_registration_number', $vehicleRegNumberArray);
                    });
                }
            );
        } else {
            $reports = $reports->when(!empty($request->chassis_number), function ($query) use ($request) {
                $query->whereHas('user_proposal', function (Builder $query) use ($request) {
                    $query->where('chassis_number', $request->chassis_number);
                });
            });
        }
        if (!empty($request->policy_start_date) && !empty($request->policy_end_date)) {
            $policy_start_date = $request->policy_start_date;
            $policy_end_date = $request->policy_end_date;

            $reports = $reports->whereHas(
                'user_proposal',
                function (Builder $query) use ($policy_start_date, $policy_end_date) {
                    $query->whereRaw(
                        "DATE_FORMAT(STR_TO_DATE(policy_start_date, '%d-%m-%Y'), '%Y-%m-%d') BETWEEN ? AND ?",
                        [$policy_start_date, $policy_end_date]
                    );
                }
            );
        } elseif (!empty($request->from_end_date) && !empty($request->to_end_date)) {
            $from_end_date = $request->from_end_date;
            $to_end_date = $request->to_end_date;

            $reports = $reports->whereHas(
                'user_proposal',
                function (Builder $query) use ($from_end_date, $to_end_date) {
                    $query->whereRaw(
                        "DATE_FORMAT(STR_TO_DATE(policy_end_date, '%d-%m-%Y'), '%Y-%m-%d') BETWEEN ? AND ?",
                        [$from_end_date, $to_end_date]
                    );
                }
            );
        }
        $reports = $reports->when(!empty($request->rto_code), function ($query) use ($request) {
            $query->whereHas('corporate_vehicles_quote_request', function ($query) use ($request) {
                $query->where('rto_code', $request->rto_code);
            });
        });

        if (empty($vehicleRegNumberArray)) {
            $reports = $reports->when(!empty($request->vehicle_reg_number), function ($query) use ($request) {
                $query->whereHas('user_proposal', function (Builder $query) use ($request) {
                    $query->whereRaw("REPLACE(vehicale_registration_number,'-','') = ? ", [str_replace("-", "", $request->vehicle_reg_number)]);
                });
            });
        }

        $removeData = [];

        $reports = $reports->when(!empty($request->policy_expiry_date_from) &&
            !empty($request->policy_expiry_date_to), function ($query) use ($request, $removeData) {
            $query->whereHas('user_proposal', function (Builder $query) use ($request) {
                $query->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') >= '{$request->policy_expiry_date_from}' AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') <= '{$request->policy_expiry_date_to}'");
            })->whereHas('journey_stage', function ($query) use ($removeData) {
                $query->whereIn('stage', [
                    STAGE_NAMES['POLICY_ISSUED'],
                    STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
                ])->whereNotIn('user_product_journey_id', $removeData);
            });
        });

        $reports = $reports->when(!empty($request->policy_no), function ($query) use ($request) {
            $query->whereHas('user_proposal.policy_details', function (Builder $query) use ($request) {
                $query->where('policy_number', $request->policy_no);
            });
        });

        $reports = $reports->when(!empty($request->seller_type), function ($query) use ($request) {
            $query->whereHas('agent_details', function (Builder $query) use ($request) {
                if ($request->seller_type == 'b2c') {
                    $query->whereNull('seller_type')->whereNotNull('user_id');
                } else {
                    $query->where('seller_type', $request->seller_type);
                }
            });
        });

        $reports = $reports->when(!empty($request->username), function ($query) use ($request) {
            $query->whereHas('agent_details', function (Builder $query) use ($request) {
                $query->where('user_name', $request->username);
            });
        });

        /* Changes As Per Dashboard Team Requirement. Show All Record in Renewal (Patch Work) */
        if (
            config('ABIBL_MG_DATA_CHANGE') === "Y" &&
            !empty($request->policy_expiry_date_from) && !empty($request->policy_expiry_date_to)
        ) {
            $request->combined_seller_ids = [];
        }

        $reports = $reports->when(!empty($request->combined_seller_ids), function ($query) use ($request) {
            $query->whereHas('agent_details', function (Builder $query) use ($request) {
                $i = 0;
                foreach ($request->combined_seller_ids as $key => $value) {
                    $where_condition = $i == 0 ? 'where' : 'orWhere';
                    $query->$where_condition(function (Builder $query) use ($key, $value) {
                        if ($key == 'b2c') {
                            $query->where(function (Builder $query) {
                                $query->whereNull('seller_type')
                                    ->orWhere('seller_type', '')
                                    ->orWhere('seller_type', 'b2c');
                            });
                            if (!empty($value)) {
                                $query->whereIn('user_id', $value);
                            }
                        } elseif ($key == 'U') {
                            $query->where('seller_type', $key)->whereNotNull('user_id');
                            if (!empty($value)) {
                                $query->whereIn('user_id', $value);
                            }
                        } else {
                            $query->where('seller_type', $key);
                            if (!empty($value)) {
                                $query->whereIn('agent_id', $value);
                            }
                        }
                    });
                    $i++;
                }
            });
        });


        $reports = $reports->when(!empty($request->user_ids), function ($query) use ($request) {
            $query->whereHas('agent_details', function (Builder $query) use ($request) {
                $query = $query->whereIn('user_id', $request->user_ids);
            });
        });

        $reports = $reports->when(!empty($request->payment_mode), function ($query) use ($request) {
            if ($request->payment_mode === "Finsal") {
                $query->whereHas('finsall_payment_details', function (Builder $query) {
                    $query = $query->where('is_payment_finsall', "Y");
                });
            }

            if ($request->payment_mode === "Customer Payment") {
                $query->doesntHave('finsall_payment_details');
            }
        });
        $reports = $reports->when(!empty($request->source), function ($query) use ($request) {
            $query->whereHas('agent_details', function (Builder $query) use ($request) {
                $query = $query->whereIn('source_type', $request->source);
            });
        });
        $reports = $reports->when(!empty($request->seller_source), function ($query) use ($request) {
            $query->whereHas('agent_details', function (Builder $query) use ($request) {
                $query = $query->whereIn('source_type', $request->seller_source);
            });
        });

        $reports = $reports->when(!empty($request->seller_id), function ($query) use ($request) {
            $query->whereHas('agent_details', function (Builder $query) use ($request) {
                if ($request->seller_type == 'b2c') {
                    $query->whereIn('user_id', $request->seller_id);
                } else {
                    $query->whereIn('agent_id', $request->seller_id);
                }
            });
        });

        if (is_array($request->product_type)) {
            $master_product_sub_type = MasterProductSubType::whereIn('parent_product_sub_type_id', $request->product_type)
                ->get()->pluck('product_sub_type_id')
                ->toArray();
        } else {
            $master_product_sub_type = MasterProductSubType::where('parent_product_sub_type_id', $request->product_type)
                ->get()
                ->pluck('product_sub_type_id')
                ->toArray();
        }

        $reports = $reports->when(!empty($request->product_type), function ($query) use ($master_product_sub_type, $request) {
            $query->whereHas('corporate_vehicles_quote_request', function ($query) use ($master_product_sub_type, $request) {
                if (!empty($master_product_sub_type)) {
                    $query->whereIn('product_id', $master_product_sub_type);
                } else {
                    $query->where('product_id', $request->product_type);
                }
            });
        });

        $reports = $reports->when(!empty($request->not_combined_seller_ids), function ($query) use ($request) {
            $query->whereHas('agent_details', function (Builder $query) use ($request) /* use($key, $value) */ {
                $i = 0;
                foreach ($request->not_combined_seller_ids as $key => $value) {
                    $key = ($key == 'b2c') ? 'U' : $key;
                    if ($i == 0) {
                        if (empty($value)) {
                            $query->where('seller_type', "!=", $key);
                        } else {
                            $query->where('seller_type', $key)->whereNotIn('agent_id', $value);
                        }
                    } else {
                        if (empty($value)) {
                            $query->orWhere('seller_type', "!=", $key);
                        } else {
                            $query->orWhere('seller_type', $key)->whereNotIn('agent_id', $value);
                        }
                    }
                    $i++;
                }
            });
        });

        $reports = $reports->when(!empty($request->block_ic), function ($query) use ($request) {
            $query->whereHas('quote_log', function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    foreach ($request->block_ic as $value) {
                        $query->where('quote_data', 'NOT LIKE', '%' . $value . '%');
                    }
                });
            });
        });
        $reports = $reports->when(!empty($request->master_company), function ($query) use ($request) {
            $query->whereHas('quote_log', function ($query) use ($request) {
                $query->whereIn('ic_id', $request->master_company);
            });
        });

        if (isset($request->limit) && isset($request->offset)) {
            $reports = $reports->skip($request->offset)->take($request->limit);
        }

        if ($request->return_only_count) {
            $excelDataCount = $reports->count();
            return response()->json([
                'status' => true,
                'excelDataCount' => $excelDataCount
            ]);
        }
        if ($request->pagination && !$customPagination) {
            $reportsPagination = $reports->paginate(((isset($request->perPageRecords) &&
                $request->perPageRecords > 0 && $request->perPageRecords != null)  ? $request->perPageRecords : 100));
            $paginationData = [
                'pagination_type' => 'integrated',
                'per_page' => $reportsPagination->perPage(),
                'current_page' => $reportsPagination->currentPage(),
                'prev_page_page' => $reportsPagination->previousPageUrl(),
                'next_page_page' => $reportsPagination->nextPageUrl(),
                'total' => $reportsPagination->total(),
                'last_page' => $reportsPagination->lastPage(),
            ];
            $reports = $reportsPagination;
        } else {
            $reports = $reports->get();
        }

        $all_records_count = $reports->count();
        $midtime = microtime(true);
        $compay_logo = MasterCompany::where('status', 'Active')
            ->get(['company_alias', 'logo']);
        $allDate = [];

        foreach ($reports as $report) {
            $temp = [];
            $temp['policy_tenture_days'] = "";
            $temp['trace_id_created_on'] = $report->created_on ?? "";
            $temp['vehicle_age'] = "";
            $temp['non_electrical_cover_amount'] = "";
            $temp['electrical_cover_amount'] = "";
            $temp['cng_cover_amount'] = "";
            $temp['vehicle_age_slab'] = "";
            $temp['cng_tp'] = "";
            $temp['addon_plan'] = "";
            $temp['trace_id'] = "";
            $temp['migration_status'] = null;
            $temp['migration_comment'] = null;
            $temp['proposer_name'] = "";
            $temp['proposer_mobile'] = "";
            $temp['proposer_emailid'] = "";
            $temp['vehicle_registration_number'] = "";
            $temp['journey_type'] = "";
            $temp['cover_amount'] = "";
            $temp['product_name'] = "";
            $temp['ft_version_id'] = null;
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
            $temp['previous_policy_start_date'] = "";
            $temp['previous_ncb'] = "";
            $temp['ncb_percentage'] = "";
            $temp['vehicle_manufacture_year'] = "";
            $temp['ncb_claim'] = "";
            $temp['vehicle_body_type'] = "";
            $temp['vehicle_no_of_wheels'] = '';
            $temp['company_alias'] = "";
            $temp['company_logo_url'] = "";
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
            $temp['od_net_premium'] = "";
            $temp['tp_premium'] = "";
            $temp['premium_amount'] = "";
            $temp['base_premium'] = "";
            $temp['tax_amount'] = "";
            $temp['ncb_discount'] = "";
            $temp['discount_amount'] = "";
            $temp['cpa_amount'] = "";
            $temp['addon_premium'] = "";
            $temp['proposal_date'] = $report->created_on;
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
            $temp['inspection_date'] = "";
            $temp['owner_type'] = "";
            $temp['is_financed'] = "";
            $temp['hypothecation_to'] = "";
            $temp['sales_date'] = "";
            $temp['transaction_date'] = "";
            $temp['policy_no'] = "";
            $temp['policy_doc_path'] = "";
            $temp['sum_assured'] = "";
            $temp['policy_period'] = "";
            $temp['prev_policy_type'] = "";
            $temp['zero_dep'] = "";
            $temp['od_discount'] = "";
            $temp['section'] = "";
            $temp['product_type'] = "";
            $temp['sub_product_type'] = "";
            $temp['selected_addons'] = "";
            $temp['selected_additional_covers'] = "";
            $temp['selected_accessories'] = "";
            $temp['selected_discounts'] = "";
            $temp['cpa_policy_end_date'] = "";
            $temp['business_type'] = "";
            $temp['ckyc_status'] = "";
            $temp["transaction_stage"] = "";
            $temp["proposal_url"] = "";
            $temp["quote_url"] = "";
            $temp["renewal_redirection_url"] = "";
            $temp["lastupdated_time"] = "";
            $temp["seller_name"] = "";
            $temp["seller_mobile"] = "";
            $temp["seller_email"] = "";
            $temp["seller_id"] = "";
            $temp["seller_type"] = "b2c";
            $temp["seller_business_type"] = "";
            $temp["seller_business_code"] = "";
            $temp["seller_username"] = "";
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
            $temp["seller_source"] = '';
            $temp["campaign_id"] = $report->campaign_id ?? "";
            $temp["branch_code"] = "";
            $temp["user_id"] = "";
            $temp["is_offline_entry"] = ($report->lead_source == 'RENEWAL_DATA_UPLOAD') ? '1' : '0';

            if (config('enquiry_id_encryption') == 'Y') {
                $temp['trace_id'] = getDecryptedEnquiryId($report->journey_id);
                $temp['encrypted_trace_id'] = $report->journey_id ?? "";
            } else {
                $temp['trace_id'] = $report->journey_id ?? "";
            }

            $temp["branch_name"] = "";
            $temp["channel_id"] = "";
            $temp["channel_name"] = "";
            $temp["region_name"] =  "";
            $temp["region_id"] = "";
            $temp["zone_id"] =  "";
            $temp["zone_name"] = "";
            $temp["idv_electrical"] = "";
            $temp["idv_non_electrical"] = "";
            $temp["idv_cng"] = "";
            $temp["cng_premium"] = "";
            $temp['cpa'] = "";
            $temp['pa_to_owner_driver'] = "";
            $temp['rto_code'] = "";
            $temp['rto_city'] = null;
            $temp['policy_category_name'] = null;
            $temp['idv_vehicle'] = "";
            $temp['idv_total'] = "";
            $temp['electrical_accessories_premium'] = "";
            $temp['nonelectrical_accessories_premium'] = "";
            $temp['zero_dep_premium'] = '';
            $temp['ll_paid_driver_premium'] = '';
            $temp['zd_previous_policy_addon'] = '';
            $temp['basic_od_premium'] =  "";
            $temp['basic_tp_premium'] =  "";
            $temp['payment_mode'] =  "";
            $temp['source_type'] =  "";
            $temp['premium_breakup']  = [];
            $temp['renewal_redirection_url_b2c']  = '';
            $temp['renewal_redirection_url_b2b']  = '';
            $temp['lead_source']  = $report->lead_source ?? '';
            $temp['ckyc_number'] = '';
            $temp['ckyc_reference_id'] = '';
            $temp['ckyc_meta_data'] =  '';
            $temp['is_ckyc_verified'] =  '';
            $temp['is_ckyc_details_rejected'] = '';
            $temp['ckyc_type'] =  '';
            $temp['ckyc_type_value'] =  '';
            $temp['ckyc_extras'] =  '';
            $temp["is_renewal"] = null;
            $temp["rollover_renewal"] = null;
            $temp["renewal_via_whatapp"] = null;
            $temp["previous_policy_trace_id"] = null;
            $temp['is_renewed'] = null;
            $temp['agent_pos_id'] = null;
            $temp['employee_pos_id'] = null;
            $temp['migration_uploaded_at'] = null;
            $temp['migration_uploaded_date'] = null;
            $temp['broker_utm_source'] = null;
            $temp['broker_utm_media'] = null;
            $temp['broker_utm_campaign'] = null;
            $temp['brokerage'] = null;
            $temp['commissionAmount'] = null;
            $temp['payInAmount'] = null;
            $temp['payInBrokerage'] = null;
            $temp['commission_difference'] = null;


            self::migrationData($report, $temp);
            self::journeyStageData($report, $temp);
            self::agentData($report, $temp, $request);
            self::userProposalData($report, $temp);
            self::corporateVehicleData($report, $temp);
            self::paymentDetails($report, $temp);
            self::getPolicyPdf($report, $temp);

            $temp['company_logo_url'] = $compay_logo->where('company_alias', $temp['company_alias'])?->first()?->logo ?? "";

            self::productData($report, $temp);
            self::addonsData($report, $temp);

            self::premiumData($report, $temp);

            if (in_array($temp['policy_type'], [
                'Third Party Breakin',
                'Third Party'
            ])) {
                $temp['previous_ncb'] = "NA";
                $temp['ncb_percentage'] = "NA";
            }

            if (!empty($report->link_delivery_status)) {
                if ($report->link_delivery_status->status == "delivered") {
                    $temp["link_delivery"] = report_date($report->link_delivery_status->created_at, null, 'd-m-Y h:i:s A');
                    $temp["link_clicked"] = '';
                } else {
                    $temp["link_delivery"] = report_date($report->link_delivery_status->created_at, null, 'd-m-Y h:i:s A');
                    $temp["link_clicked"] = report_date($report->link_delivery_status->updated_at, null, 'd-m-Y h:i:s A');
                }
            }

            $temp["reference_code"] = $report?->proposal_extra_fields?->reference_code;

            $skip = false;
            $renewal_product_type = (in_array($temp['product_type'], ['pcv', 'gcv'])) ? 'cv' : $temp['product_type'];
            $temp["renewal_redirection_url"] = url("api/renewal/{$renewal_product_type}/GenerateLead");
            $temp["renewal_redirection_url_b2c"] = url("api/renewal/{$renewal_product_type}/GenerateLead?is_renewal=Y");
            $temp["renewal_redirection_url_b2b"] = url("api/renewal/{$renewal_product_type}/GenerateLead?is_renewal=Y&redirection=YjJi");

            if ((config('GET_RENEWAL_JOURNEY_BY_OLD_ID') == 'Y' &&
                isset($request->policy_expiry_date_from)) || ($request->update_renewal_data == 'Y')) {

                $newJourneyObject = UserProductJourney::join('cv_journey_stages as js', 'js.user_product_journey_id', 'user_product_journey.user_product_journey_id')
                    ->where('old_journey_id', $report->user_product_journey_id)
                    ->select('js.user_product_journey_id', 'js.stage', 'js.proposal_url', 'js.quote_url', 'user_product_journey.created_on')
                    ->orderBy('js.user_product_journey_id', 'desc')
                    ->first();

                $newJourneyData = [];


                if (!empty($newJourneyObject)) {
                    $newJourneyData[] = [
                        'journey_id' => $newJourneyObject->journey_id ?? '',
                        'stage' => $newJourneyObject->stage ?? '',
                        'url' => self::getCurrentUrl($newJourneyObject)
                    ];
                }

                // We don't need to skip the data, if it is a renewal case
                if (
                    !empty($newJourneyData[0]['stage']) &&
                    in_array($newJourneyData[0]['stage'], [STAGE_NAMES['POLICY_ISSUED']])
                ) {
                    $temp['is_renewed'] = 'Y';
                    if ($request->update_renewal_data != 'Y') {
                        $skip = true;
                    }
                }
                $temp['renewal_journeys'] = $newJourneyData;
            }

            if (in_array(customDecrypt($report->journey_id), $renewalWhatsappArray)) {
                $temp["renewal_via_whatapp"] = 'Y';
            }

            if (
                !empty($request->previous_policy_details) &&
                $request->previous_policy_details == 'Y' &&
                !empty($report->old_journey_id)
            ) {
                $temp["previous_policy_trace_id"] = customEncrypt($report->old_journey_id);
            }

            if (config('constants.motorConstant.SMS_FOLDER') == 'hero') {
                $utm_request = LeadGenerationLogs::where('enquiry_id', $report->user_product_journey_id)
                    ->where('method', 'payload received')
                    ->latest()->value('request');
                if (!empty($utm_request)) {
                    $utm_details = json_decode($utm_request, true)['utm'];
                    $temp['broker_utm_source'] = $utm_details['utm_source'];
                    $temp['broker_utm_media'] = $utm_details['utm_media'];
                    $temp['broker_utm_campaign'] = $utm_details['utm_campaign'];
                }
            }
            if (!$skip) {
                array_push($allDate, $temp);
            }
        }

        if (
            !empty($allDate) && !empty($request->policy_expiry_date_from) &&
            !empty($request->policy_expiry_date_to) && $customPagination
        ) {
            $allDate = self::sortArray($allDate, 'policy_end_date');
            $new_pagination_data = self::pagination($allDate, $request->perPageRecords, $request->page);
            $paginationData = [
                'pagination_type'   => 'custom',
                'per_page'          => $new_pagination_data->perPage(),
                'current_page'      => $new_pagination_data->currentPage(),
                'prev_page_page'    => $new_pagination_data->previousPageUrl(),
                'next_page_page'    => $new_pagination_data->nextPageUrl(),
                'total'             => $new_pagination_data->total(),
                'last_page'         => $new_pagination_data->lastPage()
            ];
            $allDate = array_values($new_pagination_data->toArray()['data']);
        }

        $endtime = microtime(true);
        $count = count($allDate);
        list($status, $msg, $data) = $allDate
            ? [true, "{$count} Records Found", $allDate]
            : [false, 'no result found', ""];

        if (!empty($request->company_alias)) {
            $data =  collect($data)->where('company_alias', $request->company_alias)->toArray();
        }

        if (!empty($request->block_ic)) {
            $data =  collect($data)->whereNotIn('company_alias', $request->block_ic)->toArray();
        }

        return response()->json([
            'query_time' => ($midtime - $startTime),
            'for_time' => ($endtime - $midtime),
            'full_time' => ($endtime - $startTime),
            "status" => $status,
            "msg" => $msg,
            "msg_renewal" => 'Total Records ' . $all_records_count,
            "data" => $data,
            'pagination' => $paginationData ?? [],
        ]);
    }

    public static function migrationData($report, &$temp)
    {
        $migration_detail = RenewalDataMigrationStatus::select('id', 'status', 'action', 'request')
            ->where('user_product_journey_id', $report->user_product_journey_id)
            ->orderBy('id', 'DESC')
            ->first();

        if (!empty($migration_detail->action) && $migration_detail->action == 'migration') {
            $attempt_logs = $migration_detail->migration_attempt_logs->toArray();
            $attempt_logs = end($attempt_logs);
            if (!empty($attempt_logs)) {
                $temp['migration_comment'] = json_decode($attempt_logs['extras'], true)['reason'] ?? null;
            }
            $temp['migration_status'] = $migration_detail->status == 'Success' ? 'Success' : 'Failed';
            if (!empty($migration_detail->request)) {
                $temp['migration_uploaded_at'] = json_decode($migration_detail->request, true)['migration_uploaded_at'] ?? null;
                // Format will be same as of lastupdated_time
                $temp['migration_uploaded_date'] = !empty($temp['migration_uploaded_at']) ? Carbon::createFromFormat('d-m-Y h:i:s A', $temp['migration_uploaded_at'])->format('Y-m-d H:i:s') : null;
            }
        }
    }

    public static function journeyStageData($report, &$temp)
    {
        if ($report->journey_stage) {
            $temp["transaction_stage"] = $report->journey_stage->stage ?? "";
            $temp["transaction_stage_code"] = STAGE_CODE[$report->journey_stage->stage] ?? "";
            $temp["proposal_url"] = $report->journey_stage->proposal_url ?? "";
            $temp["quote_url"] = $report->journey_stage->quote_url ?? "";
            $temp["lastupdated_time"] = $report->journey_stage->updated_at;
        }
    }

    public static function agentData($report, &$temp, Request $request)
    {
        $combinedSellerTypes = array_keys($request->combined_seller_ids ?? []);
        if (!empty($report->agent_details)) {
            if (!empty($combinedSellerTypes)) {
                $agentDetails = $report->agent_details->whereIn('seller_type', $combinedSellerTypes);
            } else {
                $agentDetails = $report->agent_details;
            }
            if ($agentDetails->count() > 1) {
                $agent_details_temp = $agentDetails;
                unset($agentDetails);
                $agentDetails[0] = $agent_details_temp[0];
                unset($agent_details_temp);
            }

            if (!empty($agentDetails)) {
                foreach ($agentDetails as $agent_detail) {
                    $temp["seller_name"] = $agent_detail->agent_name;
                    $temp["seller_mobile"] = $agent_detail->agent_mobile;
                    $temp["seller_email"] = $agent_detail->agent_email;
                    $temp["seller_id"] = (int) $agent_detail->agent_id;
                    $temp["seller_type"] = $agent_detail->seller_type;
                    $temp["addhar_no"] = $agent_detail->aadhar_no;
                    $temp["pan_no"] = $agent_detail->pan_no;
                    $temp["branch_code"] = $agent_detail->branch_code ?? "";
                    $temp["user_id"] = $agent_detail->user_id ?? "";
                    $temp["seller_business_type"] = $agent_detail->agent_business_type;
                    $temp["seller_business_code"] = $agent_detail->agent_business_code;
                    $temp["seller_username"] = $agent_detail->user_name;
                    $temp["branch_name"] = $agent_detail->branch_name ?? "";
                    $temp["channel_id"] = $agent_detail->channel_id ?? "";
                    $temp["channel_name"] = $agent_detail->channel_name ?? "";
                    $temp["region_name"] = $agent_detail->region_name ?? "";
                    $temp["region_id"] = $agent_detail->region_id ?? "";
                    $temp["zone_id"] = $agent_detail->zone_id ?? "";
                    $temp["source_type"] = $agent_detail->source_type ?? "";
                    $temp["zone_name"] = $agent_detail->zone_name ?? "";
                    $temp["seller_source"] = $agent_detail->source_type ?? "";
                    $temp["employee_pos_id"] = (int) $agent_detail->employee_pos_id;
                    $temp["agent_pos_id"] = (int) $agent_detail->agent_pos_id;

                    if (
                        empty($agent_detail->seller_type) &&
                        !empty($agent_detail->user_id) && empty($agent_detail->agent_id)
                    ) {
                        $temp["seller_type"] = "b2c";
                        $temp["seller_id"] = (int) $agent_detail->user_id;
                    } elseif (empty($agent_detail->seller_type)) {
                        $temp["seller_type"] = "b2c";
                    }

                    if (in_array($agent_detail->seller_type, [
                        'P',
                        'E'
                    ])) {
                        $temp["rm_code"] = $agent_detail->pos_key_account_manager ?? "";
                    } else {
                        $temp["rm_code"] = '';
                    }
                }
            }
        }
    }

    public static function userProposalData($report, &$temp)
    {
        if (!empty($report->user_proposal)) {
            if (!empty($report->user_proposal->first_name) || !empty($report->user_proposal->last_name)) {
                if (
                    isset($report->corporate_vehicles_quote_request->vehicle_owner_type) &&
                    $report->corporate_vehicles_quote_request->vehicle_owner_type == 'I'
                ) {
                    $temp['proposer_name'] =  $report->user_proposal->first_name . " " . $report->user_proposal->last_name;
                } else {
                    $temp['proposer_name'] =  $report->user_proposal->first_name;
                }
            } else {
                $temp['proposer_name'] =  $report->user_fname . " " . $report->user_lname;
            }

            if (!empty($report->user_proposal->created_at)) {
                $temp['proposal_date'] = $report->user_proposal->created_at;
            }

            self::ckycData($report, $temp);

            $temp['company_name'] = $report->user_proposal->ic_name ?? "";
            $temp['proposal_no'] = $report->user_proposal->proposal_no ?? "";
            $temp['gender_name'] = $report->user_proposal->gender_name ?? "";
            if (empty($temp['gender_name'])) {
                $temp['gender_name'] = ($report->user_proposal->gender == 'M') ? "Male" : ($report->user_proposal->gender == 'F' ? 'Female' : null);
            }
            $temp['proposer_gender'] = $report->user_proposal->gender_name ?? "";
            if (empty($temp['proposer_gender'])) {
                $temp['proposer_gender'] = ($report->user_proposal?->gender == 'M') ? "Male" : ($report->user_proposal->gender == 'F' ? 'Female' : null);
            }
            $temp['primary_insured_gender'] = $report->user_proposal->gender_name ?? "";
            if (empty($temp['primary_insured_gender'])) {
                $temp['primary_insured_gender'] = ($report->user_proposal->gender == 'M') ? "Male" : ($report->user_proposal->gender == 'F' ? 'Female' : NULL);
            }

            $temp['primary_insured_dob'] = report_date($report->user_proposal->dob);
            $temp['primary_insured_name'] = $report->user_proposal->first_name . " " . $report->user_proposal->last_name;
            $temp['primary_insured_mobile'] = $report->user_proposal->mobile_number ?? "";
            $temp['primary_insured_emailid'] = $report->user_proposal->email ?? "";
            $temp['proposer_dob'] = report_date($report->user_proposal->dob);
            $temp['od_premium'] = (float)($report->user_proposal->od_premium ?? "");
            $temp['tp_premium'] = (float)($report->user_proposal->tp_premium ?? "");
            $temp['premium_amount'] = (float)($report->user_proposal->final_payable_amount ? $report->user_proposal->final_payable_amount : $report->quote_log->final_premium_amount ?? "");
            $temp['base_premium'] = (float)($report->user_proposal->total_premium ?? "");
            $temp['tax_amount'] = (float)($report->user_proposal->service_tax_amount ?? "");
            $temp['ncb_discount'] = (float)($report->user_proposal->ncb_discount);
            $temp['discount_amount'] = (float)($report->user_proposal->total_discount ?? "");
            $temp['cpa_amount'] = (float)($report->user_proposal->cpa_premium ?? "");
            $temp['addon_premium'] = (float)($report->user_proposal->addon_premium ?? "");
            $temp['policy_start_date'] = report_date($report->user_proposal->policy_start_date, null, 'Y-m-d');
            $temp['policy_end_date'] =   report_date($report->user_proposal->policy_end_date, null, 'Y-m-d');
            $temp['policy_tenture_days'] = Carbon::parse($temp['policy_start_date'])->diffInDays($temp['policy_end_date']);
            $temp['pincode'] = $report->user_proposal->pincode ?? "";
            $temp['address_line_1'] = $report->user_proposal->address_line1 ?? "";
            $temp['address_line_2'] = $report->user_proposal->address_line2 ?? "";
            $temp['address_line_3'] = $report->user_proposal->address_line3 ?? "";
            $temp['state'] = $report->user_proposal->state ?? "";
            $temp['city'] = $report->user_proposal->city ?? "";
            $temp['engine_number'] = $report->user_proposal->engine_number ?? "";
            $temp['chassis_number'] = $report->user_proposal->chassis_number ?? "";
            $temp['policy_term'] = 1;
            $temp['previous_insurer'] = $report->user_proposal->insurance_company_name ?? "";
            $temp['previous_policy_number'] = $report->user_proposal->previous_policy_number ?? "";
            $temp['first_name'] = $report->user_proposal->first_name ?? "";
            $temp['last_name'] = $report->user_proposal->last_name ?? "";
            $temp['cpa_policy_start_date'] = report_date($report->user_proposal->cpa_policy_fm_dt, null, 'Y-m-d');
            $temp['cpa_policy_end_date'] =  report_date($report->user_proposal->cpa_policy_to_dt, null, 'Y-m-d');
            $temp['nominee_dob'] = report_date($report->user_proposal->nominee_dob);
            $temp['nominee_relationship'] = $report->user_proposal->nominee_relationship ?? "";
            $temp['nominee_age'] = $report->user_proposal->nominee_age ?? "";
            $temp['nominee_name'] = $report->user_proposal->nominee_name ?? "";
            $temp['tp_start_date'] =  report_date($report->user_proposal->tp_start_date, null, 'Y-m-d');
            $temp['tp_end_date'] = report_date($report->user_proposal->tp_end_date, null, 'Y-m-d');
            $temp['tp_policy_number'] = $report->user_proposal->tp_insurance_number ?? "";
            $temp['tp_prev_company'] = $report->user_proposal->tp_insurance_company ?? "";
            $temp['breakin_number'] = $report->user_proposal->breakin_status->breakin_number ?? "";
            $temp['inspection_date'] =  report_date($report?->user_proposal?->breakin_status?->inspection_date, null, 'Y-m-d');
            $temp['breakin_status'] = $report->user_proposal->breakin_status->breakin_status ?? "";
            $temp['previous_policy_expiry_date'] = report_date($report->user_proposal->prev_policy_expiry_date ?? '');
            $temp['previous_policy_start_date'] = report_date($report->user_proposal->prev_policy_start_date ?? '');
            $temp['ckyc_status'] = $report->user_proposal->is_ckyc_verified ?? "N";
            if (
                !empty($report->user_proposal->ckyc_meta_data) && $temp['ckyc_status'] === "Y" &&
                in_array($report->user_proposal->ic_id, ['13'])
            ) {
                $meta_data = json_decode(($report->user_proposal->ckyc_meta_data ?? "{}"), true);
                if (
                    is_array($meta_data) && !empty($meta_data) &&
                    isset($meta_data['ckyc_status']) && $meta_data['ckyc_status'] === "CKYCInProgress"
                ) {
                    $temp['ckyc_status'] = "N";
                }
            }
            if ($report->user_proposal->owner_type == "I") {
                $temp['owner_type'] = "Individual" ?? "";
            } elseif ($report->user_proposal->owner_type == "C") {
                $temp['owner_type'] = "Company" ?? "";
            }
            $temp['is_financed'] = $report->user_proposal->is_vehicle_finance ? "true" : "false" ?? "";
            $temp['hypothecation_to'] = $report->user_proposal->name_of_financer ?? "";
            $temp['sales_date'] = date('Y-m-d', strtotime($report->user_proposal->created_date)) ?? "";

            $temp['policy_no'] = !empty($report->user_proposal->policy_details) ? $report->user_proposal->policy_details->policy_number : "";
            $temp['sum_assured'] = $report->user_proposal->idv ?? "";

            if (!empty($report->user_proposal->policy_start_date) && !empty($report->user_proposal->policy_end_date)) {
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
                    } elseif (in_array($report->quote_log->master_policy->premium_type->premium_type, ['Short Term - 6 Months', 'Short Term - 6 Months - Breakin'])) {
                        $temp['policy_period'] = '6 Months';
                    }
                }
            }
        }
        $temp['business_type'] = !empty($report->user_proposal?->business_type) ? $report->user_proposal?->business_type : $report->corporate_vehicles_quote_request?->business_type ?? "";
        $temp['proposer_mobile'] =  empty($report?->user_proposal?->mobile_number) ? ($report->user_mobile ?? "") : ($report->user_proposal->mobile_number ?? "");
        $temp['proposer_emailid'] = !empty($report->user_email) ? $report->user_email : ($report->user_proposal->email ?? "");
    }

    public static function ckycData($report, &$temp)
    {
        if (!empty($report->user_proposal)) {
            $temp['ckyc_number'] = $report->user_proposal->ckyc_number ?? '';
            $temp['ckyc_reference_id'] = $report->user_proposal->ckyc_reference_id ?? '';
            $temp['ckyc_meta_data'] = $report->user_proposal->ckyc_meta_data ?? '';
            $temp['is_ckyc_verified'] = $report->user_proposal->is_ckyc_verified ?? '';
            $temp['is_ckyc_details_rejected'] = $report->user_proposal->is_ckyc_details_rejected ?? '';
            $temp['ckyc_type'] = $report->user_proposal->ckyc_type ?? '';
            $temp['ckyc_type_value'] = $report->user_proposal->ckyc_type_value ?? '';
            $temp['ckyc_extras'] = $report->user_proposal->ckyc_extras ?? '';
        }
    }

    public static function quoteData($report, &$temp)
    {
        $quoteDetails = $report->quote_log;
        if ($report->quote_log) {
            $temp['od_premium'] = $report->quote_log->od_premium ?? "";
            $temp['tp_premium'] = $report->quote_log->tp_premium ?? "";
            $temp['premium_amount'] = $report->quote_log->final_premium_amount ?? "";
            $temp['base_premium'] = $report->quote_log->premium_json['basicPremium'] ?? "";
            $temp['tax_amount'] = $report->quote_log->service_tax ?? "";
            $temp['ncb_discount'] = $report->quote_log->revised_ncb;
            $temp['discount_amount'] = $report->quote_log->total_discount ?? "";
            $temp['company_name'] = $report->quote_log->premium_json['companyName'] ?? "";
            $temp['cng_tp'] = $report->quote_log?->premium_json['cngLpgTp'] ?? "";
            $temp['cng_premium'] = $report->quote_log?->premium_json['motorLpgCngKitValue'] ?? "";
            $temp['electrical_accessories_premium'] = $report->quote_log?->premium_json['motorElectricAccessoriesValue'] ?? "";
            $temp['nonelectrical_accessories_premium'] = $report->quote_log?->premium_json['motorNonElectricAccessoriesValue'] ?? "";
            $temp['pa_to_owner_driver'] = $report->quote_log?->premium_json['compulsoryPaOwnDriver'] ?? $report->quote_log?->premium_json['multiYearCpa'] ?? "";
            $temp['cpa'] =  (isset($report?->user_proposal?->cpa_premium) && $report?->user_proposal?->cpa_premium  > 0) ? "Yes" : "No";
            $temp['cover_amount'] = $report->quote_log->idv ?? "";

            if (!empty($report->quote_log->premium_json)) {

                # Own Damage Section
                $temp['premium_breakup']['basicPremium'] = $report->quote_log->premium_json['basicPremium'] ?? ''; // Basic Own Damage(OD)
                $temp['premium_breakup']['motorElectricAccessoriesValue'] = $report->quote_log->premium_json['motorElectricAccessoriesValue'] ?? ''; // Electrical Accessories
                $temp['premium_breakup']['motorNonElectricAccessoriesValue'] = $report->quote_log->premium_json['motorNonElectricAccessoriesValue'] ?? ''; // Non-Electrical Accessories
                $temp['premium_breakup']['motorLpgCngKitValue'] = $report->quote_log->premium_json['motorLpgCngKitValue'] ?? ''; // LPG/CNG Kit
                $temp['premium_breakup']['totalOwnDamage'] = $report->quote_log->premium_json['totalOwnDamage'] ?? ''; // Total OD Premium (A)
                $temp['premium_breakup']['geogExtensionODPremium'] = $report->quote_log->premium_json['geogExtensionODPremium'] ?? ''; // Geographical Extension

                # Liability Section
                $temp['premium_breakup']['tppdPremiumAmount'] = $report->quote_log->premium_json['tppdPremiumAmount'] ?? ''; // Third Party Liability
                $temp['premium_breakup']['tppdDiscount'] = $report->quote_log->premium_json['tppdDiscount'] ?? ''; // TPPD Discounts
                $temp['premium_breakup']['coverUnnamedPassengerValue'] = $report->quote_log->premium_json['coverUnnamedPassengerValue'] ?? ''; // PA For Unnamed Passenger
                $temp['premium_breakup']['motorAdditionalPaidDriver'] = $report->quote_log->premium_json['motorAdditionalPaidDriver'] ?? ''; // Additional PA Cover To Paid Driver
                $temp['premium_breakup']['defaultPaidDriver'] = $report->quote_log->premium_json['defaultPaidDriver'] ?? ''; // Legal Liability To Paid Driver
                $temp['premium_breakup']['cngLpgTp'] = $report->quote_log->premium_json['cngLpgTp'] ?? ''; // LPG/CNG Kit TP
                $temp['premium_breakup']['compulsoryPaOwnDriver'] = $report->quote_log->premium_json['compulsoryPaOwnDriver'] ?? ''; // Compulsory PA Cover For Owner Driver
                $temp['premium_breakup']['geogExtensionTPPremium'] = $report->quote_log->premium_json['geogExtensionTPPremium'] ?? ''; // Geographical Extension

                # Own Damage Discount
                $temp['premium_breakup']['deductionOfNcb'] = $report->quote_log->premium_json['deductionOfNcb'] ?? ''; // Deduction of NCB
                $temp['premium_breakup']['voluntaryExcess'] = $report->quote_log->premium_json['voluntaryExcess'] ?? ''; // Voluntary Deductible
                $temp['premium_breakup']['antitheftDiscount'] = $report->quote_log->premium_json['antitheftDiscount'] ?? ''; // Anti-Theft
                $temp['premium_breakup']['icVehicleDiscount'] = $report->quote_log->premium_json['icVehicleDiscount'] ?? ''; // Other Discounts

                # Addons
                $temp['premium_breakup']['zeroDepreciation'] = $report->quote_log->premium_json['addOnsData']['additional']['zeroDepreciation'] ?? ''; // Zero Depreciation
                $temp['premium_breakup']['keyReplace'] = $report->quote_log->premium_json['addOnsData']['additional']['keyReplace'] ?? ''; // Key Replacements
                $temp['premium_breakup']['engineProtector'] = $report->quote_log->premium_json['addOnsData']['additional']['engineProtector'] ?? ''; // Engine Protector
                $temp['premium_breakup']['consumables'] = $report->quote_log->premium_json['addOnsData']['additional']['consumables'] ?? ''; // Consumables
                $temp['premium_breakup']['tyreSecure'] = $report->quote_log->premium_json['addOnsData']['additional']['tyreSecure'] ?? ''; // Type Secure 
                $temp['premium_breakup']['returnToInvoice'] = $report->quote_log->premium_json['addOnsData']['additional']['returnToInvoice'] ?? ''; // Return to Invoice
                $temp['premium_breakup']['addonPremium'] = $report->quote_log->premium_json['addonPremium'] ?? ''; //Total Addon Premium (D)

            }


            $quote_data = json_decode($quoteDetails['quote_data'] ?? "", true);
            $temp['idv_vehicle'] = $report?->user_proposal?->idv ?? $report?->quote_log?->idv ?? "";
            $temp['vehicle_make'] = $quoteDetails->premium_json['mmvDetail']['manfName'] ?? ($quote_data['manfacture_name'] ?? "");
            $temp['vehicle_model'] = $quoteDetails->premium_json['mmvDetail']['modelName'] ?? ($quote_data['model_name'] ?? "");
            $temp['vehicle_version'] = $quoteDetails->premium_json['mmvDetail']['versionName'] ?? ($quote_data['version_name'] ?? "");
            $temp['vehicle_cubic_capacity'] = $quoteDetails->premium_json['mmvDetail']['cubicCapacity'] ?? $quoteDetails->premium_json['mmvDetail']['cubiccapacity'] ?? "";
            $temp['vehicle_fuel_type'] = $quoteDetails->premium_json['mmvDetail']['fyntuneVersion']['fuelType'] ?? '';
            if (empty($temp['vehicle_fuel_type'])) {
                $temp['vehicle_fuel_type'] = $quoteDetails->premium_json['mmvDetail']['fuelType'] ?? '';
                if (empty($temp['vehicle_fuel_type'])) {
                    $temp['vehicle_fuel_type'] = $quote_data['fuel_type'] ?? '';
                    if (empty($temp['vehicle_fuel_type'])) {
                        $temp['vehicle_fuel_type'] = $quoteDetails->premium_json['fuelType'] ?? '';
                    }
                }
            }

            if ($temp['vehicle_fuel_type'] == "P") {
                $temp['vehicle_fuel_type'] = "PETROL";
            } elseif ($temp['vehicle_fuel_type'] == "D") {
                $temp['vehicle_fuel_type'] = "DIESEL";
            } elseif ($temp['vehicle_fuel_type'] == "Petrol C") {
                $temp['vehicle_fuel_type'] = "PETROL+CNG";
            }

            if (
                isset($report->quote_log->master_policy->premium_type->premium_type) &&
                !empty($report->quote_log?->master_policy?->premium_type?->premium_type)
            ) {
                if (in_array($report->quote_log?->master_policy?->premium_type?->premium_type, ['Third Party', 'Third Party Breakin'])) {
                    $temp['policy_type'] = "Third Party";
                } elseif (in_array($report->quote_log?->master_policy?->premium_type?->premium_type, ['Own Damage', 'Own Damage Breakin'])) {
                    $temp['policy_type'] = "Own Damage";
                } else {
                    $temp['policy_type'] = "Comprehensive";
                }
            }

            $temp['prev_policy_type'] = $report->corporate_vehicles_quote_request->previous_policy_type ?? "";
            $temp['zero_dep'] = in_array('zeroDepreciation', $report->quote_log['premium_json']['applicableAddons'] ?? []) ? 'Yes' : 'No';

            if (isset($report->quote_log['premium_json']['company_alias']) && $report->quote_log['premium_json']['company_alias'] == 'acko') {
                $temp['od_discount'] = '80';
            } elseif (isset($report->quote_log['premium_json']['company_alias']) && $report->quote_log['premium_json']['company_alias'] == 'icici_lombard') {
                $temp['od_discount'] = '80';
            } elseif (isset($report->quote_log['premium_json']['company_alias']) && $report->quote_log['premium_json']['company_alias'] == 'godigit') {
                $temp['od_discount'] = '75';
            } elseif (isset($report->quote_log['premium_json']['company_alias']) && $report->quote_log['premium_json']['company_alias'] == 'shriram') {
                $temp['od_discount'] = 'NA';
            }
        }

        $temp['vehicle_seating_capacity'] = $quoteDetails->premium_json['mmvDetail']['seatingCapacity'] ?? "";
        $temp['vehicle_gvw'] = $quoteDetails->premium_json['mmvDetail']['grossVehicleWeight'] ?? $quoteDetails->premium_json['mmvDetail']['gvw'] ?? "";

        $temp['vehicle_registration_date'] = !empty($quoteDetails['quote_details']['vehicle_register_date']) ? $quoteDetails['quote_details']['vehicle_register_date'] : ($report?->user_proposal?->vehicale_registration_date ?? "");
        $temp['vehicle_registration_date'] = report_date($temp['vehicle_registration_date']);
        $temp['previous_ncb'] = isset($report->user_proposal->previous_ncb) ? $report?->user_proposal?->previous_ncb : ($quoteDetails['quote_details']['previous_ncb']) ?? '';
        $temp['product_name'] = $report['quote_log']['premium_json']['productName'] ?? "";
        $temp['vehicle_manufacture_year'] = !empty($report->user_proposal->vehicle_manf_year) ? $report->user_proposal?->vehicle_manf_year : $report->corporate_vehicles_quote_request?->manufacture_year ?? "";
        $temp['ncb_claim'] = !empty($report->user_proposal->is_claim) ? $report?->user_proposal?->is_claim : $quoteDetails['quote_details']['is_claim'] ?? '';
        $temp['ncb_percentage'] = isset($report->user_proposal->applicable_ncb) ? $report?->user_proposal?->applicable_ncb : $quoteDetails['quote_details']['applicable_ncb'] ?? '';
        $temp['previous_policy_expiry_date'] = (isset($quoteDetails['quote_details']['previous_policy_expiry_date'])) ? $quoteDetails['quote_details']['previous_policy_expiry_date'] : "";
        $temp['previous_policy_expiry_date'] = report_date($temp['previous_policy_expiry_date']);
        $temp['previous_policy_start_date'] =  report_date($temp['previous_policy_start_date']);
        $temp['zero_dep_premium'] = $quoteDetails?->premium_json['addOnsData']['additional']['zeroDepreciation'] ?? "";
        $temp['ll_paid_driver_premium'] = $quoteDetails?->premium_json['defaultPaidDriver'] ?? $quoteDetails?->premium_json['llPaidDriverPremium'] ?? 0.0;
        $temp['basic_od_premium'] =  $report->quote_log->premium_json['basicPremium'] ?? "";
        $temp['basic_tp_premium'] =  $quoteDetails?->premium_json['tppdPremiumAmount'] ?? "";

        $temp['company_alias'] = $quoteDetails->premium_json['company_alias'] ?? $quoteDetails->ic_alias ?? "";

        if (
            !empty($report->corporate_vehicles_quote_request) &&
            empty($temp['policy_type'])
        ) {
            $temp['policy_type'] = $report->corporate_vehicles_quote_request->policy_type;
        }
    }

    public static function corporateVehicleData($report, &$temp)
    {
        $temp['vehicle_registration_date'] = !empty($report->corporate_vehicles_quote_request->vehicle_register_date) ? $report->corporate_vehicles_quote_request->vehicle_register_date : ($temp['vehicle_registration_date'] ?? '');

        if (!empty($report->corporate_vehicles_quote_request)) {
            $temp['rto_code'] = $report->corporate_vehicles_quote_request->rto_code ?? "";
            $temp['rto_city'] = $report->corporate_vehicles_quote_request->rto_city;
            $temp['vehicle_registration_number'] = !empty($report->user_proposal->vehicale_registration_number) ? $report->user_proposal->vehicale_registration_number : ($report->corporate_vehicles_quote_request->vehicle_registration_no ?? $temp['rto_code']);
            $temp["source"] = $temp['journey_type'] = $report->corporate_vehicles_quote_request->journey_type ?? "";
            $temp["lead_source"] = !empty($temp['lead_source']) ? ($temp['lead_source'] ?? '') : ($report->corporate_vehicles_quote_request->journey_type ?? '');

            if (
                config('constants.motorConstant.SMS_FOLDER') == 'hero'
            ) {
                $policy_type = empty($temp['policy_type']) ? $report->corporate_vehicles_quote_request->policy_type : $temp['policy_type'];
                $policy_type = strtolower(str_replace(' ', '_', $policy_type));
                if (!empty($policy_type)) {
                    $temp['policy_category_name'] = self::getPolicyCategoryName(
                        $report->product_sub_type_id,
                        $report->corporate_vehicles_quote_request->business_type,
                        $policy_type
                    );
                }
            }

            if (
                !empty($report->corporate_vehicles_quote_request) &&
                !empty($report->corporate_vehicles_quote_request->version_id)
            ) {
                $mmv = get_fyntune_mmv_details(
                    $report->product_sub_type_id,
                    $report->corporate_vehicles_quote_request->version_id
                );
                $temp['ft_version_id'] = $report->corporate_vehicles_quote_request->version_id;
                $temp['vehicle_body_type'] = $mmv['data']['version']['body_type'] ?? '';
                $temp['vehicle_no_of_wheels'] = $mmv['data']['version']['no_of_wheels'] ?? null;
            }
        }

        $temp['vehicle_age'] = floor(now()->diffInMonths(Carbon::parse($temp['vehicle_registration_date'])) / 12) . " Years" . " " . now()->diffInMonths(Carbon::parse($temp['vehicle_registration_date'])) % 12 . " Months";

        $temp['zd_previous_policy_addon'] = $report?->corporate_vehicles_quote_request?->business_type == 'newbusiness' ? "No" : ((int) $temp['zero_dep_premium'] > 0 ? "Yes" : "No");
    }

    public static function paymentDetails($report, &$temp)
    {
        if (!empty($report->finsall_payment_details)) {
            if ($report->finsall_payment_details->is_payment_finsall === "Y") {
                $temp['payment_mode'] = "Finsal";
            } else {
                $temp['payment_mode'] = "Customer Payment";
            }
        } else {
            $temp['payment_mode'] = "Customer Payment";
        }

        $paymentRequestResponse = PaymentRequestResponse::select(
            'status',
            'created_at',
            'user_product_journey_id',
            'order_id',
            'updated_at'
        )
            ->where('user_product_journey_id', $report->user_proposal->user_product_journey_id)
            ->get()
            ->toArray();

        $transaction_date = '';

        if (!empty($paymentRequestResponse)) {
            foreach ($paymentRequestResponse as $p_res) {
                if ($p_res['status'] == STAGE_NAMES['PAYMENT_SUCCESS']) {
                    $temp["payment_order_id"] = $p_res['order_id'] ?? "";
                    $temp["payment_status"] = $p_res['status'] ?? "";
                    $temp["payment_time"] = $p_res['updated_at'] ?? $p_res['created_at'] ?? "";
                    $transaction_date = date('Y-m-d H:i:s', strtotime($p_res['created_at']));
                    break;
                } else {
                    $transaction_date = date('Y-m-d H:i:s', strtotime($p_res['created_at']));
                    $temp["payment_order_id"] = $p_res['order_id'] ?? "";
                    $temp["payment_status"] = $p_res['status'] ?? "";
                    $temp["payment_time"] = $p_res['updated_at'] ?? $p_res['created_at'] ?? "";
                }
            }
        }

        $temp['transaction_date'] = $transaction_date;
    }

    public static function getPolicyCategoryName($product_sub_type_id, $business_type, $policy_type)
    {
        $category_name = null;
        if (
            !empty($product_sub_type_id) &&
            !empty($business_type) && !empty($policy_type)
        ) {
            $product = [
                1 => 'MOTOR PRIVATE CAR',
                2 => 'MOTOR SCOOTER',
            ];

            $policy_type_name = [
                'own_damage' => ' OD POLICY',
                'third_party' => ' TP POLICY'
            ];

            $product_name = $product[$product_sub_type_id] ?? 'MOTOR COMMERCIAL VEHICLE';
            $category_name = $product_name;

            if ($business_type == 'newbusiness') {
                $category_name .= ' LONG TERM POLICY';
            } else {
                $category_name .= $policy_type_name[$policy_type] ?? '';
            }
        }

        return $category_name;
    }

    public static function premiumData($report, &$temp)
    {
        $temp['od_net_premium'] = (float) $temp['od_premium'] + (float) $temp['addon_premium'];

        $temp['brokerage'] = $temp['commissionAmount'] = $temp['payInAmount'] =
            $temp['payInBrokerage'] = $temp['commission_difference'] = null;

        if (!empty($report->premium_details)) {
            //pass the premium details here
            $premiumDetails = $report->premium_details->details;

            $verifyDetails = PremiumDetailController::verifyPremiumDetails($report->premium_details->user_product_journey_id);
            if ($verifyDetails['status'] ?? false) {

                $totalA = PremiumDetailController::getTotalAPremium($premiumDetails);
                $totalB = PremiumDetailController::getTotalBPremium($premiumDetails);
                $totalC = PremiumDetailController::getTotalCPremium($premiumDetails);
                $totalD = PremiumDetailController::getTotalDPremium($premiumDetails);

                $temp['premium_breakup'] = [

                    # Own Damage Section
                    'basicPremium' => $premiumDetails['basic_od_premium'] ?? 0,
                    'motorElectricAccessoriesValue' => $premiumDetails['electric_accessories_value'] ?? 0,
                    'motorNonElectricAccessoriesValue' => $premiumDetails['non_electric_accessories_value'] ?? 0,
                    'motorLpgCngKitValue' => $premiumDetails['bifuel_od_premium'] ?? 0,
                    'geogExtensionODPremium' => $premiumDetails['geo_extension_odpremium'] ?? 0,
                    'loadingAmount' => $premiumDetails['loading_amount'] ?? 0,
                    'limitedOwnPremisesOdPremium' => $premiumDetails['limited_own_premises_od'] ?? 0,
                    'totalOwnDamage' => $totalA,

                    # Liability Section
                    'tppdPremiumAmount' => $premiumDetails['basic_tp_premium'] ?? 0,
                    'tppdDiscount' => $premiumDetails['tppd_discount'] ?? 0,
                    'coverUnnamedPassengerValue' => $premiumDetails['unnamed_passenger_pa_cover'] ?? 0,
                    'motorAdditionalPaidDriver' => $premiumDetails['pa_additional_driver'] ?? 0,
                    'defaultPaidDriver' => $premiumDetails['ll_paid_driver'] ?? 0,
                    'legalLiabilityToPaidCleaner' => $premiumDetails['ll_paid_cleaner'] ?? 0,
                    'legalLiabilityToPaidConductor' => $premiumDetails['ll_paid_conductor'] ?? 0,
                    'legalLiabilityToEmployee' => $premiumDetails['ll_paid_employee'] ?? 0,
                    'cngLpgTp' => $premiumDetails['bifuel_tp_premium'] ?? 0,
                    'compulsoryPaOwnDriver' => $premiumDetails['compulsory_pa_own_driver'] ?? 0,
                    'geogExtensionTPPremium' => $premiumDetails['geo_extension_tppremium'] ?? 0,
                    'limitedOwnPremisesTpPremium' => $premiumDetails['limited_own_premises_tp'] ?? 0,

                    # Own Damage Discount
                    'deductionOfNcb' => $premiumDetails['ncb_discount_premium'] ?? 0,
                    'voluntaryExcess' => $premiumDetails['voluntary_excess'] ?? 0,
                    'antitheftDiscount' => $premiumDetails['anti_theft'] ?? 0,
                    'icVehicleDiscount' => $premiumDetails['other_discount'] ?? 0,

                    # Addons
                    'zeroDepreciation' => $premiumDetails['zero_depreciation'] ?? 0,
                    'tyreSecure' => $premiumDetails['tyre_secure'] ?? 0,
                    'roadSideAssistance' => $premiumDetails['road_side_assistance'] ?? 0,
                    'returnToInvoice' => $premiumDetails['return_to_invoice'] ?? 0,
                    'keyReplace' => $premiumDetails['key_replacement'] ?? 0,
                    'engineProtector' => $premiumDetails['engine_protector'] ?? 0,
                    'imt23' => $premiumDetails['imt_23'] ?? 0,
                    'consumables' => $premiumDetails['consumable'] ?? 0,
                    'ncbProtection' => $premiumDetails['ncb_protection'] ?? 0,
                    'lossOfPersonalBelongings' => $premiumDetails['loss_of_personal_belongings'] ?? 0,
                    'emeCover' => $premiumDetails['eme_cover'] ?? 0,
                    'accidentShield' => $premiumDetails['accident_shield'] ?? 0,
                    'conveyanceBenefit' => $premiumDetails['conveyance_benefit'] ?? 0,
                    'passengerAssist' => $premiumDetails['passenger_assist_cover'] ?? 0,
                    'windShield' => $premiumDetails['wind_shield'] ?? 0,
                    'motorProtection' => $premiumDetails['motor_protection'] ?? 0,
                    'batteryProtect' => $premiumDetails['battery_protect'] ?? 0,
                    'additionalTowing' => $premiumDetails['additional_towing'] ?? 0,

                    'addonPremium' => $totalD,
                ];

                $temp['basic_od_premium'] = $premiumDetails['basic_od_premium'] ?? 0;
                $temp['od_premium'] = $totalA - $totalC;
                $temp['nonelectrical_accessories_premium'] = $premiumDetails['non_electric_accessories_value'] ?? 0;
                $temp['electrical_accessories_premium'] = $premiumDetails['electric_accessories_value'] ?? 0;
                $temp['cng_premium'] = $premiumDetails['bifuel_od_premium'] ?? 0;
                $temp['od_net_premium'] = $totalA - $totalC + $totalD;


                $temp['basic_tp_premium'] = $premiumDetails['basic_tp_premium'] ?? 0;
                $temp['tp_premium'] = $totalB;
                $temp['ll_paid_driver_premium'] = $premiumDetails['ll_paid_driver'] ?? 0;
                $temp['cng_tp'] = $premiumDetails['bifuel_tp_premium'];
                $temp['cpa_amount'] = $temp['pa_to_owner_driver'] = $premiumDetails['compulsory_pa_own_driver'] ?? 0;
                $temp['cpa'] = round($premiumDetails['compulsory_pa_own_driver'] ?? 0) > 0 ? "Yes" : "No";

                $temp['ncb_discount'] = $premiumDetails['ncb_discount_premium'] ?? 0;
                $temp['discount_amount'] = $totalC;

                $temp['zero_dep_premium'] = $premiumDetails['zero_depreciation'] ?? 0;
                $temp['addon_premium'] = $totalD;

                $temp['base_premium'] = $premiumDetails['net_premium'] ?? 0;
                $temp['tax_amount'] = $premiumDetails['service_tax_amount'] ?? 0;
                $temp['premium_amount'] = $premiumDetails['final_payable_amount'] ?? 0;

                $temp['premium_fetched_from'] = 'premium_details';
            }

            self::commissionData($report, $temp);
        }
    }

    public static function productData($report, &$temp)
    {
        $temp['section'] = \Illuminate\Support\Str::upper(isset($report['sub_product']['product_sub_type_code']) ? $report['sub_product']['product_sub_type_code'] : "") ?? "";
        $temp['product_type'] = \Illuminate\Support\Str::lower($report->corporate_vehicles_quote_request->product_sub_type->parent->product_sub_type_code ?? null) ?? "";  //corporate_table product_id
        $temp['sub_product_type'] = \Illuminate\Support\Str::lower($report['sub_product']['product_sub_type_code'] ?? '') ?? "";
    }

    public static function addonsData($report, &$temp)
    {
        if (!empty($report->addons[0])) {
            if (isset($report->addons[0]->compulsory_personal_accident[0]['name']) && !is_null($report->addons[0]->compulsory_personal_accident[0]['name'])) {
                $temp['cpa_policy_start_date'] = report_date($temp['policy_start_date'], null, 'Y-m-d');
                $temp['cpa_policy_end_date']  =  !empty($temp['policy_start_date']) ? Carbon::parse($temp['policy_start_date'])->addYear(1)->subDay(1)->format('Y-m-d') : null;
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
                foreach ($report->addons[0]->additional_covers as $key => $value) {
                    $temp['selected_additional_covers'] .= (isset($value['name']) ? $value['name'] . ', ' : '');
                }
            }

            if (is_array($report->addons[0]->accessories)) {
                foreach ($report->addons[0]->accessories as $key => $value) {
                    $temp['selected_accessories'] .= (isset($value['name']) ? $value['name'] . ', ' : '');

                    if (!empty($value['name']) && $value['name'] == "Non-Electrical Accessories") {
                        $temp['non_electrical_cover_amount'] = strval(!empty($value['sumInsured']) ? $value['sumInsured'] : 0);
                    }
                    if (!empty($value['name']) && $value['name'] == "Electrical Accessories") {
                        $temp['electrical_cover_amount'] = strval(!empty($value['sumInsured']) ? $value['sumInsured'] : 0);
                    }
                    if (!empty($value['name']) && $value['name'] == "External Bi-Fuel Kit CNG/LPG") {
                        $temp['cng_cover_amount'] = strval(!empty($value['sumInsured']) ? $value['sumInsured'] : 0);
                    }
                }
            }

            if (is_array($report->addons[0]->accessories) && !empty($report->addons[0]->accessories)) {
                foreach ($report->addons[0]->accessories as $key => $value) {
                    if (isset($value['name']) && !empty($value['name'])) {
                        if ($value['name'] == "Non-Electrical Accessories") {
                            $temp['idv_non_electrical'] = strval(!empty($value['sumInsured']) ? $value['sumInsured'] : 0);
                        }
                        if ($value['name'] == "Electrical Accessories") {
                            $temp['idv_electrical'] = strval(!empty($value['sumInsured']) ? $value['sumInsured'] : 0);
                        }
                        if ($value['name'] == "External Bi-Fuel Kit CNG/LPG") {
                            $temp['idv_cng'] = strval(!empty($value['sumInsured']) ? $value['sumInsured'] : 0);
                        }
                    }
                }
            }

            $temp['idv_total'] = ((int) $temp['idv_non_electrical'] + (int) $temp['idv_electrical'] + (int) $temp['idv_cng'] + (int) $temp['idv_vehicle']);

            if (is_array($report->addons[0]->discounts)) {
                foreach ($report->addons[0]->discounts as $key => $value) {
                    $temp['selected_discounts'] .= (isset($value['name']) ? $value['name'] . ', ' : '');
                }
            }
        }
    }

    public static function commissionData($report, &$temp)
    {
        $commissionDetails = $report->premium_details->commission_details;
        $payInDetails = $report->premium_details->payin_details;

        if (!empty($commissionDetails['brokerage'])) {

            $premiumList = [
                'odPremium' => $temp['od_premium'],
                'totalOdPayable' => $temp['od_net_premium'],
                'tpPremium' => $temp['tp_premium'],
                'netPremium' => $temp['base_premium'],
                'netpremium' => $temp['base_premium'],
                'grosspremium' => $temp['premium_amount'],
                'basePremium' => $temp['base_premium'],
                'addOnpremium' => $temp['addon_premium'],
                'totalTax' => $temp['tax_amount'],
                'taxAmount' => $temp['tax_amount'],
                'totalAmount' => $temp['premium_amount'],
            ];

            $commission = \App\Http\Controllers\BrokerCommissionController::calculateTotalCommission(
                $premiumList,
                $commissionDetails
            );

            $temp['commissionAmount'] = $commission;
            $temp['brokerage'] = [
                'commissionAmount' => $commission,
                'qualifiedRule' => $commissionDetails['qualifiedRule'] ?? null,
                'config' => $commissionDetails['fullConfig'] ?? null
            ];
        }

        if (!empty($payInDetails['brokerage'])) {

            $premiumList = [
                'odPremium' => $temp['od_premium'],
                'totalOdPayable' => $temp['od_net_premium'],
                'tpPremium' => $temp['tp_premium'],
                'netPremium' => $temp['base_premium'],
                'netpremium' => $temp['base_premium'],
                'grosspremium' => $temp['premium_amount'],
                'basePremium' => $temp['base_premium'],
                'addOnpremium' => $temp['addon_premium'],
                'totalTax' => $temp['tax_amount'],
                'taxAmount' => $temp['tax_amount'],
                'totalAmount' => $temp['premium_amount'],
            ];

            $commission = \App\Http\Controllers\BrokerCommissionController::calculateTotalCommission(
                $premiumList,
                $payInDetails
            );

            $temp['payInAmount'] = $commission;

            $temp['payInBrokerage'] = [
                'payInAmount' => $commission,
                'qualifiedRule' => $commissionDetails['qualifiedRule'] ?? null,
                'config' => $commissionDetails['fullConfig'] ?? null
            ];
        }

        if (is_numeric($temp['payInAmount']) && is_numeric($temp['commissionAmount'])) {
            $temp['commission_difference'] = $temp['payInAmount'] - $temp['commissionAmount'];
        }
    }

    public static function getCurrentUrl($data)
    {
        $data =  $data->toArray();

        $urlKey = in_array($data['stage'], [
            STAGE_NAMES['QUOTE'],
            STAGE_NAMES['LEAD_GENERATION']
        ]) ? 'quote_url' : 'proposal_url';

        return $data[$urlKey];
    }

    public static function sortArray($data, $keyName)
    {
        usort($data, function($a, $b) use ($keyName) {
            return strcmp($a[$keyName], $b[$keyName]);
        });

        return $data;
    }

    public static function pagination($items, $perPage = 100, $page)
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = is_array($items) ? $items : $items->get()->toArray();
        $currentpage = $page;
        $offset = ($currentpage * $perPage) - $perPage;
        $itemstoshow = array_slice($items, $offset, $perPage, true);
        $return_pagination = new LengthAwarePaginator(
            $itemstoshow,
            count($items),
            $perPage,
            Paginator::resolveCurrentPage()
        );
        return $return_pagination->withPath(Paginator::resolveCurrentPath());
    }

    public static function getPolicyPdf($report, &$temp)
    {
        $temp['policy_doc_path'] = $report?->user_proposal?->policy_details?->pdf_url ?? '';
        if (
            !empty($temp['policy_doc_path']) &&
            config('constants.brokerConstant.NEW_PDF_URL_APPROACH', 'N') == 'Y'
        ) {
            $temp['policy_doc_path'] = route('policy-download',[
                'enquiryId' => $report->journey_id,
            ]);
        }
    }
}
