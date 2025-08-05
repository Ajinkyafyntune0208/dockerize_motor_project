<?php

namespace App\Http\Controllers\Proposal\Services;

use DateTime;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\PolicyDetails;
use App\Models\SelectedAddons;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\DB;
use App\Models\ChollaMandalamCvModel;
use App\Models\ChollaMandalamCvRtoMaster;
use App\Models\chollamandalamPincodeMaster;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class chollamandalamSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $cholla_model = new ChollaMandalamCvModel();
        DB::enableQueryLog();

        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $request_data=(array)$requestData;
        $productData = getProductDataByIc($request['policyId']);
        $parent_id = get_parent_code($productData->product_sub_type_id);

        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed because zero dep is not part of your previous policy',
            ];
        } */
        $getdata = json_encode($proposal);
        $proposal = json_Decode($getdata);
        $additional_details = json_decode($proposal->additional_details);

        $proposal_date  = date('d/m/Y');
        $is_new         = (($proposal->business_type == 'N')  ? true : false);
        $is_individual  = (($requestData->vehicle_owner_type == 'I')    ? true : false);
        $is_financed    = (($proposal->financer_agreement_type == '' || $proposal->financer_agreement_type == 'None')    ? false : true);
        $new_vehicle        = (($requestData->business_type == 'newbusiness') ? true : false);
        $quote = QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)->first();

        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
        // $mmv = get_mmv_details($productData,$requestData->version_id,'cholla_mandalam')/;

        $mmv = get_mmv_details($productData,$requestData->version_id,'cholla_mandalam');

      if($mmv['status'] == 1)
        {
            $mmv = $mmv['data'];
        }
        else
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }
        $mmv = (object) array_change_key_case((array) $mmv,CASE_LOWER);

        $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $is_package         = (($premium_type == 'comprehensive') ? true : false);
        $is_liability       = (($premium_type == 'third_party') ? true : false);
        $is_od              = (($premium_type == 'own_damage') ? true : false);

        $gender                                 = $proposal->gender;
        $marital_status                         = $proposal->marital_status;
        $occupation                             = $proposal->occupation;
        $AgreementType                          = $proposal->financer_agreement_type;

        $previous_insurer_address_details = DB::table('insurer_address')->where('Insurer', 'like', $proposal->insurance_company_name . '%')->first();
        $previous_insurer_address_details = keysToLower($previous_insurer_address_details);


        if ($is_od) {

            //            $tp_previous_insurer_address_details    = get_details(
            //                'previous_insurer_mappping',
            //                ['addressLine1', 'addressLine2', 'pincode'],
            //                ['previous_insurer' => $tp_insurercode]
            //            );
            //            $tp_policy_address1           = $tp_previous_insurer_address_details[0]['addressLine1'];
            //            $tp_policy_address2           = $tp_previous_insurer_address_details[0]['addressLine2'];
            //            $tp_policy_pincode            = $tp_previous_insurer_address_details[0]['pincode'];
        } else {
            $tp_policy_address1 = $tp_policy_address2 = $tp_policy_pincode = '';
        }

        if ($is_od) {
            //            $tp_insurercode = get_previous_insurer($tp_insurercode,'cholla_mandalam');
            $tp_insurercode = '';
        }

        //        $is_zero_dep = (($productData->zero_dep == '0') ? true : false);

        $product_name = $productData->product_name;
        $company_name = $productData->company_name;
        $reg_no = '';
        if ($proposal->vehicale_registration_number != 'NEW') {
            $reg_no = isset($proposal->vehicale_registration_number) ? $proposal->vehicale_registration_number : '';
        }

        if (in_array(substr($reg_no, 0, 2), ['DL', 'dl'])) {
            $regNoArray = explode('-', $reg_no);
            if (isset($regNoArray[1]) && is_numeric($regNoArray[1]) && strlen($regNoArray[1]) >= 2 && $regNoArray[1][0] == 0) {
                $regNoArray[1] = substr_replace($regNoArray[1], '', 0, 1);
            }
            $reg_no = implode('-', $regNoArray);
        } //if the reg_no="DL-08-BB-5382", it will return "DL-8-BB-5382"

        //     $rto_data = DB::select("select cmpm.state_desc as state, cmrm.* from cholla_mandalam_rto_master as cmrm inner join cholla_mandalam_pincode_master as cmpm ON
        //  cmrm.num_state_code = cmpm.state_cd = 'left' where cmrm.rto ='$requestData->rto_code' limit 1");

        //     $rto_data = (array)$rto_data[0];
        $rto_code = explode('-', $requestData->rto_code);
        if ($rto_code[0] == 'OD') {
            $requestData->rto_code = 'OR-' . $rto_code[1];
        }
        $rto_data = ChollaMandalamCvRtoMaster::join('cholla_mandalam_pincode_master as pin', 'pin.state_cd', '=', 'num_state_code')
            ->where('rto', $requestData->rto_code)
            ->select('*', 'pin.state_desc as state')
            ->limit(1)
            ->first();
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();


        #$cv_age = cv_age($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date);
        $expdate = (($requestData->previous_policy_expiry_date == 'New') || ($requestData->business_type == 'breakin')
            ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($expdate);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $cv_age = ceil($age / 12);

        $addon                      = [];
        $AddonReq                   = 'N';
        if ($productData->zero_dep == '0' && !$is_liability) {
            $nil_depreciation = '-1';
        } else {
            $nil_depreciation = '0';
        }

        $cpa_cover          = false;
        $rsa          = false;
        $consumable         = false;
        $key_replacement    = false;
        $engine_protector   = false;
        $loss_of_belonging = false;
        $is_cpa = false;
        $is_zero_dep = false;
        //$imt23 = false;
      
        $addon_req = 'Y';
        $tenure = 0;
        if (isset($selected_addons->compulsory_personal_accident[0]['name']) && !$is_od) {
            $is_cpa = true;
            $tenure = 1;
            $tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure']) ? $selected_addons->compulsory_personal_accident[0]['tenure'] : $tenure;
        }

        
        if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '') {
            $addons = $selected_addons->applicable_addons;
      
            foreach ($addons as $value) {
                if ($value['name'] == 'Engine Protector'  && $cv_age <= 5) {
                    $engine_protector = true;
                }
                if ($value['name'] == 'Zero Depreciation' && $cv_age <= 3) {
                    $is_zero_dep = true;
                }
                if ($value['name'] == 'IMT - 23'  && $interval->y < 10) {
                    $imt23 = true;
                }
                if ($value['name'] == 'Road Side Assistance' && $cv_age <= 5) {
                    $rsa = true;
                }
                if ($value['name'] == 'Key Replacement' && $cv_age <= 5) {
                    $key_replacement = true;
                }
                if ($value['name'] == 'Consumable'  && $cv_age <= 5) {
                    $consumable = true;
                }
                if ($value['name'] == 'Loss of Personal Belongings'  && $cv_age <= 5) {
                    $loss_of_belonging = true;
                }
            }
        }

        $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
        ->first();
        // if ($masterProduct->product_identifier == 'WITHOUT_ADDONS') {
        //     $is_zero_dep = false;
        //     $imt23 = false;
        //     $engine_protector = 'No';
        //     $rsa = 'N';
        //     $key_replacement = 'N';
        //     $consumable = 'N';
        //     $loss_of_belonging = 'N';
        // }

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;
        $bifuel = false;
        $BiFuelKitSi = 0;

        if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
            $accessories = ($selected_addons->accessories);
            foreach ($accessories as $value) {
                if ($value['name'] == 'Electrical Accessories' && !$is_liability) {
                    $IsElectricalItemFitted = 'true';
                    $ElectricalItemsTotalSI = $value['sumInsured'];
                } else if ($value['name'] == 'Non-Electrical Accessories' && !$is_liability) {
                    $IsNonElectricalItemFitted = 'true';
                    $NonElectricalItemsTotalSI = $value['sumInsured'];
                } else if ($value['name'] == 'External Bi-Fuel Kit CNG/LPG') #&& $is_package
                {
                    $type_of_fuel = '5';
                    $bifuel = true;
                    $Fueltype = 'CNG';
                    $BiFuelKitSi = $value['sumInsured'];

                    if ($BiFuelKitSi < 10000 || $BiFuelKitSi > 30000) {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => 'LPG/CNG cover value should be between 10000 to 30000',
                            'selected value' => $BiFuelKitSi
                        ];
                    }
                }
            }
        }

        switch ($requestData->business_type) {
            case 'rollover':
                $business_type = 'Roll Over';
                break;
            case 'newbusiness':
                $business_type = 'New';
                break;
            default:
                $business_type = $requestData->business_type;
                break;
        }

        //PA for un named passenger
        $IsPAToUnnamedPassengerCovered = false;
        $PAToUnNamedPassenger_IsChecked = false;
        $PAToUnNamedPassenger_NoOfItems = '';
        $PAToUnNamedPassengerSI = 0;
        
    //LL PAID
        $IsLLPaidDriver = !$is_od ? 1 : 0;#mandatory
        $is_ll_Paid_Cleaner = false;
        $is_ll_Paid_Coolies = false;
        $is_ll_Paid_Driver = false;

        $is_ll_Paid_Cleaner_no = 0;
        $is_ll_Paid_Coolies_no = 0;
        $ll_Paid_Driver_no = 0;
        
      
        if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
            $additional_covers = $selected_addons->additional_covers;
            //            print_r($additional_covers);die;
            foreach ($additional_covers as $value) {
                // if ($value['name'] == 'Unnamed Passenger PA Cover') {
                //     $IsPAToUnnamedPassengerCovered = true;
                //     $PAToUnNamedPassenger_IsChecked = true;
                //     $PAToUnNamedPassenger_NoOfItems = '1';
                //     $PAToUnNamedPassengerSI = $value['sumInsured'];
                //     if ($value['sumInsured'] != 100000) {
                //         return [
                //             'premium_amount'    => '0',
                //             'status'            => false,
                //             'message'           => 'Unnamed Passenger value should be 100000 only.',
                //         ];
                //     }
                //     if ($value->name == 'LL paid driver') {
                //         $is_ll_Paid_Driver = 'true';
                //         $is_ll_Paid_Cleaner = 'true';
                //         $is_ll_Paid_Coolies = 'true';

                //         $ll_Paid_Driver_no = 1;
                //     }

                if ($value['name'] == 'LL paid driver/conductor/cleaner') {
                    $is_ll_Paid_Driver = in_array('DriverLL', $value['selectedLLpaidItmes']) ? true : false;
                    $ll_Paid_Driver_no = $value['LLNumberDriver'] ?? 0;    
                    
                    
                    $is_ll_Paid_Cleaner = in_array('CleanerLL', $value['selectedLLpaidItmes']) ? true : false;
                    $is_ll_Paid_Cleaner_no = $value['LLNumberCleaner'] ?? 0;


                    $is_ll_Paid_Coolies = in_array('CooliesLL',$value['selectedLLpaidItmes']) ? true : false;
                    $is_ll_Paid_Coolies_no =$value['LLNumberConductor']?? 0;
                   
                }
                // }

                /* if($value['name'] == 'LL paid driver' && !$is_od)
            {
                $IsLLPaidDriver = 'Yes';
            } */
                if ($value['name'] == 'LL paid driver' && !$is_od) {
                    $ll_paid_driver = true;
                }
            }
            /* if($value['name'] == 'LL paid driver' && !$is_od)
                {
                    $IsLLPaidDriver = 'Yes';
                } */
        }


        $IsAntiTheftDiscount = false;

        if ($selected_addons && $selected_addons->discount != NULL && $selected_addons->discount != '') {
            $discount = $selected_addons->discount;
            foreach ($discount as $value) {
                if ($value->name == 'anti-theft device') {
                    $IsAntiTheftDiscount = 'true';
                }
            }
        }


        $voluntary_deductable       = ((!isset($voluntary_deductable)) ? 0 : $voluntary_deductable);
        $voluntary_deductable_flag  = (intval($voluntary_deductable) != 0) ? 'True' : 'False';

        $RegistrationNo             = '';
        $PreviousPolExpDt           = '';
        $usedCar                    = 'N';
        $NCBDeclartion              = 'N';
        $applicable_ncb             = '0';

        $posp_type          = 'P';
        $posp_pan           = '';

        // ro specific
        $tp_start_date = '';
        $tp_end_date = '';
        $policy_start_date = date('Y-m-d');
        if ($requestData->business_type != 'newbusiness') {
            $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($proposal->prev_policy_expiry_date)));
            $policy_end_date = date('d/m/Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
        } else {
            $policy_end_date = date('d/m/Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
        }
        $tp_insurance_company = '';
        $tp_insurance_number = '';
        if ($is_od) {
            $tp_start_date = $proposal->tp_start_date;
            $tp_end_date = $proposal->tp_end_date;
            $tp_insurance_company = $proposal->tp_insurance_company;
            $tp_insurance_number = $proposal->tp_insurance_number;
        }

        $od_rsd             = date('d-m-Y', strtotime('-1 year +1 day', strtotime($proposal->prev_policy_expiry_date)));
        $od_red             = date('d-m-Y', strtotime($proposal->prev_policy_expiry_date));

        $NewCar = 'N';
        $RollOver = 'Y';
        $Business_code = 'Roll Over';
        $cpa_cover_period = 1;
        $txt_cover_period = 1;
        $PreviousPolExpDt = date('d/m/Y', strtotime($proposal->prev_policy_expiry_date));
        
        $claims_made = $requestData->is_claim;
        if ($new_vehicle) {
            $claims_made = 'Y';
        }
        if ($claims_made == 'N') {
            $is_ncb_apllicable = true;
            $NCBDeclartion = 'Y';
            $yn_claim = 'no';
            $applicable_ncb = $requestData->applicable_ncb;
            $no_claim_bonus = $requestData->previous_ncb;
        } else {
            $is_ncb_apllicable = false;
            $yn_claim = 'yes';
            $no_claim_bonus = 0;
        }
        if ($requestData->is_claim == 'Y') {
            $no_claim_bonus = $requestData->previous_ncb;
        }
        $acc_cover_unnamed_passenger = $requestData->unnamed_person_cover_si;
        if ($acc_cover_unnamed_passenger == '25000') {
            $acc_cover_unnamed_passenger = '50000';
        }

        $is_aa_apllicable = false;

        //        // COVERS
        $automobile_association_flag                = 'False';
        $electrical_flag                            = (($requestData->electrical_acessories_value != '') ? 'True' : 'False');
        $non_electrical_flag                        = (($requestData->nonelectrical_acessories_value != '') ? 'True' : 'False');
        $unnamed_passenger_cover_flag               = (($requestData->unnamed_person_cover_si != '') ? 'True' : 'False');
        $pa_cover_driver_flag                       = (($requestData->pa_cover_owner_driver != 'N') ? 'True' : 'False');



        $is_applicable['motor_electric_accessories']        = ((!$is_liability && $IsElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_non_electric_accessories']    = ((!$is_liability && $IsNonElectricalItemFitted == 'true') ? true : false);
        $is_applicable['motor_lpg_cng_kit']                 = ((($bifuel && $BiFuelKitSi >= 10000 && $BiFuelKitSi <= 30000)) ? true : false);
      
        $fuel_type = strtoupper($mmv->fueltype);

        // $cpa_cover_flag     = ($is_cpa ? '-1' : '0');
        // $cpa_cover_period   = (($is_cpa) ? (($is_new) ? '3' : '1') : '');

        //        $is_sapa = (!$is_cpa && isset($cpa_reason) && ($cpa_reason == 'cpaWithGreat15' || $cpa_reason == 'cpaWithOtherPolicy')) ? true : false;

        $is_sapa = true;


        // $is_pa_unnamed      = ($unnamed_passenger_cover_flag == 'True') ? true : false;
        // $is_pa_paid_driver  = ($is_ll_Paid_Driver == '1') ? true : false;
        // $pa_named           = false;


        $fuel_type_cng = false;
        if (isset($mmv->fyntune_version['fuel_type']) && in_array(strtoupper($mmv->fyntune_version['fuel_type']), ['CNG', 'LPG'])) {
            $fuel_type_cng = true;
        }
        $product_id = config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_PRODUCT_ID');
        $request_data['first_reg_date'] = $requestData->vehicle_register_date;
        $request_data['policy_type']    = ($is_package ? 'Comprehensive' : ($is_liability ? 'Liability' : 'Standalone OD'));

        $request_data['make']           = $mmv->manufacturer;
        $request_data['model']          = $mmv->vehiclemodelcode;
        $request_data['fuel_type']      = $mmv->fueltype;
        $request_data['cc']             = $mmv->cubiccapacity;
        $request_data['showroom_price'] = $mmv->ex_show_room;
        $request_data['enquiryId'] = $enquiryId;
        $request_data['rto_code'] = $rto_data['txt_rto_location_code'];
        $request_data['quote']          = $quote;
        $request_data['company']        = $company_name;
        $request_data['product']        = $product_name;
        $request_data['section']        = 'CV';
        $request_data['proposal_id']    = $proposal->user_proposal_id;
        $request_data['method']         = 'Token Generation - Quote';
        $request_data['Dor']    = (int)($cholla_model->get_excel_date($requestData->vehicle_register_date));
        $request_data['sub_Class'] = (int)(($mmv->sub_class == 'A1- 4 WHEELER VEHICLES (PUBLIC)')?'A1':'A3');
        $request_data['prevpolicyexp'] = (int)($cholla_model->get_excel_date($request_data['previous_policy_expiry_date']));
        $request_data['model_code'] = (int) ($mmv->vehiclemodelcode);
        $request_data['product_id']     = $product_id;

        if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
            $request_data['tp_rsd']         = ($is_od ? (int)($cholla_model->get_excel_date($tp_start_date)) : '');
            $request_data['tp_red']         = ($is_od ? (int)($cholla_model->get_excel_date($tp_end_date)) : '');
        }
        $request_data['od_rsd']         = ($is_od ? (int)($cholla_model->get_excel_date($od_rsd)) : '');
        $request_data['od_red']         = ($is_od ? (int)($cholla_model->get_excel_date($od_red)) : '');

       $is_imt = ($masterProduct->product_identifier === "GCV Insurance - IMT23") ? true : false;
        $mmv->idv       = $proposal->idv;
        $idv      = (int)$proposal->idv;
        
        $token_response = $cholla_model->token_generation($request_data);
        if ($token_response['status'] == false) {
            return $token_response;
        }
        $token = $token_response['token'];

        $additional_body_idv = 0;
        $additional_chassis_idv = 0;
        if ($mmv->txt_fbv_flag == "Non-FBV") {
    
            $additional_data_idv = json_decode($proposal->additional_details);
            $additional_body_idv = $additional_data_idv->vehicle->bodyIdv;
            $additional_chassis_idv = $additional_data_idv->vehicle->chassisIdv;
        } else {
            $idv      = (int)$proposal->idv;
        }
        // if (!$is_liability) {
        //     $request_data['idv_premium_type'] = $premium_type; 
        //     $request_data['business_type'] = $requestData->business_type;
        //     $idv_response = $cholla_model->idv_calculation($rto_data, $request_data, $token);
           
            


        //     if ($idv_response['status'] == 'false') {
        //         $idv_response['product_identifier'] = $masterProduct->product_identifier;
        //         return [ 
        //             'status'    => false,
        //             'message'   => 'GVW not in the master data'
        //         ];
        //     }
        // } else {
        //     $idv_response['idv_range'] = [
        //         'chassis_price_input' => '0',
        //         'chassis_min' => '0',
        //         'chassis_max' => '0',

        //     ];
        // }
     
        //print_r($token);
        $manfyear = explode('-', $proposal->vehicle_manf_year);
        $mmv->app_product_name = 'gccv';
        $fullName = ($is_individual ? $proposal->first_name . (empty($proposal->last_name) ? "" : " " . $proposal->last_name) : $proposal->first_name);
        $quote_array = [
            "chassis_price_edit" => ($additional_chassis_idv == 0) ? $idv : $additional_chassis_idv,
            "body_price_edit" => ($additional_body_idv == 0) ? 0 : $additional_body_idv,//(!$is_liability)?$idv_response['idv_range']['body_price_input']: 0,
            "chassis_no" =>$proposal->chassis_number,
            "engine_no" => (is_null($proposal->engine_number) ? "" :$proposal->engine_number),
            "IMDShortcode_Dev" => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_IMDSHORTCODE_DEV'),
            "nominee_name" => (is_null($proposal->nominee_name) ? "" :$proposal->nominee_name),
            "nominee_relationship" =>(is_null($proposal->nominee_relationship) ? "" :$proposal->nominee_relationship),
            
            "pa_cover" => ($is_cpa ? 'Yes' : 'No'),
            "product_id" => $product_id,
            "proposal_id" => "",
            "quote_id" => "",
            "reg_no" => $requestData->business_type =='newbusiness' ? "NEW" : $reg_no,
            "user_code" => config('constants.IcConstants.cholla_madalam.cv.CHOLLA_MANDALAM_CV_USER_CODE'),
            "YOM" => $manfyear[1],
            "noprev_insurance" => "Yes",

            // dates
            "DOR" => $cholla_model->get_excel_date($request_data['first_reg_date']),
            "rto_location_code" =>  $rto_data['txt_rto_location_code'],

            "prev_policy_exp" => $new_vehicle ? "NEW" : $cholla_model->get_excel_date($requestData->previous_policy_expiry_date),

            "od_prev_insurer" => ($proposal->previous_insurance_company!=null ? $proposal->previous_insurance_company :"No"),
            "od_prev_policy_no" =>  $proposal->previous_policy_number,

            //MMV
            "vehicle_class_dev" => (($mmv->sub_class == 'A1- 4 WHEELER VEHICLES (PUBLIC)')?'A1':'A3'),
            "vehicle_model_code" => $mmv->vehiclemodelcode,
            "app_product_name" =>$mmv->app_product_name,

            "business_transaction_type" => $business_type,
            "product_name1" => $is_liability ? 'Liability' : 'Comprehensive',

            // CUSTOMER 
            "customer_owner_type" => ($requestData->vehicle_owner_type ? 'Individual' : 'company'),
            "first_name" => $fullName,
            "email" => $proposal->email,
            "city" => $proposal->city,
            "state" => $rto_data['state'],
            "pincode" => $proposal->pincode,
            'address' =>  $proposal->address_line1 .'|'. $proposal->address_line2.'|'.$proposal->address_line3,
            "mobile_no" => $proposal->mobile_number,
            "phone_no" => $proposal->mobile_number,
            "company_name" => '',
            "customer_aadhar" => "",
            "customer_age_dev" => '',
            "customer_dob" => $cholla_model->get_excel_date($proposal->dob),
            "customer_full_name" => $fullName,
            "customer_gender" => $gender,
            // CUSTOMER END

            //Addon
            "NilDepselected" => ($is_zero_dep == true ? 'Yes' : 'No'),
            "nil_dep_cover" => ($is_zero_dep == true  ? 'Yes' : 'No'),
            "imt_cover" => ($is_imt == true ? 'Yes': 'No'),
            "tipper_jack_opted" => "No",
            //Addon end

            //cover
            "hypothecated_yes_no" => ($proposal->is_vehicle_finance == '1' ? 'Yes' : 'No'),
            'financier_details'             => ($proposal->is_vehicle_finance == '1' ? $proposal->name_of_financer : ''),
            'financieraddress'              => ($proposal->is_vehicle_finance == '1' ? $proposal->financer_location : ''),
            //add cover
            "no_of_cleaners" => $is_ll_Paid_Cleaner_no,# $is_ll_Paid_Cleaner ? (string)($is_ll_Paid_Cleaner_no) : '0',
            "no_of_coolies" => $is_ll_Paid_Coolies_no,#$is_ll_Paid_Coolies ? (string)($is_ll_Paid_Coolies_no) : '0',
            "no_of_drivers" =>  $IsLLPaidDriver,# $is_ll_Paid_Driver ? (string)($ll_Paid_Driver_no) : '0',
            //add cover end

            // ncb
            "ncb_app" => ($is_ncb_apllicable ? 'Yes' : 'No'),
            "ncb_confirmation" => true,
            "claim_history" =>$requestData->previous_ncb.'%',


            // "dtd_input_rate" => 68,

            "edit_total_seating_capacity" => $mmv->seatingcapacity,
            "frm_prev_insurer" => "",
            "gstin_no" => (isset($proposal->gst_number) ? $proposal->gst_number : ''),
            "gvw_per_rc" => $mmv->grossvehicleweight,
            "parent_policy_no" => "",
            "pincode1" => "",
            "renewal_id" => "",
            "salutation" => "",
            "state1" => "",
            "street" => "",
            "street1" => "",
            "address1" => "",
            "place_of_reg" => $rto_data['txt_registration_state_code'],
            "area" => "",
            "area_code" => "",
            "area1" => "",
            "bank_name" => "",

            "branch_address" => "",
            "branch_name" => "",
            "city1" => "",
            "comm_diff" => "No",

        ];


        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
        if($is_pos_enabled =='Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P' && $quote->idv <= 5000000){
            $quote_array['posp_name']=  $pos_data->agent_name;
            $quote_array['POSPcode'] = $pos_data->agent_id;
            $quote_array['POSPPAN']  = $pos_data->pan_no;
            $quote_array['POSPaadhar']  = $pos_data->aadhar_no;
            $quote_array['POSPcontactno'] = $pos_data->agent_mobile;
            $quote_array['posp_direct'] =    'Direct';
        }
        if(config('constants.motorConstant.IS_POS_ENABLED_CHOLLA_TESTING')=='Y' && $quote->idv <= 5000000){
            $quote_array['posp_name']='Ravindra Singh';
            $quote_array['POSPcode']='renewbuy';
            $quote_array['POSPPAN']='DNPPS5548E';
            $quote_array['POSPaadhar']='353938860934';
            $quote_array['POSPcontactno']='9045078061';
            $quote_array['posp_direct']='Delhi';
        }
        if ($requestData->business_type == 'newbusiness' && $is_cpa) {
            $quote_array['pa_lt_dropdown'] = $tenure;
        }
        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
           
            $ckycMetaData = json_decode($proposal->ckyc_meta_data);
           
            $ckycDetails = [
                "CKYC_App_Ref_No" => $proposal->ckyc_reference_id,
                "CKYC_DOB_DOI" => date('d-M-Y', strtotime($proposal->dob)),
                "CKYC_No" => $proposal->ckyc_number,
                "CKYC_PAN_No" => "",
                "CKYC_Aadhar_No" => "",
                "CKYC_DL_No" => "",
                "CKYC_Voter_ID" => "",
                "CKYC_Passport_no" => "",
                "CKYC_CIN" => "",
                "CKYC_KYC_Verified" => "Yes", // Yes Or No
                "CKYC_Mode_of_Verification" => "",
                "CKYC_Status" => "",
                "CKYC_Policy_Gen_Flag" => "",
                "CKYC_Transaction_ID" => $ckycMetaData->transaction_id
            ];
            
            switch ($proposal->ckyc_type) {
                case 'pan_card':
                    $ckycDetails['CKYC_Mode_of_Verification'] = 'CKYC_PAN';
                    $ckycDetails['CKYC_PAN_No'] = $proposal->ckyc_type_value;
                    break;
                case 'aadhar_card':
                    $ckycDetails['CKYC_Mode_of_Verification'] = 'CKYC_AADHAR';
                    $ckycDetails['CKYC_Aadhar_No'] = $proposal->ckyc_type_value;
                    break;
            }
            $quote_array = array_merge($quote_array, $ckycDetails);
        }

        if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'cholla_mandalam', strtolower($parent_id));
            if ($agentDiscount['status'] ?? false) {
                $quote_array['retail_brokering_dtd_edit'] = $agentDiscount['discount'];
            } else {
                if (!empty($agentDiscount['message'] ?? '')) {
                    return [
                        'status' => false,
                        'message' => $agentDiscount['message']
                    ];
                }
            }
        }

        $header = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Bearer ' . $token
        ];
        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'Quote Calculation - Quote',
            'section' => $request_data['section'],
            'type' => 'request',
            'productName' => $productData->product_name,
            'transaction_type' => 'proposal',
            'headers' => $header,
        ];
        $quote_url = config('constants.IcConstants.cholla_madalam.cv.END_POINT_URL_CHOLLA_MANDALAM_CV_QUOTE');
        $get_response = getWsData(
            $quote_url,
            $quote_array,
            'cholla_mandalam',
            $additional_data

        );
        $data = $get_response['response'];

        $quote_response = json_decode($data, true);
        //        print_r($quote_response);die;
        if ($quote_response != null) {


            //            return response()->json($quote_response);
            $error_message = $quote_response['Message'];

            if ($quote_response['Status'] != 'success') {
                return [
                    'status' => false,
                    'message' => $error_message,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'return_data' => [
                        'premium_request' => $quote_array,
                        'premium_response' => $quote_response,
                    ],
                ];
            } else {

                $quote_response = array_change_key_case_recursive($quote_response);

                $quote_response_data = $quote_response['data'];
           
                $base_cover['od'] = $quote_response_data['basic_own_damage_cng_elec_non_elec'];
                $base_cover['electrical'] = $quote_response_data['electrical_accessory_prem'];
                $base_cover['non_electrical'] = $quote_response_data['non_electrical_accessory_prem'];
                $base_cover['lpg_cng_od'] = $quote_response_data['cng_lpg_own_damage'];


                $base_cover['tp'] = $quote_response_data['basic_third_party_premium'];
                $base_cover['pa_owner'] = $quote_response_data['personal_accident'];
                $base_cover['unnamed'] = $quote_response_data['unnamed_passenger_cover'];
                $base_cover['paid_driver'] = $quote_response_data['legal_liability_to_paid_driver'];
                $base_cover['legal_liability'] = $quote_response_data['paid_coolie_cleaner_premium'];
                $base_cover['lpg_cng_tp'] = $quote_response_data['cng_lpg_tp'];


                $base_cover['ncb'] = $quote_response_data['no_claim_bonus'];
                $base_cover['automobile_association'] = '0';
                $base_cover['anti_theft'] = '0';
                $base_cover['other'] = $quote_response_data['dtd_discounts'] + $quote_response_data['gst_discounts'];


                $base_cover['zero_dep'] = $quote_response_data['zero_depreciation'];
                $base_cover['imt_cover'] = $quote_response_data['imt_cover_premium'];
                $base_cover['key_replacement'] = $quote_response_data['key_replacement_cover'];
                $base_cover['consumable'] = $quote_response_data['consumables_cover'];
                $base_cover['loss_of_belongings'] = $quote_response_data['personal_belonging_cover'];
                $base_cover['rsa'] = $quote_response_data['rsa_cover'];
                $base_cover['engine_protect']  =  $quote_response_data['hydrostatic_lock_cover'];
                $base_cover['tyre_secure'] = 'NA';
                $base_cover['return_to_invoice'] = 'NA';
                $base_cover['ncb_protect'] = 'NA';

                $total_premium_amount = $quote_response_data['total_premium'];

                $total_basic_od_premium = $base_cover['od']
                    + $base_cover['electrical']
                    + $base_cover['non_electrical']
                    + $base_cover['lpg_cng_od'];

                $total_tp_premium = $base_cover['tp']
                    + $base_cover['pa_owner']
                    + $base_cover['unnamed']
                    + $base_cover['paid_driver']
                    + $base_cover['legal_liability']
                    + $base_cover['lpg_cng_tp'];

                $total_discount = $base_cover['ncb']
                    + $base_cover['automobile_association']
                    + $base_cover['anti_theft']
                    + $base_cover['other'];

                $addon_sum = (is_integer($base_cover['zero_dep']) ? $base_cover['zero_dep'] : 0)
                    + (is_integer($base_cover['key_replacement']) ? $base_cover['key_replacement'] : 0)
                    + (is_integer($base_cover['consumable']) ? $base_cover['consumable'] : 0)
                    + (is_integer($base_cover['loss_of_belongings']) ? $base_cover['loss_of_belongings'] : 0)
                    + (is_integer($base_cover['rsa']) ? $base_cover['rsa'] : 0)
                    + (is_integer($base_cover['engine_protect']) ? $base_cover['engine_protect'] : 0)
                    + (is_integer($base_cover['tyre_secure']) ? $base_cover['tyre_secure'] : 0)
                    + (is_integer($base_cover['return_to_invoice']) ? $base_cover['return_to_invoice'] : 0)
                    + (is_integer($base_cover['ncb_protect']) ? $base_cover['ncb_protect'] : 0);
                    + (is_integer($base_cover['imt_cover']) ? $base_cover['imt_cover'] : 0);


                $tax = $quote_response_data['gst'];

                $premium_data['policy_id'] = $quote_response_data['policy_id'];
                $premium_data['proposal_id'] = $quote_response_data['proposal_id'];
                $premium_data['quote_id'] = $quote_response_data['quote_id'];
                $premium_data['token'] = $token;

                $premium_data['premium_breakup'] = [
                    'total_od' => $total_basic_od_premium,
                    'total_tp' => $total_tp_premium,
                    'total_discount' => $total_discount,
                    'total_addon' => $addon_sum,
                    'cpa' => $base_cover['pa_owner'],
                    'total_premium' => $total_premium_amount
                ];

                $cpa_cover_flag = '0';

                if ($requestData->vehicle_owner_type == 'I') {
                    $client_type = 'I';
                    $dob = date('d/m/Y', strtotime($proposal->dob));
                    if (!$is_od) {
                        $cpa_cover_flag = '-1';
                    }
                    if ($gender == 'M') {
                        $salutation = 'Mr';
                    } else {
                        $salutation = 'Ms';
                    }
                    $cust_name = $proposal->first_name . ' ' . $proposal->last_name;
                    $cpa_nom_age_det = 'Y';
                    $cpa_nom_perc = '100';
                } else {
                    $client_type = 'C';
                    $occupation = 'SVCM';
                    $Capital = '1';
                    $dob = '';
                    $gender = '';
                    $salutation = 'M/S';

                    $cust_name = $proposal->first_name . '(' . $proposal->last_name . ')';

                    $cpa_nom_name = '';
                    $cpa_nom_age = '';
                    $cpa_nom_age_det = '';
                    $cpa_nom_perc = '';
                    $cpa_relation = '';
                    $cpa_appointee_name = '';
                    $cpa_appointe_rel = '';
                }


                //                $quote_array                                    = $premium_request;
                $quote_array['quote_id'] = $premium_data['quote_id'];
                $quote_array['proposal_id'] = $premium_data['proposal_id'];
                $quote_array['policy_id'] = $premium_data['policy_id'];
                $token = $premium_data['token'];

                $request_data['first_reg_date'] = $requestData->vehicle_register_date;
                $request_data['policy_type'] = ($is_package ? 'Comprehensive' : ($is_liability ? 'Third Party' : 'Long Term'));
                $request_data['rto_location_code'] = $rto_data['txt_rto_location_code'];


                $additional_data_proposal = [
                    'requestMethod' => 'post',
                    'Authorization' => $token,
                    'proposal_id' => $proposal->user_proposal_id,
                    'enquiryId' => $request_data['enquiryId'],
                    'method' => 'Proposal Submition - Proposal',
                    'section' => $request_data['section'],
                    'type' => 'request',
                    'productName' => $productData->product_name,
                    'transaction_type' => 'proposal',
                    'headers' => $header,
                ];
                //                return response()->json($quote_array);

                $get_response = getWsData(
                    config('constants.IcConstants.cholla_madalam.cv.END_POINT_URL_CHOLLA_MANDALAM_CV_PROPOSAL'),
                    $quote_array,
                    'cholla_mandalam',
                    $additional_data_proposal

                );

                $proposaldata = $get_response['response'];
           
                if ($proposaldata) {

                    $proposal_response = json_decode($proposaldata, true);
                    //                    return response()->json($proposal_response);
                    $error_message = $proposal_response['Message'];

                    if ($proposal_response['Status'] != 'success') {
                        return [
                            'status' => false,
                            'message' => $error_message,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'return_data'       => [
                                'premium_request'  => $quote_array,
                                'premium_response'  => $proposal_response,
                            ],
                        ];
                    } else {

                        $proposal_response = array_change_key_case_recursive($proposal_response);

                        $proposal_response_data = $proposal_response['data'];
                        $payment_id           = $proposal_response_data['payment_id'];
                        $total_premium         = $proposal_response_data['total_premium'];
                        $service_tax_total     = $proposal_response_data['gst'];
                        $base_premium         = $proposal_response_data['net_premium'];
                        //premium calculation


                        //print_r($quote_response_data);
                        $base_cover['od'] = $quote_response_data['basic_own_damage_cng_elec_non_elec'];
                        $base_cover['electrical'] = $quote_response_data['electrical_accessory_prem'];
                        $base_cover['non_electrical'] = $quote_response_data['non_electrical_accessory_prem'];
                        $base_cover['lpg_cng_od'] = $quote_response_data['cng_lpg_own_damage'];

                        $base_cover['tp'] = $quote_response_data['basic_third_party_premium'] -  $quote_response_data['legal_liability_to_paid_driver'];
                        $base_cover['pa_owner'] = $quote_response_data['personal_accident'];
                        $base_cover['unnamed'] = $quote_response_data['unnamed_passenger_cover'];
                        $base_cover['paid_driver'] = $quote_response_data['legal_liability_to_paid_driver'];
                        $base_cover['legal_liability'] = $quote_response_data['paid_coolie_cleaner_premium'];
                        $base_cover['lpg_cng_tp'] = $quote_response_data['cng_lpg_tp'];

                        $base_cover['ncb'] = $quote_response_data['no_claim_bonus'];
                        $base_cover['automobile_association'] = '0';
                        $base_cover['anti_theft'] = '0';
                        $base_cover['other_discount'] = $quote_response_data['dtd_discounts'] + $quote_response_data['gst_discounts'];

                        $addon['zero_dep'] = (($quote_response_data['zero_depreciation'] == '0') ? 'NA' : $quote_response_data['zero_depreciation']);
                        $addon['imt_cover'] = (($quote_response_data['imt_cover_premium'] == '0') ? 0 : $quote_response_data['imt_cover_premium']);
                        $addon['key_replacement'] = (($quote_response_data['key_replacement_cover'] == '0') ? 'NA' : $quote_response_data['key_replacement_cover']);
                        $addon['consumable'] = (($quote_response_data['consumables_cover'] == '0') ? 'NA' : $quote_response_data['consumables_cover']);
                        $addon['loss_of_belongings'] = (($quote_response_data['personal_belonging_cover'] == '0') ? 'NA' : $quote_response_data['personal_belonging_cover']);
                        $addon['rsa'] = (($quote_response_data['rsa_cover'] == '0') ? 'NA' : $quote_response_data['rsa_cover']);
                        $addon['engine_protect']  = (($quote_response_data['hydrostatic_lock_cover'] == '0') ? 'NA' : $quote_response_data['hydrostatic_lock_cover']);
                        $addon['tyre_secure'] = 'NA';
                        $addon['return_to_invoice'] = 'NA';
                        $addon['ncb_protect'] = 'NA';

                        $total_premium_amount = $quote_response_data['total_premium'];
                        

                        // $base_cover['tp'] = $base_cover['tp']; // + $base_cover['legal_liability'];

                        if ($addon['zero_dep'] == 'NA' && $is_zero_dep) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => 'Zero dep value issue',
                                'cv_age' => $cv_age,
                                'reg_date' => $requestData->vehicle_register_date
                            ];
                        }

                        $add_ons_data = [
                            'in_built' => [],
                            'additional' => [
                                'zeroDepreciation' => $addon['zero_dep'],
                                'road_side_assistance' => $addon['rsa'],
                                'engineProtector' => $addon['engine_protect'],
                                'ncbProtection' => 'NA',
                                'keyReplace' => $addon['key_replacement'],
                                'consumables' => $addon['consumable'],
                                'tyreSecure' => 'NA',
                                'returnToInvoice' => 'NA',
                                'lopb' => $addon['loss_of_belongings'],
                                'cpa_cover' => $base_cover['pa_owner'],
                                'imt23' => $addon['imt_cover']
                            ],
                            'other' => [],
                        ];

                        $base_premium_amount = $total_premium_amount / (1 + (18.0 / 100));

                        $add_ons = [];

                        foreach ($add_ons_data as $add_on_key => $add_on_value) {
                            if (count($add_on_value) > 0) {
                                foreach ($add_on_value as $add_on_value_key => $add_on_value_value) {
                                    if (is_numeric($add_on_value_value)) {
                                        $value = (string)$add_on_value_value;
                                        $base_premium_amount -= $value;
                                    } else {
                                        $value = $add_on_value_value;
                                    }
                                    $add_ons[$add_on_key][$add_on_value_key] = $value;
                                }
                            } else {
                                $add_ons[$add_on_key] = $add_on_value;
                            }
                        }

                        $base_premium_amount = $base_premium_amount * (1 + (18.0 / 100));

                        array_walk_recursive($add_ons, function (&$item, $key) {
                            if ($item == '' || $item == '0') {
                                $item = 'NA';
                            }
                        });

                        $total_od = $base_cover['od'] + $base_cover['electrical'] + $base_cover['non_electrical'] + $base_cover['lpg_cng_od'];

                        $total_tp = $base_cover['tp'] + $base_cover['legal_liability'] + $base_cover['paid_driver']; //+ $base_cover['unnamed']; //+ $base_cover['lpg_cng_tp'];

                        $total_discount = $base_cover['other_discount'] + $base_cover['automobile_association'] + $base_cover['anti_theft'] + $base_cover['ncb'];
                        //print_r($base_cover['pa_owner'].'-----');
                        //                        print_r($addon_sum.'///');
                        //print_r($total_od.'--'.$total_tp.'--'.$total_discount.'--'.$addon_sum);

                        $basePremium = $total_od + $total_tp - $total_discount + $addon_sum;

                        $totalTax = $basePremium * 0.18;

                        $final_premium = $basePremium + $totalTax;
                        //print_r($final_premium);
                        //                        print_r($base_cover);
                        //                        die;
                        $policy_start_date = date('d-m-Y', strtotime(str_replace('/', '-', $policy_start_date)));
                        $policy_end_date = date('d-m-Y', strtotime(str_replace('/', '-', $policy_end_date)));
                        $pg_transaction_id = date('Ymd') . time();


                        //                        if($is_od){
                        //                            $data["tp_policy_address1"]     = $tp_policy_address1;
                        //                            $data["tp_policy_address2"]     = $tp_policy_address2;
                        //                            $data["tp_policy_pincode"]      = $tp_policy_pincode;
                        //                        }

                        $premium_data['premium_breakup'] = [
                            'total_od' => $total_basic_od_premium,
                            'total_tp' => $total_tp_premium,
                            'total_discount' => $total_discount,
                            'total_addon' => $addon_sum,
                            'cpa' => $base_cover['pa_owner'],
                            'total_premium' => $total_premium_amount
                        ];

                        UserProposal::where('user_product_journey_id', $enquiryId)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'proposal_no' => $payment_id,
                                //'unique_proposal_id' => $payment_id,
                                'policy_start_date' =>  str_replace('00:00:00', '', $policy_start_date),
                                'policy_end_date' =>  str_replace('00:00:00', '', $policy_end_date),
                                'od_premium' => round($total_od),
                                'tp_premium' => round($total_tp),
                                'total_premium' => round($basePremium),
                                'addon_premium' => round($addon_sum),
                                'cpa_premium' => $base_cover['pa_owner'],
                                'service_tax_amount' => round($totalTax),
                                'total_discount' => round($total_discount),
                                'final_payable_amount' => $proposal_response_data['total_premium'], #round($final_premium),
                                'ic_vehicle_details' => '',
                                'discount_percent' => $no_claim_bonus . '%',
                                'vehicale_registration_number' => $proposal->vehicale_registration_number,
                                'engine_no' => $proposal->engine_number,
                                'chassis_no' => $proposal->chassis_number,
                                'final_premium' => env('APP_ENV') == 'local' ? config('constants.IcConstants.cholla_madalam.cv.STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM') : $final_premium,
                                'product_code' => $proposal->product_code,
                                'ncb_discount' => $base_cover['ncb'],
                                'dob' => ($proposal->dob != null ? date("Y-m-d", strtotime($proposal->dob)) : ''),
                                'nominee_dob' => ($proposal->nominee_dob != null ? date("Y-m-d", strtotime($proposal->nominee_dob)) : ''),
                                'cpa_policy_fm_dt' => ($proposal->cpa_policy_fm_dt != null ? date("Y-m-d", strtotime($proposal->cpa_policy_fm_dt)) : ''),
                                'cpa_policy_to_dt' => ($proposal->cpa_policy_to_dt != null ? date("Y-m-d", strtotime($proposal->cpa_policy_to_dt)) : ''),
                                'cpa_policy_no' => $proposal->cpa_policy_no,
                                'cpa_sum_insured' => $proposal->cpa_sum_insured,
                                'car_ownership' => $proposal->car_ownership,
                                'electrical_accessories' => $proposal->electrical_accessories,
                                'non_electrical_accessories' => $proposal->non_electrical_accessories,
                                'version_no' => $proposal->version_no,
                                'vehicle_category' => $proposal->vehicle_category,
                                'vehicle_usage_type' => $proposal->vehicle_usage_type,
                                'tp_start_date' => $tp_start_date,
                                'tp_end_date' => $tp_end_date,
                                'tp_insurance_company' => $tp_insurance_company,
                                'tp_insurance_number' => $tp_insurance_number,
                            ]);



                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);

                        $proposal_data = UserProposal::find($proposal->user_proposal_id);

                        $updatePremiumDetails = [
                            // OD Tags
                            "basic_od_premium" => $base_cover['od'],
                            "loading_amount" => $proposal_response['dtd_loading'] ?? 0,
                            "final_od_premium" => $total_od - $total_discount + $addon_sum,
                            // TP Tags
                            "basic_tp_premium" => $base_cover['tp'],
                            "final_tp_premium" => $total_tp,
                            // Accessories
                            "electric_accessories_value" => $base_cover['electrical'],
                            "non_electric_accessories_value" => $base_cover['non_electrical'],
                            "bifuel_od_premium" => $base_cover['lpg_cng_od'],
                            "bifuel_tp_premium" => $base_cover['lpg_cng_tp'],
                            // Addons
                            "compulsory_pa_own_driver" => $base_cover['pa_owner'],
                            "zero_depreciation" => $addon['zero_dep'],
                            "road_side_assistance" => $addon['rsa'],
                            "imt_23" => 0,
                            "consumable" => $addon['consumable'],
                            "key_replacement" => $addon['key_replacement'],
                            "engine_protector" => $addon['engine_protect'],
                            "ncb_protection" => 0, // They don't provide
                            "tyre_secure" => 0,
                            "return_to_invoice" => 0,
                            "loss_of_personal_belongings" => $addon['loss_of_belongings'],
                            "eme_cover" => 0,
                            "accident_shield" => 0,
                            "conveyance_benefit" => 0,
                            "passenger_assist_cover" => 0,
                            // Covers
                            "pa_additional_driver" => 0,
                            "unnamed_passenger_pa_cover" => $base_cover['unnamed'],
                            "ll_paid_driver" => $base_cover['legal_liability'],
                            "geo_extension_odpremium" => 0,
                            "geo_extension_tppremium" => 0,
                            // Discounts
                            "anti_theft" => $base_cover['anti_theft'],
                            "voluntary_excess" => 0, // They don't provide
                            "tppd_discount" => 0,
                            "other_discount" => $base_cover['other_discount'],
                            "ncb_discount_premium" => $base_cover['ncb'],
                            // Final tags
                            "net_premium" => $base_premium,
                            "service_tax_amount" => round($service_tax_total),
                            "final_payable_amount" => $proposal_response_data['total_premium'],
                        ];

                        $updatePremiumDetails = array_map(function ($value) {
                            return !empty($value) && is_numeric($value) ? $value : 0;
                        }, $updatePremiumDetails);

                        savePremiumDetails($proposal->user_product_journey_id, $updatePremiumDetails);
                        
                        return [
                            'status' => true,
                            'message' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' =>  $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal_data->user_product_journey_id,
                            ]
                        ];
                    }
                } else {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                    ];
                }
            }
        }
        else {
            return [
                'status'   => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'  => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                'error'        => 'no response form service'
            ];
        }
    }
}
