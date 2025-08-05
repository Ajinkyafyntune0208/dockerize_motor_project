<?php

use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;

function getQuoteOfflinePremiumCalculation($enquiryId, $requestData, $productData, $request)
{

    $no_claim_bonus = $request['no_claim_bonus'] ?? '';
    // $ncb_discountNextSlab = $request['ncb_discountNextSlab'] ?? '';
    $request['addonCover'] = [1, 2];

    $selectedAddons = SelectedAddons::where('user_product_journey_id', $enquiryId)
        ->select('*')
        ->get();

    $addionalPaidDriver = [];
    $ownerDriver = [];
    $unnamedPassenger = [];
    $eleAccessories = [];
    $nonEleAccessories = [];
    $lpgAndCng = [];
    $antiTheftDisc = [];
    $compulsorypaOwnDriver = [];

    $unnamedPassenger['addonSI'] = 0;
    $eleAccessories['addonSI'] = 0;
    $nonEleAccessories['addonSI'] = 0;
    $lpgAndCng['addonSI'] = 0;
    $compulsorypaOwnDriver['status'] = false;

    if (!$selectedAddons->isEmpty()) {


        if (!empty($selectedAddons[0]->accessories)) {

            foreach ($selectedAddons[0]->accessories as $addonVal) {

                if (in_array('Electrical Accessories', $addonVal)) {
                    $eleAccessories['status'] = 'true';
                    $eleAccessories['name'] = $addonVal['name'];
                    $eleAccessories['addonSI'] = $addonVal['sumInsured'];
                }

                if (in_array('Non-Electrical Accessories', $addonVal)) {
                    $nonEleAccessories['status'] = 'true';
                    $nonEleAccessories['name'] = $addonVal['name'];
                    $nonEleAccessories['addonSI'] = $addonVal['sumInsured'];
                }

                if (in_array('External Bi-Fuel Kit CNG/LPG', $addonVal)) {
                    $lpgAndCng['status'] = 'true';
                    $lpgAndCng['name'] = $addonVal['name'];
                    $lpgAndCng['addonSI'] = $addonVal['sumInsured'];
                }
            }
        }




        if (!empty($selectedAddons[0]->compulsory_personal_accident)) {

            foreach ($selectedAddons[0]->compulsory_personal_accident as $addon) {
                if (in_array('Compulsory Personal Accident', $addon)) {
                    $compulsorypaOwnDriver['status'] = true;
                }
            }
        }


        if (!empty($selectedAddons[0]->additional_covers)) {

            foreach ($selectedAddons[0]->additional_covers as $addon) {
                if (in_array('PA cover for additional paid driver', $addon)) {
                    $addionalPaidDriver['status'] = 'true';
                    $addionalPaidDriver['name'] = 'Silver PCV';
                    $addionalPaidDriver['addonSI'] = $addon['sumInsured'];
                }

                if (in_array('Owner Driver PA Cover', $addon)) {
                    $ownerDriver['status'] = 'true';
                    $ownerDriver['name'] = 'RSA1';
                    $ownerDriver['addonSI'] = $addon['sumInsured'];
                }

                if (in_array('Unnamed Passenger PA Cover', $addon)) {
                    $unnamedPassenger['status'] = 'true';
                    $unnamedPassenger['name'] = 'RSA1';
                    $unnamedPassenger['addonSI'] = $addon['sumInsured'];
                }
            }
        }


        if (!empty($selectedAddons[0]->discounts)) {
            foreach ($selectedAddons[0]->discounts as $discount) {
                if (in_array('anti-theft device', $discount)) {
                    $antiTheftDisc['status'] = 'true';
                }
            }
        }
    }


    $user_product_journey_id = $requestData->user_product_journey_id ?? 0;

    $get_response_data = DB::table('user_product_journey AS uj')
        ->join('corporate_vehicles_quotes_request AS qr', 'uj.user_product_journey_id', '=', 'qr.user_product_journey_id')
        ->join('motor_model_version AS mmdv', 'mmdv.version_id', '=', 'qr.version_id')
        ->join('motor_model AS mmd', 'mmd.model_id', '=', 'mmdv.model_id')
        ->join('motor_manufacturer AS mmf', 'mmf.manf_id', '=', 'mmd.manf_id')
        ->where('uj.user_product_journey_id', $user_product_journey_id)
        ->select(
            'uj.user_product_journey_id',
            'uj.user_fname AS user_fname',
            'uj.user_id',
            'uj.user_lname',
            'uj.user_email AS user_email',
            'uj.product_sub_type_id',
            'uj.user_mobile AS user_mobile',
            'qr.quotes_request_id',
            'qr.version_id',
            'qr.policy_type',
            'qr.corp_id',
            'qr.previous_policy_expiry_date',
            'qr.insurance_company_id',
            'qr.fuel_type',
            'qr.manufacture_year',
            'qr.rto_code',
            'qr.ex_showroom_price_idv',
            'qr.edit_idv',
            'qr.edit_od_discount',
            'qr.electrical_acessories_value',
            'qr.nonelectrical_acessories_value',
            'qr.bifuel_kit_value',
            'qr.is_claim',
            'qr.applicable_ncb',
            'qr.voluntary_excess_value',
            'qr.anti_theft_device',
            'qr.unnamed_person_cover_si',
            'qr.pa_cover_owner_driver',
            'qr.aa_membership',
            'qr.engine_no',
            'qr.chassis_no',
            'qr.is_od_discount_applicable',
            'mmdv.segment_id',
            'mmdv.version_name',
            'mmdv.cubic_capacity',
            'mmdv.Grosss_Vehicle_Weight',
            'mmdv.carrying_capicity',
            'mmdv.showroom_price',
            'mmdv.fuel_type',
            'mmdv.seating_capacity',
            'mmd.model_id',
            'mmd.model_name',
            'mmf.manf_id',
            'mmf.manf_name',
            'qr.bifuel_kit_value'
        )
        ->first();

    $current_date = date('d-m-Y');

    $vehicle_in_days = (strtotime($current_date) - strtotime(date('d-m-Y', strtotime($get_response_data->manufacture_year)))) / (60 * 60 * 24);

    $manf_id = $get_response_data->manf_id;
    $model_id = $get_response_data->model_id;
    $version_id = $get_response_data->version_id;
    $user_edit_showroom_price = $get_response_data->edit_idv;
    $edit_od_discount = $get_response_data->edit_od_discount;
    $seating_capacity = $get_response_data->seating_capacity;
    $product_sub_type_id = $get_response_data->product_sub_type_id == '' ? $requestData->product_sub_type_id : $get_response_data->product_sub_type_id;
    $unnamed_person_cover_si = $get_response_data->unnamed_person_cover_si;
    $cover_unnamed_passenger_value =  $unnamedPassenger['addonSI'];

    if ($unnamed_person_cover_si != $cover_unnamed_passenger_value) {
        DB::table('corporate_vehicles_quotes_request')
            ->where('user_product_journey_id', $user_product_journey_id)
            ->update(['unnamed_person_cover_si' => $cover_unnamed_passenger_value]);
    }

    if ($requestData->voluntary_excess_value != "" && $requestData->voluntary_excess_value != 0) {
        DB::table('corporate_vehicles_quotes_request')
            ->where('user_product_journey_id', $user_product_journey_id)
            ->update(['voluntary_excess_value' => $requestData->voluntary_excess_value]);
    }

    if ($requestData->pa_cover_owner_driver && ($requestData->pa_cover_owner_driver == '1' || $requestData->pa_cover_owner_driver == '0')) {
        DB::table('corporate_vehicles_quotes_request')
            ->where('user_product_journey_id', $user_product_journey_id)
            ->update(['pa_cover_owner_driver' => $requestData->pa_cover_owner_driver == '1' ? 'Y' : 'N']);
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $applicable_year = 1;

    if ($get_response_data->policy_type == 'newbusiness') {
        $policy_type = $get_response_data->policy_type = 'N';
        $cover_type = '1Y3T';
        $applicable_year = 3;
    } else {
        $policy_type = $get_response_data->policy_type = 'R';

        if ($product_sub_type_id == 1 || $product_sub_type_id == 2) {
            if ($vehicle_in_days > 1077) {
                $cover_type = '1YC';
            } else {
                $cover_type = '1Y_STAND_OD';
            }
        } else {
            $cover_type = '1YC';
        }
    }

    $showroom_price = $get_response_data->showroom_price;
    $ex_showroom_price_idv = $get_response_data->ex_showroom_price_idv;
    $vehicle_registration_no = $get_response_data->rto_code;
    $vehicle_use_id = $request['vehicleUseId'];
    $car_rto_no = $requestData->rto_code;
    $corporate_client_id = $get_response_data->corp_id ?? '';
    $policy_start_date = $policy_start_date ?? $current_date;

    $motor_manf_date = '01-' . $requestData->manufacture_year;

    // $car_age = 0;
    // $car_age = ((date('Y', strtotime($current_date)) - date('Y', strtotime($motor_manf_date))) * 12) + (date('m', strtotime($current_date)) - date('m', strtotime($motor_manf_date)));
    // $car_age = $car_age < 0 ? 0 : $car_age;

    $car_age = 0;
    $date1 = new DateTime($requestData->vehicle_register_date);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $car_age = (($interval->y * 12) + $interval->m) + 1;
    $car_age = $car_age < 0 ? 0 : $car_age;

    $motor_depreciation = DB::table('motor_idv')
        ->whereRaw($car_age . " BETWEEN age_min AND age_max")
        ->first();

    $vehicle_idv = $premium_type == 'third_party' ? 0 : $showroom_price * (1 - ($motor_depreciation->depreciation_rate / 100));

    $idvRange = DB::table('ic_idv_range')
                ->where('ic_id', $productData->company_id)
                ->first();

    $min_idv = 0;
    $max_idv = 0;

    if ($productData->company_alias === 'shriram') {
        $min_idv = $vehicle_idv - ($vehicle_idv * $idvRange->min_idv);
        $max_idv = $vehicle_idv + ($vehicle_idv * $idvRange->max_idv);
    } elseif($productData->company_alias === 'icici_lombard') {
        $min_idv = $vehicle_idv - ($vehicle_idv * $idvRange->min_idv);
        $max_idv = $vehicle_idv + ($vehicle_idv * $idvRange->max_idv);
    }


    if ($user_edit_showroom_price != '' && $user_edit_showroom_price != 0 && $premium_type != 'third_party') {
        $vehicle_idv = $user_edit_showroom_price;
    }

    if ($premium_type != 'third_party') {
        if ($vehicle_idv < $min_idv) {
            $vehicle_idv = $min_idv;
        } elseif ($vehicle_idv > $max_idv) {
            $vehicle_idv = $max_idv;
        }
    }

    $claim = $get_response_data->is_claim ?? 'N';


    $ncb_discount = $get_response_data->applicable_ncb;
    $vehicle_in_90_days = 0;

    if ($claim == 'Yes' || $premium_type == 'third_party') {
        $ncb_discount = 0;
    } elseif ($policy_type == "N") {
        $vehicle_registration_no = $policy_type . "_" . $get_response_data->rto_code;
        $policyStartDate = date('d-m-Y');
        $ncb_discount = 0;
    } else if ($policy_type == "R") {
        $policyStartDate = $get_response_data->previous_policy_expiry_date;
        $ncb_discount_next_slab = DB::table('motor_ncb AS a')
            ->join('motor_ncb AS b', 'b.ncb_level', '=', DB::raw('a.ncb_level+1'));

        if ($ncb_discount != "") {
            $ncb_discount_next_slab = $ncb_discount_next_slab->where('a.discount_rate', $ncb_discount);
        }

        if ($no_claim_bonus != "") {
            $ncb_discount_next_slab = $ncb_discount_next_slab->where('a.ncb_level', $no_claim_bonus);
        }

        $ncb_discount_next_slab = $ncb_discount_next_slab->select('a.ncb_level', 'b.discount_rate')
            ->first();
        // $ncb_discount = count($ncb_discountNextSlab)==0?0:$ncb_discountNextSlab[0]['discount_rate'];
        $ncb_discount = $get_response_data->applicable_ncb;

        if (isset($policyStartDate) && $policyStartDate != 0) {
            $vehicle_in_90_days = (strtotime($current_date) - strtotime($policyStartDate)) / (60 * 60 * 24);
            if ($vehicle_in_90_days > 90) {
                $ncb_discount = 0;
            }
        }
    }

    $rto_code = $get_response_data->rto_code;
    $rto_zone_details = getRto((isset($request['stateId']) ? $request['stateId'] : ''), (isset($request['stateCode']) ? $request['stateCode'] : ''), (isset($get_response_data->rto_code) ? $get_response_data->rto_code : $car_rto_no), (isset($request['rtoId']) ? $request['rtoId'] : ''));
    $zone_id = count($rto_zone_details) == 0 ? 0 : $rto_zone_details[0]->zone_id;

    $vehicle_cc = $get_response_data->cubic_capacity;
    $fuel_type = $get_response_data->fuel_type;
    $bifuel_kit_value = $lpgAndCng['addonSI'];
    if (!is_numeric($showroom_price)) {
        $showroom_price = 0;
    }

    $motor_additional_paid_driver = $request['motorAdditionalPaidDriver'] ?? '0';
    $rewrrrrrr = [
        'zone_id' => $zone_id,
        'vehicle_cc' => $vehicle_cc,
        'no_of_passenger' => $seating_capacity,
        'vehicle_age' => $car_age
    ];

    if ($product_sub_type_id == 1 || $product_sub_type_id == 2) {
        $get_res_premium = getTariff($zone_id, $vehicle_cc, $car_age, $product_sub_type_id);
    } else if ($product_sub_type_id == 6 || $product_sub_type_id == 7) {
        $get_res_premium = getTariffPccv($rewrrrrrr);
    }



    // $nonelectric_user_value = (isset($requestData->nonelectrical_acessories_value) && $requestData->nonelectrical_acessories_value != '' || $requestData->nonelectrical_acessories_value != '0') ? $requestData->nonelectrical_acessories_value : 0;
    $nonelectric_user_value = (isset($nonEleAccessories['addonSI']) && $nonEleAccessories['addonSI'] != '' || $nonEleAccessories['addonSI'] != '0') ? $nonEleAccessories['addonSI'] : 0;

    $nonelectric_premium_value = 0;
    $basic_premium_comm = 0;

    if ($get_res_premium != '') {
        $basic_premium_comm = $vehicle_idv * ($get_res_premium->rate / 100);
        $nonelectric_premium_value = $nonelectric_user_value * ($get_res_premium->rate / 100);
    } else {
        $basic_premium_comm = 0;
    }

    $lpgcng_det = DB::table('motor_lpgcng')->get();
    $lpgcng_det = $lpgcng_det[0];

    // $electric_premium_value = (isset($requestData->electrical_acessories_value) && $requestData->electrical_acessories_value != '' || $requestData->electrical_acessories_value != '0')  ? ($requestData->electrical_acessories_value * ($lpgcng_det->electrical / 100)) : 0;
    $electric_premium_value = (isset($eleAccessories['addonSI']) && $eleAccessories['addonSI'] != '' || $eleAccessories['addonSI'] != '0')  ? ($eleAccessories['addonSI'] * ($lpgcng_det->electrical / 100)) : 0;

    $applicable_year = 1;

    $tppd_array = [
        'vehicle_cc' => $vehicle_cc,
        'product_sub_type_id' => $product_sub_type_id,
        'policyStartDate' => date('Y-m-d', strtotime($policy_start_date)),
        'applicable_year' => $applicable_year
    ];

    $tppd = getCommonTppd($tppd_array);

    if ($tppd && ($product_sub_type_id == 6 || $product_sub_type_id == 7)) {
        $tppd->premium_amount = $tppd->fixed_amount + ($tppd->additional_amount * ($seating_capacity - 1));
    }
    if ($policy_type == "R" && $cover_type == '1Y_STAND_OD') {
        $tppd->premium_amount = 0;
    }

    $user_id = $get_response_data->user_id;

    $mmv_detail = [
        'version_id' => $get_response_data->version_id,
        'version_name' => $get_response_data->version_name,
        'cubic_capacity' => $get_response_data->cubic_capacity,
        'carrying_capicity' => $get_response_data->carrying_capicity,
        'Gross_Vehicle_Weight' => $get_response_data->Grosss_Vehicle_Weight,
        'showroom_price' => $get_response_data->showroom_price,
        'fuel_type' => $get_response_data->fuel_type,
        'seating_capacity' => $get_response_data->seating_capacity,
        'model_id' => $get_response_data->model_id,
        'model_name' => $get_response_data->model_name,
        'manf_id' => $get_response_data->manf_id,
        'manf_name' => $get_response_data->manf_name,
    ];

    $get_response1['vehicle_in_90_days'] = $vehicle_in_90_days;
    $get_response1['get_policy_expiry_date'] = $policy_start_date;

    $masterPolicyValues = array(
        'ic_id' => $productData->company_id,
        'policy_id' => $productData->policy_id,
        'policy_no' => "",
        'corp_client_id' => $productData->corp_client_id,
        'product_sub_type_id' => $productData->product_sub_type_id
    );

    $master_policy_id = getMasterPolicy($masterPolicyValues['policy_id'], $masterPolicyValues['policy_no'], $masterPolicyValues['corp_client_id'], $masterPolicyValues['ic_id'], $product_sub_type_id);

    $get_response = [];
    $count_save = 0;
    $get_response1['policyStartDate'] = $policy_start_date;
    $get_all_online_service_response = [];
    $acko_response_sub_service = [];
    $max_addons_selection = 0;

    foreach ($master_policy_id as $master_policy_id_key => $master_policy_id_value) {
        $basic_premium = $basic_premium_comm;
        $company_name = $master_policy_id_value->company_name;
        $company_logo = $master_policy_id_value->logo;
        $insurance_company_id = $master_policy_id_value->insurance_company_id;
        $product_name = $master_policy_id_value->product_sub_type_name;

        $chk_brek_in_exp_date = implode('', explode('-', date('Y-m-d', strtotime($get_response_data->previous_policy_expiry_date))));
        $chk_brek_in_curnt_date = implode('', explode('-', date('Y-m-d')));
        $chck_is_breakin = '';
        if (($policy_type == "R") && $chk_brek_in_exp_date < $chk_brek_in_curnt_date) {
            $chck_is_breakin = 'Yes';
        }

        if ($master_policy_id_value->insurance_company_id == '43' && !((isset($requestData->pa_cover_owner_driver)) && $requestData->pa_cover_owner_driver != '0') && ($policy_type == "R" || $ncb_discount == 0)) {
            continue;
        }
        if ($master_policy_id_value->is_premium_online == "Yes" && $master_policy_id_value->insurance_company_id == '39' && $chck_is_breakin == 'Yes') {
            continue;
        }
        if ($master_policy_id_value->is_premium_online == "Yes" && $master_policy_id_value->insurance_company_id == '39' && $chck_is_breakin != 'Yes') {
            // skip
        } else {
            $segmentvalue = array(
                'version_id' => $version_id,
                'master_policy_id' => $master_policy_id_value->policy_id
            );
            $segment_id = 0;
            $segment_detail = getsegmentforpremium($segmentvalue);
            if (!$segment_detail->isEmpty()) {
                $segment_id = $segment_detail[0]->policywise_segment_id;
            }

            $rtoClusterValues = array(
                'car_rto_no' => $car_rto_no,
                'master_policy_id' => $master_policy_id_value->policy_id
            );

            $cluster_id = 0;

            $rto_cluster_id = getrtoclusterforpremium($rtoClusterValues);

            //decline rto start
            $get_response1['rto_decline'] = '0';
            $get_response1['rto_decline_number'] = '';
            $get_response1['mmv_decline'] = '0';
            $get_response1['mmv_decline_name'] = '';
            $rto_decline_values = array(
                'rto_number' => $car_rto_no,
                'master_policy_id' => $master_policy_id_value->policy_id,
                'user_id' => $user_id
            );

            $check_rto_decline = checkDeclineRTO($rto_decline_values);

            if ($check_rto_decline && count($check_rto_decline) && $check_rto_decline[0]['status'] == 'Decline') {
                $get_response1['rto_decline'] = '1';
                $get_response1['rto_decline_number'] = $car_rto_no;
            }

            $mmv_decline_values = array(
                'manf_id' => $manf_id,
                'model_id' => $model_id,
                'version_id' => $version_id,
                'master_policy_id' => $master_policy_id_value->policy_id,
                'user_id' => $user_id
            );

            $check_mmv_decline = checkDeclineMMV($mmv_decline_values);

            if (!$rto_cluster_id->isEmpty()) {
                $cluster_id = $rto_cluster_id[0]->cluster_id;
            }

            $vehicleDiscountValues = array(
                'master_policy_id' => $master_policy_id_value->policy_id,
                'product_sub_type_id' => $product_sub_type_id,
                'segment_id' => $segment_id,
                'rto_cluster_id' => $cluster_id,
                'car_age' => $car_age
            );

            //return $vehicleDiscountValues;
            $vehicle_discount_detail = getVehicleDiscountforpremium($vehicleDiscountValues);

            $get_changed_discount_quote_policy_id = 0;
            $get_changed_discount_quoteid = 0;
            $changesDiscountValue = 0;

            $cng_lpg_tp = (isset($bifuel_kit_value) && $bifuel_kit_value != '' && $bifuel_kit_value > 0) ? $lpgcng_det->cpgcng_tp : 0;

            $motor_lpg_cng_kit_value = 0;

            if ($fuel_type == "BIFUEL" || $fuel_type == "CNG" || $fuel_type == "LPG") {
                $motor_lpg_cng_kit_value = ($basic_premium_comm + $nonelectric_premium_value) * ($lpgcng_det->lpgcng_inbuild / 100);
                $cng_lpg_tp = $lpgcng_det->cpgcng_tp;
            } else {
                $motor_lpg_cng_kit_value = (isset($bifuel_kit_value) && $bifuel_kit_value != '' && $bifuel_kit_value > 0)
                    ? ($bifuel_kit_value * ($lpgcng_det->lpgcng_kit / 100))
                    : 0;
            }

            if ($premium_type == 'third_party') {
                $motor_lpg_cng_kit_value = 0;
            }

            /*lpg-cng,accessories kit calculation */
            if (!$vehicle_discount_detail->isEmpty()) {
                /* For Change Discount rate Start*/

                if (isset($request['changedDiscountQuoteId']) && $request['changedDiscountQuoteId'] != 0) {

                    $get_changed_discount_quoteid = $request['changedDiscountQuoteId'];
                    $get_changed_discount_quote_detail = DB::table('quote_log')
                        ->where('quote_id', $request['changedDiscountQuoteId'])
                        ->select('*')
                        ->get();

                    $get_changed_discount_quote_policy_id = $get_changed_discount_quote_detail['master_policy_id'];
                    if ($master_policy_id_value->policy_id == $get_changed_discount_quote_policy_id && $get_changed_discount_quote_detail['change_default_discount'] != '') {
                        $changesDiscountValue = $get_changed_discount_quote_detail['change_default_discount'];
                        $vehicle_discount_detail[0]->discount_rate = $get_changed_discount_quote_detail['change_default_discount'];
                    }
                }
                /* For Change Discount rate End*/
                $edit_od_discount = 0;
                if ($edit_od_discount > 0) {
                    if ($get_response_data->is_od_discount_applicable == 'Y' && $master_policy_id_value->insurance_company_id == '33') {
                        $ic_vehicle_discount = ($edit_od_discount * ($basic_premium + $nonelectric_premium_value + $motor_lpg_cng_kit_value + $electric_premium_value)) / 100;
                    } else {
                        $ic_vehicle_discount = ($edit_od_discount * ($basic_premium + $nonelectric_premium_value)) / 100;
                    }
                } else {
                    if ($get_response_data->is_od_discount_applicable == 'Y' && $master_policy_id_value->insurance_company_id == '33') {
                        $ic_vehicle_discount = ($vehicle_discount_detail[0]->discount_rate * ($basic_premium + $nonelectric_premium_value + $motor_lpg_cng_kit_value + $electric_premium_value)) / 100;
                    } else {
                        $ic_vehicle_discount = ($vehicle_discount_detail[0]->discount_rate * ($basic_premium + $nonelectric_premium_value)) / 100;
                    }
                }

                $basic_premium = $premium_type == 'third_party' ? 0 : $basic_premium - $ic_vehicle_discount;
                $vehicleDiscountValues['ic_vehicle_discount'] = $ic_vehicle_discount;
            } else {
                $ic_vehicle_discount = 0;
                $basic_premium = $basic_premium - $ic_vehicle_discount;
                $vehicleDiscountValues['ic_vehicle_discount'] = $ic_vehicle_discount;
            }



            $total_own_damage = $basic_premium;



            /*Unnamed Passenger value*/
            $cover_unnamed_passenger_value = 0;
            $compulsory_pa_own_driver = 0;
            $cover_detail = '';
            if ($compulsorypaOwnDriver['status'] = true) {
                $cover_array = [
                    'product_sub_type_id' => $product_sub_type_id,
                    'ic_id' => $insurance_company_id,
                    'cover_code' => 'ownerdriver'
                ];

                $cover_detail = getfixedvaluecoverrates($cover_array);
                $compulsory_pa_own_driver = $cover_detail[0]->cover_rate ?? 0;
            }

            /* anti theft discount code */
            $antitheftdiscount = 0;


            /*lpg-cng,accessories kit calculation */

            $motor_electric_accessories_value = $premium_type == 'third_party' ? 0 : $electric_premium_value;
            $motor_non_electric_accessories_value = $premium_type == 'third_party' ? 0 : $nonelectric_premium_value; //=$nonelectric_user_value
            $total_accessories_amount = $motor_electric_accessories_value  + $motor_non_electric_accessories_value;
            if (!empty($antiTheftDisc) && $antiTheftDisc['status'] == true) {
                $cover_array = [
                    'product_sub_type_id' => $product_sub_type_id,
                    'ic_id' => $insurance_company_id,
                    'cover_code' => 'antitheft'
                ];

                $db_antitheft = getfixedvaluecoverrates($cover_array);

                $antitheftdiscount = ($basic_premium_comm + $total_accessories_amount + $motor_lpg_cng_kit_value) * ($db_antitheft[0]->cover_rate ?? 0) / 100;
                $antitheftdiscount = $antitheftdiscount > 500 ? 500 : $antitheftdiscount;
            }


            /* anti theft discount code */

            $cover_unnamed_passenger_value = 0;
            if (isset($unnamedPassenger['addonSI']) &&  $unnamedPassenger['addonSI'] != '' && $premium_type == 'third_party') {
                $cover_array = [
                    'product_sub_type_id' => $product_sub_type_id,
                    'ic_id' => 0,
                    'cover_code' => 'unnamedpassenger'
                ];
                $cover_detail = getfixedvaluecoverrates($cover_array);
                if ($cover_detail != '' && isset($cover_detail[0]->base_si) > 0) {
                    $cover_si =  $unnamedPassenger['addonSI'] / $cover_detail[0]->base_si;
                    $cover_unnamed_passenger_value = $cover_si * $cover_detail[0]->cover_rate * $get_response_data->seating_capacity;
                }
            }

            // DEFAULT_PAID_DRIVER = 50

            $default_paid_driver = 50;

            // $tppd->premium_amount = 0;

            $total_liability_premium = $tppd->premium_amount + $default_paid_driver + $cover_unnamed_passenger_value + $compulsory_pa_own_driver;

            $addon_premium = 0;
            $selected_addon = [];

            $all_selected_addons = [];

            $db_manf_date = explode("-", $motor_manf_date);
            $cover_array = [
                'policy_id' => $master_policy_id_value->policy_id,
                'registrationdate' => $db_manf_date[2] . '-' . $db_manf_date[1] . '-' . $db_manf_date[0],
                'vehicle_cc' => $vehicle_cc
            ];


            $compulsory_addon_cover_detail = get_compulsory_addon_cover($cover_array);

            if (isset($request['addonCover']) && $request['addonCover'] != "") {
                $cover_array = [
                    'policy_id' => $master_policy_id_value->policy_id,
                    'registrationdate' => $db_manf_date[2] . '-' . $db_manf_date[1] . '-' . $db_manf_date[0],
                    'vehicle_cc' => $vehicle_cc,
                    'version_id' => $version_id
                ];

                $addon_cover_detail = get_addon_cover($cover_array);


                if ($addon_cover_detail != '') {
                    $addonval = 0;
                    $addoncount = 0;
                    foreach ($request['addonCover'] as $addonkey => $addonvalue) {
                        $current_addon_item = '';
                        foreach ($addon_cover_detail as $addon_cover_detail_key => $addon_cover_detail_item) {
                            if ($addon_cover_detail_item->cover_name == $addonvalue) {
                                $current_addon_item = $addon_cover_detail_item;
                            }
                        }

                        if ($current_addon_item && isset($current_addon_item->addon_cover_apply_on)) {
                            if (isset($current_addon_item->abs_rate_flag) && $current_addon_item->abs_rate_flag == 'absolute') {
                                $addonval = $current_addon_item->min_rate;
                            } else {
                                //FOR APPLY ON VEHICLE IDV
                                if (isset($current_addon_item->addon_cover_apply_on) && $current_addon_item->addon_cover_apply_on == 1) {
                                    $addonval = (($current_addon_item->min_rate * $vehicle_idv) / 100); // vehicle IDV
                                } elseif (isset($current_addon_item->addon_cover_apply_on) && $current_addon_item->addon_cover_apply_on == 3) {
                                    $addonval = (($current_addon_item->min_rate * $request['showroom_price']) / 100); // showroom price
                                }
                            }
                            $addon_premium = $addon_premium + $addonval;
                            $addonAmount = round($addonval);
                            array_push($selected_addon, [$current_addon_item->addon_name => $addonAmount]);
                            array_push($all_selected_addons, ['addon_id' => $addonvalue, 'addon_name' => $current_addon_item->addon_name, 'addon_premium' => $addonAmount]);
                        } else {
                            $in_compulsory = 0;
                            foreach ($compulsory_addon_cover_detail as $compulsory_addon_cover_detail_key => $compulsory_addon_cover_detail_item) {
                                if ($compulsory_addon_cover_detail_item->cover_name == $addonvalue) {
                                    $in_compulsory = 1;
                                }
                            }
                            if ($in_compulsory == 0) {
                                $addon_detail_add = getAddon($addonvalue, '');

                                array_push($selected_addon, [$addon_detail_add[0]->addon_name => '0']);
                                array_push($all_selected_addons, ['addon_id' => $addonvalue, 'addon_name' => $addon_detail_add[0]->addon_name, 'addon_premium' => '0']);
                            }
                        }
                    }
                }
            }

            $current_addon_item = '';
            foreach ($compulsory_addon_cover_detail as $addon_cover_detail_key => $addon_cover_detail_item) {
                $current_addon_item = $addon_cover_detail_item;

                if ($current_addon_item && isset($current_addon_item->addon_cover_apply_on)) {
                    if (isset($current_addon_item->abs_rate_flag) && $current_addon_item->abs_rate_flag == 'absolute') {
                        $addonval = $current_addon_item->min_rate;
                    } else {
                        //FOR APPLY ON VEHICLE IDV
                        if (isset($current_addon_item->addon_cover_apply_on) && $current_addon_item->addon_cover_apply_on == 1) {
                            $addonval = (($current_addon_item->min_rate * $vehicle_idv) / 100); // vehicle IDV
                        } elseif (isset($current_addon_item->addon_cover_apply_on) && $current_addon_item->addon_cover_apply_on == 3) {
                            $addonval = (($current_addon_item['min_rate'] * $request->showroom_price) / 100); // showroom price
                        }
                    }

                    $addon_premium = $addon_premium + $addonval;
                    $addonAmount = round($addonval);
                    array_push($selected_addon, [$current_addon_item->addon_name => $addonAmount]);
                    array_push($all_selected_addons, ['addon_id' => $addon_cover_detail_item->addon_id, 'addon_name' => $current_addon_item->addon_name, 'addon_premium' => $addonAmount]);
                }
            }
            $max_addons_selection = count($selected_addon) > $max_addons_selection ? count($selected_addon) : $max_addons_selection;
            /************Add compulsory Addond ***********/
            $DeductibaleAmount = $get_param["voluntary_excess"] ?? 0;
            $tmpdeductible = 0;
            if ($DeductibaleAmount > 0) {
                $deductible_array = [
                    'voluntary_excess' => $get_param["voluntary_excess"] ?? '',
                    'product_sub_type_id' => $product_sub_type_id
                ];
                $deductible_detail = getvoluntarydeductible($deductible_array);
                if ($deductible_detail) {
                    $tmpdeductible = ($total_own_damage * $deductible_detail[0]['discount_in_percent']) / 100;
                    if ($tmpdeductible > $deductible_detail[0]['max_amount']) {
                        $tmpdeductible = $deductible_detail[0]['max_amount'];
                    }
                }
            }

            $total_liability_premium = $total_liability_premium + $cng_lpg_tp;

            $total_discount = $antitheftdiscount + $tmpdeductible;
            $total_own_damage = $total_own_damage  +  $motor_lpg_cng_kit_value + $total_accessories_amount - $total_discount;
            $ncb_value = $total_own_damage * ($ncb_discount / 100);
            $total_premium = $total_own_damage - $ncb_value + $total_liability_premium + $addon_premium;
            // $premium_amount = $total_premium * (1 + (SERVICE_TAX / 100));
            $premium_amount = $total_premium * (1 + (18.0 / 100));
            $get_response1['get_changed_discount_quoteid'] = $get_changed_discount_quoteid;
            $get_response1['vehicle_discount_detail'] = $vehicle_discount_detail;
            $get_response1['policy_type'] = $policy_type;
            $get_response1['cover_type'] = $cover_type;
            $get_response1['hypothecation'] = $addon_cover_detail;
            $get_response1['hypothecation_name'] = $cover_array;
            $get_response1['antitheft_discount'] = $antitheftdiscount;
            $get_response1['vehicle_registration_no'] = $vehicle_registration_no;
            $get_response1['voluntary_excess'] = $tmpdeductible;
            $get_response1['version_id'] = $version_id;
            $get_response1['selected_addon'] = $selected_addon;
            $get_response1['all_selected_addons'] = $all_selected_addons;
            $get_response1['showroom_price'] = $showroom_price;
            $get_response1['fuel_type'] = $fuel_type;
            $get_response1['vehicle_idv'] = round($vehicle_idv);
            $get_response1['max_addons_selection'] = $max_addons_selection;

            $get_response1['ncb_discount'] = $ncb_discount;
            $get_response1['company_name'] = $company_name;
            $get_response1['company_logo'] = $company_logo;
            $get_response1['product_name'] = $product_name;
            $get_response1['mmv_detail'] = $mmv_detail;
            $get_response1['zone_id'] = $zone_id;
            $get_response1['vehicle_cc'] = $vehicle_cc;
            $get_response1['motor_manf_year'] = '2018';
            $get_response1['car_rto_no'] = $requestData->rto_code;
            $get_response1['motor_manf_date'] = $motor_manf_date;
            $get_response1['current_date'] = $current_date;
            $get_response1['car_age'] = $car_age;
            $get_response1['Tariff Rate'] = $get_res_premium->rate;
            $get_response1['master_policy_id'] = $master_policy_id_value;
            $get_response1['vehicleDiscountValues'] = $vehicleDiscountValues;
            $get_response1['basic_premium'] = round($basic_premium);
            $get_response1['deduction_of_ncb'] = round($ncb_value);
            $get_response1['premium_amount'] = round($premium_amount);
            $get_response1['tppd_premium_amount'] = $tppd->premium_amount;
            $get_response1['motor_electric_accessories_value'] = round($motor_electric_accessories_value);
            $get_response1['motor_non_electric_accessories_value'] = round($motor_non_electric_accessories_value);
            $get_response1['motor_lpg_cng_kit_value'] = round($motor_lpg_cng_kit_value);
            $get_response1['cover_unnamed_passenger_value'] = $cover_unnamed_passenger_value;

            $get_response1['seating_capacity'] = $get_response_data->seating_capacity;

            // $get_response1['default_paid_driver'] = DEFAULT_PAID_DRIVER;
            $get_response1['default_paid_driver'] = 50;

            $get_response1['motor_additional_paid_driver'] = $motor_additional_paid_driver;
            $get_response1['compulsory_pa_own_driver'] = $compulsory_pa_own_driver;
            $get_response1['total_accessories_amount(net_od_premium)'] = $total_accessories_amount;
            // $get_response1['Tonnage']=$Tonnage;
            $get_response1['total_own_damage'] = round($total_own_damage);
            $get_response1['cng_lpg_tp'] = round($cng_lpg_tp);
            $get_response1['total_liability_premium'] = $total_liability_premium;
            $get_response1['net_premium'] = round($total_premium);
            $get_response1['service_tax_amount'] = round($premium_amount - $total_premium);
            // $get_response1['service_tax'] = SERVICE_TAX;
            $get_response1['service_tax'] = "18.0";
            $get_response1['total_discount_od'] = round($ncb_value);
            $get_response1['add_on_premium_total'] = round($addon_premium); //'0';
            $get_response1['addon_premium'] = round($addon_premium);
            $get_response1['is_premium_online'] = $master_policy_id_value->is_premium_online;
            $get_response1['is_proposal_online'] = $master_policy_id_value->is_proposal_online;
            $get_response1['is_payment_online'] = $master_policy_id_value->is_payment_online;
            $get_response1['policy_id'] = $master_policy_id_value->policy_id;
            $get_response1['user_product_journey_id'] = $user_product_journey_id; //$showroom_price *(1-($get_motor_depreciation['depreciation_rate']/100));

            $timeCalculateArr1 = [
                'from_where' => 'after savePremiumCalculate',
                'user_product_journey_id' => $enquiryId,
                'execute_current_time' => date('Y-m-d H:i:s')
            ];

            $save_timeCalculateArr1 = DB::table('time_calculate')
                ->insert($timeCalculateArr1);

            $get_quote_detail = DB::table('quote_log')
                ->where('user_product_journey_id', $enquiryId)
                ->select('*')
                ->get();

            date_default_timezone_set('Asia/Kolkata');

            $update_param_quote1 = [
                'quote_response' => json_encode($get_response1, JSON_PRETTY_PRINT),
                'searched_at' => date("Y-m-d H:i:s"),
                'premium_json' => json_encode($get_response1, JSON_PRETTY_PRINT),
                'ex_showroom_price_idv' => $get_response1['showroom_price'],
                'addon_premium' => $get_response1['addon_premium'],
                'service_tax' => $get_response1['service_tax_amount'],
                'od_premium' => $get_response1['total_own_damage'],
                'tp_premium' => $get_response1['tppd_premium_amount'],
                'final_premium_amount' => $get_response1['premium_amount'] == '' ? 0 : $get_response1['premium_amount'],
                'status' => 1,
                'is_selected' => 0,
                'master_policy_id' => $master_policy_id_value->policy_id
            ];

            $timeCalculateArr1 = [
                'from_where' => 'before saveMotorQuoteLogData',
                'user_product_journey_id' => $enquiryId,
                'execute_current_time' => date('Y-m-d H:i:s')
            ];


            $save_timeCalculateArr1 = DB::table('time_calculate')->insert($timeCalculateArr1);

            $insert_param_quote_details = [
                'user_id' => $get_quote_detail[0]->user_id,
                'product_sub_type_id' => $get_quote_detail[0]->product_sub_type_id,
                'quotes_request_id' => $get_quote_detail[0]->quotes_request_id,
                'quote_data' => $get_quote_detail[0]->quote_data,
                'quote_response' => json_encode($get_response1, JSON_PRETTY_PRINT),
                'source' => $get_quote_detail[0]->source,
                'ex_showroom_price_idv' => $get_response1['showroom_price'],
                'final_premium_amount' => $get_response1['premium_amount'] == '' ? 0 : $get_response1['premium_amount'],
                'master_policy_id' => $master_policy_id_value->policy_id,
                'premium_json' => json_encode($get_response1, JSON_PRETTY_PRINT),
                'is_selected' => 0,/*$get_quote_detail['is_selected']*/
                'od_premium' => $get_response1['total_own_damage'],
                'tp_premium' => $get_response1['tppd_premium_amount'],
                'service_tax' => $get_response1['service_tax_amount'],
                'addon_premium' => $get_response1['addon_premium'],
                'sequence_count' => $count_save,
                'searched_at' => date("Y-m-d H:i:s")
            ];

            // $get_response_save_quote = saveMotorQuoteLogData($insert_param_quote_details, $enquiryId);

            // $get_response1['quote_id'] = $get_response_save_quote['quote_id'];

            if ($changesDiscountValue > 0) {
                $update_param_quote1insert_param_quote_details['is_discount_change'] = 'Yes';
                $update_param_quote1insert_param_quote_details['change_default_discount'] = $changesDiscountValue;

                $get_response_upd_quote_new = DB::table('quote_log')
                    ->where('user_product_journey_id', $enquiryId)
                    ->update($update_param_quote1insert_param_quote_details);
            }

            $count_save++;

            $get_quote_id_for_ola_insert = [
                'quote_id' => $requestData->user_product_journey_id,
                'created_by' => $get_quote_detail[0]->user_id,
                'ic_id' => $insurance_company_id,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $get_quote_id_for_ola = DB::table('ola_quote_id')
                ->insert($get_quote_id_for_ola_insert);

            $get_quote_detail_ola = DB::table('ola_quote_id')
                ->where('quote_id', '=', $requestData->user_product_journey_id)
                ->orderBy('id', 'DESC')
                ->select()
                ->get()->toArray();


            $get_all_online_service_response_val = $get_response1;
            $quote_data_OLA = json_decode($get_quote_detail[0]->quote_data, true);


            $policy_start_format = date_create($get_all_online_service_response_val['policyStartDate']);
            $policy_start_date_OLA = date_format($policy_start_format, "d-m-Y");

            $company_name_OLA = $get_all_online_service_response_val['master_policy_id']->company_name;
            $insurance_company_id_OLA = $get_all_online_service_response_val['master_policy_id']->insurance_company_id;
            $premium_amount_OLA = $get_all_online_service_response_val['premium_amount'];
            $vehicle_idv_OLA = $get_all_online_service_response_val['vehicle_idv'];
            $policy_end_Array_OLA = date_create($get_all_online_service_response_val['master_policy_id']->policy_end_date);
            $policy_end_OLA = date('d-m-Y', strtotime($policy_start_date_OLA . ' + 364 days'));
            $ncb_discount_OLA = $get_all_online_service_response_val['ncb_discount'];
            $deduction_of_ncb_OLA = $get_all_online_service_response_val['deduction_of_ncb'];
            $total_liability_premium = $get_all_online_service_response_val['total_liability_premium'];
            $vehicle_registration_no_OLA = $get_all_online_service_response_val['vehicle_registration_no'];
            $version_id_OLA = $get_all_online_service_response_val['version_id'];
            $digits = 5;
            $quote_id = $get_quote_detail_ola[0]->id;

            array_push($get_response, $get_response1);
        }
    }

    $final_response = [];
    foreach ($get_response as $get_response_key => $get_response_val) {
        $tmp_response = $get_response_val;
        $tmp_response['max_addons_selection'] = $max_addons_selection;
        array_push($final_response, $tmp_response);
    }

    if (!empty($final_response)) {

        $final_od_premium = $premium_type == 'third_party' ? 0 : ($final_response[0]['basic_premium'] + $final_response[0]['vehicleDiscountValues']['ic_vehicle_discount'] + $final_response[0]['motor_electric_accessories_value'] + $final_response[0]['motor_non_electric_accessories_value']);
        $final_tp_premium = ($final_response[0]['tppd_premium_amount'] + $final_response[0]['cover_unnamed_passenger_value'] + $final_response[0]['default_paid_driver'] + $final_response[0]['motor_additional_paid_driver'] + $final_response[0]['cng_lpg_tp']);
        $final_total_discount = ($final_response[0]['vehicleDiscountValues']['ic_vehicle_discount'] + $final_response[0]['deduction_of_ncb']);
        $final_net_premium = $final_od_premium + $final_tp_premium  - $final_total_discount;
        $final_gst_amount = ($final_net_premium * 0.18);
        $final_payable_amount = ($final_net_premium + $final_gst_amount);


        $data_response = array(
            'status' => true,
            'msg' => 'Found',
            'data' => [
                'idv' => $final_response[0]['vehicle_idv'],
                'min_idv' => $min_idv,
                'max_idv' => $max_idv,
                'pp_enddate' => $final_response[0]['master_policy_id']->policy_end_date,
                'addon_cover_data_get' => '',
                'policy_type' => $premium_type == 'third_party' ? 'Third Party' : 'Comprehensive',
                'cover_type' => '1YC',
                'vehicle_registration_no' => $requestData->rto_code,
                'voluntary_excess' => $requestData->voluntary_excess_value,
                'version_id' => $final_response[0]['version_id'],
                'selected_addon' => $final_response[0]['selected_addon'],
                'showroom_price' => $final_response[0]['showroom_price'],
                'fuel_type' => $requestData->fuel_type,
                'vehicle_idv' => $vehicle_idv,
                'ncb_discount' => $premium_type == 'third_party' ? 0 : $requestData->applicable_ncb,
                'company_name' => $final_response[0]['company_name'],
                'company_logo' => url(config('constants.motorConstant.logos') . $final_response[0]['company_logo']),
                'product_name' => 'Private Car',
                'mmv_detail' => $final_response[0]['mmv_detail'],
                'master_policy_id' => array(
                    'policy_id' => $final_response[0]['policy_id'],
                    'policy_no' => $final_response[0]['master_policy_id']->policy_no,
                    'policy_start_date' => $final_response[0]['master_policy_id']->policy_start_date,
                    'policy_end_date' => $final_response[0]['master_policy_id']->policy_end_date,
                    'sum_insured' => $final_response[0]['master_policy_id']->sum_insured,
                    'corp_client_id' => $final_response[0]['master_policy_id']->corp_client_id,
                    'product_sub_type_id' => $final_response[0]['master_policy_id']->product_sub_type_id,
                    'insurance_company_id' => $final_response[0]['master_policy_id']->insurance_company_id,
                    'status' => $final_response[0]['master_policy_id']->status,
                    'corp_name' => "Ola Cab",
                    'product_sub_type_name' => $final_response[0]['master_policy_id']->product_sub_type_name,
                    'flat_discount' => $final_response[0]['master_policy_id']->flat_discount
                ),
                'motor_manf_date' => $motor_manf_date,
                'vehicle_register_date' => $requestData->vehicle_register_date,
                'vehicleDiscountValues' => array(
                    'master_policy_id' => $final_response[0]['vehicleDiscountValues']['master_policy_id'],
                    'product_sub_type_id' => $final_response[0]['vehicleDiscountValues']['product_sub_type_id'],
                    'segment_id' => 0,
                    'rto_cluster_id' => 0,
                    'car_age' => $car_age,
                    'ic_vehicle_discount' => round($final_response[0]['vehicleDiscountValues']['ic_vehicle_discount'])
                ),
                'basic_premium' => $final_response[0]['basic_premium'],
                'deduction_of_ncb' => $final_response[0]['deduction_of_ncb'],
                'tppd_premium_amount' => $final_response[0]['tppd_premium_amount'],
                'motor_electric_accessories_value' => $final_response[0]['motor_electric_accessories_value'],
                'motor_non_electric_accessories_value' => $final_response[0]['motor_non_electric_accessories_value'],
                'motor_lpg_cng_kit_value' => $final_response[0]['motor_lpg_cng_kit_value'],
                'cover_unnamed_passenger_value' => $final_response[0]['cover_unnamed_passenger_value'],
                'seating_capacity' => $final_response[0]['seating_capacity'],
                'default_paid_driver' => $final_response[0]['default_paid_driver'],
                'motor_additional_paid_driver' => 0,
                'compulsory_pa_own_driver' => $final_response[0]['compulsory_pa_own_driver'],
                'total_accessories_amount(net_od_premium)' => $final_response[0]['total_accessories_amount(net_od_premium)'],
                'total_own_damage' => $final_response[0]['total_own_damage'],
                'cng_lpg_tp' => $final_response[0]['cng_lpg_tp'],
                'total_liability_premium' => $final_response[0]['total_liability_premium'],
                'net_premium' => $final_response[0]['net_premium'],
                'service_tax_amount' => $final_response[0]['service_tax_amount'],
                'service_tax' => 18,
                'ic_vehicle_discount' => round($final_response[0]['vehicleDiscountValues']['ic_vehicle_discount']),
                'total_discount_od' => 0,
                'add_on_premium_total' => 0,
                'addon_premium' => 0,
                'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                'quotation_no' => '',
                'premium_amount' => $final_response[0]['premium_amount'],
                'antitheft_discount' => $final_response[0]['antitheft_discount'],
                'service_data_responseerr_msg' => "success",
                'user_id' => $requestData->user_id,
                'product_sub_type_id' => $final_response[0]['master_policy_id']->product_sub_type_id,
                'user_product_journey_id' => $requestData->user_product_journey_id,
                'business_type' => $requestData->policy_type == 'newbusiness' ? 'New Business' : 'Rollover',
                'service_err_code' => NULL,
                'service_err_msg' => NULL,
                'policyStartDate' => date('d-m-Y', strtotime($final_response[0]['policyStartDate'])),
                'policyEndDate' => date('d-m-Y', strtotime($get_response_data->previous_policy_expiry_date)),
                'ic_of' => $final_response[0]['master_policy_id']->insurance_company_id,
                'vehicle_in_90_days' => $vehicle_in_90_days,
                'get_policy_expiry_date' => NULL,
                'get_changed_discount_quoteid' => 0,
                'vehicle_discount_detail' => [
                    'discount_id' => NULL,
                    'discount_rate' => NULL
                ],
                'is_premium_online' => $final_response[0]['master_policy_id']->is_premium_online,
                'is_proposal_online' => $final_response[0]['master_policy_id']->is_proposal_online,
                'is_payment_online' => $final_response[0]['master_policy_id']->is_payment_online,
                'policy_id' => $final_response[0]['master_policy_id']->policy_id,
                'insurane_company_id' => $final_response[0]['master_policy_id']->insurance_company_id,
                "max_addons_selection" => NULL,
                "final_od_premium" => round($final_od_premium),
                "final_tp_premium" =>  round($final_tp_premium),
                "final_total_discount" => round($final_total_discount),
                "final_net_premium" => round($final_net_premium),
                "final_gst_amount" => round($final_gst_amount),
                "final_payable_amount" => round($final_payable_amount)
            ]
        );
    } else {
        $data_response = array(
            'status' => false,
            'msg' => "No offline IC quote data found."
        );
    }

    $data_response['data']['add_ons_data']['in_built'] = [];
    $data_response['data']['add_ons_data']['additional'] = [];

    if (!empty($final_response[0]['selected_addon'])) {
        foreach ($final_response[0]['selected_addon'] as $addons_key => $addons) {

            if ($productData->policy_id == 532) {
                if (isset($addons['Road Side Assistance'])) {
                    $data_response['data']['add_ons_data']['in_built']['road_side_assistance'] = $addons['Road Side Assistance'];
                }

                if (isset($addons['Zero Depreciation'])) {
                    $data_response['data']['add_ons_data']['additional']['zero_depreciation'] = $addons['Zero Depreciation'];
                }
            }

            if ($productData->policy_id == 529) {

                $data_response['data']['add_ons_data']['in_built']['addons'] = [];

                if (isset($addons['Zero Depreciation'])) {
                    $data_response['data']['add_ons_data']['additional']['zero_depreciation'] = $addons['Zero Depreciation'];
                }

                if (isset($addons['Road Side Assistance'])) {
                    $data_response['data']['add_ons_data']['additional']['road_side_assistance'] = $addons['Road Side Assistance'];
                }
            }
        }
    }


    return camelCase($data_response);
}
////


function saveMotorQuoteLogData($requests, $enquiryId)
{
    $requests['quotes_request_id'] = $requests['quotes_request_id'] == "" ? 0 : $requests['quotes_request_id'];
    $requests['user_id'] = $requests['user_id'] == "" ? 0 : $requests['user_id'];
    $requests['is_selected'] = $requests['is_selected'] == "" ? 0 : $requests['is_selected'];

    $sequence_count = $requests['sequence_count'] == "" ? 1 : 0;
    $sequence_count = $requests['sequence_count'] ?? 1;

    if ($sequence_count == 0) {
        $updateLog = DB::table('quote_log')
            ->where('user_product_journey_id', $enquiryId)
            ->where('STATUS', 1)
            ->update(['STATUS' => 0]);
    }

    $saveLog = QuoteLog::updateOrCreate([
        'user_product_journey_id' => $enquiryId
    ],[
        'quotes_request_id' => $requests['quotes_request_id'],
        'user_id' => $requests['user_id'],
        'quote_data' => $requests['quote_data'],
        'product_sub_type_id' => $requests['product_sub_type_id'],
        'searched_at' => $requests['searched_at'],
        'ex_showroom_price_idv' => $requests['ex_showroom_price_idv'],
        'final_premium_amount' => $requests['final_premium_amount'],
        'master_policy_id' => $requests['master_policy_id'],
        'premium_json' => $requests['premium_json'],
        'is_selected' => $requests['is_selected'],
        'od_premium' => $requests['od_premium'],
        'tp_premium' => $requests['tp_premium'],
        'service_tax' => $requests['service_tax'],
        'addon_premium' => $requests['addon_premium'],
        'quote_response' => $requests['quote_response']
    ]);

    return [
        "quote_id" => $saveLog->quote_id
    ];
}

function getsegmentforpremium($request)
{
    $version_id = $request['version_id'] ?? "";
    $master_policy_id = $request['master_policy_id'] ?? "";

    $polydWiseSegment1 = DB::table('policy_wise_sub_segment AS a')
        ->join('policywise_segment AS b', 'a.sub_segment_id', '=', 'b.master_segment_id')
        ->join('master_segment AS ms', 'a.master_segment_id', '=', 'ms.segment_id')
        ->join('motor_model AS c', 'c.model_id', '=', 'b.model_id')
        ->join('motor_model_version AS d', 'd.model_id', '=', 'c.model_id')
        ->where('master_policy_id', $master_policy_id)
        ->where('d.version_id', $version_id)
        ->where('b.manf_id', '=', '0')
        ->where('b.model_id', '=', '0')
        ->select('b.policywise_segment_id')
        ->get();

    if (!$polydWiseSegment1->isEmpty()) {
        echo "1";
        exit;
    } else {

        $polydWiseSegment2 = DB::table('policy_wise_sub_segment AS a')
            ->join('policywise_segment AS b', 'a.sub_segment_id', '=', 'b.master_segment_id')
            ->join('master_segment AS ms', 'a.master_segment_id', '=', 'ms.segment_id')
            ->join('motor_model AS c', 'c.model_id', '=', 'b.model_id')
            ->join('motor_model_version AS d', 'd.model_id', '=', 'c.model_id')
            ->where('b.version_id', '>', '0')
            ->where('b.manf_id', '=', '0')
            ->where('master_policy_id', $master_policy_id)
            ->where('b.version_id', $version_id)
            ->where('b.manf_id', '=', '0')
            ->where('b.model_id', '=', '0')
            ->select('b.policywise_segment_id')
            ->get();

        if (!$polydWiseSegment2->isEmpty()) {
            echo "2";
            exit;
        } else {

            $polydWiseSegment3 = DB::table('policy_wise_sub_segment AS a')
                ->join('policywise_segment AS b', 'a.sub_segment_id', '=', 'b.master_segment_id')
                ->join('master_segment AS ms', 'a.master_segment_id', '=', 'ms.segment_id')
                ->join('motor_manufacturer AS c', 'c.manf_id', '=', 'b.manf_id')
                ->join('motor_model AS m', 'c.manf_id', '=', 'm.model_id')
                ->join('motor_model_version AS mv', 'mv.model_id', '=', 'm.model_id')
                ->where('b.version_id', '=', '0')
                ->where('b.manf_id', '>', '0')
                ->where('master_policy_id', $master_policy_id)
                ->where('mv.version_id', $version_id)
                ->where('b.manf_id', '>', '0')
                ->where('b.model_id', '=', '0')
                ->select('a.sub_segment_id', 'policywise_segment_id')
                ->get();

            if (!$polydWiseSegment3->isEmpty()) {
                $polydWiseSegmentres = DB::table('policy_wise_sub_segment AS a')
                    ->join('policywise_segment AS b', 'a.sub_segment_id', '=', 'b.master_segment_id')
                    ->join('master_segment AS ms', 'a.master_segment_id', '=', 'ms.segment_id')
                    ->join('motor_manufacturer AS c', "c.manf_id", "=", "b.manf_id")
                    ->join('motor_model AS m', "c.manf_id", "=", "m.manf_id")
                    ->join('motor_model_version AS m', "mv.model_id", "=", "m.model_id")
                    ->where('master_policy_id', $master_policy_id)
                    ->where('b.segment_type_id', '=', '1')
                    ->where('b.manf_id', '=', '0')
                    ->where('b.version_id', '=', '0')
                    ->select('a.sub_segment_id', 'policywise_segment_id')
                    ->get();
            } else {

                $polydWiseSegmentres = DB::table('policy_wise_sub_segment AS a')
                    ->join('policywise_segment AS b', 'a.sub_segment_id', '=', 'b.master_segment_id')
                    ->join('master_segment AS ms', 'a.master_segment_id', '=', 'ms.segment_id')
                    ->where('master_policy_id', $master_policy_id)
                    ->where('b.segment_type_id', '=', '1')
                    ->where('b.manf_id', '=', '0')
                    ->where('b.version_id', '=', '0')
                    ->select('a.sub_segment_id as policywise_segment_id')
                    ->get();
            }
        }
    }

    return isset($polydWiseSegmentres) ? $polydWiseSegmentres : "";
}

function getrtoclusterforpremium($request)
{
    return DB::table('policy_wise_sub_rto_cluster AS pwsrc')
        ->join('master_rto_cluster AS mrc', 'pwsrc.rto_group_id', '=', 'mrc.rto_group_id')
        ->join('policy_wise_rto_cluster AS pwrc', 'pwsrc.sub_cluster_id', '=', 'pwrc.cluster_id')
        ->join('master_rto AS mr', 'pwrc.city_id', '=', 'mr.rto_id')
        ->where('mrc.master_policy_id', $request['master_policy_id'])
        ->where('mr.rto_number', $request['car_rto_no'])
        ->select('pwsrc.sub_cluster_id as cluster_id')->get();
}

function checkDeclineRTO($request)
{

    $declineRto = DB::table('decline_rto')
        ->where('rto_number', $request['rto_number'])
        ->where('master_policy_id', $request['master_policy_id'])
        ->select('*')
        ->get();

    return isset($declineRto) ? $declineRto : "";
}

function checkDeclineMMV($request)
{
    $checkDeclineMMV1 = DB::table('decline_mmv')
        ->where('decline_by_mmv_id', $request['manf_id'])
        ->where('master_policy_id', $request['master_policy_id'])
        ->select('*')
        ->get();

    if (!$checkDeclineMMV1->isEmpty()) {
        $checkDeclineMMV = $checkDeclineMMV1;
    } else {
        $checkDeclineMMV2 = DB::table('decline_mmv')
            ->where('decline_by_mmv_id', $request['model_id'])
            ->where('master_policy_id', $request['master_policy_id'])
            ->select('*')
            ->get();

        if (!$checkDeclineMMV2->isEmpty()) {
            $checkDeclineMMV = $checkDeclineMMV2;
        } else {

            $checkDeclineMMV3 = DB::table('decline_mmv')
                ->where('decline_by_mmv_id', $request['version_id'])
                ->where('master_policy_id', $request['master_policy_id'])
                ->select('*')
                ->get();

            if (!$checkDeclineMMV3->isEmpty()) {
                $checkDeclineMMV = $checkDeclineMMV3;
            }
        }
    }

    return isset($checkDeclineMMV) ? $checkDeclineMMV : "";
}

function getVehicleDiscountforpremium($request)
{
    if ($request['car_age'] == 0) {
        $car_age = 1;
    } else {
        $car_age = $request['car_age'];
    }

    $vehDiscountPrem1 = DB::table('vehicle_discount')
        ->whereRaw($car_age . ' between age_min and age_max')
        ->where('segment_id', $request['segment_id'])
        ->where('master_policy_id', $request['master_policy_id'])
        ->where('rto_cluster_id', $request['rto_cluster_id'])
        ->select('discount_id', 'discount_rate')
        ->get();

    if (!$vehDiscountPrem1->isEmpty()) {
        $vehDiscountPrem = $vehDiscountPrem1;
    } else {

        $vehDiscountPrem2 = DB::table('master_policy')
            ->where('policy_id', $request['master_policy_id'])
            ->select('default_discount')
            ->get();

        if (!$vehDiscountPrem2->isEmpty()) {
            $vehDiscountPrem = DB::table('master_policy')
                ->where('policy_id', $request['master_policy_id'])
                ->select(DB::raw('1 AS discount_id, default_discount as discount_rate'))
                ->get();
        } else {
            $vehDiscountPrem = DB::table('master_policy')
                ->select(DB::raw('1 AS discount_id, 0 AS discount_rate'))
                ->get();
        }
    }

    return $vehDiscountPrem;
}

function getfixedvaluecoverrates($request)
{

    return $selectStatement = DB::table("vehicle_fixed_value_cover_rates AS a")
        ->join('master_vehicle_fixed_value_cover as b', 'a.cover_id', '=', 'b.cover_id')
        ->where('a.product_sub_type_id', $request['product_sub_type_id'])
        ->where('a.company_id', $request['ic_id'])
        ->where('b.cover_code', $request['cover_code'])
        ->where('a.isactive', '1')
        ->where('b.isactive', '1')
        ->select('*')
        ->get();
}

function get_compulsory_addon_cover($request)
{
    $get_compulsory_addon_cover = DB::table("motor_master_cover AS a")
        ->join('motor_addon as b', 'a.cover_name', '=', 'b.addon_id')
        ->join('master_plan as mp', 'mp.plan_id', '=', 'a.plan_id')
        ->whereRaw($request['vehicle_cc'] . ' between a.min_cc AND a.max_cc')
        ->whereRaw("IFNULL(a.section_name,'A')='C'")
        ->whereRaw("TIMESTAMPDIFF(MONTH, '" . $request['registrationdate'] . "', NOW()) between a.min_age and a.max_age")
        ->where('mp.policy_id', $request['policy_id'])
        ->select('*')
        ->get();


    return $get_compulsory_addon_cover;
}

function get_addon_cover($request)
{
    $segment_type_id = DB::table("ic_version_mapping AS a")
        ->join('tata_aig_model_master as b', 'a.ic_version_code', '=', 'b.vehiclemodelcode')
        ->where('a.fyn_version_id', $request['version_id'])
        ->select('b.txt_segmenttype')
        ->get();

    $today = date('d-m-Y');

    $veh_age = ((date('Y', strtotime($today)) - date('Y', strtotime($request['registrationdate']))) * 12) + (date('m', strtotime($today)) - date('m', strtotime($request['registrationdate'])));
    $veh_age = $veh_age < 0 ? 0 : $veh_age;

    if ($request['policy_id'] == 530) {
        $get_addon_cover = DB::table("motor_master_cover AS a")
            ->join('motor_addon AS b', 'a.cover_name', '=', 'b.addon_id')
            ->join('master_plan AS mp', 'mp.plan_id', '=', 'a.plan_id')
            ->whereRaw('TIMESTAMPDIFF(MONTH, ' . $request['registrationdate'] . ', NOW()) between a.min_age AND a.max_age ')
            ->where('mp.policy_id', $request['policy_id'])
            ->where('a.segment_type_id', $segment_type_id[0]->txt_segmenttype)
            ->select('*')
            ->get();
    } else {
        $get_addon_cover = DB::table("motor_master_cover AS a")
            ->join('motor_addon AS b', 'a.cover_name', '=', 'b.addon_id')
            ->join('master_plan AS mp', 'mp.plan_id', '=', 'a.plan_id')
            ->whereRaw('IFNULL(a.section_name,"A")="A"')
            ->whereRaw($veh_age . ' between a.min_age AND a.max_age ')
            ->where('mp.policy_id', $request['policy_id'])
            ->whereRaw($request['vehicle_cc'] . ' between a.min_cc AND a.max_cc')
            ->select('*')
            ->get();
    }

    return $get_addon_cover;
}

function getvoluntarydeductible($request)
{
    $request['voluntary_excess'] = $request['voluntary_excess'] ?? '0';

    if ($request['product_sub_type_id'] == 6) {
        $product_sub_type_id = 1;
    } else {
        $product_sub_type_id = $request['product_sub_type_id'];
    }

    $getvoluntarydeductible = DB::table("voluntary_deductible AS a")
        ->join('tata_aig_model_master as b', 'a.ic_version_code', '=', 'b.vehiclemodelcode')
        ->where('deductible_amount', $request['voluntary_excess'])
        ->where('product_id',  $product_sub_type_id)
        ->select('discount_in_percent', 'max_amount', 'deductible_amount ')
        ->get();

    return $getvoluntarydeductible;
}

function getAddon($addon_id, $addon_name)
{

    $getvoluntarydeductible = DB::table("motor_addon")
        ->where('status', 'Active');

    if (!empty($addon_id)) {
        $getvoluntarydeductible->where('addon_id', $addon_id);
    }

    if (!empty($addon_name)) {
        $getvoluntarydeductible->where('addon_name', $addon_name);
    }

    $getvoluntarydeductible = $getvoluntarydeductible->get();

    return $getvoluntarydeductible;
}


function getRto($state_id, $state_code, $rto_code, $rto_id)
{
    $state_id = $state_id == '' ? 0 : $state_id;
    $rto_id = $rto_id == '' ? 0 : $rto_id;

    $rto_data = DB::table('master_rto AS a')
        ->join('master_zone AS b', 'b.zone_id', '=', 'a.zone_id')
        ->join('master_state AS c', 'c.state_id', '=', 'a.state_id');

    if (is_numeric($state_id) && $state_id > 0) {
        $rto_data = $rto_data->where('a.state_id', $state_id);
    }

    if ($rto_code != '') {
        $rto_data = $rto_data->where('a.rto_number', $rto_code);
    }

    if ($state_code != '') {
        $rto_data = $rto_data->where('c.state_code', $state_code);
    }

    if (is_numeric($rto_id) && $rto_id > 0) {
        $rto_data = $rto_data->where('a.rto_id', $rto_id);
    }

    $rto_data = $rto_data->get();

    return $rto_data;
}

function getTariff($zone_id, $vehicle_cc, $vehicle_age, $product_sub_type_id)
{
    $product_sub_type_id = $product_sub_type_id ?? 1;

    $getTariff = DB::table('motor_tariff')
        ->where('product_sub_type_id', $product_sub_type_id);

    if ($zone_id != 0) {
        $getTariff = $getTariff->where('zone_id', $zone_id);
    }
    if ($vehicle_age != 0) {
        $getTariff = $getTariff->whereRaw($vehicle_age . 'between age_min and age_max');
    }
    if ($vehicle_cc != 0) {
        $getTariff = $getTariff->whereRaw($vehicle_cc . 'between cc_min and cc_max');
    }

    $getTariff = $getTariff->get();

    return $getTariff;
}

function getTariffPccv($request)
{
    $zone_id = $request['zone_id'];
    $vehicle_cc = $request['vehicle_cc'];
    $no_of_passenger = $request['no_of_passenger'];
    $vehicle_age = $request['vehicle_age'];

    $getTariffPccv = DB::table('pccv_tariff')
        ->where('zone_id', $zone_id)
        ->whereRaw($vehicle_cc . ' between min_cc and max_cc')
        ->whereRaw($no_of_passenger . ' between min_passenger and max_passenger')
        ->whereRaw($vehicle_age . ' between min_age and max_age')
        ->select('*')
        ->get();


    return $getTariffPccv[0];
}
