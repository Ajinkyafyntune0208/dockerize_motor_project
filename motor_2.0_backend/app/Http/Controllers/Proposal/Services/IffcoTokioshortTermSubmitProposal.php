<?php

namespace App\Http\Controllers\Proposal\Services;

use Carbon\Carbon;
use App\Models\MasterPolicy;
use App\Models\QuoteLog;
use App\Models\SelectedAddons;
use DateTime;
use function Composer\Autoload\includeFile;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;
use App\Http\Controllers\LiveCheck\LivechekBreakinController;
use App\Http\Controllers\SyncPremiumDetail\Services\IffcoTokioPremiumDetailController;
use App\Models\CvBreakinStatus;
use App\Models\iffco_tokioFinancierMaster;

// includeFile(app_path('Helpers/CvWebServiceHelper.php'));
include_once app_path().'/Helpers/CvWebServiceHelper.php';
class IffcoTokioshortTermSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $enquiryId = customDecrypt($request['enquiryId']);

        $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        $quote_data = json_decode($quote_log->quote_data, true);
        $master_policy = MasterPolicy::find($request['policyId']);

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $quote_log_data = QuoteLog::where("user_product_journey_id", $enquiryId)
                ->first();

        $idv = $quote_log_data->idv;

        $is_individual  = $requestData->vehicle_owner_type == "I" ? true : false;
        $is_new         = (($requestData->business_type == "newbusiness") ? true : false);

        $is_three_months    = (in_array($premium_type, ['short_term_3', 'short_term_3_breakin']) ? true : false);

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

        $is_breakin_date = false;
        $is_breakin = (in_array($premium_type, ['short_term_3_breakin', 'short_term_6_breakin']) ? true : false);

        $isBreakInMorethan90days = 'N';

        $noPreviousPolicyData = ($requestData->previous_policy_type == 'Not sure');
        if(!$is_new)
        {
            if($noPreviousPolicyData)
            {
                $is_breakin_date = true;
                $isBreakInMorethan90days = 'Y';
                $is_breakin = true;
            }
            else if(Carbon::parse($requestData->previous_policy_expiry_date) < Carbon::parse(date('d-m-Y')))
            {
                $is_breakin_date = true;
                if((Carbon::parse($requestData->previous_policy_expiry_date)->diffInDays(Carbon::parse(date('d-m-Y')))) > 90)
                {
                    $isBreakInMorethan90days = 'Y';
                }
                $is_breakin = true;
            }
            else{
                $is_breakin_date = false;
                $is_breakin = false;
            }
        }

        $mmv = get_mmv_details($productData, $requestData->version_id, 'iffco_tokio');
        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
            ];
        }

        $mmv_data = array_change_key_case((array) $mmv, CASE_LOWER);

        // $mmv_data = DB::table('iffco_tokio_pcv_short_term_mmv_master AS itstmmv')
        //     ->where('itstmmv.make_code', $mmv_data['make_code'])
        //     ->first();

        $rto_code = $requestData->rto_code;
        $city_name = DB::table('master_rto as mr')
            ->where('mr.rto_number', $rto_code)
            ->select('mr.*')
            ->first();

        if(isset($city_name->iffco_city_code))
        {
            $rto_arr = DB::table('iffco_tokio_city_master as ift')
            ->where('rto_city_code', $city_name->iffco_city_code)
            ->select('ift.*')->first();
        }
        else
        {
            
            $rto_arr = DB::table('iffco_tokio_city_master as ift')
            ->where('rto_city_name', $city_name->rto_name)
            ->select('ift.*')->first();
        }

        $rto_data = (object)[
            'state_code' => $rto_arr->state_code,
            'city_code' => $rto_arr->rto_city_code,
            'city_name' => $rto_arr->rto_city_name,

            'state_short_code' => explode('-',$rto_code)[0],
        ];
        $rto_data->city_display_name = $rto_data->state_short_code.'-'.$rto_data->city_name;

        $rto_city_data = DB::table('iffco_tokio_rto_city_master')
        ->where('rto_city_name', strtoupper($rto_arr->rto_city_code))
        ->first();
        if(!empty($rto_city_data))
        {
            $rto_data->city_display_name = $rto_city_data->display_name;
        }

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $tp_only = in_array($premium_type, ['third_party', 'third_party_breakin']);

        $is_pa_cover_owner_driver = 'N';
        $isValidDrivingLicense = 'Y';
        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $cpa_cover) {
                if (isset($cpa_cover['name']) && $cpa_cover['name'] == 'Compulsory Personal Accident') {
                    $is_pa_cover_owner_driver = 'Y';
                    $isValidDrivingLicense = 'Y';
                } elseif (isset($cpa_cover['reason']) && $cpa_cover['reason'] != "") {
                    if ($cpa_cover['reason'] == 'I do not have a valid driving license.') {
                        $is_pa_cover_owner_driver = 'N';
                        $isValidDrivingLicense = 'N';
                    }
                }
            }
        }

        if($is_breakin_date)
        {
            $policy_start_date = Carbon::parse(date('d-m-Y'));
        }
        else{
            $policy_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1);
        }

        $policy_end_date = Carbon::parse($policy_start_date)->addMonth($is_three_months ? 3 : 6)->subDay(1);

        if ($is_pa_cover_owner_driver == 'Y') {
            $proposal->is_cpa = 'Y';
        }

        if (strlen($proposal->chassis_number) > 20) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Chassis No. length can not be greater than 20 characters',
            ];
        }

        $first_reg_date = Carbon::parse($requestData->vehicle_register_date);

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);
        $addons = ($selected_addons->applicable_addons == null ? [] : $selected_addons->applicable_addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);

        $is_electrical = $is_non_electrical = $is_lpg_cng = false;
        $electrical_si = $non_electrical_si = $lpg_cng_si = 0;

        foreach ($accessories as $key => $value)
        {
            if (in_array('Electrical Accessories', $value))
            {
                $is_electrical = true;
                $electrical_si = $value['sumInsured'];
            }
    
            if (in_array('Non-Electrical Accessories', $value))
            {
                $is_non_electrical = true;
                $non_electrical_si = $value['sumInsured'];
            }
    
            if (in_array('External Bi-Fuel Kit CNG/LPG', $value))
            {
                $is_lpg_cng = true;
                $lpg_cng_si = $value['sumInsured'];
            }
        }

        $is_paid_driver = $is_pa_unnamed = $is_ll_paid = false;
        $paid_driver_si = $pa_unnamed_si = $ll_paid_si = 0;

        foreach ($additional_covers as $key => $value)
        {
            if (in_array('PA cover for additional paid driver', $value))
            {
                $is_paid_driver = true;
                $paid_driver_si = $value['sumInsured'];
            }
    
            if (in_array('Unnamed Passenger PA Cover', $value))
            {
                $is_pa_unnamed = true;
                $pa_unnamed_si = $value['sumInsured'];
            }
    
            if (in_array('LL paid driver', $value))
            {
                $is_ll_paid = true;
                $ll_paid_si = $value['sumInsured'];
            }
        }

        $is_pa_unnamed = false;
        $pa_unnamed_si = 0;

        $is_anti_theft = $is_voluntary_access = $is_tppd = false;
        $voluntary_excess_si = 0;

        foreach ($discounts as $key => $discount)
        {
            if ($discount['name'] == 'anti-theft device')
            {
                $is_anti_theft = true;
            }
    
            if ($discount['name'] == 'voluntary_insurer_discounts' && isset($discount['sumInsured']))
            {
                $is_voluntary_access = 'Y';
                $voluntary_excess_si = $discount['sumInsured'];
            }
    
            if ($discount['name'] == 'TPPD Cover')
            {
                $is_tppd = 'Y';
            }
        }

        $date1 = Carbon::parse($requestData->vehicle_register_date);
        $vehicle_age = $date1->diffInYears($policy_start_date);
        
        $include_consumable = false;

        // $is_consumable = false;
        $is_zero_dep = false;

        // if ($vehicle_age < 7) { // Less than 7 i.e. upto 6 and including 6
        foreach ($addons as $key => $addon) {
            if (in_array('Zero Depreciation', $addon)) {
                $is_zero_dep = true;
            }
            if (in_array('Consumable', $addon)) {
                $is_consumable = true;
            }
        }
        // }

        $is_lpg_cng_internal = false;
        if (in_array(TRIM(STRTOUPPER($mmv_data['fuel_type'])), ['OTHER','OTHERS'])) {
            if (in_array(TRIM(STRTOUPPER($requestData->fuel_type)), ['CNG', 'LPG', 'BIFUEL'])) {
                $is_lpg_cng_internal = true;
            }
        }
        else if(in_array(TRIM(STRTOUPPER($mmv_data['fuel_type'])), ['CNG', 'LPG', 'BIFUEL']))
        {
            $is_lpg_cng_internal = true;
        }
        if(in_array(TRIM(STRTOUPPER($requestData->fuel_type)), ['CNG', 'LPG', 'BIFUEL']) && config('constants.motorConstant.SMS_FOLDER') == 'ace')
        {
            $is_lpg_cng_internal = true;
        }

        $is_consumable = null;//$is_consumable ? 'Y' : null;
        $is_zero_dep = $is_zero_dep ? 'Y' : null;
        
        $vehicle_register_no = explode("-", $proposal->vehicale_registration_number);

        $vehicle_register_no = implode("", $vehicle_register_no);

        $partnerCode = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM');
        $partnerPass = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_PASSWORD_SHORT_TERM');
        $quoteServiceRequest = [
            "uniqueReferenceNo" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM') . time() . rand(10, 99), // Unique Number everytime
            "contractType" => "CVI",
            "partnerDetail" => [
                "partnerCode" => $partnerCode,
                "partnerBranch" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_BRANCH_SHORT_TERM'),
                "partnerSubBranch" => config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_SUB_BRANCH_SHORT_TERM'),
                "responseURL" =>route('cv.payment-confirm',['iffco_tokio', 'policy_type' => 'short_term']),
            ],
            //ITGICVI001
            "commercialVehicle" => [
                "commercialMakeWrapper" =>
                [
                    "makeCode" => $mmv_data['make_code'],
                    // "makeCode" => $mmv_data['model_code'],
                    // "makeCodeName" => $mmv_data['make_code_name'],
                    // "vehicleClass" => $mmv_data['class'],
                    // "vehicleSubClass" => $mmv_data['sub_class'],
                    // "vehicleSubClassName" => $mmv_data['sub_class_name'],
                    // "manufacturer" => $mmv_data['manufacturer'],
                    // "model" => $mmv_data['model'],
                    // "variant" => $mmv_data['variant'],
                    // "cc" => round($mmv_data['cc']),
                    // "seatingCapacity" => round($mmv_data['seating_capacity']),
                    // "fuelType" => $mmv_data['fuel_type'],
                ],

                "policyType" => "CP", // [ACT_ONLY, COMPREHENSIVE, COMPREHENSIVE_WITH_ZERO_DEP, CP]
                "contractType" => "CVI",
                "insuranceType" => "PARTNER_RENEWAL",

                "corporateClient" => ($is_individual ? 'N' : 'Y'),

                "stateCode" => $rto_data->state_code,
                "stateName" => $rto_data->state_code,
                "cityCode" => $rto_data->city_code,
                "cityName" => $rto_data->city_name,
                "cityDisplayName" => $rto_data->city_display_name,

                "dateOfFirstRegistration" => $first_reg_date->format('d/m/Y'),
                "monthAndYearOfRegistartion" => $first_reg_date->format('m/Y'),
                "yearOfMake" => Carbon::parse('01-'.$requestData->manufacture_year)->format('Y'),

                "inceptionDate" => $policy_start_date->format('d/m/Y'),
                "expirationDate" => $policy_end_date->format('d/m/Y'),

                "registrationNo" => $vehicle_register_no,

                "noClaimBonus" => (int)($requestData->applicable_ncb),

                "towingAndRelated" => null,
                "paValueAutoInsuredPersons" => null,
                "towingAndRelatedLimit" => null,
                "paValueAutoInsuredPersonsLimit" => null,
                "towing" => null,
                "otherAccessories" => null,
                "otherAccessoriesValue" => null,

                // ADDONS
                "consumable" => $is_consumable,

                "depreciationWaiver" => $is_zero_dep,
                "zeroDep" => null,
                // END ADDONS

                // ELECTRICAL ACCESSORIES
                "electricalAccessories" => ($is_electrical ? 'Y' : 'N'),
                "electricalAccessoriesValue" => $electrical_si,
                // END ELECTRICAL ACCESSORIES

                // LPG CNG
                "cngLpg" => ($is_lpg_cng ? 'Y' : null),
                "cngLpgFitted" => ($is_lpg_cng_internal ? 'Y' : null),
                "cngLpgValue" => $lpg_cng_si,

                "vehicleDrivenByCngLpg" => null,// ($is_lpg_cng ? 'Y' : 'N'),
                "companyFittedCngLpg" => null,// ($is_lpg_cng ? 'Y' : 'N'),
                "valueOfCngLpgKit" => null,// $lpg_cng_si,

                // "cngLpg" => "Y",
                // "cngLpgFitted" => null,
                // "cngLpgValue" => 2000,
        
                // "vehicleDrivenByCngLpg" => null,
                // "companyFittedCngLpg" => null,
                // "valueOfCngLpgKit" => null,
                // END LPG CNG

                // CPA
                "paOwnerDriver" => $is_pa_cover_owner_driver,
                "paValueAutoOwnerDriver" => null,
                // END CPA

                // PA TO PAID DRIVER                
                // "insurePaidDriver" => ($is_paid_driver ? 'Y' : null),
                'paPaidDriver' => $paid_driver_si,
                // END PA TO PAID DRIVER

                // PA TO PASSENGER
                "passangersUnderPersonnelAccidentCover" => null,
                "passangersUnderPersonnelAccidentCoverLimit" => null,

                "paToPassenger" => ($is_pa_unnamed ? 'Y' : null),
                "paToPassengerTotalMember" => null,
                "paToPassengerSumInsured" => $pa_unnamed_si,

                "imt43TotalPassenger" => null,
                "nonFarePayingPaxTotalPassenger" => null,
                // END PA TO PASSENGER

                // LEGAL LIABILITY
                "llPaidDriverCleanerConductor" => ($is_ll_paid ? 'Y' : null),
                "llPaidDriverCleanerConductorTotalPassenger" => ($is_ll_paid ? '1' : null),
                // END LEGAL LIABILITY

                "imt23" => null,
                "imt34" => null,
                "imt36" => null,
                "imt42" => null,
                "imt43" => null,
                "imt44" => null,
                "nonFarePayingPax" => null,
                "monthOfRegistration" => date('m', strtotime($requestData->vehicle_register_date)),
                "yearOfRegistration" => date('Y', strtotime($requestData->vehicle_register_date)),
                "engineNo" => $proposal->engine_number,
                "chasisNo" => $proposal->chassis_number,

                "previousInsurer" => (($is_new || $noPreviousPolicyData) ? "" : $proposal->previous_insurance_company),
                "previousPolicyNo" => (($is_new || $noPreviousPolicyData) ? "" : $proposal->previous_policy_number),
                // "previousPolicyEndDate" => Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y'),
                "previousPolicyExpiryDate" => ($noPreviousPolicyData ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y')),
            ],
        ];
        $quoteServiceRequest["commercialVehicle"]["defaultIDV"] = $idv;

        $customerAddress = $proposal->address_line1 . ', ' . $proposal->address_line2 . ', ' . $proposal->address_line3 . ', ,' . $proposal->city . ', ' .  $proposal->pincode . ', ' .  $proposal->state . ', IND';  
        $customerFullAddress = $proposal->address_line1 . ', ' . $proposal->address_line2 . ', ' . $proposal->address_line3 . ', ,' . $proposal->city . ', ' .  $proposal->state . ' - ' .  $proposal->pincode;  

        if ($is_individual)
        {
            if ($proposal->gender == "M" || $proposal->gender == "Male" || $proposal->gender == "MALE")
            {
                $insured_prefix = "MR";
            }
            else
            {
                if (($proposal->gender == "F" || $proposal->gender == "Female" || $proposal->gender == "FEMALE") && $proposal->marital_status == "Single")
                {
                    $insured_prefix = "MISS";
                }
                else
                {
                    $insured_prefix = "MRS";
                }
            }
        }
        else
        {
            $insured_prefix = "M/S";
        }

        if($is_breakin) {
            $quoteServiceRequest["commercialVehicle"]["inspectionAgency"] = 'LiveChek';
            $quoteServiceRequest["commercialVehicle"]["inspectionDate"] = date('d/m/Y');
            $quoteServiceRequest["commercialVehicle"]["inspectionNo"] = 'test' . time() . rand(10, 99);
            $quoteServiceRequest["commercialVehicle"]["inspectionStatus"] = 'APPROVED';
        }
        if($isBreakInMorethan90days == 'Y')
        {
            $quoteServiceRequest["commercialVehicle"]["breakInMorethan90days"] = $isBreakInMorethan90days;
        }
        $proposalServiceRequest = $quoteServiceRequest;

        $address_data = [
            'address' => $proposal->address_line1,
            'address_1_limit'   => 30,
            'address_2_limit'   => 30,
            'address_3_limit'   => 30,
            'address_4_limit'   => 30
        ];
        $getAddress = getAddress($address_data);

        if($is_individual && ($proposal->last_name === null || $proposal->last_name == ''))
        {
            $proposal->last_name = '.';
        }

        $proposalServiceRequest["commercialVehicle"]["policyProfile"] = [
            "contractType" => "CVI",

            "salutation" => $insured_prefix,
            "firstName" => $proposal->first_name,
            "lastName" => $proposal->last_name,
            "fullName" => $proposal->first_name . ' ' . $proposal->last_name,

            "mobile" => $proposal->mobile_number,
            "emailId" => $proposal->email,

            "aadharNumber" => null,
            "panNumber" => null,

            "gender" => $proposal->gender,
            "maritalStatus" => $proposal->marital_status,
            "occupation" => $proposal->occupation ?? null,

            "address1" => $getAddress['address_1'],
            "address2" => empty($getAddress['address_2']) ? '.' : $getAddress['address_2'],
            "address3" => $getAddress['address_3'],
            "address4" => $getAddress['address_4'],
            "cityCode" => $proposal->city,
            "pincode" => $proposal->pincode,
            "stateCode" => $proposal->state,
            "country" => "IND",
            "address" => $customerAddress,
            "fullAddress" => $customerFullAddress,
            "dateOfBirth" => ($is_individual && $proposal->dob != '') ? Carbon::parse($proposal->dob)->format('d/m/Y') : '',
        ];

        if(!$is_individual)
        {
            $proposalServiceRequest["commercialVehicle"]["policyProfile"] = [
                "name" => $proposal->first_name,

                "mobile" => $proposal->mobile_number,
                "emailId" => $proposal->email,

                "address1" => $getAddress['address_1'],
                "address2" => empty($getAddress['address_2']) ? '.' : $getAddress['address_2'],
                "address3" => $getAddress['address_3'],
                "address4" => $getAddress['address_4'],
                "address5" => '',

                "cityCode" => $proposal->city,
                "pincode" => $proposal->pincode,
                "country" => "IND",

                "gstinNo" =>$proposal->gst_number ?? '',
                "dateOfBirth" => !empty($proposal->dob) ? Carbon::parse($proposal->dob)->format('d/m/Y') : '',
            ];
        }

        // $quoteServiceRequest["commercialVehicle"]["vehicleName"] = null;

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                "Authorization: Basic " . base64_encode($partnerCode . ":" . $partnerPass),
                "Content-Type: application/json"
            ],
            'requestMethod' => 'post',
            'requestType' => 'JSON',
            'section' =>  'PCV',
            'method' => 'Quote Calculation - Proposal',
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
        ];
    
        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_QUOTE_URL_SHORT_TERM'), $quoteServiceRequest, 'iffco_tokio', $additional_data);
        $quoteServiceResponse = $get_response['response'];

        if (empty($quoteServiceResponse)) {
            return [
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => 'Insurer not Reacheable',
            ];
        }

        $premWebserviceId = $get_response['webservice_id'];
    
        $quoteServiceResponse = json_decode($quoteServiceResponse, true);

        if ($quoteServiceResponse === null || empty($quoteServiceResponse)) {
            return [
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => 'Insurer not Reacheable',
            ];
        }

        if (isset($quoteServiceResponse['error']) && !empty($quoteServiceResponse['error'])) {
            if(!is_array($quoteServiceResponse['error']))
            {
                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => $quoteServiceResponse['error'],
                ];
            }
            else if(count($quoteServiceResponse['error']) > 0)
            {
                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => implode(', ', array_column($quoteServiceResponse['error'], 'errorMessage')),
                ];
            }
            else
            {
                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => 'Insurer not Reacheable',
                ];
            }
        }

        $premiumData = $quoteServiceResponse;

        $idv = round($premiumData['basicIDV']);

        $odPremium = $nonElectricalPremium = $electricalPremium = $cngOdPremium = $totalOdPremium = 0;
        $tpPremium = $legalLiabilityToDriver = $paUnnamed = $cngTpPremium = $totalTpPremium = 0;
        $ncbAmount = $aaiDiscount = $antiTheft = $voluntaryDeductible = $otherDiscount = $tppdDiscount = $totalDiscountPremium = 0;
    
        $odPremium = isset($premiumData['basicOD']) ? round(abs($premiumData['basicOD'])) : 0;
        $electricalPremium = isset($premiumData['electricalOD']) ? round(abs($premiumData['electricalOD'])) : 0;
        $cngOdPremium = isset($premiumData['cngOD']) ? round(abs($premiumData['cngOD'])) : 0;
    
        $totalOdPremium = $odPremium + $nonElectricalPremium + $electricalPremium + $cngOdPremium;
    
        $tpPremium = isset($premiumData['basicTP']) ? round(abs($premiumData['basicTP'])) : 0;
        $legalLiabilityToDriver = isset($premiumData['llDriverTP']) ? round(abs($premiumData['llDriverTP'])) : 0;
        $paUnnamed = isset($premiumData['paPassengerTP']) ? round(abs($premiumData['paPassengerTP'])) : 0;
        $cngTpPremium = isset($premiumData['cngTP']) ? round(abs($premiumData['cngTP'])) : 0;
    
        $totalTpPremium = $tpPremium + $legalLiabilityToDriver + $paUnnamed + $cngTpPremium;
    
        $paOwnerDriver = isset($premiumData['paOwnerDriverTP']) ? round(abs($premiumData['paOwnerDriverTP'])) : 0;
    
        $ncbAmount = isset($premiumData['ncb']) ? round(abs($premiumData['ncb'])) : 0;

        $antiTheft = isset($premiumData['antiTheftDisc']) ? round(abs($premiumData['antiTheftDisc'])) : 0;
        $voluntaryDeductible = isset($premiumData['voluntaryExcessDisc']) ? round(abs($premiumData['voluntaryExcessDisc'])) : 0;

        $tppdDiscount = isset($premiumData['tppdDiscount']) ? round(abs($premiumData['tppdDiscount'])) : 0;
    
        $otherDiscount = isset($premiumData['premiumDiscount']) ? round(abs($premiumData['premiumDiscount'])) : 0;
    
        $totalDiscountPremium = $ncbAmount + $aaiDiscount + $antiTheft + $voluntaryDeductible + $otherDiscount + $tppdDiscount;

        $zero_dep_amount = isset($premiumData['nilDep']) ? round(abs($premiumData['nilDep'])) : 0;
        $consumable_amount = isset($premiumData['consumablePrem']) ? round(abs($premiumData['consumablePrem'])) : 0;

        $totalAddonPremium = $zero_dep_amount + $consumable_amount;

        $totalBasePremium = $premiumData['grossPremiumAfterDiscount'];
        $serviceTax = round(abs($totalBasePremium * 18/100));
        $totalPayableAmount = $quoteServiceResponse['premiumPayble'];

        $proposalServiceRequest["uniqueReferenceNo"] = config('constants.cv.iffco.IFFCO_TOKIO_PCV_PARTNER_CODE_SHORT_TERM') . time() . rand(10, 99);

        $proposalServiceRequest["commercialVehicle"]["paToPassengerTotalMember"] = ($is_pa_unnamed ? '1' : null);

        $proposalServiceRequest["partnerDetail"]["paymentAtPartnerEnd"] = 'N';//config('constants.cv.iffco.IFFCO_TOKIO_PCV_PAYMENT_AT_PARTNER_END_SHORT_TERM');
        
        $proposalServiceRequest["commercialVehicle"]["itgiKYCReferenceNo"] = $proposal->ckyc_reference_id;// As per git ID #12827
        
        $proposalServiceRequest["commercialVehicle"]["paOwnerDriverNominee"] = $proposal->nominee_name;
        $proposalServiceRequest["commercialVehicle"]["paOwnerDriverNomineeRelationship"] = $proposal->nominee_relationship;

        $proposalServiceRequest["commercialVehicle"]["policyPeriod"] = $is_three_months ? "3" : "6";

        $proposalServiceRequest["commercialVehicle"]["insuranceType"] = "RENEWAL";

        // paPaidDriver

        $additional_details = json_decode($proposal->additional_details, true);
        
        $financerAgreementType = $nameOfFinancer = $hypothecationCity = '';

        if ($proposal->is_vehicle_finance == '1') 
        {
            $financerAgreementType  = 'HY';
            $financer = iffco_tokioFinancierMaster::where('code', $proposal->name_of_financer)
                ->orWhere('name', 'like', "%{$proposal->name_of_financer}%")
                ->distinct()
                ->limit(1)
                ->get()
                ->toArray();
            if (!empty($financer)) {
                $nameOfFinancer = $financer[0]['code'];
            } else {
                return [
                    'status' => false,
                    'message' => 'Financer details not found',
                    'request' => [
                        'financer' =>$proposal->name_of_financer
                    ]
                ];
            }
        }
       
        
        
        $proposalServiceRequest["commercialVehicle"]["hypothecation"] = $financerAgreementType;
        $proposalServiceRequest["commercialVehicle"]["intrestedParty"] = $nameOfFinancer;

        $proposalServiceRequest["commercialVehicle"]["isValidDrivingLicense"] = $isValidDrivingLicense;



        // $proposalServiceRequest["commercialVehicle"]["inspectionAgency"] = $proposal->previous_policy_number;
        // $proposalServiceRequest["commercialVehicle"]["inspectionDate"] = $proposal->previous_policy_number;
        // $proposalServiceRequest["commercialVehicle"]["inspectionNo"] = $proposal->previous_policy_number;
        // $proposalServiceRequest["commercialVehicle"]["inspectionStatus"] = $proposal->previous_policy_number;



        $proposalServiceRequest["commercialVehicle"]["premiumPayble"] = $quoteServiceResponse['premiumPayble'];

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                "Authorization: Basic " . base64_encode($partnerCode . ":" . $partnerPass),
                "Content-Type: application/json"
            ],
            'requestMethod' => 'post',
            'requestType' => 'JSON',
            'section' =>  'PCV',
            'method' => 'Proposal submission - Proposal',
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
        ];

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            //$proposalServiceRequest['itgiKYCReferenceNo'] = $proposal->ckyc_reference_id; //// As per git ID #12827
        }
        $get_response = getWsData(config('constants.cv.iffco.IFFCO_TOKIO_PCV_PROPOSAL_URL_SHORT_TERM'), $proposalServiceRequest, 'iffco_tokio', $additional_data);
        $proposalServiceResponse = $get_response['response'];

        if (empty($proposalServiceResponse)) {
            return [
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => 'Insurer not Reacheable',
            ];
        }
    
        $proposalServiceResponse = json_decode($proposalServiceResponse, true);

        if ($proposalServiceResponse === null || empty($proposalServiceResponse)) {
            return [
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status' => false,
                'message' => 'Insurer not Reacheable',
            ];
        }

        if (isset($proposalServiceResponse['error']) && !empty($proposalServiceResponse['error'])) {
            if(!is_array($proposalServiceResponse['error']))
            {
                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => $proposalServiceResponse['error'],
                ];
            }
            else if(count($proposalServiceResponse['error']) > 0)
            {
                return [
                    'premium_amount' => 0,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'status' => false,
                    'message' => implode(', ', array_column($proposalServiceResponse['error'], 'errorMessage')),
                ];
            }
            else
            {
                return [
                    'premium_amount' => 0,
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => 'Insurer not Reacheable',
                ];
            }
        }

        if(isset($proposalServiceResponse['statusMessage']) && $proposalServiceResponse['statusMessage'] != 'SUCCESS')
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Proposal Service status - '.$proposalServiceResponse['statusMessage'],
            ];
        }

        $vehicleDetails = [
            'manufacture_name' => $mmv_data['manufacturer'],
            'model_name' => $mmv_data['model'],
            'version' => $mmv_data['variant'],
            'fuel_type' => $mmv_data['fuel_type'],
            'seating_capacity' => $mmv_data['seating_capacity'],
            'carrying_capacity' => $mmv_data['seating_capacity'] - 1,
            'cubic_capacity' => $mmv_data['cc'],
            'gross_vehicle_weight' => '',
            'vehicle_type' => 'Taxi',
        ];

        $totalOdPremium = $odPremium + $nonElectricalPremium + $electricalPremium + $cngOdPremium;
        $totalTpPremium = $tpPremium + $legalLiabilityToDriver + $paUnnamed + $cngTpPremium;
        $totalDiscountPremium = $ncbAmount + $aaiDiscount + $antiTheft + $voluntaryDeductible + $otherDiscount + $tppdDiscount;

        $proposal->policy_start_date = date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date)));
        $proposal->policy_end_date = date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date)));
        $proposal->proposal_no = $proposalServiceResponse['orderNo'];
        $proposal->unique_proposal_id = $proposalServiceRequest["uniqueReferenceNo"];
        $proposal->od_premium = round($totalOdPremium) - ($totalDiscountPremium - $tppdDiscount);
        $proposal->tp_premium = $totalTpPremium + $paOwnerDriver;
        $proposal->totalTpPremium = $totalTpPremium;
        $proposal->addon_premium = $totalAddonPremium;
        $proposal->cpa_premium = $paOwnerDriver;
        $proposal->final_premium = round($totalBasePremium);
        $proposal->total_premium = round($totalBasePremium);
        $proposal->service_tax_amount = round($serviceTax);
        $proposal->final_payable_amount = round($totalPayableAmount);
        $proposal->ic_vehicle_details = $vehicleDetails;
        $proposal->ncb_discount = abs($ncbAmount);
        $proposal->total_discount = $totalDiscountPremium;
        $proposal->electrical_accessories = $electricalPremium;
        $proposal->unique_quote = $proposalServiceResponse['orderNo'];
        $proposal->non_electrical_accessories = $nonElectricalPremium;
        $proposal->policy_type = 'short_term';

        $additional_details['iffco']['ptnrTransactionLogId'] = $proposalServiceResponse['ptnrTransactionLogId'];
        $additional_details['iffco']['orderNo'] = $proposalServiceResponse['orderNo'];
        $additional_details['iffco']['traceNo'] = $proposalServiceResponse['traceNo'];
        $additional_details['iffco']['proposalServiceRequest'] = $proposalServiceRequest;
        $additional_details['iffco']['quoteServiceRequest'] = $quoteServiceRequest;
        $additional_details['iffco']['premium_type'] = $premium_type;

        $proposal->additional_details_data = $additional_details;

        $data['user_product_journey_id'] = $enquiryId;
        $data['ic_id'] = $master_policy->insurance_company_id;
        $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
        $data['proposal_id'] = $proposal->user_proposal_id;

        $proposal->save();

        IffcoTokioPremiumDetailController::saveShortTermPremiumDetails($premWebserviceId);

        if ($is_breakin && !$tp_only) {
            $breakinExists = CvBreakinStatus::where('user_proposal_id', $proposal->user_proposal_id)->first();
            if ($breakinExists) {
                return response()->json([
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "BreakIn already exists. Inspection No. : " . $breakinExists->breakin_number . " Inspection status : " . $breakinExists->breakin_status,
                ]);
            }
            $payload = [
                'user_name' => $proposal->first_name . ' ' . $proposal->last_name,
                'user_email' => $proposal->email,
                'reg_number' => $proposal->vehicale_registration_number,
                'veh_manuf' => $mmv_data['manufacturer'],
                'veh_model' => $mmv_data['model'],
                'mobile_name' => $proposal->mobile_number,
                'fuel_type' => $mmv_data['fuel_type'],
                'veh_variant' => $mmv_data['variant'],
                'vehicle_category' => 'car', // Should be as per Documentation
                'enquiry_id' => $enquiryId,
                'address' => implode(', ', [$proposal->address_line1, $proposal->address_line2, $proposal->address_line3, $proposal->state]),
                'city' => $proposal->city,
                'model_year' => $requestData->manufacture_year,
                'section' => 'cv',
                'ic_name' => 'iffco_tokio'
            ];
            
            $obj = new LivechekBreakinController();
            $create_breakin = $obj->LiveChekBreakin($payload);
            if ($create_breakin['status']) { // If the status is true then LiveChek API is success
                $inspection_no = isset($create_breakin['data']['data']) ? $create_breakin['data']['data']['refId'] : $create_breakin['data']['refId'];
                $proposal->is_breakin_case = 'Y';
                $proposal->save();
                $cvBreakinStatus = [
                    'ic_id' => $master_policy->insurance_company_id,
                    'user_proposal_id' => $proposal->user_proposal_id,
                    'breakin_number' => $inspection_no,// Get inspection no. from LiveChek
                    'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                    'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                    'breakin_response' => json_encode($create_breakin['data']),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                DB::table('cv_breakin_status')->updateOrInsert(['user_proposal_id' => $proposal->user_proposal_id], $cvBreakinStatus);
                
                $is_breakin = 'Y';
                $data['stage'] = STAGE_NAMES['INSPECTION_PENDING'];
            }else{
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => "Error while generating vehicle inspection. Please try after sometime.",
                ];
            }
        } else {
            $is_breakin = '';
            $inspection_no = '';
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
        }
        updateJourneyStage($data);

        return response()->json([
            'status' => true,
            'msg' => "Proposal Submitted Successfully!",
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'data' => [
                'proposalId' => $proposal->user_proposal_id,
                'userProductJourneyId' => $data['user_product_journey_id'],
                'proposalNo' => $proposalServiceResponse['orderNo'],
                'finalPayableAmount' => $proposal->final_payable_amount,
                'is_breakin' => $is_breakin,
                'inspection_number' => $inspection_no,
            ],
        ]);
    }

}
