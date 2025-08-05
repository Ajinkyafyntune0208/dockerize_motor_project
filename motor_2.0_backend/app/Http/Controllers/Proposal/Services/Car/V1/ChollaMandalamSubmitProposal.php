<?php

namespace App\Http\Controllers\Proposal\Services\Car\V1;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

use App\Http\Controllers\SyncPremiumDetail\Car\ChollaMandalamPremiumDetailController;
use App\Models\PolicyDetails;
use Carbon\Carbon;
use DateTime;
use App\Models\UserProposal;
use App\Models\MasterProduct;
use App\Models\SelectedAddons;
use App\Models\chollamandalammodel;
use Illuminate\Support\Facades\DB;
use App\Models\MasterPremiumType;
use App\Models\QuoteLog;
use App\Models\chollamandalamPincodeMaster;
use App\Models\ChollaMandalamRtoMaster;
use App\Http\Controllers\Inspection\Service\Car\ChollaMandalamInspectionService;
use Illuminate\Http\Request;

class ChollaMandalamSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $cholla_model= new chollamandalammodel();
        DB::enableQueryLog();

        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        /* if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y')) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Zero dep is not allowed because zero dep is not part of your previous policy',
            ];
        } */
        $getdata = json_encode($proposal);
        $proposal = json_Decode($getdata);
        $additional_details=json_decode($proposal->additional_details);

        $proposal_date  = date('d/m/Y');
        $is_new         = (($proposal->business_type == 'N')  ? true : false);
        $is_individual  = (($requestData->vehicle_owner_type == 'I')    ? true : false);
        $is_breakin     = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);
        $is_financed    = (($proposal->financer_agreement_type == '' || $proposal->financer_agreement_type == 'None')    ? false : true);
        $new_vehicle        = (($requestData->business_type == 'newbusiness') ? true : false);
        $quote =QuoteLog::where('user_product_journey_id', $proposal->user_product_journey_id)->first();

        $policy_holder_type = ($requestData->vehicle_owner_type == "I" ? "INDIVIDUAL" : "COMPANY");
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

        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();
        $is_package         = (($premium_type == 'comprehensive') ? true : false);
        $is_liability       = (($premium_type == 'third_party') ? true : false);
        $is_od              = (in_array($premium_type, ['own_damage', 'own_damage_breakin']) ? true : false);

        $gender                                 = $proposal->gender;
        $marital_status                         = $proposal->marital_status;
        $occupation                             = $proposal->occupation;
        $AgreementType                          = $proposal->financer_agreement_type;

    $previous_insurer_address_details = DB::table('insurer_address')->where('Insurer', 'like', $proposal->insurance_company_name . '%')->first();
    $previous_insurer_address_details = keysToLower($previous_insurer_address_details);


        if($is_od)
        {

//            $tp_previous_insurer_address_details    = get_details(
//                'previous_insurer_mappping',
//                ['addressLine1', 'addressLine2', 'pincode'],
//                ['previous_insurer' => $tp_insurercode]
//            );
//            $tp_policy_address1           = $tp_previous_insurer_address_details[0]['addressLine1'];
//            $tp_policy_address2           = $tp_previous_insurer_address_details[0]['addressLine2'];
//            $tp_policy_pincode            = $tp_previous_insurer_address_details[0]['pincode'];
        }
        else
        {
            $tp_policy_address1 = $tp_policy_address2 = $tp_policy_pincode = '';
        }

        if($is_od)
        {
//          $tp_insurercode = get_previous_insurer($tp_insurercode,'cholla_mandalam');
            $tp_insurercode ='';
        }

//        $is_zero_dep = (($productData->zero_dep == '0') ? true : false);

        $product_name = $productData->product_name;
        $company_name = $productData->company_name;
        
//     $rto_data = DB::select("select cmpm.state_desc as state, cmrm.* from cholla_mandalam_rto_master as cmrm inner join cholla_mandalam_pincode_master as cmpm ON
//  cmrm.num_state_code = cmpm.state_cd = 'left' where cmrm.rto ='$requestData->rto_code' limit 1");

//     $rto_data = (array)$rto_data[0];
    // $rto_code = explode('-',$requestData->rto_code);
    $rto_code = RtoCodeWithOrWithoutZero($requestData->rto_code,true); //DL RTO code
    
    if(substr($rto_code, 0, 2) == 'OD')
    {
       $requestData->rto_code = 'OR'. substr($rto_code, 2);
    }
    $rto_data = ChollaMandalamRtoMaster::join('cholla_mandalam_pincode_master', 'cholla_mandalam_pincode_master.state_cd', '=', 'cholla_mandalam_rto_master.num_state_code')
    ->where('cholla_mandalam_rto_master.rto',$rto_code)
    ->select('cholla_mandalam_rto_master.*','cholla_mandalam_pincode_master.state_desc as state')
    ->limit(1)
    ->first();
        
    $reg_no='';
    if($proposal->vehicale_registration_number!='NEW'){
        $reg_no = $proposal->vehicale_registration_number;
        $reg_no = explode('-', $reg_no);

        if($reg_no[0] == 'DL' && strlen($reg_no[2]) == 3){
            $reg_no[1] = is_numeric($reg_no[1]) ? ($reg_no[1] * 1).$reg_no[2][0] : $reg_no[1].$reg_no[2][0];
            $reg_no[2] = $reg_no[2][1].$reg_no[2][2];
            $reg_no = $reg_no[0].'-'.$reg_no[1].'-'.$reg_no[2].'-'.$reg_no[3];
        }elseif($reg_no[0] == 'DL') {
            $registration_no = RtoCodeWithOrWithoutZero($reg_no[0].$reg_no[1]);
            $reg_no = $registration_no.'-'.$reg_no[2].'-'.$reg_no[3];
        }  else {
            $reg_no = $proposal->vehicale_registration_number;
        }
        // $reg_no = isset($proposal->vehicale_registration_number) ? $proposal->vehicale_registration_number : '';
    }

    /* if (in_array(substr($reg_no, 0, 2), ['DL', 'dl'])) {
        $regNoArray = explode('-', $reg_no);
       
        if (isset($regNoArray[1]) && is_numeric($regNoArray[1]) && strlen($regNoArray[1]) >= 2 && $regNoArray[1][0] == 0) {
            $regNoArray[1] = substr_replace($regNoArray[1], '', 0, 1);
        }
        $reg_no = implode('-', $regNoArray);
    } *///if the reg_no="DL-08-BB-5382", it will return "DL-8-BB-5382"

        $selected_addons = SelectedAddons::where('user_product_journey_id',$enquiryId)->first();

        #$car_age = car_age($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date);
        $expdate=(($requestData->previous_policy_expiry_date == 'New') || ($requestData->business_type == 'breakin') ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($expdate);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? 1 : 0);
        $car_age = ceil($age / 12);
        
        $addon                      = [];
        $AddonReq                   = 'N';
        if ($productData->zero_dep == '0' && !$is_liability)
        {
            $nil_depreciation ='-1';
        }
        else
        {
            $nil_depreciation ='0';
        }

        $cpa_cover          = 'N';
        $rsa          = 'N';
        $consumable         = 'N';
        $key_replacement    = 'N';
        $engine_protector   = 'No';
        $loss_of_belonging='N';
        $is_cpa =false;
        $is_zero_dep=false;


            $addon_req = 'Y';
        $tenure = 0;
        if(isset($selected_addons->compulsory_personal_accident[0]['name']) && !$is_od)
        {
            $is_cpa=true;
            $tenure = 1;
            $tenure = isset($selected_addons->compulsory_personal_accident[0]['tenure'])? $selected_addons->compulsory_personal_accident[0]['tenure'] :$tenure;
        }


            if($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '')
            {
                $addons = $selected_addons->applicable_addons;

              foreach ($addons as $value) {
                    if($value['name'] == 'Engine Protector'  /*&& $car_age <=5*/)
                    {
                        $engine_protector = 'Yes';
                    }
                    if($value['name'] == 'Zero Depreciation' /*&& $car_age <=5*/)
                    {
                        $is_zero_dep = true;
                    }

                    if($value['name'] == 'Road Side Assistance' /*&& $car_age <=5*/)
                    {
                        $rsa='Y';
                    }
                    if($value['name'] == 'Key Replacement' /*&& $car_age <=5*/)
                    {
                        $key_replacement='Y';
                    }


                    if($value['name'] == 'Consumable'  /*&& $car_age <=5*/)
                    {
                        $consumable = 'Y';
                    }

                    if($value['name'] == 'Loss of Personal Belongings'  /*&& $car_age <=5*/)
                    {
                        $loss_of_belonging = 'Y';
                    }


                }
            }

            $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)
            ->first();
            if($masterProduct->product_identifier == 'WITHOUT_ADDONS')
            {
                $is_zero_dep = false;
                $engine_protector = 'No';
                $rsa = 'N';
                $key_replacement = 'N';
                $consumable = 'N';
                $loss_of_belonging = 'N';
            }

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;
        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;
        $bifuel = false;
        $BiFuelKitSi = 0;

        if($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '')
        {
            $accessories = ($selected_addons->accessories);
            foreach ($accessories as $value) {
                if($value['name'] == 'Electrical Accessories' && !$is_liability)
                {
                    $IsElectricalItemFitted = 'true';
                    $ElectricalItemsTotalSI = $value['sumInsured'];
                }
                else if($value['name'] == 'Non-Electrical Accessories' && !$is_liability)
                {
                    $IsNonElectricalItemFitted = 'true';
                    $NonElectricalItemsTotalSI = $value['sumInsured'];
                }
                else if($value['name'] == 'External Bi-Fuel Kit CNG/LPG')#&& $is_package
                {
                    $type_of_fuel = '5';
                    $bifuel = true;
                    $Fueltype = 'CNG';
                    $BiFuelKitSi = $value['sumInsured'];



                    if($BiFuelKitSi < 10000 || $BiFuelKitSi > 30000)
                    {
                        return [
                            'premium_amount' => 0,
                            'status'         => false,
                            'message'        => 'LPG/CNG cover value should be between 10000 to 30000',
                            'selected value'=> $BiFuelKitSi
                        ];
                    }

                }
            }
        }

        //PA for un named passenger
        $IsPAToUnnamedPassengerCovered = false;
        $PAToUnNamedPassenger_IsChecked = false;
        $PAToUnNamedPassenger_NoOfItems = '';
        $PAToUnNamedPassengerSI = 0;
        $IsLLPaidDriver = !$is_od ? 'Yes': 'No';#mandatory

        if($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '')
        {
            $additional_covers = $selected_addons->additional_covers;
//            print_r($additional_covers);die;
            foreach ($additional_covers as $value) {
                if($value['name'] == 'Unnamed Passenger PA Cover')
                {
                    $IsPAToUnnamedPassengerCovered = true;
                    $PAToUnNamedPassenger_IsChecked = true;
                    $PAToUnNamedPassenger_NoOfItems = '1';
                    $PAToUnNamedPassengerSI = $value['sumInsured'];
                    if($value['sumInsured'] != 100000){
                        return [
                            'premium_amount'    => '0',
                            'status'            => false,
                            'message'           => 'Unnamed Passenger value should be 100000 only.',
                        ];
                    }
                }
                /* if($value['name'] == 'LL paid driver' && !$is_od)
                {
                    $IsLLPaidDriver = 'Yes';
                } */
            }
        }

        $IsAntiTheftDiscount = 'false';

        if($selected_addons && $selected_addons->discount != NULL && $selected_addons->discount != '')
        {
            $discount = $selected_addons->discount;
            foreach ($discount as $value) {
                if($value->name == 'anti-theft device')
                {
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
        $tp_start_date='';
        $tp_end_date='';
        $policy_start_date=date('Y-m-d');
        if($requestData->business_type!='newbusiness'){
            $policy_start_date = date('d/m/Y', strtotime('+1 day', strtotime($proposal->prev_policy_expiry_date)));
            $policy_end_date = date('d/m/Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
            $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
            $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';

        } else {
            $policy_end_date = ($premium_type == 'third_party') ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime($tp_start_date))))) : date('d/m/Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));
            $tp_start_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime($policy_start_date)) : '';
            $tp_end_date = in_array($premium_type ,['comprehensive','third_party']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+3 year -1 day', strtotime($tp_start_date))))) : '';

        }
        $tp_insurance_company='';
        $tp_insurance_number='';
        if($is_od){
            $tp_start_date =$proposal->tp_start_date;
            $tp_end_date = $proposal->tp_end_date;
            $tp_insurance_company=$proposal->tp_insurance_company;
            $tp_insurance_number=$proposal->tp_insurance_number;

        }

        $od_rsd 			= date('d-m-Y', strtotime('-1 year +1 day', strtotime($proposal->prev_policy_expiry_date)));
        $od_red 			= date('d-m-Y', strtotime($proposal->prev_policy_expiry_date));

        $NewCar = 'N';
        $RollOver = 'Y';
        $Business_code = 'Roll Over';
        $cpa_cover_period = 1;
        $txt_cover_period = 1;
        $PreviousPolExpDt = '';

        $claims_made=$requestData->is_claim;
        if($new_vehicle){
            $claims_made='Y';
        }
        if ($claims_made == 'N') {
            $is_ncb_apllicable = true;
            $NCBDeclartion = 'Y';
            $yn_claim = 'no';
            $applicable_ncb = $requestData->applicable_ncb;
            $no_claim_bonus = $requestData->previous_ncb;
        }
        else
        {
            $is_ncb_apllicable = false;
            $yn_claim = 'yes';
            $no_claim_bonus = 0;
        }
if($requestData->is_claim == 'Y'){
    $no_claim_bonus = $requestData->previous_ncb;   
}
        $acc_cover_unnamed_passenger=$requestData->unnamed_person_cover_si;
        if($acc_cover_unnamed_passenger == '25000'){
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

           $fuel_type = strtoupper($mmv->fuel);

        $cpa_cover_flag     = ($is_cpa ? '-1' : '0');
        $cpa_cover_period   = (($is_cpa) ? (($is_new) ? '3' : '1') : '' );

//        $is_sapa = (!$is_cpa && isset($cpa_reason) && ($cpa_reason == 'cpaWithGreat15' || $cpa_reason == 'cpaWithOtherPolicy')) ? true : false;

        $is_sapa=true;


        $is_pa_unnamed      = ($unnamed_passenger_cover_flag == 'True') ? true : false;
        $is_pa_paid_driver  = ($IsLLPaidDriver == '1') ? true : false;
        $pa_named           = false;


        $fuel_type_cng = false;
        if(isset($mmv->fyntune_version['fuel_type']) && in_array(strtoupper($mmv->fyntune_version['fuel_type']), ['CNG', 'LPG']))
        {
            $fuel_type_cng = true;
        }
        $product_id = ($is_package ? config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID') : ($is_liability ? config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_TP') : config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_OD')));

        $request_data['first_reg_date'] = $requestData->vehicle_register_date;
        $request_data['policy_type']    = ($is_package ? 'Comprehensive': ($is_liability ? 'Liability' : 'Standalone OD'));

        $request_data['make']           = $mmv->make;
        $request_data['model']          = $mmv->vehiclemodel;
        $request_data['fuel_type']      = $mmv->fuel;
        $request_data['cc']             = $mmv->cubic_capacity;
        $request_data['showroom_price'] = $mmv->exshowroom;
        $request_data['enquiryId'] = $enquiryId;

        $request_data['quote']          = $quote;
        $request_data['company']        = $company_name;
        $request_data['productName']        = $product_name;
        $request_data['section']        = 'car';
        $request_data['proposal_id']    = '';
        $request_data['method']         = 'Token Generation - Quote';

        $request_data['product_id']     = $product_id;
        $request_data['tp_rsd']         = ($is_od ? (int)($cholla_model->get_excel_date($tp_start_date)) : '');
        $request_data['tp_red']         = ($is_od ? (int)($cholla_model->get_excel_date($tp_end_date)) : '');
        $request_data['od_rsd']         = ($is_od ? (int)($cholla_model->get_excel_date($od_rsd)) : '');
        $request_data['od_red']         = ($is_od ? (int)($cholla_model->get_excel_date($od_red)) : '');
        $request_data['productName']    = $productData->product_name;
        if($is_breakin){
            $product_id = config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID');
            $request_data['policy_type'] = 'breakin';
            $policy_start_date = date('d-m-Y');
            $tp_start_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? str_replace('/','-',$policy_start_date) : '';
            $tp_end_date =  in_array($premium_type ,['comprehensive','third_party','breakin','third_party_breakin']) ? date('d-m-Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date))))) : '';
        }
        if($premium_type == 'third_party_breakin'){
            $product_id = config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_TP');
            $request_data['policy_type'] = 'third_party_breakin';
        }
        if($premium_type == 'own_damage_breakin'){
            $product_id = config('IC.CHOLLA_MANDALAM.V1.CAR.PRODUCT_ID_OD');
            $request_data['policy_type'] = 'own_damage_breakin';
        }
        $mmv->idv       = $proposal->idv;
        $idv      = $proposal->idv;

        $token_response = $cholla_model->token_generation($request_data);
        if ($token_response['status'] == 'false') {
            return $token_response;
        }
        $token = $token_response['token'];
        //print_r($token);
        $manfyear=explode('-',$proposal->vehicle_manf_year);

        $firstName = $proposal->first_name;

        if (strlen($firstName) <= 3 && !empty($proposal->last_name)) {
            $firstName = $proposal->last_name;
        }
        $previous_policy_number= (empty($proposal->previous_policy_number)) ? '' :  $proposal->previous_policy_number;
        if (strtoupper($requestData->previous_policy_type) == 'NOT SURE'){
            $PreviousPolExpDt = date('d-m-Y');
            $PreviousPolExpDt = $cholla_model->get_excel_date(date('d-m-Y',  strtotime('-3 Months - 10 day', strtotime($PreviousPolExpDt))));
            $previous_policy_number = "NOTAVAILABLE";
        }
        $quote_array = [
            'date_of_reg'                   => $cholla_model->get_excel_date($requestData->vehicle_register_date),
            'idv_input'                     => $idv,
            'ex_show_room'                  => $request_data['showroom_price'],
            'frm_model_variant'             => $request_data['model'].' / '. $request_data['fuel_type'] .' / '.$request_data['cc'].' CC',
            'make'                          => $request_data['make'],
            'model_variant'                 => $request_data['model'],
            'cubic_capacity'                => $request_data['cc'],
            'fuel_type'                     => $request_data['fuel_type'],
            'vehicle_model_code'			=> $mmv->model_code,

            'IMDShortcode_Dev'              => config('IC.CHOLLA_MANDALAM.V1.CAR.IMDSHORTCODE_DEV'),
            'product_id'                    => $product_id,
            'user_code'                     => config('IC.CHOLLA_MANDALAM.V1.CAR.USER_CODE'),
            'intermediary_code'             => '',//'2013965725280001',
            'partner_name'                  => '',

            'Customertype'                  => ($is_individual ? 'Individual' : 'Corporate'),
            'sel_policy_type'               =>  ($premium_type=='third_party' ? 'Third Party Liability' : ($is_od ?'Standalone OD':($requestData->business_type=='newbusiness' ? 'Long Term':'Comprehensive'))),

            'authorizeChk'                  => true,
            'no_previous_insurer_chk'       => false,

            'first_name'                    => $firstName,
            'fullName'                      => ($is_individual ? $proposal->first_name.' '.$proposal->last_name : $proposal->first_name),
            'cus_mobile_no'                 => $proposal->mobile_number,
            'email_id'                      => $proposal->email,
            'email'                         => $proposal->email,
            'phone_no'                      => $proposal->mobile_number,
            'mobile_no'                     => $proposal->mobile_number,
            'title'                         => (is_null($proposal->title) ? "" : $proposal->title ),

            'cust_mobile'                   => $proposal->mobile_number,
            'customer_dob_input'            => $cholla_model->get_excel_date($proposal->dob),
            'contract_no'                   => '',

            'NilDepselected'                => ($is_zero_dep ? 'Yes' : 'No'),

            'pa_cover'                      => ($is_cpa ? 'Yes' : 'No'),
            'PAAddon'                       => ($is_cpa ? 'Yes' : 'No'),
            'paid_driver_opted'             => $IsLLPaidDriver,
            'unnamed_cover_opted'           => ($PAToUnNamedPassenger_IsChecked ? 'Yes' : 'No'),
            'unnamed_passenger_cover_optional'      => ($PAToUnNamedPassenger_IsChecked ? 'Yes' : 'No'),
            'legal_liability_paid_driver_optional'	=> $IsLLPaidDriver,

            'consumables_cover_app'             => ($consumable=='Y' ? 'Yes' : 'No'),
            'hydrostatic_lock_cover_app'        => $engine_protector,
            'hydrostatic_lock_cover'            => $engine_protector,
            'key_replacement_cover_app'         => ($key_replacement=='Y' ? 'Yes' : 'No'),
            'no_of_unnamed'                     => ($PAToUnNamedPassenger_IsChecked ? ((int)($mmv->seating_capacity) - 1) : ''),
            'pc_cvas_cover'                     => 'No',
            'personal_belonging_cover_app'      => ($loss_of_belonging=='Y' ? 'Yes' : 'No'),
            'rsa_cover_app'                     => ($rsa=='Y' ? 'Yes' : 'No'),
            'vehicle_color'                     => $proposal->vehicle_color,

            'YOM'                           => $manfyear[1],
            'YOR'                           => (int)($cholla_model->get_excel_date('01-01-'.date('Y',strtotime($requestData->vehicle_register_date)))),

            'prev_insurer_name'             => ($proposal->previous_insurance_company!=null ? $proposal->previous_insurance_company :"No"),
            'prev_policy_no'                => $previous_policy_number,
            'prev_exp_date_comp'            => ($proposal->prev_policy_expiry_date !=null ? $cholla_model->get_excel_date($proposal->prev_policy_expiry_date):(empty($PreviousPolExpDt) ? "No" : $PreviousPolExpDt)),

            'place_of_reg'                  => $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'frm_rto'                       => $rto_data['state'] . '-' . $rto_data['txt_rto_location_desc'] . '(' . $rto_data['state'] . ')',
            'place_of_reg_short_code'       => $rto_data['txt_registration_state_code'],
            'IMDState'                      => $rto_data['txt_registration_state_code'],
            'city_of_reg'                   => $rto_data['txt_rto_location_desc'],
            'rto_location_code'				=> $rto_data['txt_rto_location_code'],

            'no_prev_ins'                   => ($new_vehicle ? "Yes" : 'No'),
            // 'save_percentage'               => '40%',

            'PrvyrClaim'                    => $yn_claim,
            'B2B_NCB_App'                   => '',
            'Lastyrncb_percentage'          => '',
            // 'D2C_NCB_PERCENTAGE'            => $no_claim_bonus.'%',
            'D2C_NCB_PERCENTAGE' => $requestData->previous_policy_type != 'Third-party'? $no_claim_bonus . '%' : "",

            'engine_no'  					=> (is_null($proposal->engine_number) ? "" :$proposal->engine_number),
            'chassis_no' 					=>  $proposal->chassis_number,#($proposal->chassis_no!=null ? $proposal->chassis_no : ''),

            'financier_details'             => ($proposal->is_vehicle_finance == '1' ? $proposal->name_of_financer : ''),
            'financieraddress'              => ($proposal->is_vehicle_finance == '1' ? $proposal->financer_location : ''),
            'hypothecated'                  => ($proposal->is_vehicle_finance == '1' ? 'Yes' : 'No'),

            'nominee_name'                  => (is_null($proposal->nominee_name) ? "" :$proposal->nominee_name),
            'nominee_relationship'          => (is_null($proposal->nominee_relationship) ? "" :$proposal->nominee_relationship),
            'AgeofNominee'                  => !is_null($proposal->nominee_age) ? $proposal->nominee_age : '0',

            'pan_number'                  	=> (is_null($proposal->pan_number) ? "" :$proposal->pan_number ),
            'customer_gst_no'          		=> (isset($proposal->gst_number) ? $proposal->gst_number : ''),

            'reg_area'                      => '',
            'reg_houseno'                   => '',
            // 'reg_no'                        => $requestData->business_type =='newbusiness' ? '' : $reg_no,
            'reg_no'                        => $requestData->business_type =='newbusiness' ? 'NEW' : $reg_no,
            'reg_street'                    => '',

            'reg_city'                      => $proposal->city,
            'reg_state'                     => $proposal->state,
            'reg_pincode'                   => $proposal->pincode,
            'reg_toggle'                    => ($proposal->is_car_registration_address_same == '1' ? false : true),

            'communi_area'                  => '',
            'communi_houseno'               => '',
            'communi_street'                => '',
            'commaddress'                   => ($proposal->is_car_registration_address_same == '1' ? '' : $proposal->car_registration_address1 .' '. $proposal->car_registration_address2),
            'communi_city'                  => ($proposal->is_car_registration_address_same == '1' ? '' : (is_null($proposal->car_registration_city)?'':$proposal->car_registration_city)),
            'communi_pincode'               => ($proposal->is_car_registration_address_same == '1' ? '' : (is_null($proposal->car_registration_pincode)?'':$proposal->car_registration_pincode)),
            'communi_state'                 => ($proposal->is_car_registration_address_same == '1' ? '' : (is_null($proposal->car_registration_state)?'':$proposal->car_registration_state)),

            'address'                       => $proposal->address_line1 .'|'. $proposal->address_line2.'|'.$proposal->address_line3,
            'pincode'                       => $proposal->pincode,
            'city'                          => $proposal->city,
            'state'                         => $proposal->state,

            // 'return_to_invoice'             => 'No',
            // 'roadtaxpaid'                   => '',

            // 'sel_allowance'                 => '',
            // 'sel_time_excess'               => '',

            'aadhar'                        => '',
            'account_no'                    => '',
            'agree_checbox'                 => '',
            'b2brto_master_availability'    => '',
            'branch_code_sol_id'            => '',
            'broker_code'                   => '',
            'claim_year'                    => '',
            'cmp_gst_no'                    => '',
            'covid19_addon'                 => 'No',
            'covid19_dcb_addon'             => 'No',
            'covid19_dcb_benefit'           => '',
            'covid19_lossofjob_addon'       => 'No',
            'd2cdtd_masterfetched'          => '',
            'd2cmodel_master_availability'  => '',
            'd2crto_master_availability'    => '',
            'emp_code'                      => '',
            'employee_id'                   => '',
            'enach_reg'                     => '',
            'od_prev_insurer_name'          => '',
            'od_prev_policy_no'             => '',
            'od_red'                        => '',
            'od_rsd'                        => '',
            'proposal_id'                   => '',
            'quote_id'                      => '',
            'sel_idv'                       => '',
            'seo_master_availability'       => '',
            'seo_policy_type'               => '',
            'seo_preferred_time'            => '',
            'seo_vehicle_type'              => '',
            'sol_id'                        => '',
            'tp_red'                        => '',
            'tp_rsd'                        => '',
            'user_type'                     => '',
            'usr_make'                      => '',
            'usr_mobile'                    => '',
            'usr_model'                     => '',
            'usr_name'                      => '',
            'usr_variant'                   => '',
            'utm_campaign'                  => '',
            'utm_content'                   => '',
            'utm_details'                   => '',
            'utm_medium'                    => '',
            'utm_source'                    => '',
            'utm_term'                      => '',
            'val_claim'                     => '',

            'externally_fitted_cng_lpg_opted'   => 'No', // $fuel_type_cng ? 'No' :($is_applicable['motor_lpg_cng_kit'] ? 'Yes' : 'No'), // https://github.com/Fyntune/motor_2.0_backend/issues/11095#issuecomment-1335125420
            'externally_fitted_cng_lpg_idv'     => '0', // $fuel_type_cng ? '0' : ($is_applicable['motor_lpg_cng_kit'] ? $BiFuelKitSi : '0'), // https://github.com/Fyntune/motor_2.0_backend/issues/11095#issuecomment-1335125420

            'cng_lpg_app'                       => ($fuel_type_cng ? 'Yes' : 'No'),
            'cng_lpg_value'                     => ($fuel_type_cng  ? '10000' : '0'),

            'externally_fitted_cng_lpg_max_idv' => '',
            'externally_fitted_cng_lpg_min_idv' => '',

            'elec_acc_app'              => ($is_applicable['motor_electric_accessories'] ? 'Yes' : 'No'),
            'elec_acc_desc'             => '',
            'elec_acc_idv'              => $requestData->electrical_acessories_value,
            'elec_acc_max_idv'          => '',
            'elec_acc_type_1'           => 'electrical_accessories',
            'elec_acc_value_1'          => $requestData->electrical_acessories_value,

            'non_elec_acc_app'          => ($is_applicable['motor_non_electric_accessories'] ? 'Yes' : 'No'),
            'non_elec_acc_desc'         => '',
            'non_elec_acc_idv'          => $requestData->nonelectrical_acessories_value,
            'non_elec_acc_max_idv'      => '',
            'non_elec_acc_type_1'       => 'non_electrical_accessories',
            'non_elec_acc_value_1'      => $requestData->nonelectrical_acessories_value,

            'save_percentage' => '',       

        ];
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
    
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if(config('REMOVE_POS_TAG_FOR_50_LAC_IDV_ENABLE') == 'Y' && $quote->idv >= 5000000){
            $quote_array['posp_name']=  '';
            $quote_array['POSPcode'] ='';
            $quote_array['POSPPAN']  = '';
            $quote_array['POSPaadhar']  = '';
            $quote_array['POSPcontactno'] = '';
            $quote_array['posp_direct'] =  '';
        }
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
        if($is_od){
            $quote_array['chola_value_added_services']  = 'No';
            $quote_array['daily_cash_allowance']        = 'No';
            $quote_array['emi_entered']                 = 0;
            $quote_array['hydrostatic_lock_cover']      = 'No';
            $quote_array['monthly_installment_cover']   = 'No';
            $quote_array['registrationcost']            = 0;
            $quote_array['reinstatement_value_basis']   = 'No';
            $quote_array['return_to_invoice']           = 'No';
            $quote_array['roadtaxpaid']                 = 0;
            // $quote_array['rto_location_code']           = '';
            $quote_array['sel_allowance']               = '';
            $quote_array['sel_time_excess']             = 0;
            $quote_array['vehicle_model_code']          = $mmv->model_code;

            if (strtoupper($requestData->previous_policy_type) != 'NOT SURE') {
                $quote_array['od_prev_insurer_name']        = $requestData->previous_insurer;
                $quote_array['od_prev_policy_no']           = $proposal->previous_policy_number;
                $quote_array['prev_insurer_name']           = $tp_insurance_company; //$proposal->tp_insurance_company
                $quote_array['prev_policy_no']              = $tp_insurance_number;//$proposal->tp_insurance_number
                $quote_array['tp_red']                      = $request_data['tp_red'];
                $quote_array['tp_rsd']                      = $request_data['tp_rsd'];
            }

            $quote_array['od_red']                      = $request_data['od_red'];
            $quote_array['od_rsd']                      = $request_data['od_rsd'];
        }
//print_r(json_encode($quote_array));
//die;
        if($requestData->business_type == 'newbusiness' && $is_cpa)
        {
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
        $additional_data = [
            'requestMethod' => 'post',
            'Authorization' => $token,
            'enquiryId' => $request_data['enquiryId'],
            'method' => 'Premium Calculation',
            'section' => $request_data['section'],
            'type' => 'request',
            'transaction_type' => 'proposal',
        ];
        $quote_url = ($is_package ? config('IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE') : ($is_liability ? config('IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE_TP') : config('IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_QUOTE_OD')));

        if(config('IC.CHOLLA_MANDALAM.V1.CAR.IS_TP_IMDSHORTCODE_DEV') == 'Y' && in_array($premium_type, ['third_party', 'third_party_breakin']))
        {
            $quote_array['IMDShortcode_Dev'] = config('IC.CHOLLA_MANDALAM.V1.CAR.TP_IMDSHORTCODE_DEV');
        }

        if (!in_array($premium_type, ['third_party_breakin', 'third_party'])) {
            $agentDiscount = calculateAgentDiscount($enquiryId, 'cholla_mandalam', 'car');
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
        
        $get_response = getWsData(
            $quote_url,
            $quote_array,
            'cholla_mandalam',
            $additional_data

        );
        $data = $get_response['response'];

        $quote_response = json_decode($data, true);
//        print_r($quote_response);die;
        if ($quote_response!=null) {


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
            }
            else {

                $quote_response = array_change_key_case_recursive($quote_response);

                $quote_response_data = $quote_response['data'];

                $base_cover['od'] = $quote_response_data['basic_own_damage_cng_elec_non_elec'];
                $base_cover['electrical'] = $quote_response_data['electrical_accessory_prem'];
                $base_cover['non_electrical'] = $quote_response_data['non_electrical_accessory_prem'];
                $base_cover['lpg_cng_od'] = $quote_response_data['cng_lpg_own_damage'];


                $base_cover['tp'] = $quote_response_data['basic_third_party_premium'];
                $base_cover['pa_owner'] = $quote_response_data['personal_accident'];
                $base_cover['unnamed'] = $quote_response_data['unnamed_passenger_cover'];
                $base_cover['paid_driver'] = '0';
                $base_cover['legal_liability'] = $quote_response_data['legal_liability_to_paid_driver'];
                $base_cover['lpg_cng_tp'] = $quote_response_data['cng_lpg_tp'];


                $base_cover['ncb'] = $quote_response_data['no_claim_bonus'];
                $base_cover['automobile_association'] = '0';
                $base_cover['anti_theft'] = '0';
                $base_cover['other'] = $quote_response_data['dtd_discounts'] + $quote_response_data['gst_discounts'];


                $base_cover['zero_dep'] = $quote_response_data['zero_depreciation'];
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




                // ro specific end

//                if (isset($prev_proposal_id)) {
//                    $agent_data = get_details('motor_agent_proposal', ['*'], ['motor_proposal_id' => base64_decode($prev_proposal_id), 'company_name' => 'CHOLLA_MANDALAM']);
//                    if (!empty($agent_data)) {
//                        $POS_data = get_agent_data($agent_data[0]['agent_id'], 'cholla_mandalam');
//                        if (!empty($POS_data)) {
//                            $posp_type = 'P';
//                            $posp_pan = $POS_data['pan_no'];
//                        }
//                    }
//                }else if (is_agent_logged_in()) {
//                    $POS_data = get_agent_data($this->session->userdata('agent_id'), 'cholla_mandalam');
//                    if (!empty($POS_data)) {
//                        $posp_type = 'P';
//                        $posp_pan = $POS_data['pan_no'];
//                    }
//                }


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

                $quote_response = json_decode($data, true);
                if (empty($quote_response)) {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Service issue'
                    ];
                }

                $isInspectionWaivedOff = false;
                $isInspectionRequired = $is_breakin && !(in_array($premium_type, ['third_party', 'third_party_breakin']));

                if (
                    $isInspectionRequired &&
                    !empty($requestData->previous_policy_expiry_date) &&
                    strtoupper($requestData->previous_policy_expiry_date) != 'NEW' &&
                    config('constants.IcConstants.cholla_madalam.CHOLLA_MANDALAM_INSPECTION_WAIVED_OFF') == 'Y'
                ) {
                    $date1 = new DateTime($requestData->previous_policy_expiry_date);
                    $date2 = new DateTime();
                    $interval = $date1->diff($date2);

                    //inspection is not required for breakin within 10 days
                    if ($interval->days <= 10) {
                        $isInspectionWaivedOff = true;
                    }
                }

                if ($isInspectionRequired && !$isInspectionWaivedOff) {
                    $token_array = [
                        'grant_type' => 'client_credentials'
                    ];
                    $token_breakin = getWsData(config('IC.CHOLLA_MANDALAM.V1.BREAKIN.TOKEN'), $token_array, 'cholla_mandalam', [
                        'requestMethod' => 'post',
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                            'Authorization' => 'Basic' . ' ' . config('IC.CHOLLA_MANDALAM.V1.BREAKIN.AUTH'),
                        ],
                        'enquiryId' => $request_data['enquiryId'],
                        'method' => 'Token Generation',
                        'productName' => $request_data['productName'],
                        'section' => 'car',
                        'transaction_type' => 'proposal',
                        'type'          => 'Breakin Token'
                    ]);
                    $token_response = json_decode($token_breakin['response']);
                    $actoken = $token_response->access_token;

                    $initiatebreak = [
                        'QuoteID' => $quote_response['Data']['quote_id'],
                        'CustomerName' => $proposal->first_name . ' ' . $proposal->last_name,
                        'EmailID' => $proposal->email,
                        'Mobilenumber' => $proposal->mobile_number,
                        'Productcode' => '3362',
                        'Vehiclemodelcode' => $mmv->model_code,
                        'RegistrationNumber' => $proposal->vehicale_registration_number,
                        'Intermediary_Code' => config('IC.CHOLLA_MANDALAM.V1.INTERMEDIARY.CODE'),//'201584922090',
                        'TieupFlag' => config('IC.CHOLLA.CAR.BREAKIN.BROKERNAME'), //config for BROKER NAME
                    ];
                    $additional_data = [
                        'requestMethod' => 'post',
                        'Authorization' => $actoken,
                        'enquiryId' => $request_data['enquiryId'],
                        'method' => 'Break-in Id Generation',
                        'section' => $request_data['section'],
                        'type' => 'request',
                        'transaction_type' => 'proposal',
                    ];
                    $break_in_url = getWsData(config('constants.IcConstants.cholla_madalam.INITIATE_BREAKIN'), $initiatebreak, 'cholla_mandalam', $additional_data);

                    //breakin response
                    $breakin_resp = json_decode($break_in_url['response']);
                    //Insert record into cv_breakin_status table
                    if (!empty($breakin_resp) && $breakin_resp->Status != false) {
                        DB::table('cv_breakin_status')->insert([
                            'user_proposal_id' => $proposal->user_proposal_id,
                            'ic_id' => $productData->company_id,
                            'breakin_number' => $breakin_resp->Referencenumber,
                            'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        //update user proposal
                        DB::table('user_proposal')
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'is_breakin_case' => 'Y',
                                'unique_quote' => $quote_response['Data']['quote_id'],
                                'final_payable_amount' => $quote_response['Data']['Total_Premium'],
                                'additional_details_data' => $quote_array
                            ]);
                        $breakin_inspection_no = $breakin_resp->Referencenumber;
                        $breakin_url_generate = $breakin_resp->Breakin_InspectionURL;
                        updateJourneyStage([
                            'user_product_journey_id' => $enquiryId,
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['INSPECTION_PENDING'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);
                        if (config('IC.CHOLLA_MANDALAM.ENABLE.SMS_NOTIFICATION') == 'Y') {
                            $old_api = $request;
                            $request = new Request();

                            $request->merge([
                                'mobileNo' => $proposal->mobile_number,
                                'link' => $breakin_url_generate,
                                'inspectionNo' => $breakin_inspection_no
                            ]);

                            sendSMS($request, null, 'inspectionIntimation');
                            $request = $old_api;
                        }
                        //return proposal submitted succesfully with breakin id and is_breakin = Y
                        return [
                            'status' => true,
                            'message' => STAGE_NAMES['INSPECTION_PENDING'],
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => $quote_response['Data']['quote_id'],
                                'finalPayableAmount' => $quote_response['Data']['Total_Premium'],
                                'is_breakin' => 'Y',
                                'inspection_number' => $breakin_resp->Referencenumber
                            ]
                        ];
                    } else {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => $breakin_resp->Message ?? 'Error in Break-in Service'
                        ];
                    }
                }
                $additional_data_proposal = [
                    'requestMethod' => 'post',
                    'Authorization' => $token,
                    'proposal_id' => '0',
                    'enquiryId' => $request_data['enquiryId'],
                    'method' => 'Proposal Submition - Proposal',
                    'section' => $request_data['section'],
                    'type' => 'request',
                    'transaction_type' => 'proposal',
                ];
//                return response()->json($quote_array);


                $get_response = getWsData(
                    config('IC.CHOLLA_MANDALAM.V1.CAR.END_POINT_URL_PROPOSAL'),
                    $quote_array,
                    'cholla_mandalam',
                    $additional_data_proposal

                );


                $proposaldata = $get_response['response'];
                if ($proposaldata)
                {

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
                    }
                    else
                    {

                        $proposal_response = array_change_key_case_recursive($proposal_response);

                        $proposal_response_data = $proposal_response['data'];
                        $payment_id       	= $proposal_response_data['payment_id'];
                        $total_premium 		= $proposal_response_data['total_premium'];
                        $service_tax_total 	= $proposal_response_data['gst'];
                        $base_premium 		= $proposal_response_data['net_premium'];
                 //premium calculation


//print_r($quote_response_data);
                        $base_cover['od'] = $quote_response_data['basic_own_damage_cng_elec_non_elec'];
                        $base_cover['electrical'] = $quote_response_data['electrical_accessory_prem'];
                        $base_cover['non_electrical'] = $quote_response_data['non_electrical_accessory_prem'];
                        $base_cover['lpg_cng_od'] = $quote_response_data['cng_lpg_own_damage'];

                        $base_cover['tp'] = $quote_response_data['basic_third_party_premium'];
                        $base_cover['pa_owner'] = $quote_response_data['personal_accident'];
                        $base_cover['unnamed'] = $quote_response_data['unnamed_passenger_cover'];
                        $base_cover['paid_driver'] = '0';
                        $base_cover['legal_liability'] = $quote_response_data['legal_liability_to_paid_driver'];
                        $base_cover['lpg_cng_tp'] = $quote_response_data['cng_lpg_tp'];

                        $base_cover['ncb'] = $quote_response_data['no_claim_bonus'];
                        $base_cover['automobile_association'] = '0';
                        $base_cover['anti_theft'] = '0';
                        $base_cover['other_discount'] = $quote_response_data['dtd_discounts'] + $quote_response_data['gst_discounts'];

                        $addon['zero_dep'] = (($quote_response_data['zero_depreciation'] == '0') ? 'NA' : $quote_response_data['zero_depreciation']);
                        $addon['key_replacement'] = (($quote_response_data['key_replacement_cover'] == '0') ? 'NA' : $quote_response_data['key_replacement_cover']);
                        $addon['consumable'] = (($quote_response_data['consumables_cover'] == '0') ? 'NA' : $quote_response_data['consumables_cover']);
                        $addon['loss_of_belongings'] = (($quote_response_data['personal_belonging_cover'] == '0') ? 'NA' : $quote_response_data['personal_belonging_cover']);
                        $addon['rsa'] = (($quote_response_data['rsa_cover'] == '0') ? 'NA' : $quote_response_data['rsa_cover']);
                        $addon['engine_protect']  = (($quote_response_data['hydrostatic_lock_cover'] == '0') ? 'NA' : $quote_response_data['hydrostatic_lock_cover']);
                        $addon['tyre_secure'] = 'NA';
                        $addon['return_to_invoice'] = 'NA';
                        $addon['ncb_protect'] = 'NA';

                        $total_premium_amount = $quote_response_data['total_premium'];


                        $base_cover['tp'] = $base_cover['tp'];// + $base_cover['legal_liability'];

                        if ($addon['zero_dep'] == 'NA' && $is_zero_dep) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => 'Zero dep value issue',
                                'car_age' => $car_age,
                                'reg_date' => $requestData->vehicle_register_date
                            ];
                        }
                        $add_ons_data = [];
                        if($masterProduct->product_identifier == 'BASIC_ADDONS'){
                            $add_ons_data = [
                                'in_built' => [],
                                'additional' => [
                                    // 'zeroDepreciation' => $addon['zero_dep'],
                                    'road_side_assistance' => $addon['rsa'],
                                    'engineProtector' => $addon['engine_protect'],
                                    'ncbProtection' => 0,
                                    'keyReplace' => $addon['key_replacement'],
                                    'consumables' => $addon['consumable'],
                                    'tyreSecure' => 0,
                                    'returnToInvoice' => 0,
                                    'lopb' => $addon['loss_of_belongings'],
                                    'cpa_cover' => $base_cover['pa_owner']
                                ],
                                'other' => [
                                        // 'LL_paid_driver' => 0//$base_cover['legal_liability'] temprory removed abhishek need to build logic for it
                                        ],
                                ];
                        }elseif($masterProduct->product_identifier == 'ZERO_DEP'){
                            $add_ons_data = [
                                'in_built' => [
                                    'zeroDepreciation' => $addon['zero_dep'],
                                ],
                                'additional' => [
                                    'road_side_assistance' => $addon['rsa'],
                                    'engineProtector' => $addon['engine_protect'],
                                    'ncbProtection' => 0,
                                    'keyReplace' => $addon['key_replacement'],
                                    'consumables' => $addon['consumable'],
                                    'tyreSecure' => 0,
                                    'returnToInvoice' => 0,
                                    'lopb' => $addon['loss_of_belongings'],
                                    'cpa_cover' => $base_cover['pa_owner']
                                ],
                                'other' => [
                                        // 'LL_paid_driver' => 0//$base_cover['legal_liability'] temprory removed abhishek need to build logic for it
                                        ],
                                ];
                        }elseif($masterProduct->product_identifier == 'BASIC'){
                            $add_ons_data = [
                                'in_built' => [],
                                'additional' => [],
                                'other' => [],
                                ];
                        }

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
                        $total_tp = $base_cover['tp'] + $base_cover['legal_liability'] + $base_cover['unnamed'] + $base_cover['lpg_cng_tp']+$base_cover['pa_owner'];

                        $total_discount = $base_cover['other_discount'] + $base_cover['automobile_association'] + $base_cover['anti_theft'] + $base_cover['ncb'];
//print_r($base_cover['pa_owner'].'-----');
//                        print_r($addon_sum.'///');
//print_r($total_od.'--'.$total_tp.'--'.$total_discount.'--'.$addon_sum);

                        $basePremium = $total_od + $total_tp - $total_discount+$addon_sum;

                        $totalTax = $basePremium * 0.18;

                        $final_premium = $basePremium + $totalTax;
   $policy_start_date = date('d-m-Y',strtotime(str_replace('/', '-', $policy_start_date)));
                        $policy_end_date = date('d-m-Y',strtotime(str_replace('/', '-', $policy_end_date)));
                        $pg_transaction_id = date('Ymd').time();


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

                        $proposalUpdate = [
                            'proposal_no' => $payment_id,
                            //'unique_proposal_id' => $payment_id,
                            'policy_start_date' =>  str_replace('00:00:00', '', $policy_start_date),
                            'policy_end_date' =>  $requestData->business_type == 'newbusiness' && $premium_type == 'comprehensive' ?  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-'))): ($requestData->business_type == 'newbusiness' && $premium_type == 'third_party' ?   date('d-m-Y', strtotime(strtr($policy_start_date . ' + 3 year - 1 days', '/', '-'))):  date('d-m-Y', strtotime(strtr($policy_start_date . ' + 1 year - 1 days', '/', '-')))),
                            'od_premium' => round($total_od - $total_discount),
                            'tp_premium' => round($total_tp),
                            'total_premium' => round($basePremium),
                            'addon_premium' => round($addon_sum),
                            'cpa_premium' => $base_cover['pa_owner'],
                            'service_tax_amount' => round($totalTax),
                            'total_discount' => round($total_discount),
                            'final_payable_amount' => $proposal_response_data['total_premium'],#round($final_premium),
                            'ic_vehicle_details' => '',
                            'discount_percent' => $no_claim_bonus.'%',
                            'vehicale_registration_number'=>$proposal->vehicale_registration_number,
                            'engine_no'=>$proposal->engine_number,
                            'chassis_no'=>$proposal->chassis_number,
                            'final_premium'=>env('APP_ENV') == 'local' ? config('constants.IcConstants.cholla_madalam.STATIC_PAYMENT_AMOUNT_CHOLLA_MANDALAM') : $final_premium,
                            'product_code'=>$proposal->product_code,
                            'ncb_discount'=>$base_cover['ncb'],
                            'dob'=>($proposal->dob!=null ? date("Y-m-d", strtotime($proposal->dob)):''),
                            'nominee_dob'=>($proposal->nominee_dob!=null ? date("Y-m-d", strtotime($proposal->nominee_dob)):''),
                            'cpa_policy_fm_dt'=>($proposal->cpa_policy_fm_dt!=null ? date("Y-m-d", strtotime($proposal->cpa_policy_fm_dt)): ''),
                            'cpa_policy_to_dt'=>($proposal->cpa_policy_to_dt!=null ? date("Y-m-d", strtotime($proposal->cpa_policy_to_dt)):''),
                            'cpa_policy_no'=>$proposal->cpa_policy_no,
                            'cpa_sum_insured'=>$proposal->cpa_sum_insured,
                            'car_ownership'=>$proposal->car_ownership,
                            'electrical_accessories'=>$proposal->electrical_accessories,
                            'non_electrical_accessories'=>$proposal->non_electrical_accessories,
                            'version_no'=>$proposal->version_no,
                            'vehicle_category'=>$proposal->vehicle_category,
                            'vehicle_usage_type'=>$proposal->vehicle_usage_type,
                            'tp_start_date'=>$tp_start_date,
                            'tp_end_date'=>$tp_end_date,
                            'tp_insurance_company'=>$tp_insurance_company,
                            'tp_insurance_number'=>$tp_insurance_number,
                        ];

                        UserProposal::where('user_product_journey_id', $enquiryId)
                        ->where('user_proposal_id', $proposal->user_proposal_id)
                        ->update($proposalUpdate);

                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
                            'ic_id' => $productData->company_id,
                            'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                            'proposal_id' => $proposal->user_proposal_id,
                        ]);

                        $proposal_data = UserProposal::find($proposal->user_proposal_id);

                        ChollaMandalamPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                        return [
                            'status' => true,
                            'message' => "Proposal Submitted Successfully!",
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'data' => [
                                'proposalId' =>  $proposal->user_proposal_id,
                                'proposalNo' => $payment_id,
                                'userProductJourneyId' => $proposal_data->user_product_journey_id,
                            ]
                        ];


                    }
                }
                else
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                    ];
                }
            }
        }else {
            return [
                'status'   => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'  => 'Sorry, we are unable to process your request at the moment. Kindly retry the transaction.',
                'error'		=> 'no response form service'
            ];
        }

    }
}
