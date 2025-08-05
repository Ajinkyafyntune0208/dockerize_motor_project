<?php

namespace App\Http\Controllers;

use DateTime;
use Exception;
use App\Models\ProposalHash;
use App\Models\Agents;
use App\Models\Gender;
use App\Models\Feedback;
use App\Models\QuoteLog;
use App\Models\SbiColor;
use App\Models\MasterRto;
use App\Models\CompareSms;
use App\Models\MotorModel;
use Illuminate\Support\Str;
use App\Models\BrokerDetail;
use App\Models\JourneyStage;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterCompany;
use App\Models\MasterProduct;
use App\Models\PolicyDetails;
use Ixudra\Curl\Facades\Curl;
use App\Mail\UserCreationMail;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use App\Models\CvBreakinStatus;
use App\Models\PreviousInsurer;
use App\Models\usgiColorMaster;
use Illuminate\Validation\Rule;
use App\Models\MasterOccupation;
use App\Models\MotorModelVersion;
use App\Models\CarApplicableAddon;
use App\Models\GcvApplicableAddon;
use App\Models\kotakPincodeMaster;
use App\Models\nicFinancierMaster;
use App\Models\PcvApplicableAddon;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\AgentIcRelationship;
use App\Models\BikeApplicableAddon;
use App\Models\LsqJourneyIdMapping;
use App\Models\newIndiaColorMaster;
use App\Models\NicVehicleColorMaster;
use App\Models\NomineeRelationship;
use App\Models\PreviousInsurerList;
use App\Models\ShriramPinCityState;
use App\Models\VoluntaryDeductible;
use App\Models\kotakFinancierMaster;
use App\Models\MagmaFinancierMaster;
use App\Models\MasterProductSubType;
use App\Models\TataAigPincodeMaster;
use Illuminate\Support\Facades\Http;
use \Illuminate\Support\Facades\Mail;
use App\Models\RahejaFinancierMaster;
use App\Models\DefaultApplicableCover;
use App\Models\FinancierAgreementType;
use App\Models\ShriramFinancierMaster;
use App\Models\VehicleCategoriesModel;
use App\Models\VehicleUsageTypesModel;
use Illuminate\Support\Facades\Schema;
use App\Models\CkycAckoFailedCasesData;
use App\Models\FastlaneRequestResponse;
use App\Models\HdfcErgoFinancierMaster;
use App\Models\MagmaMotorPincodeMaster;
use App\Models\MasterOrganizationTypes;
use App\Models\MasterIndustryType;

use Illuminate\Support\Facades\Storage;
use App\Models\edelweissFinancierMaster;
use App\Models\UserTokenRequestResponse;
use App\Models\HdfcErgoV2FinancierMaster;
use App\Models\HdfcErgoMiscFinancierMaster;
use App\Models\IciciLombardPincodeMaster;
use App\Models\WebServiceRequestResponse;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\AdrilaController;
use App\Models\CkycGodigitFailedCasesData;
use App\Models\HdfcErgoMotorPincodeMaster;
use App\Models\iffco_tokioFinancierMaster;
use App\Models\RoyalSundaramPincodeMaster;
use App\Models\UnitedIndiaFinancierMaster;
use App\Models\UnitedIndiaFinancierBranchMaster;
use App\Models\chollamandalamPincodeMaster;
use App\Models\HdfcErgoV2MotorPincodeMaster;
use App\Http\Controllers\Mail\MailController;
use App\Http\Controllers\Zoop\ZoopController;
use App\Models\chollaMandalamFinancierMaster;
use App\Models\GodigitPincodeStateCityMaster;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\LibertyVideoconFinancierMaster;
use App\Models\ManufacturerTopFive;
use App\Models\{
    AdditionalDetails,
    BrokerConfigAsset,
    ReliancePincodeStateCityMaster,
    JourneycampaignDetails, MiscdApplicableAddons, MmvBlocker, PospUtility, PospUtilityMmv, ProposalFields, UserJourneyAdditionalData, VahanServicePriorityList, VersionCount, HidePyi, ProposalExtraFields};
use App\Http\Controllers\AgentMappingController;
use App\Models\WebserviceRequestResponseDataOptionList;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Controllers\EnquiryIdValidation\EnquiryIdController;
use App\Http\Controllers\Masters\MmvMasterController;
use App\Models\PaymentRequestResponse;
use Maatwebsite\Excel\Concerns\ToArray;
use Mtownsend\XmlToArray\XmlToArray;

use App\Http\Controllers\Lte\Admin\CommunicationConfigurationController;
use App\Models\ckycUploadDocuments;
use App\Http\Controllers\Extra\UtilityApi;
use App\Http\Controllers\PospUtility\PospUtilityController;
use Illuminate\Support\Facades\Artisan;
use App\Models\rtoCityName;
use App\Jobs\VahanUploadMigration;
use App\Models\JourneyTokenMapping;
use App\Models\ManufacturerPriority;
use App\Models\InsurerLogoPriorityList;

class CommonController extends Controller
{

    public function getProductDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstName' => 'nullable',
            'lastName' => 'nullable',
            'mobileNo' => 'nullable|numeric|digits:10',
            'emailId' => ( ( config( 'email.dns.validation.enabled' ) == "Y" ) ? 'nullable|email:rfc,dns' : 'nullable|email:rfc' ),
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $checkTransactionStageValidity = checkTransactionStageValidity(customDecrypt($request->enquiryId));
        if(!$checkTransactionStageValidity['status']) {
            return response()->json($checkTransactionStageValidity);
        }

        $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
        
        $agentDetail =  CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                                            ->get()
                                            ->toArray();
        $pos_flag = 'N';
        foreach ($agentDetail as $key => $value) {
            $EV_Category = config('POS_CATEGORY_IDENTIFIER');
            if($value['seller_type'] == 'P' && $EV_Category !== NULL && $value['category'] !== NULL && $value['category'] == $EV_Category)
            {
               $pos_flag = 'EV';
               break;
            }
            else if($value['seller_type'] == 'P' && $value['category'] == 'Essone')
            {
               $pos_flag = 'A';
               break;
            }
            else if($value['seller_type'] == 'P')
            {
               $pos_flag = 'P';
               break;
            } 
            else if ($value['seller_type'] == 'E') {
                $pos_flag = 'E';
                break;
            }
            else if($value['seller_type'] == 'misp')//MISP
            {
                $pos_flag = 'M';
                break;
            }
        }
        
        if(trim($CorporateVehiclesQuotesRequest['journey_type']) == 'driver-app')
        {
           $pos_flag = 'D'; 
        }
        
        $parent_code = strtolower(get_parent_code($request->productSubTypeId));
        if($request->selectedPreviousPolicyType == 'Not sure')
        {
           if($request->policyType == 'own_damage')
           {
              $premiumTypeId = [6];
           }
           else
           {
              $premiumTypeId = [4,7];
           }
        }
        else if($request->selectedPreviousPolicyType == 'Third-party' && $request->businessType == "breakin")
        {
           if($request->policyType == 'own_damage')
           {
              $premiumTypeId = [6];
           }
           else
           {
              $premiumTypeId = [4,7];
           }
        }
        else if($request->selectedPreviousPolicyType == 'Third-party' && $request->businessType == "rollover")
        {
           if($request->policyType == 'own_damage')
           {
              $premiumTypeId = [6];
           }
           else
           {
              $premiumTypeId = [4,2];
           }
        }
        else
        {

            if ($request->policyType == 'own_damage') {
                if ($request->businessType == 'breakin') {
                    $premiumTypeId = [6];
                } else {
                    $premiumTypeId = [3];
                }
            } else {
                if ($request->businessType == 'breakin') {
                    $premiumTypeId = [4, 7];
                } else {
                    $premiumTypeId = [1, 2];
                }
            }

        }

        if ($request->policyType == 'comprehensive')
        {
            if($request->selectedPreviousPolicyType == 'Third-party' && $request->businessType == 'rollover')
            {
                array_push($premiumTypeId, 9);
                array_push($premiumTypeId, 10);
            }
            else if($request->businessType == 'rollover')
            {
                array_push($premiumTypeId, 5);
                array_push($premiumTypeId, 8);
            }
            else if($request->businessType == 'breakin')
            {
                array_push($premiumTypeId, 9);
                array_push($premiumTypeId, 10);
            }
        }
        $is_renewal_allowed = config('IS_RENEWAL_ALLOWED');
        $st_previous_insurer = $request->previousInsurer;
        $SHORT_TERM_RENEWAL_REMOVE_IC = explode(',',config('SHORT_TERM_RENEWAL_REMOVE_IC'));
        if($request->previousInsurer != '' && $is_renewal_allowed == 'Y')
        {
          if($parent_code == 'pcv' || $parent_code == 'gcv')
          {
            $allowed_ic = config('RENEWAL_ALLOWED_IC');
            $allowed_ic_array = explode(',',$allowed_ic);            
            //if($request->previousInsurer == 'godigit' || $request->previousInsurer == 'acko')
            if($CorporateVehiclesQuotesRequest['is_renewal'] != 'Y' && in_array($request->previousInsurer,$allowed_ic_array))
            {
                $request->previousInsurer = '';
            }
          }
        }

        $CorporateVehiclesOwnerType = $request->vehicleOwnerType ?? $CorporateVehiclesQuotesRequest['vehicle_owner_type'];
        $result = DB::table('master_policy as mp')
        ->where('mp.product_sub_type_id', $request->productSubTypeId)
        ->whereIn('mp.premium_type_id', $premiumTypeId)
        ->whereRaw("FIND_IN_SET('$pos_flag', pos_flag)")
        ->whereRaw("FIND_IN_SET('$CorporateVehiclesOwnerType', owner_type)");
        
        if(isset($agentDetail[0]['seller_type']) && $agentDetail[0]['seller_type'] == "Dealer"){
            $dealer = \Illuminate\Support\Facades\DB::table('original_equipment_manufacturer_dealers')->where('id', $agentDetail[0]['agent_id'])->first();
            $payment_modes = \Illuminate\Support\Facades\DB::table('oem_dealer_payment_modes')->where('dealer_id', $dealer->id)->get('payment_mode_id')->pluck('payment_mode_id');
            $payment_modes = \Illuminate\Support\Facades\DB::table('payment_modes as pm')->join('payment_modes as pm_child', 'pm_child.id', 'pm.payment_mode_id')->whereIn('pm.id', $payment_modes)->get('pm_child.name');
            if($payment_modes){
                $payment_modes = $payment_modes->pluck('name')->toArray();
                if (in_array('Online', $payment_modes) && in_array('Offline', $payment_modes)) {
                    $result = $result->whereIn('is_payment_online', ['Yes', "No"]);
                } else if (in_array('Offline', $payment_modes)) {
                    $result = $result->where('is_payment_online', 'No');
                } else if (in_array('Online', $payment_modes)) {
                    $result = $result->where('is_payment_online', 'Yes');
                }
            }
            $result = $result->where('oem_id', $dealer->oem_id);
        }
        if($request->selectedPreviousPolicyType != 'Third-party' && $request->businessType != "breakin")
            {
              $result = $result->whereRaw("FIND_IN_SET('$request->businessType', mp.business_type)");
            }
            $result = $result->where('mp.status', 'Active')
            ->join('master_company as mc', 'mc.company_id', '=', 'mp.insurance_company_id');
             
            if($request->businessType != "breakin"){
                $result =  $result->where('mc.company_alias', '!=', $request->previousInsurer);
            }

            if (!empty(config('constants.motorConstant.block_ic'))) {
                $seller_types = collect($agentDetail)->pluck('seller_type')->toArray();
                if (in_array('P', $seller_types)) {
                    $seller_ids = collect($agentDetail)->where('seller_type', 'P')->first()['agent_id'];
                    if (!empty(config('constants.motorConstant.block_pos')) && in_array($seller_ids, explode(',', config('constants.motorConstant.block_pos')))) {
                        $result = $result->whereNotIn('mc.company_alias', explode(',', config('constants.motorConstant.block_ic')));
                    }

                }
                if (in_array('E', $seller_types)) {
                    $seller_ids = collect($agentDetail)->where('seller_type', 'E')->first()['agent_id'];
                    if (!empty(config('constants.motorConstant.block_employee')) && in_array($seller_ids, explode(',', config('constants.motorConstant.block_employee')))) {
                        $result = $result->whereNotIn('mc.company_alias', explode(',', config('constants.motorConstant.block_ic')));
                    }
                }
            }
           
                    
            $result = $result->join('master_product_sub_type as mpst', 'mpst.product_sub_type_id', '=', 'mp.product_sub_type_id')
            ->join('master_premium_type as mpt', 'mpt.id', '=', 'mp.premium_type_id')
            ->join('master_product as mpro', 'mpro.master_policy_id', '=', 'mp.policy_id')
            ->select('mp.policy_id', 'mc.company_id', 'mc.company_name', 'mc.company_shortname', 'mc.company_alias', 'mc.url', 'mc.logo', 'mpst.product_sub_type_id', 'mpst.product_sub_type_code', 'mpst.product_sub_type_name', 'mpst.short_name', 'mpt.premium_type_code', 'mp.zero_dep',
                'mpro.product_identifier', 'mpro.product_name' ,'mp.good_driver_discount','mp.tenure')
            ->orderBy('mp.zero_dep', 'asc')
             ->get();
            
        //if ($result->count()) {
        $returnArray = [];
        foreach ($result as $key => $value) {
            $value->claim_covered = NULL;
            if($value->company_alias == 'godigit' && $value->product_sub_type_id == 1 && $value->zero_dep == 0)
            {
                if($value->product_identifier == 'zero_dep')
                {
                   $value->claim_covered = 'ONE'; 
                }
                else if($value->product_identifier == 'zero_dep_double_claim')
                {
                   $value->claim_covered = 'TWO'; 
                }
                else if($value->product_identifier == 'zero_dep_unlimited_claim')
                {
                   $value->claim_covered = 'UNLIMITED'; 
                }  
            }
            if($value->good_driver_discount=='Yes')
            {
                $value->good_driver_discount="Y";
            }
            else
            {
                $value->good_driver_discount="N";
            }
            //$returnArray[$value->premium_type_code == 'breakin' ? 'comprehensive' : $value->premium_type_code][] = camelCase($value);

            if ($value->premium_type_code == 'third_party_breakin') {
                $returnArray['third_party'][] = camelCase($value);
            } 
            else if (in_array($value->premium_type_code, ['short_term_3', 'short_term_6','short_term_3_breakin','short_term_6_breakin'])) 
            {
                //Handling short-term products for other brokers incase of short-term renewal new flow
                if (config('SHORT_TERM_MONTHS_RENEWAL_NEW_FLOW') == 'Y') 
                {
                    if (!(in_array($st_previous_insurer, $SHORT_TERM_RENEWAL_REMOVE_IC) 
                        && $value->company_alias == $st_previous_insurer || $request->previousInsurer == 'tata_aig')) 
                    {
                        $returnArray['short_term'][] = camelCase($value);
                    }
                }
                else
                {
                    if (!(in_array($st_previous_insurer, $SHORT_TERM_RENEWAL_REMOVE_IC) && $value->company_alias == $st_previous_insurer)) 
                    {
                        $returnArray['short_term'][] = camelCase($value);
                    }
                }               
            } else {
                $arr_tenure = [
                    // 11 => '1_Year_TP_1_Year_OD',
                    '22' => '2_Year_TP_2_Year_OD',
                    '33' => '3_Year_TP_3Year_OD',
                    // 01 => '1_Year_TP',
                    '02' => '2_Year_TP',
                    '03' => '3_Year_TP'
                ];
                $is_multiyear = null;
                if(in_array($value->tenure,$arr_tenure))
                { 
                    $value->premium_type_code = $arr_tenure[$value->tenure];
                    $value->is_multiyear = 'Y';  
                }
                else{
                    $value->is_multiyear = 'N';
                }
                $value->jApiUrl = in_array($value->company_alias, explode(',', config('JAVA_API_COMPANY_ALIAS_LIST')))
                                    ? config('JAVA_API_URL') . "/$value->company_alias"
                                    : null;
                $returnArray[(in_array($value->premium_type_code, ['breakin', 'own_damage', 'own_damage_breakin','2_Year_TP_2_Year_OD','3_Year_TP_3_Year_OD','2_Year_TP','3_Year_TP'])) ? 'comprehensive' : $value->premium_type_code][] = camelCase($value);
            }
            
           
        }
        //Handling Short term renewal for TATA i.e rollover journey
        if (isset($returnArray['short_term']) && config('SHORT_TERM_MONTHS_RENEWAL_NEW_FLOW') == 'Y' && $st_previous_insurer == 'tata_aig') {
            // Check if short-term has tata in it
            $hasTataAigInShortTerm = false;
            foreach ($returnArray['short_term'] as $item) {
                if (isset($item['companyAlias']) && $item['companyAlias'] == 'tata_aig') {
                    $hasTataAigInShortTerm = true;
                    break;
                }
            }
            // If 'tata_aig' is found in short_term, unset it from comprehensive and third_party
            if ($hasTataAigInShortTerm) {
                // Remove all Tata AIG products from comprehensive
                if (isset($returnArray['comprehensive']) && is_array($returnArray['comprehensive'])) {
                    $returnArray['comprehensive'] = array_values(array_filter($returnArray['comprehensive'], function ($item) {
                        return !(isset($item['companyAlias']) && $item['companyAlias'] == 'tata_aig');
                    }));
                }
                // Remove all Tata AIG products from third_party
                if (isset($returnArray['third_party']) && is_array($returnArray['third_party'])) {
                    $returnArray['third_party'] = array_values(array_filter($returnArray['third_party'], function ($item) {
                        return !(isset($item['companyAlias']) && $item['companyAlias'] == 'tata_aig');
                    }));
                }
            }
        }
    
        if (!isset($returnArray['comprehensive'])) {
            $returnArray['comprehensive'] = [];
        }
        if (!isset($returnArray['third_party'])) {
            $returnArray['third_party'] = [];
        }
        if (!isset($returnArray['short_term'])) {
            $returnArray['short_term'] = [];
        }
        $section_wise_renewal_allowed = false;
        if($parent_code == 'bike')
        {
            $allowed_ic = config('BIKE_RENEWAL_ALLOWED_IC');
            $allowed_ic_array = explode(',',$allowed_ic); 
            if(in_array($CorporateVehiclesQuotesRequest['previous_insurer_code'],$allowed_ic_array))
            {
                $section_wise_renewal_allowed = true;
            }
            
        }
        else if($parent_code == 'car')
        {
            $allowed_ic = config('CAR_RENEWAL_ALLOWED_IC');
            $allowed_ic_array = explode(',',$allowed_ic);
            if(in_array($CorporateVehiclesQuotesRequest['previous_insurer_code'],$allowed_ic_array))
            {
                $section_wise_renewal_allowed = true;
            }
        }
        else if($parent_code == 'pcv' || $parent_code == 'gcv')
        {
            $allowed_ic = config('CV_RENEWAL_ALLOWED_IC');
            $allowed_ic_array = explode(',',$allowed_ic);
            if(in_array($CorporateVehiclesQuotesRequest['previous_insurer_code'],$allowed_ic_array))
            {
                $section_wise_renewal_allowed = true;
            }
        }

        if($request->businessType == "breakin")
        {
            $section_wise_renewal_allowed = false;
        }

        $blockRenewal = ($request->hideRenewal ?? false) === true;
        if($CorporateVehiclesQuotesRequest['is_renewal'] == 'Y' && $section_wise_renewal_allowed == true  && !$blockRenewal)
        {
            $request->previousInsurer = $CorporateVehiclesQuotesRequest['previous_insurer_code'];
            $applicable_premium_type_id = $CorporateVehiclesQuotesRequest['applicable_premium_type_id'];
            $previous_master_policy_id = $CorporateVehiclesQuotesRequest['previous_master_policy_id'];
            
            $selected_addons =  SelectedAddons::where('user_product_journey_id', customDecrypt($request->enquiryId))
                                            ->get()
                                            ->first();
            $ZD = '1';
            if(isset($selected_addons['applicable_addons']))
            {
                foreach ($selected_addons['applicable_addons'] as $key => $value) 
                {
                    if(isset($value['name']) && $value['name'] == 'Zero Depreciation')
                    {
                       $ZD = '0';
                       break;
                    }
                }                
            }
            
            if(!in_array($request->productSubTypeId,[1,2]))
            {
                $group_1 = [1,5,8];//ROLLOVER,
                $group_2 = [4,9,10];//BREAKIN
                if(in_array($applicable_premium_type_id,$group_1))
                {
                   $applicable_premium_type_id = $group_1;
                }
                else if(in_array($applicable_premium_type_id,$group_2))
                {
                   $applicable_premium_type_id = $group_2;
                }
                else
                {
                    $applicable_premium_type_id = [$applicable_premium_type_id];
                }
                // RENEWAL BREAKIN ALLOWED IN CV
                if(isset($request->businessType) && ($request->businessType == 'breakin'))
                {
                    $applicable_premium_type_id = $group_2;
                }
            }
            else
            {
                $applicable_premium_type_id = NULL;
                if($applicable_premium_type_id == NULL)
                {
                    $applicable_premium_type_id = $premiumTypeId;
                }
                else
                {
                    $applicable_premium_type_id = [$applicable_premium_type_id]; 
                }  
            }
            
            $result = DB::table('master_policy as mp')
                ->where('mp.product_sub_type_id', $request->productSubTypeId)
                //->where('mp.zero_dep', $ZD)
                //->whereIn('mp.premium_type_id', [$applicable_premium_type_id])
                ->whereRaw("FIND_IN_SET('$pos_flag', pos_flag)");
                if($request->selectedPreviousPolicyType != 'Third-party' && $request->businessType != "breakin")
                {
                  $result = $result->whereRaw("FIND_IN_SET('$request->businessType', mp.business_type)");
                }
//                if($previous_master_policy_id != '')
//                {
//                   $result = $result->where('mp.policy_id', $previous_master_policy_id); 
//                }
//                else
//                {
//                    $result = $result->where('mp.zero_dep', $ZD)
//                                ->whereIn('mp.premium_type_id', [$applicable_premium_type_id]);
//                }
                $result = $result->whereIn('mp.premium_type_id', $applicable_premium_type_id);
                $result = $result->where('mp.status', 'Active')
                ->join('master_company as mc', 'mc.company_id', '=', 'mp.insurance_company_id');

                $result =  $result->where('mc.company_alias', '=', $request->previousInsurer);

                $result = $result->join('master_product_sub_type as mpst', 'mpst.product_sub_type_id', '=', 'mp.product_sub_type_id')
                ->join('master_premium_type as mpt', 'mpt.id', '=', 'mp.premium_type_id')
                ->select('mp.policy_id', 'mc.company_id', 'mc.company_name', 'mc.company_shortname', 'mc.company_alias', 'mc.url', 'mc.logo', 'mpst.product_sub_type_id', 'mpst.product_sub_type_code', 'mpst.product_sub_type_name', 'mpst.short_name', 'mpt.premium_type_code', 'mp.zero_dep')
                ->orderBy('mp.zero_dep', 'desc')
                ->get();
            foreach ($result as $key => $value) {
                $value->is_renewal = 'Y';
                $returnArray['renewal'][] = camelCase($value);
            }            
        }
        
        $comprehensive = $returnArray['comprehensive'];
        $third_party = $returnArray['third_party'];
        $short_term = $returnArray['short_term'];
        $renewal = $returnArray['renewal'] ?? [];
        unset($returnArray);
        
        $returnArray['comprehensive'] = $comprehensive;
        $returnArray['third_party'] = $third_party;
        $returnArray['short_term'] = $short_term;


        if($CorporateVehiclesQuotesRequest['is_renewal'] == 'Y')
        {
            foreach($renewal as $r_k => $r_v)
            {
                // $r_v["redirection_url"] = config('constants.motorConstant.CAR_FRONTEND_URL');
                if(in_array($r_v['premiumTypeCode'],['short_term_3','short_term_6','short_term_3_breakin','short_term_6_breakin']))
                {
                   $returnArray['short_term'][] = $r_v; 
                }
                else {
                    if (in_array($CorporateVehiclesQuotesRequest['previous_policy_type'], ['Comprehensive', 'Own Damage', 'Own-damage', 'breakin', 'Third Party', 'Third-party', 'third_party_breakin'])) 
                    {
                        if (in_array($r_v['premiumTypeCode'], ['Comprehensive', 'comprehensive','Own Damage', 'Own-damage', 'breakin','own_damage', 'own_damage_breakin']))
                        {
                            $returnArray['comprehensive'][] = $r_v;
                        }
                        else if (in_array($r_v['premiumTypeCode'], ['Third Party', 'Third-party', 'third_party_breakin', 'third_party']))
                        {
                            $returnArray['third_party'][] = $r_v;
                        }
                    }
                }
            }
        }

        if (isset($request->tpOdOnly) && !empty($request->tpOdOnly)) {
            
            if($request->tpOdOnly  == "01" ) {

                $returnArray['comprehensive'] = [];
            }
            else if ($request->tpOdOnly == "10") {
    
                $returnArray['third_party'] = [];
            }
        }
        
        if($CorporateVehiclesQuotesRequest['is_renewal'] == 'Y' && config("DUMMY_TILE") == 'Y')
        {
            if($request->productSubTypeId == 1) {
                $RenewalAllowedIc = config('CAR_RENEWAL_DUMMAY_CARD_ALLOWED_IC');
            } else {
                $RenewalAllowedIc = config('BIKE_RENEWAL_DUMMAY_CARD_ALLOWED_IC');
            }
    
            $RenewalAllowedIcList = explode(',', $RenewalAllowedIc);
           
            if(in_array($CorporateVehiclesQuotesRequest['previous_insurer_code'], $RenewalAllowedIcList)) 
            {
                $compnay_id = MasterCompany::where('company_alias',$CorporateVehiclesQuotesRequest['previous_insurer_code'])->get()->first();
    
                $is_product_active = MasterPolicy::where('insurance_company_id',$compnay_id->company_id)
                                ->where('status','Active')
                                ->get()->count();
               
                if($is_product_active > 0)
                {
                    foreach($returnArray as $key => $val)
                    {
                        foreach($val as $v_key => $v_val)
                        {
                            if ($v_key === array_key_last($val)) {
    
                                if (strtolower($CorporateVehiclesQuotesRequest['previous_policy_type']) == strtolower($key)) {

                                    $returnArray[$key][] =
                                    [
                                        // "policyId" => 0,
                                        "companyId" => $compnay_id,
                                        "companyAlias" => $CorporateVehiclesQuotesRequest['previous_insurer_code'],
                                        // "logo" => "tata_aig.png",
                                        "productSubTypeId" => $request->productSubTypeId,
                                        "premiumTypeCode" => $key,
                                        "redirection_url" => $compnay_id['url']
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        if($parent_code == 'car' && config('ENBALE_MULTI_RESPONSE_IN_QUOTE') == 'Y' && config('ENBALE_MULTI_RESPONSE_SEPARATE_API_CALLING') == 'Y')
        {
                if(isset($returnArray['comprehensive'][0]))
                {
                    $master_policy_id = '';
                    $premiumTypeCode = '';
                    $enquiryId = customDecrypt($request->enquiryId);
                    $comprehensive_data = collect($returnArray['comprehensive']);
                    $comprehensive_data_icici = $comprehensive_data->filter(function ($value,  $key)  use(&$master_policy_id, &$premiumTypeCode) {
                        $master_policy_id = $value['policyId'];
                        $premiumTypeCode = $value['premiumTypeCode'];
                        return $value['companyAlias'] == 'icici_lombard';
                    });
                    if(!empty($comprehensive_data_icici))
                    {

                        $comprehensive_data =  $comprehensive_data->reject(function ( $value,  $key) {
                            return $value['companyAlias'] == 'icici_lombard';
                        });
                        $productData = getProductDataByIc($master_policy_id);
                        $corelationId = getUUID($enquiryId);
                        $requestData = getQuotation($enquiryId);
                        $first_reg_date = date('Y-m-d', strtotime($requestData->vehicle_register_date));
                        $mmv = get_mmv_details($productData, $requestData->version_id, 'icici_lombard');
                        $mmv = $mmv['data'] ?? [];
                        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
                        $master_rto = MasterRto::where('rto_code', $requestData->rto_code)->first();
                        $state_name = \App\Models\MasterState::where('state_id', $master_rto->state_id)->first();
                        $state_name = strtoupper($state_name->state_name);
                        $rto_data = DB::table('car_icici_lombard_rto_location')
                            ->where('txt_rto_location_code', $master_rto->icici_4w_location_code)
                            ->first();

                        switch ($premiumTypeCode) {
                            case "comprehensive":
                                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR');
                                break;
                            case "own_damage":
                                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_OD');
        
                                break;
                            case "third_party":
                                $deal_id = config('constants.IcConstants.icici_lombard.CALCULATE_PREMIUM_DEAL_ID_ICICI_LOMBARD_MOTOR_TP');
                                break;
        
                        }
                        
                        if ($requestData->business_type == 'newbusiness') {
                            $PolicyStartDate = date('Y-m-d');
                        } else {
                            if ($requestData->previous_policy_type == 'Not sure') {
                                $requestData->previous_policy_expiry_date = date('d-m-Y', strtotime('-100 day', strtotime(date('d-m-Y'))));
            
                            }
                            $PolicyStartDate = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
                            $breakin_days = get_date_diff('day', $requestData->previous_policy_expiry_date);
                            if (($requestData->business_type == 'breakin')) {
                                $PolicyStartDate = date('d-M-Y', strtotime('+3 day'));
                            }
                        }
                        include_once app_path() . '/Quotes/Car/icici_lombard.php';

                        if(function_exists('getAddonsService')) 
                        {
                            global  $bundleCoversAddonsArray ;
                            global $bundleCoversAddonsArrayAll ;
                            global  $standAloneCoversAddonsArray ;
                            global  $dependentCoversAddonsArray ;
                            global  $allAddonsFromService ;
                            global $allPackagesWithAddons;
                                    $bundleCoversAddonsArray = [];
                                    $bundleCoversAddonsArrayAll = [];
                                    $standAloneCoversAddonsArray = [];
                                    $dependentCoversAddonsArray = [];
                                    $allAddonsFromService = [];
                                    $allPackagesWithAddons['standard'] = []; 

                            $addons_data_icici = getAddonsService(
                                $enquiryId,
                                $productData,
                                $premiumTypeCode,
                                $corelationId,
                                $first_reg_date,
                                $mmv_data,
                                $rto_data,
                                $deal_id,
                                $PolicyStartDate
        
                            );
                            foreach ($comprehensive_data_icici as $c_key => $c_value) 
                            {
                                $comprehensive_data_icici = $c_value;
                            }
  
                            $icici_packages = [];

                            if(!empty($addons_data_icici))
                            {
                                foreach ($addons_data_icici as $icici_key => $icici_value) 
                                {
                                    $comprehensive_data_icici['plan_name'] = $icici_key;
                                    $comprehensive_data_icici['addons'] = $icici_value;
                                    $returnArray['comprehensive'][] = $comprehensive_data_icici;
                                }
                            }
                        }


                    }


                }
            }
        $returnArray = AgentDiscountController::getProductDetails($returnArray, $request->enquiryId);
        $returnArray = PospUtilityController::filterIcParam($returnArray, $request->enquiryId); //$request->enquiry_id

        $returnArray = PospUtilityController::filterRtoPospUtility($returnArray, $request->enquiryId);

        // Check if the short term products are enabled or not
        \App\Http\Controllers\Admin\MconfiguratorController::checkShortTermProducts($returnArray);
        $returnArray = PospUtilityController::filterQuoteByMmv($request,$returnArray);

        if (
            config('ENABLE_BROKERAGE_COMMISSION', 'N') == 'Y' &&
            (!$request->isQuoteShared || config('DISABLE_COMMISSION_ON_QUOTE_SHARE', 'N') != 'Y')
        ) {
            BrokerCommissionController::attachRules($returnArray, $request->enquiryId, false, [
                'transactionType' => 'QUOTE'
            ]);
        }

        return response()->json([
            "status" => true,
            "data" => $returnArray,
            "premiumId" => json_encode($premiumTypeId)
        ]);
    }

    public function getVehicleCategory(Request $request)
    {
        $vehicleCategory = MasterProductSubType::where('status','Active');
        if (!empty($request->enquiry_id)) 
        {
            $EV_Category = config('POS_CATEGORY_IDENTIFIER');
            $agent_details = false;
            if($EV_Category !== NULL)
            {
                $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiry_id))
                    ->where('seller_type', 'P')
                    ->where('category', $EV_Category)
                    ->exists();              
            }            
            if($agent_details) 
            {
                $EV_SUB_PRODUCT_TYPE = explode(',', config('EV_SUB_PRODUCT_TYPE'));
                $vehicleCategory = $vehicleCategory->whereIn('product_sub_type_id', $EV_SUB_PRODUCT_TYPE);
            }
        }
        $vehicleCategory = $vehicleCategory->get()->toArray();
        if ($vehicleCategory) {
            $vehicleCategory = array_column($vehicleCategory, null, 'product_sub_type_id');
            $vehicleCats = [];
            foreach ($vehicleCategory as $key => $value) {
                if ($value['parent_id'] != 0) {
                    $vehicleCats[$key]['productSubTypeId']   = $value['product_sub_type_id'];
                    $vehicleCats[$key]['productSubTypeCode'] = $value['product_sub_type_code'];
                    $vehicleCats[$key]['productSubTypeDesc'] = $value['product_sub_type_name'];
                    $vehicleCats[$key]['productSubTypelogo'] = url(config('constants.motorConstant.vehicleCategory') . $value['logo']);
                    $vehicleCats[$key]['productCategoryId']  = $vehicleCategory[$value['parent_id']]['product_sub_type_id'];
                    $vehicleCats[$key]['productCategoryName'] = $vehicleCategory[$value['parent_id']]['product_sub_type_code'];
                }
            }
            return response()->json([
                'status' => true,
                'msg' => 'Found',
                'data' => array_values($vehicleCats)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No data found',
            ]);
        }    
    }

    public function getVehicleType(Request $request)
    {
        $vehicle_types = DB::table('commercial_vehicle_type')->where('status', 'Y')->get();

        if (!$vehicle_types->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => camelCase($vehicle_types)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No data found',
            ]);
        }
    }

    public function getVehicleSubType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'corpClientId' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $vehSubType = DB::table('master_home_view')
            ->join('master_product_sub_type', 'master_home_view.product_sub_type_id', '=', 'master_product_sub_type.product_sub_type_id')
            ->join('master_policy', 'master_policy.product_sub_type_id', '=', 'master_home_view.product_sub_type_id')
            ->where('master_policy.corp_client_id', $request->corpClientId)
            ->select('master_home_view.product_sub_type_id', 'master_home_view.image_url', 'master_home_view.redirect_url', 'master_product_sub_type.product_sub_type_name')
            ->distinct()
            ->get();

        if (!$vehSubType->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => camelCase($vehSubType[0])
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Vehicle sub type not found.'
            ]);
        }
    }

    public function getRto(Request $request)
    {

        if(!empty($request->enquiryId)){
            $trace_id = substr($request->enquiryId,-5);
            $agent_details = CvAgentMapping::join('agents','cv_agent_mappings.agent_id','agents.agent_id')
            ->select('agents.city','agents.state','agents.pincode')->where('cv_agent_mappings.user_product_journey_id',$trace_id)->first();

            $agent_preferred_city = DB::table('rto_preferred_city')->where('city_name',$agent_details->city)->first();
            $agent_preferred_pincode = DB::table('new_india_pincode_master')
                                        ->join('master_rto','new_india_pincode_master.geo_area_name','master_rto.rto_name')
                                        ->select('geo_area_name')->where('pin_code',$agent_details->pincode)->first();

            if(!empty($agent_details) && !empty($agent_preferred_city)){
                $rto_preferred_city = DB::table('rto_preferred_city')
                ->orderByRaw("CASE WHEN rto_preferred_city.city_name = '$agent_preferred_city->city_name' THEN 1 ELSE 2 END")->get();
            

                $rto_data = DB::table('master_rto AS a')
                ->select('a.rto_id', 'a.rto_name', 'a.rto_number', 'a.state_id', 'c.state_name', 'a.zone_id', 'b.zone_name', 'c.state_name','a.rto_name as city',)
                ->join('master_zone AS b', 'b.zone_id', '=', 'a.zone_id')
                ->join('master_state AS c', 'c.state_id', '=', 'a.state_id')
                ->orderBy('rto_number')->get();

                $preferred_city = [];
                foreach ($rto_preferred_city as $key => $value) {
                    $preferred_city[] = $value->city_name;
                    $city_wise_rto_data = DB::table('master_rto')->select('rto_id', 'rto_name', 'rto_number')
                        ->where('rto_name', 'LIKE', "%{$value->city_name}%");
                        if(!empty($agent_preferred_pincode)){
                            $city_wise_rto_data = $city_wise_rto_data->orderByRaw("CASE WHEN rto_name = '$agent_preferred_pincode->geo_area_name' THEN 1 ELSE 2 END");
                        }
                        else{
                            $city_wise_rto_data = $city_wise_rto_data->orderBy('a.rto_number');
                        }
                        $city_wise_rto_data = $city_wise_rto_data->get();

                    $preferred_city_data[$value->city_name] = $city_wise_rto_data;
                }
                $data['all_rto_data'] = $rto_data;
                $data['city'] = $preferred_city;
                $data['city_rto'] = $preferred_city_data;
                unset($agent_details, $agent_preferred_city, $agent_preferred_pincode, $rto_preferred_city, $value, $city_wise_rto_data);
                if (count($rto_data) > 0) {
                    return response()->json(['status' => true, 'data' => camelCase($data)]);
                } else {
                    return response()->json(['status' => false, 'msg' => 'No data found']);
                }
            }
        }
        else{
            $rto_preferred_city = DB::table('rto_preferred_city AS a')->orderBy('priority')->get();
        }

        $search_string = trim($request->searchString ?? '');
        if ($search_string != '') {
            $rto_data = DB::table('master_rto')
                ->where('status', 'Active')
                ->where('rto_number', 'like', '%' . $search_string . '%')
                ->select(DB::raw('0 AS rto_group_id'), DB::raw('"" AS rto_group_name'), 'rto_id', 'rto_number', 'rto_name', 'state_id', 'zone_id')
                ->orderBy('rto_number')
                ->get();
        } else {
            $state_id = $request->stateId ?? 0;
            $state_code = $request->stateCode ?? '';
            $rto_id = $request->rtoId ?? 0;
            $rto_code = $request->rtoCode ?? '';

            $rto_data = DB::table('master_rto AS a')
                ->select('a.rto_id', 'a.rto_name', 'a.rto_number', 'a.state_id', 'c.state_name', 'a.zone_id', 'b.zone_name', 'c.state_name','a.rto_name as city',)
                ->join('master_zone AS b', 'b.zone_id', '=', 'a.zone_id')
                ->join('master_state AS c', 'c.state_id', '=', 'a.state_id')
                ->orderBy('rto_number');

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
        }

            $preferred_city = [];
            if (config('constants.motorConstant.MANUFACTURER_TOP_FIVE') == 'Y') {
                
                $rtoCities = DB::table('rto_city_names')
                    ->join('rto_counts', 'rto_city_names.rto_id', '=', 'rto_counts.id')
                    ->select('rto_city_names.rto_city_name', DB::raw('SUM(rto_counts.policy_count) as total_policy_count'))
                    ->groupBy('rto_city_names.rto_city_name')
                    ->orderByDesc('total_policy_count')
                    ->pluck('rto_city_name')
                    ->toArray();
                    
                    $rto_preferred_city = $rtoCities;
            }
            foreach ($rto_preferred_city as $key => $value) {
                $city_name = isset($value->city_name) ? $value->city_name : $value;
                $preferred_city[] = $city_name;
                $city_wise_rto_data = DB::table('master_rto AS a')
                    ->select('a.rto_id', 'a.rto_name', 'a.rto_number')
                    ->where('rto_name', 'LIKE', "%{$city_name}%")
                    ->orderBy('rto_number')
                    ->get();
                $preferred_city_data[strtolower(str_replace(' ', '', $city_name))] = $city_wise_rto_data;
            }
            $data['all_rto_data'] = $rto_data;
            $data['city'] = $preferred_city;
            $data['city_rto'] = $preferred_city_data;
            
            if (config('constants.motorConstant.MANUFACTURER_TOP_FIVE') == 'Y') {
                $data['city'] = $rtoCities;
            }
          
        if (!$rto_data->isEmpty()) {
            return response()->json([ 'status' => true, 'data' => camelCase($data)]);
        } else {
            return response()->json([ 'status' => false, 'msg' => 'No data found' ]);
        }
    }

    public function getManufacturer(Request $request)
    {
        if(config("MMV_MASTER_NEW_FLOW_ENABLED") == 'Y'){
            return MmvMasterController::getManufacturer($request);
        }
        
        $validator = Validator::make($request->all(), [
            'productSubTypeId' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
//      $product_sub_type_id = [1, 2, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16,17,18];

//        if($request->productSubTypeId == 18)
//        {
//            return response()->json([
//                'status' => true,
//                'data' => camelCase([
//                    [
//                        "cvType" => "MISC",
//                        "img" => "http://motor_2.0_backend.test?pN3=hCN3zA34t%2FA4ty3tLbbfoUvuT4TVIdhLJtxMYrCFX3LPz12hs0yrBqxEMlph19e18vj%2BgMClVIpPC6rsQ7O9Jg%3D%3D",
//                        "manfId" => "366",
//                        "manfName" => "EICHER TRACTOR",
//                        "priority" => "1"
//                    ]
//                ])
//            ]);
//        }

        if (in_array($request->productSubTypeId, $product_sub_type_id)) {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }
            $product = strtolower(get_parent_code($request->productSubTypeId));
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name  = $path . $product . '_manufacturer.json';
            //$mmv_data = json_decode(file_get_contents($file_name), true);
            $mmv_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
            $data = [];
            if ($mmv_data) {
                $mmv_1 = [];
                $mmv_2 = [];
                foreach ($mmv_data as $mmv) {
                    unset($mmv['is_discontinued']);
                    unset($mmv['is_active']);
                    $manf_img = str_replace(" ", "_", strtolower(trim($mmv['manf_name'])));
                    if ($manf_img == "maruti_suzuki") {
                        if (in_array($product, ['pcv', 'gcv'])) {
                            $manf_img = "maruti_suzuki";
                        } else {
                            $manf_img = "maruti";
                        }
                    }
                    //$mmv['img'] = /* Storage::url */file_url((config('constants.motorConstant.vehicleModels')) . '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png');
                    // $mmv['img'] = Storage::url(config('constants.motorConstant.vehicleModels')) . '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png';
                    if(config('DEFAULT_MODEL_LOGO_ENABLE') == 'Y')
                    {                        
                        $mmv['img'] = url('storage/'.config('constants.motorConstant.vehicleModels'). '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png');
                    }
                    else
                    {
                        $mmv['img'] = /* Storage::url */file_url((config('constants.motorConstant.vehicleModels')) . '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png');
                    }
                    
                    if ($mmv['priority'] != '' && $mmv['priority'] != 0) {
                        $mmv_1[] = $mmv;
                    } else {
                        $mmv_2[] = $mmv;
                    }
                }

              
                array_multisort(array_column($mmv_1, 'priority'), SORT_ASC, $mmv_1);
                array_multisort(array_column($mmv_2, 'manf_name'), SORT_ASC, $mmv_2);
                $data = array_merge($mmv_1, $mmv_2);
                $manufacturer_arr = [];

                //MMV Blocker
                if (config('MMV_BLOCKER') == 'Y') {

                    $blockedManufacturer = MmvBlocker::all();
                    $sellerTypes = [];
                    $allBlockedMakes = [];

                    foreach ($blockedManufacturer as $blocked) {
                        $product = strtolower(get_parent_code($request->productSubTypeId));
                        //Seller types
                        $sellerTypes = [
                            'B2C' => [
                                "USER_WITH_REGISTRATION" => "U",
                                "USER_WITHOUT_REGISTRATION" => "B2C",
                                "DEALER" => 'Dealer',
                            ],
                            'B2B' => [
                                "EMPLOYEE" => "E",
                                "PARTNER"   => "Partner",
                                "POSP" => "P",
                                "MISP" => "MISP"
                            ]
                        ];
                        //Map variables 
                        $segment = $blocked['segment'];
                        $productSubTypeIdBlock = $blocked['product_sub_type_id'];
                        $blockedMake = $blocked['manufacturer'] ?? [];
                        $blockedSellerType = $blocked['seller_type']; // B2B or B2C
                    
                        // Map B2B or B2C to detailed seller types
                        $detailedSellerTypes = $sellerTypes[$blockedSellerType] ?? [];
                        $blockedJourneytype = array_values($detailedSellerTypes); 
                    
                        $cvAgentMappings = \App\Models\CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->whereIn('seller_type', $blockedJourneytype)
                            ->pluck('seller_type'); 
                        
                        $b2bSellerTypes = array_values($sellerTypes['B2B']);

                        // Only consider it B2B if there is data AND any value matches a known B2B type
                        $isB2B = $cvAgentMappings->isNotEmpty() && $cvAgentMappings->contains(function ($type) use ($b2bSellerTypes) {
                            return in_array($type, $b2bSellerTypes, true);
                        });

                        //In hero, we might get U or B2C seller type while doing journey through their portal
                        $journeyType = $isB2B ? 'B2B' : 'B2C';
                    
                        // Collect blocked manufacturer with seller type and segment
                        if ($journeyType == $blocked['seller_type'] && $productSubTypeIdBlock == $request->productSubTypeId) {
                            $allBlockedMakes = array_merge($allBlockedMakes, (array) $blockedMake);
                        }
                    }
                    
                    // filter blocked manufacturers
                    if (!empty($allBlockedMakes)) {
                        $data = array_filter($data, function ($item) use ($allBlockedMakes) {
                            return in_array(strtoupper($item['manf_name'] ?? ''), array_map('strtoupper', $allBlockedMakes));
                        });
                        $data = array_values($data); 
                    }
                }
                if(config('constants.motorConstant.MANUFACTURER_TOP_FIVE') == 'Y') {
                    
                    $prefix = [
                        'car' => 'CRP',
                        'bike' => 'BYK',
                        'pcv' => 'PCV',
                        'gcv' => 'GCV',
                    ];
                
                    $parent_code = strtolower(get_parent_code(1));
                    $prefix = $prefix[$parent_code] ?? 'MISD';
                    
                    $manList = VersionCount::select('make', DB::raw('SUM(version_counts.policy_count) as total_policy_count'))
                    ->where('version', 'like', "{$prefix}%")
                    ->where('status', 'Y')
                    ->groupBy('make')
                    ->orderByDesc('total_policy_count')
                    ->get()
                    ->pluck('make')
                    ->toArray();
                    
                    $manListIndexed = array_flip($manList);
                    
                    foreach ($data as $key => $value) {
                        $data[$key]['priority'] = 0;
                        if (isset($manListIndexed[$value['manf_name']])) {
                            $data[$key]['priority'] = $manListIndexed[$value['manf_name']] + 1;
                        }
                    }
                
                    $matched = [];
                    $unmatched = [];
                    $priority = count($manList) + 1;
                    foreach ($data as $item) {
                        if ($item['priority'] > 0) {
                            $matched[] = $item;
                        } else {
                            $item['priority'] = $priority++;
                            $unmatched[] = $item;
                        }
                    }

                    $data = array_merge($matched, $unmatched);
                    usort($data, function ($a, $b) {
                        return $a['priority'] <=> $b['priority'];
                    });
                }
                if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
                    $data = array_filter($data, function($item) {
                        return strtolower($item['manf_name']) != 'morris garages';
                    });
                    $data = array_values($data);
                }
                else if(isset($request->enquiryId) && config('constants.motorConstant.SMS_FOLDER') == 'tmibasl')
                {
                    $lead_source = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))->pluck('lead_source')->first();
                    if($lead_source == 'TML')
                    {
                        $data = array_filter($data, function($item) {
                            return in_array(strtoupper($item['manf_name']),['TATA','TATA MOTORS']);
                        });
                        $data = array_values($data);
                        if(isset($data[0]['priority']))
                        {
                            $data[0]['priority'] = "1";
                        }
                    }
                }
                if ($product == 'gcv') {
                    $gcv_data = [];
                    foreach ($data as $key => $value) {
                        if ($value['cv_type'] == 'PICK UP/DELIVERY/REFRIGERATED VAN' && $request->productSubTypeId == 9) {
                            $gcv_data[] =  $value;
                        } else if ($value['cv_type'] == 'DUMPER/TIPPER' && $request->productSubTypeId == 13) {
                            $gcv_data[] =  $value;
                        } else if ($value['cv_type'] == 'TRUCK' && $request->productSubTypeId == 14) {
                            $gcv_data[] =  $value;
                        } else if ($value['cv_type'] == 'TRACTOR' && $request->productSubTypeId == 15) {
                            $gcv_data[] =  $value;
                        } else if ($value['cv_type'] == 'TANKER/BULKER' && $request->productSubTypeId == 16) {
                            $gcv_data[] =  $value;
                        }
                    }
                    unset($data);
                    $data = $gcv_data;
                } elseif ($product == 'pcv') {
                    $pcv_data = [];
                    foreach ($data as $key => $value) {
                        if ($value['cv_type'] == 'AUTO RICKSHAW' && $request->productSubTypeId == 5) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'TAXI' && $request->productSubTypeId == 6) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'PASSENGER-BUS' && $request->productSubTypeId == 7) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'SCHOOL-BUS' && $request->productSubTypeId == 10) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'E-RICKSHAW' && $request->productSubTypeId == 11) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'TEMPO-TRAVELLER' && $request->productSubTypeId == 12) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'BUS' && in_array($request->productSubTypeId,[7,10])) {
                            $pcv_data[] =  $value;
                        }
                    }

                    unset($data);
                    $data = $pcv_data;
                }
            }

            if(isset($request->userProductJourneyId)){
                $enquiryId = customDecrypt($request->userProductJourneyId);
                $agent_details = \App\Models\CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();
                if(isset($agent_details->seller_type) && $agent_details->seller_type = 'Dealer'){
                    $dealer = \Illuminate\Support\Facades\DB::table('original_equipment_manufacturer_dealers')->where('id', $agent_details->agent_id)->first();
                    $oem = \Illuminate\Support\Facades\DB::table('original_equipment_manufacturers')->where('id', $dealer->oem_id)->first();
                    $manf_data = collect($data)->where('manf_id', $oem->for)->first();
                    $data = [json_decode(json_encode($manf_data), true)];
                }
            }

            try {

                if (!empty($request->enquiryId)) {

                    $EV_Category = config('POS_CATEGORY_IDENTIFIER');
                    $agent_details = false;
                    if($EV_Category !== NULL)
                    {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'P')
                            ->where('category', $EV_Category)
                            ->exists();                        
                    }
                    else if(config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR')
                    {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'misp')
                            ->whereNotNull('other_details')
                            ->first();
                    }
                    if ($agent_details) {

                        $manfucturer_ids = self::getManufacturerByFuelType($request);

                        //return $manfucturer_ids;
                        if(config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR')
                        {
                            $manfucturer_ids = [];
                        }
                        else if ($manfucturer_ids instanceof \Illuminate\Http\JsonResponse) {
                            $manfucturer_ids = json_decode($manfucturer_ids->getContent(), true)['data'] ?? [];
                        } else {
                            $manfucturer_ids = [];
                        }
                        if(config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR')
                        {
                            $data = array_values(collect($data)->toArray());
                        }
                        else
                        {
                            $data = array_values(collect($data)->whereIn("manf_id", $manfucturer_ids)->toArray());
                        }

                        // Temporary Condition added for KMD's Bharat Benz Demo, please check with Dipraj before removing the if condition
                        if( config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR' && config('BHARAT_BENZ_DEMO') == 'Y')
                        {
                            return response()->json([
                                'status' => true,
                                'msg'    => $env_folder,
                                'data'   => [ [ "manfId" => "14", "manfName" => "BHARAT BENZ", "priority" => "5", "cvType" => "TRUCK", "img" => "https://motor-uat.s3.ap-south-1.amazonaws.com/uploads/vehicleModels/gcv/bharat_benz.png#" ] ]
                            ]); exit;
                        }

                        $misp_id = json_decode($agent_details->other_details,true);
                        if(!empty($misp_id['oem_name']))
                        {
                            $oem_name = str_replace('_',' ',strtoupper(trim($misp_id['oem_name'])));
                            $data = array_values(collect($data)->whereIn("manf_name", $oem_name)->toArray());
                        }

                        return response()->json([
                            'status' => true,
                            'msg'    => $env_folder,
                            'data' => camelCase($data)
                        ]);
                    }
                }

            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }
            $isB2B = \App\Models\CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))->whereIn('seller_type',['P','E'])->exists();
            $sellerType = $isB2B ? 'B2B' : 'B2C';
            $data = self::getPriortyManufacturer($sellerType, $request->productSubTypeId, $data);

            // Temporary Condition added for KMD's Bharat Benz Demo, please check with Dipraj before removing the if condition
            if( config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR' && config('BHARAT_BENZ_DEMO') == 'Y')
            {
                return response()->json([
                    'status' => true,
                    'msg'    => $env_folder,
                    'data'   => [ [ "manfId" => "14", "manfName" => "BHARAT BENZ", "priority" => "5", "cvType" => "TRUCK", "img" => "https://motor-uat.s3.ap-south-1.amazonaws.com/uploads/vehicleModels/gcv/bharat_benz.png#" ] ]
                ]); exit;
            }
            // elseif (config('constants.motorConstant.SMS_FOLDER') === 'ola' && $request->productSubTypeId == 2){
                
            //     $listOfManu = !empty(config('BIKE_ALLOWED_MAKE')) ? explode(',', config('BIKE_ALLOWED_MAKE')) : [];
                
            //     if (!empty($listOfManu) && $request->productSubTypeId == 2) {
            //         $data = array_filter($data, function ($item) use ($listOfManu) {
            //             return in_array(strtoupper($item['manf_name'] ?? ''), $listOfManu);
            //         });
            //         $data = array_values($data);
            //     }
            //     return response()->json([
            //         'status' => true,
            //         'msg'    => $env_folder,
            //         'data' => camelCase(array_values($data)),
            //         'productSubTypeId' => $request->productSubTypeId
            //     ]); exit;
            // }
            return response()->json([
                'status' => true,
                'msg'    => $env_folder,
                'data' => camelCase($data),
                'productSubTypeId' => $request->productSubTypeId
            ]);
        }


        $searchString = $request->searchString;
        if (!empty($searchString)) {
            $priority = 0;
        } else {
            $priority = 1;
        }

        $showActive = isset($request->showActive) ? $priority : 0;

        if ($priority == 1 && $showActive == 0) {
            $manufacturerData = DB::table('motor_manufacturer')
                ->where('product_sub_type_id', $request->productSubTypeId)
                ->where('status', 'Active')
                ->select('manf_id', 'manf_name', 'img')
                ->get();
        } elseif (!empty($searchString)) {
            $manufacturerData = DB::table('motor_manufacturer')
                ->where('product_sub_type_id', $request->productSubTypeId)
                ->where('status', 'Active')
                ->orWhere('manf_name', 'LIKE', "%{$searchString}%")
                ->select('manf_id', 'manf_name', 'img')
                ->get();
        } elseif ($priority == 1 && $showActive == 1) {
            $manufacturerData = DB::table('motor_manufacturer')
                ->where('product_sub_type_id', $request->productSubTypeId)
                ->where('status', 'Active')
                ->select('manf_id', 'manf_name', 'img')
                ->get();
        }

        if (!$manufacturerData->isEmpty()) {
            foreach ($manufacturerData as $key => $value) {
                $manufacturerData[$key]->img =  url(config('constants.motorConstant.vehicleModels') . $value->img);
            }

            // Temporary Condition added for KMD's Bharat Benz Demo, please check with Dipraj before removing the if condition
            if( config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR' && config('BHARAT_BENZ_DEMO') == 'Y')
            {
                return response()->json([
                    'status' => true,
                    'msg'    => $env_folder,
                    'data'   => [ [ "manfId" => "14", "manfName" => "BHARAT BENZ", "priority" => "5", "cvType" => "TRUCK", "img" => "https://motor-uat.s3.ap-south-1.amazonaws.com/uploads/vehicleModels/gcv/bharat_benz.png#" ] ]
                ]); exit;
            }
            // else if(config('constants.motorConstant.SMS_FOLDER') == 'ola' && $request->productSubTypeId == 2){
            //     $listOfManu = !empty(config('BIKE_ALLOWED_MAKE')) ? explode(',', config('BIKE_ALLOWED_MAKE')) : [];
                
            //     if (!empty($listOfManu) && $request->productSubTypeId == 2) {
            //         $manufacturerData = array_filter($manufacturerData, function ($item) use ($listOfManu) {
            //             return in_array(strtoupper($item['manf_name'] ?? ''), $listOfManu);
            //         });
            //         $data = array_values($data);
            //     }
            //     return response()->json([
            //         'status' => true,
            //         'msg'    => $env_folder,
            //         'data' => camelCase(array_values($manufacturerData)),
            //         'productSubTypeId' => $request->productSubTypeId
            //     ]); exit;
            // }
            
            return response()->json([
                'status' => true,
                'data' => camelCase($manufacturerData)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Some Error Occurred during Found'
            ]);
        }
    }
    public static function getPriortyManufacturer($sellerType, $product_subType, $data)
    {
        // $parent_product_sub_type_id = DB::table('master_product_sub_type')->where('product_sub_type_id', $product_subType)->pluck('parent_product_sub_type_id')->first();
        $priority = ManufacturerPriority::where('vehicleType', $product_subType)
            ->where('sellerType', $sellerType)
            ->select('priority', 'insurer')
            ->orderBy('priority', 'asc')
            ->get();
        $icNames = [];
        $icNameWithPriority = [];
        foreach ($priority as $key => $value) {
            $icNames[] = $value->insurer;
            $icNameWithPriority[$value->insurer] = $value->priority;
        }
        foreach ($data as $key => $value) {
            $data[$key]['priority'] = 0;
            if (in_array($value['manf_name'], $icNames)) {
                $data[$key]['priority'] = $icNameWithPriority[$value['manf_name']];
            }
        }
        usort($data, function ($a, $b) {
            if ($a['priority'] == 0 && $b['priority'] == 0) {
                return 0;
            }
            if ($a['priority'] == 0) {
                return 1;
            }
            if ($b['priority'] == 0) {
                return -1;
            }
            return $a['priority'] <=> $b['priority'];
        });
        return $data;
    }
    public function setMmvPriority(Request $request)
    {
        if (config("constants.motorConstant.MANUFACTURER_TOP_FIVE") == 'Y') {
            try {
                
                Artisan::call('update:versioncount', ['type' => 'fetch']);
                $fetchOutput = Artisan::output();
    
                return response()->json([
                    'success' => true,
                    'message' => 'Commands executed successfully.',
                    'fetchOutput' => $fetchOutput,
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error executing commands: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'MMV priority update is disabled in the configuration.',
            ], 403);
        }
    }
    public function updateVersionCount(Request $request)
    {
        if (config("constants.motorConstant.MANUFACTURER_TOP_FIVE") == 'Y') {
            try {
                
                Artisan::call('update:versioncount', ['type' => 'update']);
                $updateOutput = Artisan::output();
    
                return response()->json([
                    'success' => true,
                    'message' => 'Commands executed successfully.',
                    'updateOutput' => $updateOutput,
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error executing commands: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'MMV priority update is disabled in the configuration.',
            ], 403);
        }
    }
    public function setRtoPriority(Request $request)
    {
        if (config("constants.motorConstant.MANUFACTURER_TOP_FIVE") == 'Y') {
            try {
                
                Artisan::call('update:rtocount');
                $updateOutput = Artisan::output();
    
                return response()->json([
                    'success' => true,
                    'message' => 'Commands executed successfully.',
                    'updateOutput' => $updateOutput,
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error executing commands: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'RTO priority update is disabled in the configuration.',
            ], 403);
        }
    }
    public function getModelVersion(Request $request)
    {
        if(config("MMV_MASTER_NEW_FLOW_ENABLED") == 'Y'){
            return MmvMasterController::getModelVersion($request);
        }
        $validator = Validator::make($request->all(), [
            'modelId' => 'required',
            'fuelType' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        //$product_sub_type_id = [1, 2, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16];
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
//        if($request->productSubTypeId == 18)
//        {
//            return response()->json([
//                'status' => true,
//                'data' => camelCase([
//                    [
//                        "carryingCapicity" => "1",
//                        "cubicCapacity" => "",
//                        "fuelFype" => "PETROL",
//                        "grosssVehicleWeight" => "",
//                        "kw" => "null",
//                        "modelId" => "6338",
//                        "seatingCapacity" => "1",
//                        "vehicleBuiltUp" => "",
//                        "versionId" => "MISC4C3010211543",
//                        "versionName" => "EICHER 241"
//                    ]
//                ])
//            ]);
//        }
        if (in_array($request->productSubTypeId, $product_sub_type_id)) {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }
            $is_3_wheeler_blocked = config('IS_3_WHEELER_BLOCKED') == 'Y';
            $is_PCV_greater_7_seater_blocked = config('IS_PCV_GREATER_THAN_7_SEATER_BLOCKED') == 'Y';
            $product = strtolower(get_parent_code($request->productSubTypeId));
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name  = $path . $product . '_model_version.json';
            $model_file = $path . $product . '_model.json';
            $mmv_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($model_file), true);
           
            $model_name = null;
            foreach ($mmv_data as $model) {
                if ($model['model_id'] == $request->modelId) {
                    $model_name = $model['model_name'];
                    break;
                }
            }

            if (!$model_name) {
                return response()->json([
                    "status" => false,
                    "message" => "Model not found for the given ID.",
                ]);
            }
            $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
            $mmv_1 = $mmv_2 = [];
            if ($data) {
                foreach ($data as $value) {
                    if ($value['model_id'] == $request->modelId) 
                    {
                    $value1['carrying_capicity'] = $value['carrying_capacity'] ?? $value['seating_capacity'];
                    $value1['cubic_capacity'] = $value['cubic_capacity'] ?? '';
                    $value1['fuel_fype'] = $value['fuel_type'];
                    $value1['grosss_vehicle_weight'] = $value['gvw'] ?? '';
                    $value1['kw'] = $value['kw'] ?? '';
                    $value1['model_id'] = $value['model_id'];
                    $value1['seating_capacity'] = $value['seating_capacity'];
                    $value1['version_id'] = $value['version_id'];
                    //$value1['version_id'] = $value['sequence_id'];
                    $value1['version_priority'] = $value['version_priority'];
                    $value1['version_name'] = $value['version_name'];
                    $value1['vehicle_built_up'] = $value['vehicle_built_up'] ?? '';
                    $value1['no_of_wheels'] = $value['no_of_wheels'] ?? '';
                    if (!empty($value1['fuel_fype'])) {
                        $value1['fuel_fype'] = strtoupper($value1['fuel_fype']);
                    };
                        
                        unset($value['is_discontinued']);
                        unset($value['is_active']);
                       

                        if ($value['version_priority'] != '' && $value['version_priority'] != 0) {
                            
                            $mmv_1[] = $value1;
                        } else {
                           
                            $mmv_2[] = $value1;
                        }
                        
                        
                        if($request->fuelType == 'NULL')
                        {
                            if(!in_array($request->productSubTypeId,[1,2]) && isset($value['seating_capacity']) && $value['seating_capacity'] > 7 && $is_PCV_greater_7_seater_blocked)
                            {
//                                //as per git id https://github.com/Fyntune/motor_2.0_backend/issues/10832
                              //not included seating capecity greater than 7
                            }else if(isset($value['no_of_wheels']) && $value['no_of_wheels'] == 3 && $is_3_wheeler_blocked) 
                            {
                                // if 3 wheeler variants are blocked then don't show variants
                            }
                            else
                            {
                                $result[] = $value1;
                            }
                           
                        }
                        else
                        {
                            if ($product == 'bike') 
                            {
                                if ($value['fuel_type'] == $request->fuelType) 
                                {
                                    $result[] = $value1;
                                }
                            } 
                            else 
                            {                                
                                if(!in_array($request->productSubTypeId,[1,2]) && isset($value['seating_capacity']) && $value['seating_capacity'] > 7 && $is_PCV_greater_7_seater_blocked)
                                {
//                                //as per git id https://github.com/Fyntune/motor_2.0_backend/issues/10832
                                  //not included seating capecity greater than 7
                                }
                                else if(isset($value['no_of_wheels']) && $value['no_of_wheels'] == 3 && $is_3_wheeler_blocked) 
                                {
                                    // if 3 wheeler variants are blocked then don't show variants
                                } 
                                else 
                                {
                                    if (in_array($value['fuel_type'], ['CNG', 'LPG', 'PETROL+CNG', 'PETROL+LPG', 'DIESEL+CNG', 'DIESEL+LPG']) && in_array($request->fuelType, ['CNG', 'LPG'])) 
                                    {
                                        $result[] = $value1;
                                    } 
                                    else if (strtolower($request->fuelType) == strtolower($value['fuel_type'])) 
                                    {
                                        $result[] = $value1;
                                    }
                                }
                            }
                        }
                    }
                }
                array_multisort(array_column($mmv_1, 'version_priority'), SORT_ASC, $mmv_1);
                array_multisort(array_column($mmv_2, 'version_name'), SORT_ASC, $mmv_2);
                        // $result = array_merge($mmv_1, $mmv_2); // temporary fix for mmv #32586
                        
                        if(config('constants.motorConstant.MANUFACTURER_TOP_FIVE') == 'Y') {
                            $prefix = [
                                'car' => 'CRP',
                                'bike' => 'BYK',
                                'pcv' => 'PCV',
                                'gcv' => 'GCV',
                            ];
                    
                            $parent_code = strtolower(get_parent_code($request->productSubTypeId));
                            $prefix = $prefix[$parent_code] ?? 'MISD';
                            $version_count = VersionCount::select(DB::raw('DISTINCT version'),'variant', DB::raw('SUM(policy_count) as total_policy_count'))
                            ->where('model', $model_name)
                            ->where('version', 'like', "{$prefix}%")
                            ->where('status', 'Y')
                            ->groupBy('version', 'variant')
                            ->orderByDesc('total_policy_count')
                            ->take(config('constants.motorConstant.MANUFACTURER_AUTOLOAD_PRIORITY',5))
                            ->get()
                            ->toArray();
                            
                                $version_arr = [];
                                foreach ($version_count as $v_count) {
                                    $arr_version = json_decode(json_encode(get_fyntune_mmv_details($request->productSubTypeId, $v_count['version'], $gcv_carrier_type=NULL)), true);
                                    
                                    
                                    if ($arr_version['status'] == true && isset($arr_version['data']['version'])) {
                                        
                                        $version_arr[$arr_version['data']['version']['version_id']] = $arr_version['data']['version']['version_name'];
                                        
                                        
                                    }
                                    
                                }
                                
                                $version_priority = [];
                                $priority = 1;
                                
                                foreach ($version_arr as $key=> $version_name) {
                                    $version_priority[] = [
                                        'version_name' => $version_name,
                                        'version_priority' => $priority++,
                                        'version_id' => $key
                                    ];
                                    
                                    
                                }
                                
                                $priority_map = array_column($version_priority, 'version_priority', 'version_id');
                                
                                $matched = [];
                                $unmatched = [];
                                
                                $priority = count($priority_map) + 1;
                                foreach ($result as $item) {
                                    if (isset($priority_map[$item['version_id']])) {
                                        $item['version_priority'] = $priority_map[$item['version_id']];
                                        
                                        $matched[] = $item;
                                        
                                    } else {
                                        $item['version_priority'] = $priority++;
                                        $unmatched[] = $item;
                                    }
                                    
                                }
                                
                                $result = array_merge($matched, $unmatched);
                                
                                usort($result, function ($a, $b) {
                                    return $a['version_priority'] <=> $b['version_priority'];
                                });
                        }
            }

            try {

                if (!empty($request->enquiryId)) {

                    $EV_Category = config('POS_CATEGORY_IDENTIFIER');
                    $agent_details = false;
                    if($EV_Category !== NULL)
                    {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'P')
                            ->where('category', $EV_Category)
                            ->exists();                        
                    }
                    
                    if ($agent_details) {

                        $result = array_values(collect($result)->whereIn("fuel_fype", "ELECTRIC")->toArray());
                        return response()->json([
                            'status' => true,
                            'msg'    => $env_folder,
                            'data' => camelCase($result)
                        ]);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'msg'    => $env_folder,
                'data' => camelCase($result)
            ]);
        }
        $motor_model_version = MotorModelVersion::select('version_id', 'version_name', 'fuel_type', 'model_id', 'cubic_capacity', 'carrying_capicity', 'seating_capacity', 'grosss_vehicle_weight')
            ->where('model_id', $request->modelId)
            ->when($request->fuelType == 'CNG', function ($query) {
                if (isset(request()->LpgCngKitValue) && request()->LpgCngKitValue > 0) {
                    return $query->where('fuel_type', 'PETROL');
                } else {
                    return $query->where('fuel_type', 'CNG');
                }
            }, function ($query) {
                return $query->where('fuel_type', request()->fuelType);
            })
            ->get()
            ->toArray();

        list($status, $msg, $result)  = $motor_model_version
            ? [true, 'Model Version List Fetched Successfully...!', camelCase($motor_model_version)]
            : [false, 'No data found', null];

        return response()->json([
            'status' => $status,
            'msg' => $msg,
            'data' => $result
        ]);
    }

    public function getModel(Request $request)
    {
        if(config("MMV_MASTER_NEW_FLOW_ENABLED") == 'Y'){
            return MmvMasterController::getModel($request);
        }
        $validator = Validator::make($request->all(), [
            'manfId' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
//        if($request->productSubTypeId == 18)
//        {
//            return response()->json([
//                'status' => true,
//                'data' => camelCase([
//                    [
//                        "manfId" => "366",
//                        "modelId" => "6338",
//                        "modelName" => "EICHER 241",
//                        "modelPriority" => "1"
//                    ]
//                ])
//            ]);
//        }

        if (in_array($request->productSubTypeId, $product_sub_type_id))
        //if($request->productSubTypeId == 6)
        {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }

            $product = strtolower(get_parent_code($request->productSubTypeId));
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            
            $manufacturer_file = $path . $product . '_manufacturer.json';
            $model_file = $path . $product . '_model.json';
            $mmv_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($manufacturer_file), true);

            $manf_name = null;
            foreach ($mmv_data as $manufacturer) {
                if ($manufacturer['manf_id'] == $request->manfId) {
                    $manf_name = $manufacturer['manf_name'];
                    break;
                }
            }

            if (!$manf_name) {
                return response()->json([
                    "status" => false,
                    "message" => "Manufacturer not found for the given ID.",
                ]);
            }

            $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($model_file), true);

            $mmv_1 = $mmv_2 = [];
            foreach ($data as $value) {
                if ($value['manf_id'] == $request->manfId) {
                    unset($value['is_discontinued'], $value['is_active']);
                    if (!empty($value['model_priority'])) {
                        $mmv_1[] = $value;
                    } else {
                        $mmv_2[] = $value;
                    }
                }
            }
            array_multisort(array_column($mmv_1, 'model_priority'), SORT_ASC, $mmv_1);
            array_multisort(array_column($mmv_2, 'model_name'), SORT_ASC, $mmv_2);
            $result = array_merge($mmv_1, $mmv_2);
            if(config('constants.motorConstant.MANUFACTURER_TOP_FIVE') == 'Y') {
                if ($manf_name) {
                    $prefix = [
                        'car' => 'CRP',
                        'bike' => 'BYK',
                        'pcv' => 'PCV',
                        'gcv' => 'GCV',
                    ];
            
                    $parent_code = strtolower(get_parent_code($request->productSubTypeId));
                    $prefix = $prefix[$parent_code] ?? 'MISD';
                    $user_proposal = VersionCount::select(DB::raw('DISTINCT version'), 'model', DB::raw('SUM(policy_count) as total_policy_count'))
                    ->where('make', $manf_name)
                    ->where('version', 'like', "{$prefix}%")
                    ->where('status', 'Y')
                    ->groupBy('version', 'model')
                    ->orderByDesc('total_policy_count')
                    ->take(config('constants.motorConstant.MANUFACTURER_AUTOLOAD_PRIORITY',5))
                    ->get()
                    ->toArray();
                    
                    $model_arr = [];
                    foreach ($user_proposal as $value) {
                        $arr_mod = json_decode(json_encode(get_fyntune_mmv_details($request->productSubTypeId, $value['version'], $gcv_carrier_type = null)), true);

                        if ($arr_mod['status'] == true && isset($arr_mod['data']['model'])) {
                            $model_arr[$arr_mod['data']['model']['model_name']] = $arr_mod['data']['model']['model_name'];
                        }
                    }

                        $model_priority = [];
                        $priority = 1;
                        foreach ($model_arr as $model_name) {
                            $model_priority[] = [
                                'model_name' => $model_name,
                                'model_priority' => $priority++,
                            ];
                            
                        }
                    
                        $priority_map = array_column($model_priority, 'model_priority', 'model_name');
                        $matched = [];
                        $unmatched = [];
                        $priority = count($priority_map) + 1;
                        foreach ($result as $item) {
                            if (isset($priority_map[$item['model_name']])) {
                                $item['model_priority'] = $priority_map[$item['model_name']];
                                $matched[] = $item;
                            } else {
                                $item['model_priority'] = $priority++;
                                $unmatched[] = $item;
                            }
                            
                        }
                        
                        $result = array_merge($matched, $unmatched);
                        usort($result, function ($a, $b) {
                            return $a['model_priority'] <=> $b['model_priority'];
                        });
                }
            }   
                
            try {

                if (!empty($request->enquiryId)) {

                    $EV_Category = config('POS_CATEGORY_IDENTIFIER');
                    $agent_details = false;                    
                    if($EV_Category !== NULL)
                    {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'P')
                            ->where('category', $EV_Category)
                            ->exists();
                    }                    

                    if ($agent_details) {

                        $model_ids = self::getModelIdByFuelType($request);

                        if ($model_ids instanceof \Illuminate\Http\JsonResponse) {
                            $model_ids = json_decode($model_ids->getContent(), true)['data'] ?? [];
                        } else {
                            $model_ids = [];
                        }

                        $result = array_values(collect($result)->whereIn("model_id", $model_ids)->toArray());

                        return response()->json([
                            'status' => true,
                            'msg'    => $env_folder,
                            'data' => camelCase($result)
                        ]);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }

                return response()->json([
                    'status' => true,
                    'msg'    => $env_folder,
                    'data' => camelCase($result)
                ]);
        }
        $models = MotorModel::select('model_id', 'manf_id', 'vehicle_id', 'model_name')
            ->where('status', 'Active')
            ->where('manf_id', $request->manfId)
            ->get()
            ->toarray();
        list($status, $msg, $data) = $models
            ? [true, 'Models List Fetched Successfully...!', camelCase($models)]
            : [false, 'Something went wrong !', null];
        return response()->json([
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    public function getFuelType(Request $request)
    {
        if(config("MMV_MASTER_NEW_FLOW_ENABLED") == 'Y'){
            return MmvMasterController::getFuelType($request);
        }
        $validate = Validator::make($request->all(), [
            'productSubTypeId' => 'required',
            'modelId' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        } else {
            $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();

            try {
                if (!empty($request->enquiryId)) {

                    $EV_Category = config('POS_CATEGORY_IDENTIFIER');
                    $agent_details = false;

                    if ($EV_Category !== NULL) {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'P')
                            ->where('category', $EV_Category)
                            ->exists();
                    }

                    if ($agent_details) {
                        return response()->json([
                            'status' => true,
                            'data' => camelCase([
                               "ELECTRIC"
                            ])
                        ]);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }

            if($request->productSubTypeId == 18)
            {
                return response()->json([
                    'status' => true,
                    'data' => camelCase([
                        "Petrol",
                        "DIESEL",
                        "CNG"
                    ])
                ]);
            }

            if (in_array($request->productSubTypeId, $product_sub_type_id))
            //if($request->productSubTypeId == 6)
            {
                $env = config('app.env');
                if ($env == 'local') {
                    $env_folder = 'uat';
                } else if ($env == 'test') {
                    $env_folder = 'production';
                } else if ($env == 'live') {
                    $env_folder = 'production';
                }

                $product = strtolower(get_parent_code($request->productSubTypeId));
                $product = $product == 'car' ? 'motor' : $product;
                $path = 'mmv_masters/' . $env_folder . '/';
                //$file_name  = $path.'pcv_model.json';
                $file_name  = $path . $product . '_model_version.json';
                //$data = json_decode(file_get_contents($file_name), true);
                $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
                $result = [];
                if ($data) {
                    foreach ($data as $value) {
                        if ($value['model_id'] == $request->modelId) {
                            if (!(in_array($value['fuel_type'], $result))) {
                                $result[] =  $value['fuel_type'];   
                            }
                        }
                    }
                }

                $result = array_map(function ($res) {
                    if (in_array($res, ['PETROL+CNG', 'PETROL+LPG', 'DIESEL+CNG', 'DIESEL+LPG'])) {
                        return 'CNG';
                    }

                    return $res;
                }, $result);
                 
                if(in_array("HYBRID",$result) && config('constants.motorConstant.HYBRID_FUEL_TYPE.ENABLE') != 'Y')
                {
                    $hypbridIndex = array_search('HYBRID', $result);
                    unset($result[$hypbridIndex]);
                }

                rsort($result);
                return response()->json([
                    'status' => true,
                    'msg'    => $env_folder,
                    'data' => camelCase($result)
                ]);
            } else {
                $fuel_types = DB::table('motor_model_version')
                    ->where([
                        'status' => 'Active',
                        'model_id' => $request->modelId,
                    ])
                    ->groupBy('fuel_type')
                    ->pluck('fuel_type')
                    ->toArray();

                if (empty($fuel_types)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Fuel Types Not Found',
                    ]);
                } else {
                    return response()->json([
                        'status' => true,
                        'data' => $fuel_types,
                    ]);
                }
            }
        }
    }

    public function getNcb(Request $request)
    {
        $ncb = DB::table('motor_ncb')->get();

        if (!$ncb->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => camelCase($ncb)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No data found'
            ]);
        }
    }

    public function getOwnerTypes(Request $request)
    {
        $owner_types = DB::table('vehicle_owner_type')->get();

        if (!$owner_types->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => camelCase($owner_types)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No data found'
            ]);
        }
    }

    public function getVehicleInfo(Request $request)
    {
        $validations = Validator::make($request->all(), [
            'versionId' => 'required|numeric'
        ]);

        if ($validations->fails()) {
            return response()->json([
                'status' => false,
                'msg' => $validations->errors()
            ]);
        } else {
            $vehicle_info = DB::table('motor_manufacturer AS a')
                ->join('motor_model AS b', 'b.manf_id', '=', 'a.manf_id')
                ->join('motor_model_version AS c', 'c.model_id', '=', 'b.model_id')
                ->where('version_id', $request->versionId)
                ->select('a.manf_name', 'b.model_name', 'c.version_name', 'c.Grosss_Vehicle_Weight', 'c.fuel_type')
                ->get();

            if (!$vehicle_info->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => camelCase($vehicle_info)
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'No data found'
                ]);
            }
        }
    }

    public function getRelationshipMapping()
    {
        $nominee_relationship = DB::table('relationship_mapping')
            ->select('nominee_relationship')
            ->get();

        if (!$nominee_relationship->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => array_map(function ($nomineeRelationship) {
                    return $nomineeRelationship->nominee_relationship;
                }, $nominee_relationship->toArray())
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => "No data found"
            ]);
        }
    }

    public function checkPincode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pincode' => 'required|numeric|digits:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'msg' => $validator->errors()
            ]);
        } else {
            $checkPincode = DB::table('master_pincode_state_city')
                ->where('pincode', $request->pincode)
                ->get();

            if (!$checkPincode->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'msg' => "Valid pincode"
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => "Invalid pincode"
                ]);
            }
        }
    }

    public function getPreviousInsurers()
    {
        $previous_insurer = DB::table('previous_insurer_mapping')
            ->select('previous_insurer')
            ->get();

        if (!$previous_insurer->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => array_map(function ($prev_insurer) {
                    return $prev_insurer->previous_insurer;
                }, $previous_insurer->toArray())
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => "No data found"
            ]);
        }
    }

    public function getAddonList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productSubTypeId' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'msg' => $validator->errors()
            ]);
        } else {
            if (isset($request->corpId) && $request->corpId != '') {
                $addon_list = DB::table('master_policy AS mp')
                    ->join('master_plan AS mplan', 'mp.policy_id', '=', 'mplan.policy_id')
                    ->join('motor_master_cover AS mc', 'mc.plan_id', '=', 'mplan.plan_id')
                    ->join('motor_addon AS ma', 'mc.cover_name', '=', 'ma.addon_id')
                    ->whereRaw('(IFNULL(cover_type_id, 1) = 1 OR cover_type_id = 0)')
                    ->where('mp.corp_client_id', $request->corpId)
                    ->where('mp.product_sub_type_id', $request->productSubTypeId)
                    ->select(DB::raw('DISTINCT mp.corp_client_id'), 'mp.product_sub_type_id', 'ma.addon_name AS addon_name', 'ma.addon_id', 'ma.addon_id AS cover_name', 'ma.addon_title')
                    ->union(
                        DB::table('master_policy AS mp')
                            ->join('master_plan AS mplan', 'mp.policy_id', '=', 'mplan.policy_id')
                            ->join('motor_master_cover AS mc', 'mc.plan_id', '=', 'mplan.plan_id')
                            ->join('master_bundle_addon AS mb', 'mc.cover_name', '=', 'mb.master_bundle_id')
                            ->join('addon_bundle_mapping AS b', 'mb.master_bundle_id', '=', 'b.master_bundle_id')
                            ->join('motor_addon AS ma', 'ma.addon_id', '=', 'b.addon_id')
                            ->where('cover_type_id', '2')
                            ->where('corp_client_id', $request->corpId)
                            ->where('product_sub_type_id', $request->productSubTypeId)
                            ->select(DB::raw('DISTINCT mp.corp_client_id'), 'mp.product_sub_type_id', 'ma.addon_name AS addon_name', 'ma.addon_id', 'ma.addon_id AS cover_name', 'ma.addon_title')
                    )
                    ->get();
            } else {
                $addon_list = DB::table('master_policy AS mp')
                    ->join('master_plan AS mplan', 'mp.policy_id', '=', 'mplan.policy_id')
                    ->join('motor_master_cover AS mc', 'mc.plan_id', '=', 'mplan.plan_id')
                    ->join('motor_addon AS ma', 'mc.cover_name', '=', 'ma.addon_id')
                    ->whereRaw('(IFNULL(cover_type_id, 1) = 1 OR cover_type_id = 0)')
                    ->where('mp.product_sub_type_id', $request->productSubTypeId)
                    ->select(DB::raw('DISTINCT mp.corp_client_id'), 'mp.product_sub_type_id', 'ma.addon_name AS addon_name', 'ma.addon_id', 'ma.addon_id AS cover_name', 'ma.addon_title')
                    ->union(
                        DB::table('master_policy AS mp')
                            ->join('master_plan AS mplan', 'mp.policy_id', '=', 'mplan.policy_id')
                            ->join('motor_master_cover AS mc', 'mc.plan_id', '=', 'mplan.plan_id')
                            ->join('master_bundle_addon AS mb', 'mc.cover_name', '=', 'mb.master_bundle_id')
                            ->join('addon_bundle_mapping AS b', 'mb.master_bundle_id', '=', 'b.master_bundle_id')
                            ->join('motor_addon AS ma', 'ma.addon_id', '=', 'b.addon_id')
                            ->where('cover_type_id', '2')
                            ->where('product_sub_type_id', $request->productSubTypeId)
                            ->select(DB::raw('DISTINCT mp.corp_client_id'), 'mp.product_sub_type_id', 'ma.addon_name AS addon_name', 'ma.addon_id', 'ma.addon_id AS cover_name', 'ma.addon_title')
                    )
                    ->get();
            }

            if (!$addon_list->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => camelCase($addon_list)
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => "No data found"
                ]);
            }
        }
    }

    public function getGenderType()
    {
        $getGender = DB::table('gender_mapping')
            ->select('gender')
            ->get();

        if (!$getGender->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => [
                    "genderType1" => $getGender[0]->gender,
                    "genderType2" => $getGender[1]->gender
                ]
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Some Error Occurred during Found'
            ]);
        }
    }

    public function getOccupationType()
    {
        $getOccupation = DB::table('occupation_mapping')
            ->select('occupation')
            ->get();

        if (!$getOccupation->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => [
                    "occupationType1" => $getOccupation[0]->occupation,
                    "occupationType2" => $getOccupation[1]->occupation,
                    "occupationType3" => $getOccupation[2]->occupation,
                    "occupationType4" => $getOccupation[3]->occupation,
                    "occupationType5" => $getOccupation[4]->occupation,
                    "occupationType6" => $getOccupation[5]->occupation,
                    "occupationType7" => $getOccupation[6]->occupation
                ]
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Some Error Occurred during Found'
            ]);
        }
    }

    public function getMaritalStatusType()
    {
        $getMaritalStatus = DB::table('marital_status_mapping')
            ->select('marital_status')
            ->get();

        if (!$getMaritalStatus->isEmpty()) {
            return response()->json([
                'status' => true,
                'data' => [
                    "meritalStatusType1" => $getMaritalStatus[0]->marital_status,
                    "meritalStatusType2" => $getMaritalStatus[1]->marital_status
                ]
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Some Error Occurred during Found'
            ]);
        }
    }

    public static function getMmvPathDetails($request): string
    {
        $env = config('app.env');

        if ($env == 'local') {
            $env_folder = 'uat';
        } else if ($env == 'test') {
            $env_folder = 'production';
        } else if ($env == 'live') {
            $env_folder = 'production';
        }

        $product = strtolower(get_parent_code($request->productSubTypeId));
        $product = ($product == 'car') ? 'motor' : $product;

        return 'mmv_masters/' . $env_folder . '/' . $product;
    }

    public function getManufacturerByFuelType(Request $request)
    {
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();

        if (in_array($request->productSubTypeId, $product_sub_type_id)) {

            $path = self::getMmvPathDetails($request);

            /* Get Manufacturer Details */
            $model_file_name  = $path . '_model.json';

            $model_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')
                ->get($model_file_name), true);

            $data = self::getModelIdByFuelType($request);

            if ($data instanceof \Illuminate\Http\JsonResponse) {
                $data = json_decode($data->getContent(), true)['data'] ?? [];
            }else{
                $data = [];
            }

            $model_data = collect($model_data)->whereIn('model_id', $data)->pluck('manf_id')->unique()->values();

            return response()->json([
                'status' => true,
                'data' => $model_data
            ]);
        }
      
    }


    public function getModelIdByFuelType(Request $request)
    {

        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
        
        if (in_array($request->productSubTypeId, $product_sub_type_id)) {

            $path = self::getMmvPathDetails($request);

            /* Get Model Id Details */
            $file_name  = $path . '_model_version.json';

            $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);

            if(in_array(get_parent_code($request->productSubTypeId), ["PCV", "GCV"])){
                $data = collect($data)->filter(function (array $value, mixed $key): bool {
                    return $value['fuel_type'] === 'ELECTRIC' && (substr($value['version_id'], 0, 5) == 'GCV3W' || substr($value['version_id'], 0, 5) == 'PCV3W');
                })->pluck('model_id')->unique()->values();
            }else{
                $data = collect($data)->filter(function (array $value, mixed $key): bool {
                    return $value['fuel_type'] === 'ELECTRIC';
                })->pluck('model_id')->unique()->values();
            }

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        }
      
    }

    public function setLeadStage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $userProductJourneyId = $request->enquiryId;
        $leadStageId = $request->leadStageId ?? 1;

        $leadStageData = DB::table('user_prodoct_journey')
            ->where('user_product_journey_id', $userProductJourneyId)
            ->whereRaw('IFNULL(lead_stage_id,0)<' . $leadStageId)
            ->select('user_product_journey_id')
            ->limit(1)
            ->get();

        if (!$leadStageData->isEmpty()) {
            $updateLeadStageData = DB::table('user_prodoct_journey')
                ->where('user_product_journey_id', $userProductJourneyId)
                ->update(['lead_stage_id' => $leadStageId]);
        } else {
            $setLeadStage = DB::table('user_prodoct_journey')
                ->where('user_product_journey_id', $userProductJourneyId)
                ->select('*')
                ->get();

            if (!$setLeadStage->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => camelCase($setLeadStage[0])
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'msg' => 'Data not found.'
                ]);
            }
        }
    }

    public function saveQuoteData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'productSubTypeId' => 'required|numeric',
            'masterPolicyId' => 'required',
            'premiumJson' => 'required',
            'exShowroomPriceIdv' => 'required',
            'finalPremiumAmount' => 'required',
            'odPremium' => 'required',
            'tpPremium' => 'required',
            'serviceTax' => 'required',
            'addonPremiumTotal' => 'required',
            'icId' => 'nullable',
            'icAlias' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $quoteData = [
            'product_sub_type_id' => $request->productSubTypeId,
            'master_policy_id' => $request->masterPolicyId,
            'premium_json' => $request->premiumJson,
            'ex_showroom_price_idv' => $request->exShowroomPrice, //$request->exShowroomPriceIdv,
            'idv' => $request->exShowroomPriceIdv,
            'final_premium_amount' => $request->finalPremiumAmount,
            'od_premium' => $request->odPremium,
            'tp_premium' => $request->tpPremium,
            'service_tax' => $request->serviceTax,
            'addon_premium' => $request->addonPremiumTotal,
            'ic_id' => $request->icId,
            'ic_alias' => $request->icAlias
        ];

        if (isset($request->revisedNcb)) {
            $quoteData['revised_ncb'] = $request->revisedNcb;
        }        

        $enquiryId = customDecrypt($request->enquiryId);
        $JourneyStage_data = JourneyStage::where('user_product_journey_id', $enquiryId)->first();

        if(isset($JourneyStage_data->stage) && ($JourneyStage_data->stage == STAGE_NAMES['POLICY_ISSUED'] || $JourneyStage_data->stage == STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] || strtolower($JourneyStage_data->stage) == strtolower( STAGE_NAMES['PAYMENT_SUCCESS']))) {
            return response()->json([
                'status' => false,
                'msg' => 'Transaction Already Completed',
                'data' => $JourneyStage_data
            ]);
        } else if (strtolower($JourneyStage_data->stage ?? '') == STAGE_NAMES['PAYMENT_INITIATED']) {
            return response()->json([
                'status' => false,
                'msg' => STAGE_NAMES['PAYMENT_INITIATED'],
                'data' => $JourneyStage_data
            ]);
        }
        updateJourneyStage([
            "user_product_journey_id" => $enquiryId,
            "stage" => STAGE_NAMES['QUOTE']
        ]);
        
        QuoteLog::where(["user_product_journey_id" => $enquiryId])->update($quoteData);

        if (isset($request->applicableAddons)) {
            selectedAddons::where(["user_product_journey_id" => $enquiryId])->update(['applicable_addons' => $request->applicableAddons]);
        }

        // This event will be triggered when user clicks on BuyNow button and user redirects to proposal page
        //$is_renewal_case = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->select('is_renewal')->first();
        $corporateVehiclesQuotesData = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->select('is_renewal','previous_insurer_code', 'vehicle_owner_type')->first();
        if($corporateVehiclesQuotesData?->is_renewal == 'Y')
        {
                $current_company_alias = $request->premiumJson['company_alias'];

                if($corporateVehiclesQuotesData->previous_insurer_code == $current_company_alias)
                {
                        $is_rollover_renewal = 'N'; //if both IC is same then N
                }
                else
                {
                        $is_rollover_renewal = 'Y'; // if both ic diff then Y
                }

                CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->update([
                        'rollover_renewal' => $is_rollover_renewal
                ]);

        }
        // Don't push renewal data at this stage - 23-12-2022
        if($corporateVehiclesQuotesData?->is_renewal != 'Y') {
            event(new \App\Events\clickedOnBuyNow($enquiryId));
        }

        // reseting ckyc details if user changes IC in quote page
        $proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        if(!empty($proposal) && !empty($request->icId ?? '') && $request->icId != $proposal->ic_id)
        {
            $proposal->ckyc_number = null;
            $proposal->ckyc_reference_id = null;
            $proposal->ckyc_meta_data = null;
            $proposal->ckyc_type = null;
            $proposal->ckyc_type_value = null;
            $proposal->is_ckyc_verified = 'N';
            $proposal->is_ckyc_details_rejected = 'N';
            $proposal->save();

            $proposal->ckyc_request_response?->delete();
        }else if(!empty($proposal->ic_id) && $request->icId == $proposal->ic_id && (isset($request->premiumJson['company_alias']) && in_array($request->premiumJson['company_alias'], ['kotak','royal_sundaram'])))
        {
            $proposal->ckyc_number = null;
            $proposal->ckyc_reference_id = null;
            $proposal->ckyc_meta_data = null;
            $proposal->ckyc_type = null;
            $proposal->ckyc_type_value = null;
            $proposal->is_ckyc_verified = 'N';
            $proposal->is_ckyc_details_rejected = 'N';
            $proposal->save();

        }

        if (!empty($proposal) && !empty($proposal->additional_details)) {
            $previousOwnerType = json_decode($proposal->additional_details,1)['owner']['prevOwnerType'] ?? '';
            if ($previousOwnerType != $corporateVehiclesQuotesData->vehicle_owner_type) {
                $proposal->ckyc_number = null;
                $proposal->ckyc_reference_id = null;
                $proposal->ckyc_meta_data = null;
                $proposal->ckyc_type = null;
                $proposal->ckyc_type_value = null;
                $proposal->is_ckyc_verified = 'N';
                $proposal->is_ckyc_details_rejected = 'N';
                $proposal->save();
            }
        }
        if (config("CHECK_PROPOSAL_HASH_ENABLE") == "Y") {
            ProposalHash::where('user_product_journey_id', $enquiryId)
            ->update([
                    'hash' => null
            ]);
        }

        // end reseting ckyc details if user changes IC in quote page

        event(new \App\Events\PushDashboardData($enquiryId));

        return response()->json([
            'status' => true,
            'msg' => 'Quote data saved successfully..!'
        ], 200);
    }

    public function saveQuoteRequestData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'stage' => 'required|in:1,2,3,4,5,6,7,8,9,10,11,12',
            'firstName' => 'nullable',
            'lastName' => 'nullable',
            'emailId' => 'nullable',
            'mobileNo' => 'nullable',
            'sellerType' => ['nullable', 'in:E,P,U,Dealer,Partner,misp'],
            'agentId' => [Rule::requiredIf(function () use ($request) {
                return in_array($request->sellerType, ['E', 'P', 'U', 'Partner','misp']);
            })],
            'agentName' => [Rule::requiredIf(function () use ($request) {
                return in_array($request->sellerType, ['E', 'P', 'Partner','misp']);
            })],
            'agentMobile' => [Rule::requiredIf(function () use ($request) {
                return in_array($request->sellerType, ['E', 'P', 'U','misp']);
            })],
            'agentEmail' => [Rule::requiredIf(function () use ($request) {
                return in_array($request->sellerType, ['E', 'P', 'U', 'Partner','misp']);
            }), 'email'],
            'vehicleRegistrationNo' => 'nullable',
            'productSubTypeId' => 'required_if:stage,==,3',
            'manfactureId' => 'required_if:stage,==,4',
            'manfactureName' => 'required_if:stage,==,4',
            'model' => 'required_if:stage,==,5',
            'modelName' => 'required_if:stage,==,5',
            'fuelType' => 'required_if:stage,==,6',
            'version' => 'required_if:stage,==,7',
            'versionName' => 'required_if:stage,==,7',
            'rtoNumber' => 'required_if:stage,==,8',
            //'vehicleRegisterDate' => 'required_if:stage,==,9',
            'manufactureYear' => 'required_if:stage,==,9',
            'vehicleOwnerType' => 'required_if:stage,==,10',
            'isClaim' => 'required_if:stage,==,11',
            //'isNcbConfirmed' => 'required_if:stage,==,11',
            //'previousNcb' => 'required_if:stage,==,11',
            //'applicableNcb' => 'required_if:stage,==,11',
            'hasExpired' => 'required_if:stage,==,11',
            //'policyExpiryDate' => 'required_if:stage,==,11',
            'previousInsurer' => 'nullable',
            'previousInsurerCode' => 'nullable',
            //'previousPolicyType' => 'required_if:stage,==,11',
            'vehicleUsage' => 'nullable',
            'journeyType' => 'nullable',
            'siteIdentifier' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if(config('enquiry_id_encryption') == 'Y')
        {
            $enquiryIdController = new EnquiryIdController();
            $isEnquiryIdValid = $enquiryIdController->isEnquiryIdValid($request);
            $response = json_decode(json_encode($isEnquiryIdValid), true);
            $isValid = $response['original']['status'];
            if($isValid != true){
                return response()->json([
                    'status' => false,
                    'msg' => 'Enquiry ID is not valid.'
                ]);
            }
        }

        $checkTransactionStageValidity = checkTransactionStageValidity(customDecrypt($request->enquiryId));
        if(!$checkTransactionStageValidity['status']) {
            return response()->json($checkTransactionStageValidity);
        }

        if (checkValidRcNumber($request->vehicleRegistrationNo)) {
            return response()->json([
                'status' => false,
                'msg' => 'This Rc Number Blocked On Portal',
            ]);
        }
        if (!empty($request->vehicleRegistrationNo) && config('DISABLE_REGISTRATION_NO_CHECK') != 'Y') {
            $registrationNoCheck = UtilityApi::registrationNoCheck($request);
            if ($registrationNoCheck['status'] == false) {
                $productSubTypeId = $request->productSubTypeName === 'CAR' ? 1 : ($request->productSubTypeName === 'BIKE' ? 2 : NULL);
                $UserProductJourney = UserProductJourney::create([
                    'product_sub_type_id' => $productSubTypeId,
                ]);
                $existingMapping = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
 
                if ($existingMapping) {
                    $newData = $existingMapping->replicate();
                    $newData->user_product_journey_id = $UserProductJourney->user_product_journey_id;
                    $newData->save();
                }
                return response()->json([
                    'status' => false,
                    'msg' => 'Registration Number Cannot Change For this Enquiry ID',
                    'validation_msg' => $registrationNoCheck['message'],
                    'newEnquiryId' => customEncrypt($UserProductJourney->user_product_journey_id)
                ]);
            }
        }
        $enquiryId = customDecrypt($request->enquiryId);
        if (isset($request->tokenResp)) {
            $request['tokenResp'] = gettype($request->tokenResp) != 'array' ? json_decode($request->tokenResp, true) : $request->tokenResp;
            if (!empty($request->token)) {
                JourneyTokenMapping::updateOrCreate([
                    'user_product_journey_id' => $enquiryId,
                ],[
                    'dashboard_token' => $request->token
                ]);
            }
        }

        if (isset($request->rtoNumber) && $request->rtoNumber != '') {
            $rto_data = DB::table('master_rto')
                ->where('rto_number', $request->rtoNumber)
                ->first();
            if (empty($rto_data)) {
                return response()->json(
                    [
                        'status' => false,
                        'msg' => $request->rtoNumber . ' Invalid RTO Number'
                    ]
                );
            }
        }
        $JourneyStage_data = JourneyStage::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
        if(!empty($request->resetJourneyStage) && $request->resetJourneyStage == 'Y')
        {
            $JourneyStage_data->stage = STAGE_NAMES['QUOTE'];
            JourneyStage::where( 'user_product_journey_id', $enquiryId )
            ->first()
            ->update( [ 'stage' => STAGE_NAMES['QUOTE'] ] );
        }

        if(!empty($JourneyStage_data->stage) && strtolower( $JourneyStage_data->stage ) === strtolower( STAGE_NAMES['PAYMENT_FAILED'] ) )
        {
            return response()->json( [
                'status' => false,
                'msg' => STAGE_NAMES['PAYMENT_FAILED'],
                'data' => $JourneyStage_data
            ] ); exit;
        }

        if( isset( $JourneyStage_data->stage ) && in_array( strtolower( $JourneyStage_data->stage ), array_map( 'strtolower', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS'] ] ) ) )
        { 
            return response()->json([
                'status' => false,
                'msg' => 'Transaction Already Completed',
                'data' => $JourneyStage_data
            ]); exit;
        }

        if(isset($request->previousPolicyTypeIdentifierCode, $request->policyType) && in_array($request->previousPolicyTypeIdentifierCode, ['13', '15']) && $request->policyType == 'own_damage')
        {
            $UserProposal = UserProposal::where('user_product_journey_id', $enquiryId)->select('tp_end_date')->first();

            if(!empty($UserProposal->tp_end_date))
            {
                if($request->previousPolicyTypeIdentifierCode = '13')
                {
                    UserProposal::where('user_product_journey_id', $enquiryId)->update([
                        'tp_start_date' => date('d-m-Y', strtotime('-3 year +1 day', strtotime($UserProposal->tp_end_date)))
                    ]);
                }
                else if($request->previousPolicyTypeIdentifierCode = '15')
                {
                    UserProposal::where('user_product_journey_id', $enquiryId)->update([
                        'tp_start_date' => date('d-m-Y', strtotime('-5 year +1 day', strtotime($UserProposal->tp_end_date)))
                    ]);
                }
            }
        }
        
        if(!in_array($request->stage, ['1', '2']))
        {
            $url = $JourneyStage_data->quote_url ?? NULL;
            if($url != NULL)
            {
                $parts = parse_url($url);
                parse_str($parts['query'] ?? '', $query);
                $token = $query['token'] ?? '';
                if($token != '')
                {
                    unset($agentDetail);
                    $agentDetail =  CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                                    ->get()
                                    ->toArray();
                    if(count($agentDetail) == 0)
                    {
                        return response()->json([
                            'status' => false,
                            'msg' => 'Agent Details is Missing'
                        ]);
                    }                               
                }                
            }            
        }        
        if(!empty($request->manufactureYear) && !empty($request->vehicleRegisterDate) && $request->vehicleRegisterDate != 'NULL')
        {
            $disable_month_validation = !(config('DISABLE_MANUFACTURE_MONTH_VALIDATION') == 'Y');
            $disable_year_validation  = !(config('DISABLE_MANUFACTURE_YEAR_VALIDATION') == 'Y');
            [$manufactureYear_month,$manufactureYear_year] = explode('-',$request->manufactureYear);
            [$vehicleRegisterDate_date,$vehicleRegisterDate_month,$vehicleRegisterDate_year] = explode('-',$request->vehicleRegisterDate);
            if($manufactureYear_year > $vehicleRegisterDate_year  && $disable_year_validation)
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Manufacture Year Should be less than or equal to Vehicle Registration Year'
                ]);
            }
            else if($manufactureYear_year == $vehicleRegisterDate_year)
            {
                if($manufactureYear_month > $vehicleRegisterDate_month && $disable_month_validation)
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Manufacture Month Should be less than or equal to Vehicle Registration Month'
                    ]);
                }
            }
        }
        $userProductJourneyData = $corporateVehiclesQuotesRequestData = $QuoteLogData = $quoteDataJson = $journeyCampaignData = [];
        // userProductJourneyData
        if (isset($request->userId) && $request->userId != '') {
            $quoteDataJson['user_id'] = $QuoteLogData['user_id'] = $userProductJourneyData['user_id'] = $request->userId;
        }

        if (isset($request->corpId) && $request->corpId != '') {
            $quoteDataJson['corp_id'] = $QuoteLogData['corp_id'] = $corporateVehiclesQuotesRequestData['corp_id'] = $userProductJourneyData['corp_id'] = $request->corpId;
        }
        if (isset($request->firstName) && isset($request->lastName)) {
            $quoteDataJson['full_name'] = $request->firstName . ' ' . $request->lastName;
        }
        if (isset($request->editChassisIdv)) {
            $quoteDataJson['edit_chassis_idv'] = $request->editChassisIdv;
        }
        if (isset($request->isChassisIdvChanged)) {
            $quoteDataJson['is_chassis_idv_changed'] = $request->isChassisIdvChanged;
        }
        if (isset($request->editBodyIdv)) {
            $quoteDataJson['edit_body_idv'] = $request->editBodyIdv;
        }
        if (isset($request->isBodyIdvChanged)) {
            $quoteDataJson['is_body_idv_changed'] = $request->isBodyIdvChanged;
        }
        if (isset($request->emailId)) {
            $quoteDataJson['user_email'] = $userProductJourneyData['user_email'] = $request->emailId;
        }
        if (isset($request->mobileNo)) {
            $quoteDataJson['user_mobile'] = $userProductJourneyData['user_mobile'] = $request->mobileNo;
        }
        if (isset($request->productSubTypeId)) {
            $quoteDataJson['product_sub_type_id'] = $QuoteLogData['product_sub_type_id'] = $corporateVehiclesQuotesRequestData['product_id'] = $userProductJourneyData['product_sub_type_id'] = $request->productSubTypeId;
        }
        if (isset($request->manfactureId)) {
            $quoteDataJson['manfacture_id'] = $request->manfactureId;
            $quoteDataJson['manfacture_name'] = $request->manfactureName;
        }
        if (isset($request->model)) {
            $quoteDataJson['model'] = $request->model;
            $quoteDataJson['model_name'] = $request->modelName;
        }
        if (isset($request->version)) {
            $quoteDataJson['version_id'] = $request->version;
            if(str_starts_with($request->version,'GCV'))
            {
                $getVehiclePartialBuild = getVehiclePartialBuild($request->version);
                $quoteDataJson['is_vehicle_partial_build'] = $corporateVehiclesQuotesRequestData['is_vehicle_partial_build'] = $getVehiclePartialBuild['is_partial_build'] == true ? 'Y' : 'N'; 
                $quoteDataJson['vehicle_build_type'] = $corporateVehiclesQuotesRequestData['vehicle_build_type'] = $getVehiclePartialBuild['vehicle_build_type']; 
            }
        }
        if ( isset($request->lead_source) || isset($request->utm_campaign ) || isset($request->utm_medium ) || isset($request->utm_source) ) {
            $jounrneyCampaignData['user_product_journey_id'] = $enquiryId;
        }

        if (isset($request->vehicleUsage)) {
            $quoteDataJson['vehicle_usage'] = $request->vehicleUsage;
        }
        if (isset($request->lead_source)) {
            $userProductJourneyData['lead_source'] = $request->lead_source;
            $journeyCampaignData['lead_source'] = $request->lead_source;
        }
        if (isset($request->utm_campaign)) {
            $userProductJourneyData['campaign_id'] = $request->utm_campaign;
            $journeyCampaignData['utm_campaign'] = $request->utm_campaign;
        }
        if (isset($request->utm_medium)) {
            $journeyCampaignData['utm_medium'] = $request->utm_medium;
        }
        if (isset($request->utm_source)) {
            $journeyCampaignData['utm_source'] = $request->utm_source;
            if( config('bcl.overwrite.lead_source_with_utm_source') == "Y" )
            {
                $journeyCampaignData['lead_source'] = $request->utm_source;
            }
        }
        if (!empty($userProductJourneyData)) {
            $userProductJourneyId = UserProductJourney::updateOrCreate(['user_product_journey_id' => $enquiryId], $userProductJourneyData);
        }
        if (!empty($journeyCampaignData)) {
            $journeyCampaignDataId = JourneycampaignDetails::updateOrCreate(['user_product_journey_id' => $enquiryId], $journeyCampaignData);
        }
        if (!empty($request->ownershipChanged)) {
            $corporateVehiclesQuotesRequestData['ownership_changed'] = $request->ownershipChanged;
        }
        // corporateVehiclesQuotesRequestData
        $QuoteLogData['user_product_journey_id'] = $corporateVehiclesQuotesRequestData['user_product_journey_id'] = $enquiryId;

        if (isset($request->policyType)) {
            if ($request->policyType == 'NULL') {
                $quoteDataJson['policy_type'] = $corporateVehiclesQuotesRequestData['policy_type'] = NULL;
            } else {
                $quoteDataJson['policy_type'] = $corporateVehiclesQuotesRequestData['policy_type'] = $request->policyType;
            }
        }
        if (isset($request->businessType)) {
            if ($request->businessType == 'NULL') {
                $quoteDataJson['business_type'] = $corporateVehiclesQuotesRequestData['business_type'] = NULL;
            } else {
                $quoteDataJson['business_type'] = $corporateVehiclesQuotesRequestData['business_type'] = $request->businessType;
            }
        }
        if (isset($request->version)) {
            $quoteDataJson['version_id'] = $corporateVehiclesQuotesRequestData['version_id'] = $request->version;
            $quoteDataJson['version_name'] = $corporateVehiclesQuotesRequestData['version_name'] = $request->versionName;
        }
        if (isset($request->vehicleRegistrationNo)) {
            if ($request->vehicleRegistrationNo == 'NULL') {
                $quoteDataJson['vehicle_registration_no'] = $corporateVehiclesQuotesRequestData['vehicle_registration_no'] = NULL;
            } else {
                $quoteDataJson['vehicle_registration_no'] = $corporateVehiclesQuotesRequestData['vehicle_registration_no'] = removingExtraHyphen($request->vehicleRegistrationNo);
            }
        }
        if (isset($request->vehicleRegisterDate)) {
            if ($request->vehicleRegisterDate == 'NULL') {
                $quoteDataJson['vehicle_register_date'] = $corporateVehiclesQuotesRequestData['vehicle_register_date'] = NULL;
            } else {
                $quoteDataJson['vehicle_register_date'] = $corporateVehiclesQuotesRequestData['vehicle_register_date'] = $request->vehicleRegisterDate;
            }
        }
        if (isset($request->policyExpiryDate)) {
            if ($request->policyExpiryDate == 'NULL') {
                $quoteDataJson['previous_policy_expiry_date'] = $corporateVehiclesQuotesRequestData['previous_policy_expiry_date'] = NULL;
            } else {
                $quoteDataJson['previous_policy_expiry_date'] = $corporateVehiclesQuotesRequestData['previous_policy_expiry_date'] = $request->policyExpiryDate;
            }
        }
        if (isset($request->previousPolicyType)) {
            if ($request->previousPolicyType == 'NULL') {
                $quoteDataJson['previous_policy_type'] = $corporateVehiclesQuotesRequestData['previous_policy_type'] = NULL;
            } else {
                if(strtolower($request->previousPolicyType) == 'not sure') {
                    //Gave priority to user preference over OnGrid/Fastlane/Adrila - #8415
                    UserProposal::where('user_product_journey_id', $enquiryId)->update([
                        'previous_insurance_company' => null,
                        'previous_policy_number' => null,
                    ]);
                }
                $quoteDataJson['previous_policy_type'] = $corporateVehiclesQuotesRequestData['previous_policy_type'] = $request->previousPolicyType;
            }
        }
        if (isset($request->previousInsurer)) {
            if ($request->previousInsurer == 'NULL') {
                $quoteDataJson['previous_insurer'] = $corporateVehiclesQuotesRequestData['previous_insurer'] = NULL;
                $corporateVehiclesQuotesRequestData['previous_insurer_code'] = NULL;
            } else {
                $quoteDataJson['previous_insurer'] = $corporateVehiclesQuotesRequestData['previous_insurer'] = $request->previousInsurer;
                $corporateVehiclesQuotesRequestData['previous_insurer_code'] = $request->previousInsurerCode;
            }
        }
        if (isset($request->fuelType)) {
            $quoteDataJson['fuel_type'] = $corporateVehiclesQuotesRequestData['fuel_type'] = $request->fuelType;
        }
        if (isset($request->manufactureYear)) {
            $quoteDataJson['manufacture_year'] = $corporateVehiclesQuotesRequestData['manufacture_year'] = $request->manufactureYear;
        }
        if (isset($request->vehicleRegisterAt)) {
            $quoteDataJson['rto_code'] = $corporateVehiclesQuotesRequestData['rto_code'] = $request->vehicleRegisterAt;
        }
        if (isset($request->rtoNumber)) {
            $quoteDataJson['rto_code'] = $corporateVehiclesQuotesRequestData['rto_code'] = $request->rtoNumber;
            $quoteDataJson['rto_city'] = $corporateVehiclesQuotesRequestData['rto_city'] = $rto_data->rto_name;
        }
        if (isset($request->vehicleOwnerType)) {
            $quoteDataJson['vehicle_owner_type'] = $corporateVehiclesQuotesRequestData['vehicle_owner_type'] = $request->vehicleOwnerType;
        }
        if (isset($request->vehicleLpgCngKitValue) && $request->vehicleLpgCngKitValue != '') {
            $quoteDataJson['vehicle_lpg_cng_kit_value'] = $corporateVehiclesQuotesRequestData['bifuel_kit_value'] = ($request->vehicleLpgCngKitValue ?? null);
        }
        if (isset($request->isClaim)) {
            $quoteDataJson['is_claim'] = $corporateVehiclesQuotesRequestData['is_claim'] = $request->isClaim;
        }
        if (isset($request->isNcbConfirmed)) {
            $quoteDataJson['is_ncb_confirmed'] = $corporateVehiclesQuotesRequestData['is_ncb_confirmed'] = $request->isNcbConfirmed;
        }
        if (isset($request->previousNcb)) {
            if ($request->previousNcb == 'NULL')
            {
                $quoteDataJson['previous_ncb'] = $corporateVehiclesQuotesRequestData['previous_ncb'] = NULL;
            }
            else
            {
               $quoteDataJson['previous_ncb'] = $corporateVehiclesQuotesRequestData['previous_ncb'] = $request->previousNcb;
            }
        }
        if (isset($request->applicableNcb)) {
            if ($request->applicableNcb == 'NULL')
            {
               $quoteDataJson['applicable_ncb'] = $corporateVehiclesQuotesRequestData['applicable_ncb'] = NULL;
            }
            else
            {
               $quoteDataJson['applicable_ncb'] = $corporateVehiclesQuotesRequestData['applicable_ncb'] = $request->applicableNcb;
            }
        }
        if (isset($request->isNcbVerified)) {
            $quoteDataJson['is_ncb_verified'] = $corporateVehiclesQuotesRequestData['is_ncb_verified'] = $request->isNcbVerified;
        }

        if (isset($request->zeroDepInLastPolicy)) {
            $quoteDataJson['zero_dep_in_last_policy'] = $corporateVehiclesQuotesRequestData['zero_dep_in_last_policy'] = $request->zeroDepInLastPolicy;
        }

        if (isset($request->gcvCarrierType)) {
            $quoteDataJson['gcv_carrier_type'] = $corporateVehiclesQuotesRequestData['gcv_carrier_type'] = $request->gcvCarrierType;
        }
        if (isset($request->prevShortTerm)) {
            $quoteDataJson['prev_short_term'] = $corporateVehiclesQuotesRequestData['prev_short_term'] = $request->prevShortTerm;
        }
        if (isset($request->SelectedPolicyType)) {
            $quoteDataJson['selected_policy_type'] = $corporateVehiclesQuotesRequestData['selected_policy_type'] = $request->SelectedPolicyType;
        }
        if (isset($request->isClaimVerified)) {
            $quoteDataJson['is_claim_verified'] = $corporateVehiclesQuotesRequestData['is_claim_verified'] = $request->isClaimVerified;
        }
        if (isset($request->isIdvSelected)) {
            $quoteDataJson['is_idv_selected'] = $corporateVehiclesQuotesRequestData['is_idv_selected'] = $request->isIdvSelected;
        }
        if (isset($request->isRedirectionDone)) {
            $quoteDataJson['is_redirection_done'] = $corporateVehiclesQuotesRequestData['is_redirection_done'] = $request->isRedirectionDone;
        }
        if (isset($request->isRenewalRedirection)) {
            $quoteDataJson['is_renewal_redirection'] = $corporateVehiclesQuotesRequestData['is_renewal_redirection'] = $request->isRenewalRedirection;
        }
        if (isset($request->prefillPolicyNumber)) {
            $quoteDataJson['prefill_policy_number'] = $corporateVehiclesQuotesRequestData['prefill_policy_number'] = $request->prefillPolicyNumber;
        }
        if (isset($request->selectedGvw)) {
            $quoteDataJson['selected_gvw'] = $corporateVehiclesQuotesRequestData['selected_gvw'] = !empty($request->selectedGvw) ? $request->selectedGvw : null;
        }
        
        if (isset($request->defaultGvw)) {
            $quoteDataJson['default_gvw'] = $corporateVehiclesQuotesRequestData['default_gvw'] = !empty($request->defaultGvw) ? $request->defaultGvw : null;
        }
        
        if (isset($request->previousPolicyTypeIdentifier)) {
            $quoteDataJson['previous_policy_type_identifier'] = $corporateVehiclesQuotesRequestData['previous_policy_type_identifier'] = $request->previousPolicyTypeIdentifier;
        }
        if (isset($request->previousPolicyTypeIdentifierCode)) {
            $quoteDataJson['previous_policy_type_identifier_code'] = $corporateVehiclesQuotesRequestData['previous_policy_type_identifier_code'] = $request->previousPolicyTypeIdentifierCode;
        }
        
        if (isset($request->isMultiYearPolicy)) {
            $quoteDataJson['is_multi_year_policy'] = $corporateVehiclesQuotesRequestData['is_multi_year_policy'] = $request->isMultiYearPolicy;
        }
        
        if (isset($request->renewalRegistration)) {
            $quoteDataJson['renewal_registration'] = $corporateVehiclesQuotesRequestData['renewal_registration'] = $request->renewalRegistration;
        }
        if (isset($request->seatingCapacity)) {
            $quoteDataJson['seating_capacity'] = $request->seatingCapacity;
        }
        
        
        //        if (isset($request->businessType)) {
        //            // $quoteDataJson['policy_type'] = $corporateVehiclesQuotesRequestData['policy_type'] = $request->businessType;
        //            $quoteDataJson['business_type'] = $corporateVehiclesQuotesRequestData['business_type'] = $request->businessType;
        //        }

        if(isset($request->journeyType) && $request->journeyType == "QR-REDIRECTION"){
            $corporateVehiclesQuotesRequestData['journey_type'] = $request->journeyType; //for bcl hide agent
        }
        else if(isset($request->journeyType) && $request->journeyType != null){
            $corporateVehiclesQuotesRequestData['journey_type'] = base64_decode($request->journeyType);
        }

        if (isset($request->journeyType) && $request->journeyType == "ZHJpdmVyLWFwcA=="){
            CvAgentMapping::updateOrCreate(["seller_type" => "E", "user_product_journey_id" => $enquiryId],[
                "user_product_journey_id" => $enquiryId,
                "stage" => "quote",
                "seller_type" => "E",
                "agent_id" => "11",
                "agent_name" => "driver_app",
            ]);
        }

        if (isset($request->journeyType) && $request->journeyType == "ZW1iZWRkZWRfc2NydWI="){
            CvAgentMapping::updateOrCreate(["seller_type" => "E", "user_product_journey_id" => $enquiryId],[
                "user_product_journey_id" => $enquiryId,
                "stage" => "quote",
                "seller_type" => "E",
                "agent_id" => "11",
                "agent_name" => "embedded_scrub",
            ]);
        }
        //Blocking back button on b2c
        $additional_details_array = [
            'block_back_button' => 'N'
        ];

        if(isset($request->blockBackButton)){
            if($request->blockBackButton == 'Y'){
                $additional_details_array['block_back_button'] = 'Y';
            }
        }

        if ($request->stage == 1){
            AdditionalDetails::updateOrCreate([
                "user_product_journey_id" => $enquiryId,
                "additional_data" => json_encode($additional_details_array),
            ]);
        }
        if (isset($request->journeyType) && $request->journeyType == "b2Vt" && $request->stage == 1){
            CvAgentMapping::updateOrCreate(["seller_type" => "Dealer", "user_product_journey_id" => $enquiryId],[
                "user_product_journey_id" => $enquiryId,
                "stage" => "quote",
                "seller_type" => $request['tokenResp']['seller_type'],
                "agent_id" => $request['tokenResp']['seller_id'],
                "agent_name" => $request['tokenResp']['name'],
                "agent_mobile" => $request['tokenResp']['name'],
                "agent_email" => $request['tokenResp']['email'],
                "agent_business_type" => $request['tokenResp']['business_type'] ?? null,
                "agent_business_code" => $request['tokenResp']['business_code'] ?? null,
            ]);
        }
        if(isset($request->whatsappNo)){
            UserProductJourney::where('user_product_journey_id',$enquiryId)->update([
                "user_whatsapp_no"=>$request->whatsappNo,
            ]);
        }
        $token ='';
        if(isset($request->siteIdentifier) && $request->siteIdentifier != null){
            if(isset($request->journeyType) && $request->journeyType == 'Z3JhbWNvdmVyLWFwcC1qb3VybmV5')
            $corporateVehiclesQuotesRequestData['site_identifier'] = 'gramcover-android-app';
        }

        if(isset($request->journeyType) && $request->journeyType == 'Z3JhbWNvdmVyLWFwcC1qb3VybmV5'){
            if (isset($request->tokenResp)) {
            $token = $request->tokenResp['dp_token'] ?? null;
            }
        } else {
            if (isset($request->tokenResp)) {
                $token = $request->tokenResp['remote_token'] ?? null;
            }
        }
        if(isset($request->whatsappConsent) && $request->whatsappConsent != null){
            $corporateVehiclesQuotesRequestData['whatsapp_consent'] = $request->whatsappConsent;
        }

        if (isset($request->addtionalData) && !empty($request->addtionalData) && is_array($request->addtionalData)) {
            $request->agentId = $request->addtionalData['user_id'] ?? "";
            $request->agentName = $request->addtionalData['name'] ?? "";
            $request->agentEmail = $request->addtionalData['email'] ?? "";
            $request->agentMobile = $request->addtionalData['mobile'] ?? "";
            if(isset($request->addtionalData['is_emp'])){
                $request->sellerType = ($request->addtionalData['is_emp'] == 1) ? 'E' : 'P' ;
            }
            $request->aadharNo = $request->addtionalData['aadhaar_no'] ?? "";
            $request->panNo = $request->addtionalData['pan_no'] ?? "";
        }
        
        if (isset($request->tokenResp)) {
            $request->unique_number = $request->tokenResp['unique_number'] ?? null;
        }

        if (isset($request->frontendTags)) {
            $corporateVehiclesQuotesRequestData['frontend_tags'] = $request->frontendTags; //json_encode($request->frontendTags);
        }

        if (isset($request->journeyWithoutRegno)) {
            $corporateVehiclesQuotesRequestData['journey_without_regno'] = $request->journeyWithoutRegno;//json_encode($request->journeyWithoutRegNo);
        }
        if(isset($request->vehicleInvoiceDate))
        {
            if($request->vehicleInvoiceDate != 'NULL' || !empty($request->vehicleInvoiceDate))
            {
                $corporateVehiclesQuotesRequestData['vehicle_invoice_date'] = $request->vehicleInvoiceDate;
            }
           
        }

        if(isset($request->frontendHandling) && !empty($request->frontendHandling)){
            ProposalExtraFields::updateOrCreate(['enquiry_id' => $enquiryId],['frontend_handling' => $request->frontendHandling]);
        }

        if (!empty($corporateVehiclesQuotesRequestData)) {
            //\Illuminate\Support\Facades\Log::info('corosrar', $corporateVehiclesQuotesRequestData);
            $corporateVehiclesQuotesRequestId = CorporateVehiclesQuotesRequest::updateOrCreate(['user_product_journey_id' => $enquiryId], $corporateVehiclesQuotesRequestData);
        }
        if ($request->stage == '1' && in_array($request->sellerType, ['E', 'P', 'U', 'Partner','misp'])) {
            if(config('constants.motorConstant.SMS_FOLDER') == 'ace')
            {
                $ALLOWED_SELLER_TYPES = explode(',',config('ALLOWED_SELLER_TYPES'));
                if(!(in_array($request->sellerType,$ALLOWED_SELLER_TYPES)))
                {
                    return response()->json([
                        'status' => false,
                        'msg' => $request->sellerType.' - Invalid Seller...!'
                    ]);
                }
            }
            $other_details = [];
            if(isset($request['tokenResp']['oem_name']))
            {
                $other_details['oem_name'] = $request['tokenResp']['oem_name'];
            }
            CvAgentMapping::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId,
                    // 'seller_type'   => $request->sellerType,
                ],
                [
                    'seller_type'   => $request->sellerType ?? null,
                    'agent_id'      => $request->agentId ?? null,
                    'user_name'     => isset($request->userName) ? $request->userName : null,
                    'agent_name'    => $request->agentName ?? $request->userfirstName . ' ' . $request->userlastName,
                    'agent_mobile'  => $request->agentMobile ?? null,
                    'agent_email'   => $request->agentEmail ?? null,
                    'unique_number' => $request->unique_number ?? null,
                    'aadhar_no'     => $request->aadharNo ?? null,
                    'pan_no'        => $request->panNo ?? null,
                    'stage'         => "quote",
                    "category"      => $request->categoryName ?? null,
                    "relation_sbi" => $request->relationSbi ?? null,
                    "relation_tata_aig" => (isset($request['tokenResp']['relation_tata_aig']) ? ($request->tokenResp['relation_tata_aig'] ?? null) : ''),
                    "relation_united_india" => (isset($request['tokenResp']['relation_united_india']) ? ($request->tokenResp['relation_united_india'] ?? null) : ''),
                    "relation_shriram" => (isset($request['tokenResp']['relation_shriram']) ? ($request->tokenResp['relation_shriram'] ?? null) : ''),
                    'token'=>$token,
                    'branch_code'=> (isset($request['tokenResp']['branch_code']) ? ($request->tokenResp['branch_code'] ?? null) : ''),
                    /* 
                      It is Overwriting As Null/Blank if User Id Present 
                    'user_id'=> (isset($request['tokenResp']['user_id']) ? ($request->tokenResp['user_id'] ?? null) : ''), 
                    */
                    'pos_key_account_manager' => $request['tokenResp']['pos_key_account_manager'] ?? null,
                    "agent_business_type" => $request['tokenResp']['business_type'] ?? null,
                    "agent_business_code" => $request['tokenResp']['business_code'] ?? null,
                    'branch_name'=> (isset($request['tokenResp']['branch_name']) ? ($request->tokenResp['branch_name'] ?? null) : ''),
                    'channel_id'=> (isset($request['tokenResp']['channel_id']) ? ($request->tokenResp['channel_id'] ?? null) : ''),
                    'channel_name'=> (isset($request['tokenResp']['channel_name']) ? ($request->tokenResp['channel_name'] ?? null) : ''),
                    'region_name'=> (isset($request['tokenResp']['region_name']) ? ($request->tokenResp['region_name'] ?? null) : ''),
                    'region_id'=> (isset($request['tokenResp']['region_id']) ? ($request->tokenResp['region_id'] ?? null) : ''),
                    'zone_id'=> (isset($request['tokenResp']['zone_id']) ? ($request->tokenResp['zone_id'] ?? null) : ''),
                    'zone_name'=> (isset($request['tokenResp']['zone_name']) ? ($request->tokenResp['zone_name'] ?? null) : ''),
                    'source_type'=> (isset($request['tokenResp']['source']) ? ($request->tokenResp['source'] ?? null) : ''),
                    'agent_pos_id' => $request['tokenResp']['agent_pos_id'] ?? null,
                    'employee_pos_id' => $request['tokenResp']['employee_pos_id'] ?? null,
                    'other_details'  => empty($other_details) ? NULL :  json_encode($other_details)
                ]
            );
        }

        /* In Seller Type U Agent Id is User Id */

        if (isset($request['tokenResp']['seller_type']) && $request['tokenResp']['seller_type'] === "U") {
            CvAgentMapping::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId,
                ],
                [
                    'user_id' => $request['tokenResp']['agent_id'] ?? $request['tokenResp']['user_id'],
                ]
            );
        }

        /* MISP relation for Oriental */
        if (isset($request['tokenResp']['seller_type']) && $request['tokenResp']['seller_type'] === "misp" && !empty($request['tokenResp']['relation_oriental'])) {
            CvAgentMapping::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId,
                ],
                [
                    'relation_oriental' => $request['tokenResp']['relation_oriental'] ?? '',
                ]
            );
        } 

        /* Save Registration Number in Corporate Request Renewal Case #16790 */

        if (!empty($request['tokenResp']['vehicle_registration_number'])) {
            CorporateVehiclesQuotesRequest::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId
                ],
                [
                    'vehicle_registration_no' => $request['tokenResp']['vehicle_registration_number'] ?? ''
                ]
            );
        }

        if (($request->stage == '1' && in_array($request->sellerType, ['E', 'P', 'U', 'Partner']) ) && config('constants.motorConstant.IS_GRAMCOVER_INSURANCE') == 'Y'){
            if(isset($request->tokenResp['remote_user_id']) && $request->tokenResp['remote_user_id'] != null)
            {
                CvAgentMapping::updateOrCreate(
                    [
                        'user_product_journey_id' => $enquiryId,
                        'agent_id'   => $request->tokenResp['remote_user_id'],
                        'source' => 'gramcover-app-journey'
                ],
    
                     [
                        'user_product_journey_id' => $enquiryId,
                         'seller_type'   => $request->sellerType,
                         'agent_id'      => $request->tokenResp['remote_user_id'],
                         'user_name'     => isset($request->userName) ? $request->userName : null,
                         'agent_name'    => $request->agentName ?? $request->userfirstName . ' ' . $request->userlastName,
                         'agent_mobile'  => $request->agentMobile,
                         'agent_email'   => $request->agentEmail,
                         'unique_number' => $request->unique_number,
                         'aadhar_no'     => $request->aadharNo,
                         'pan_no'        => $request->panNo,
                         'stage'         => "quote",
                         "category"      => $request->categoryName,
                         "relation_sbi"  => $request->relationSbi,
                         "relation_tata_aig" => (isset($request['tokenResp']['relation_tata_aig']) ? ($request->tokenResp['relation_tata_aig'] ?? null) : ''),
                         "relation_united_india" => (isset($request['tokenResp']['relation_united_india']) ? ($request->tokenResp['relation_united_india'] ?? null) : ''),
                         'source'       => "gramcover-app-journey",
                         'token'        => $token,
                     ]
                 ); 
            }else{
                return response()->json([
                    'status' => false,
                    'msg' => 'Remote user id is Empty , Please Login Again',
                    'devStatus' => 'Agent Entry does not exist'
                ]);
            }   
         }
        
        try{
            $agentMapping = new AgentMappingController($enquiryId);
            $agentMapping->mapAgent($request);
        }catch(\Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage()
            ]);
        }
        
        if ($corporateVehiclesQuotesRequestId) {
            $QuoteLogData['quotes_request_id'] = $corporateVehiclesQuotesRequestId->quotes_request_id;
            $QuoteLogData['quote_data'] = json_encode($quoteDataJson);
            QuoteLog::updateOrCreate(['user_product_journey_id' => $enquiryId], $QuoteLogData);
        }
        SelectedAddons::updateOrCreate(['user_product_journey_id' => $enquiryId]);

        $agentJourney = config('constants.agentJourney') ?? null;

        if(isset($agentJourney) && $agentJourney == 'Y')
        {
            $agentDetail = CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();

            if(!$agentDetail)
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Token Expired , Please Login Again',
                    'devStatus' => 'Agent Entry does not exist'
                ]);
            }
        }

        if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
        {
            $user_product_journey = UserProductJourney::find($enquiryId);
            $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;
            $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;
            $agent_details = $user_product_journey->agent_details;

            if (in_array($request->stage, [1, 2, 4, 11]))
            {
                if ( ! isset($lsq_journey_id_mapping->lead_id))
                {
                    $lead = createLsqLead($enquiryId);

                    if ($request->stage == 1 && $lead['status'])
                    {
                        if ( ! $agent_details || ($agent_details && ( ! isset($agent_details[count($agent_details) - 1]) || (isset($agent_details[count($agent_details) - 1]) && $agent_details[count($agent_details) - 1]->agent_name != 'driver_app'))))
                        {
                            createLsqActivity($enquiryId, 'lead');
                        }
                    }                    
                }

                if ($request->stage == 2)
                {
                    if ($lsq_journey_id_mapping && is_null($lsq_journey_id_mapping->opportunity_id) && ! is_null($request->vehicleRegistrationNo) && $request->vehicleRegistrationNo != 'NULL')
                    {
                        if ($agent_details && isset($agent_details[count($agent_details) - 1]) && in_array($agent_details[count($agent_details) - 1]->agent_name, ['driver_app', 'embedded_admin', 'embedded_scrub']))
                        {
                            $opportunity = createLsqOpportunity($enquiryId, 'RC Submitted', [
                                'rc_number' => $request->vehicleRegistrationNo
                            ]);

                            if ($opportunity['status'])
                            {
                                createLsqActivity($enquiryId, NULL, 'RC Submitted');
                            }
                        }
                    }
                }

                if ($request->stage == 4)
                {
                    if ($lsq_journey_id_mapping && is_null($lsq_journey_id_mapping->opportunity_id))
                    {
                        createLsqActivity($enquiryId, 'lead', 'MMV Form Submitted');
                    }
                    else
                    {
                        updateLsqOpportunity($enquiryId, 'MMV Form Submitted');
                        createLsqActivity($enquiryId, NULL, 'MMV Form Submitted');
                    }
                }
            }

            if ($request->stage == 9 && $lsq_journey_id_mapping && $lsq_journey_id_mapping->is_quote_page_visited == 1)
            {
                if ($lsq_journey_id_mapping->is_rc_updated == 1)
                {
                    LsqJourneyIdMapping::where('enquiry_id', $enquiryId)
                        ->update([
                            'is_rc_updated' => 0
                        ]);
                }
                else
                {
                    if ($lsq_journey_id_mapping && ! is_null($lsq_journey_id_mapping->opportunity_id))
                    {
                        updateLsqOpportunity($enquiryId);
                        createLsqActivity($enquiryId);
                    }
                    else
                    {
                        createLsqActivity($enquiryId, 'lead');
                    }
                }
            }

            if ($request->stage == 8 && $lsq_journey_id_mapping && $lsq_journey_id_mapping->is_quote_page_visited == 1 && $corporate_vehicles_quote_request->business_type == 'newbusiness')
            {
                if ($lsq_journey_id_mapping && ! is_null($lsq_journey_id_mapping->opportunity_id))
                {
                    updateLsqOpportunity($enquiryId);
                    createLsqActivity($enquiryId);
                }
                else
                {
                    createLsqActivity($enquiryId, 'lead');
                }
            }

            if ($request->stage == 11)
            {
                $user_product_journey = UserProductJourney::find($enquiryId);
                $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;
                $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;

                if ($lsq_journey_id_mapping && isset($lsq_journey_id_mapping->is_duplicate) && $lsq_journey_id_mapping->is_duplicate == 0 && isset($request->lsq_stage) && $request->lsq_stage == 'Quote Seen')
                {
                    if ($lsq_journey_id_mapping && is_null($lsq_journey_id_mapping->opportunity_id))
                    {
                        if ( ! is_null($corporate_vehicles_quote_request->vehicle_registration_no) || $corporate_vehicles_quote_request->business_type == 'newbusiness')
                        {
                            $opportunity = createLsqOpportunity($enquiryId, NULL, [
                                'rc_number' => $corporate_vehicles_quote_request->vehicle_registration_no
                            ]);

                            if ($opportunity['status'])
                            {
                                createLsqActivity($enquiryId);
                            }                            
                        }
                        else
                        {
                            createLsqActivity($enquiryId, 'lead');
                        }
                    }
                    else
                    {
                        updateLsqOpportunity($enquiryId);
                        createLsqActivity($enquiryId);
                    }
                }
            }
        }

        if(isset($request->token) && $request->token != NULL)
        {
            $agentDetail = CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();
            if(!$agentDetail)
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Token Data Missing'
                ]);
            }           
        }
        // if(isset($request->clearStartDate) && $request->clearStartDate) {
        //     UserProposal::where('user_product_journey_id', $enquiryId)->update([
        //         'policy_start_date' => null,
        //         'policy_end_date' => null
        //     ]);
        // }
        
        if ($request->stage == 11)
        {
            if(!isset($request->preventKafkaPush))
            {
                if (Schema::hasTable('kafka_data_push_logs')) 
                {
                    $kafka_data = \App\Models\KafkaDataPushLogs::where('user_product_journey_id', $enquiryId)->where('stage','quote')->first();
                    $is_renewal_case = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->select('is_renewal')->first();
                    // Don't push renewal data at this stage - 23-12-2022
                    if(empty($kafka_data) && ($is_renewal_case?->is_renewal != 'Y'))
                    {
                        // This event will be triggered when user visits on quote page 1st time.
                        event(new \App\Events\LandedOnQuotePage($enquiryId));
                    }
                }
            } else if (config('constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED') == 'Y') {
                event(new \App\Events\LandedOnQuotePage($enquiryId));
            }
        }

        if (!empty($request->vt) && $request->stage == 1 && !in_array($request->section, ['car', 'bike']) && config('constants.motorConstant.SMS_FOLDER') == 'hero') {
            $default_id = match ($request->vt) {
                'pcv' => 6,
                'gcv' => 9,
                'miscd' => 17,
                default => null,
            };
            if (empty($default_id)) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Journey mismatch. Please check the URL and try again.'
                ]);
            } else {
                UserProductJourney::find($enquiryId)->update([
                    'product_sub_type_id' => $default_id
                ]);
            }
        }

        event(new \App\Events\PushDashboardData($enquiryId));

        return response()->json([
            'status' => true,
            'msg' => 'User qoute saved Successfully..!'
        ], 200);
    }

    public function getUserRequestedData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
 
        if(config('enquiry_id_encryption') == 'Y')
        {
            $enquiryIdController = new EnquiryIdController();
            $isEnquiryIdValid = $enquiryIdController->isEnquiryIdValid($request);
            $response = json_decode(json_encode($isEnquiryIdValid), true);
            $isValid = $response['original']['status'];
            if($isValid != true){
                return response()->json([
                    'status' => false,
                    'msg' => 'Enquiry ID is not valid.',
                    'overrideMsg' => 'Enquiry ID is not valid.'
                ]);
            }
        }   
       

        $userRequestedData = UserProductJourney::with([
            'quote_log',
            'quote_log.master_policy' => function ($query) {
                $query->select('policy_id', 'product_sub_type_id', 'insurance_company_id', 'premium_type_id', 'pos_flag', 'zero_dep', 'default_discount', 'is_premium_online', 'is_proposal_online', 'is_payment_online','good_driver_discount');
            },
            'addons',
            'sub_product.parent',
            'corporate_vehicles_quote_request',
            'user_proposal',
            'agent_details' => function ($ad_query) {
                $ad_query->select('id', 'agent_name', 'user_name', 'user_product_journey_id', 'user_proposal_id', 'stage', 'ic_id', 'ic_name', 'seller_type', 'agent_id', 'created_at', 'updated_at', 'category', 'relation_sbi', 'unique_number', 'token', 'source')->latest()->first();
            },
            'journey_stage',
            'additional_details',
            'proposal_extra_fields'
        ])
            ->where('user_product_journey_id', customDecrypt($request->input('enquiryId')))
            ->first();

        $user_requestd_data = $userRequestedData->toArray();
        $good_driver_discount = empty($user_requestd_data['quote_log']['master_policy']['good_driver_discount']) ? 'No' : $user_requestd_data['quote_log']['master_policy']['good_driver_discount'];
        $user_requestd_data['is_non_breakin_inspection'] = $good_driver_discount == 'Yes' ? true : false;

        if(isset($user_requestd_data['additional_details']['additional_data'])){
            $user_requestd_data['additional_details']['additional_data'] = json_decode($user_requestd_data['additional_details']['additional_data']); 
        }

        if(config('enquiry_id_encryption') == 'Y')
        {
            $user_requestd_data['trace_id'] = getDecryptedEnquiryId($user_requestd_data['journey_id']);
        }
        $user_requestd_data['cvBreakinDetails'] = NULL;
        //        if (isset($user_requestd_data['user_proposal']['gender']) && isset($user_requestd_data['user_proposal']['gender_name'])) 
        //        {
        //            if(strtolower($user_requestd_data['user_proposal']['gender']) == "male" || strtolower($user_requestd_data['user_proposal']['gender']) == "m")
        //            {
        //                $user_requestd_data['user_proposal']['gender_name'] = "Male";
        //            }
        //            else
        //            {
        //                $user_requestd_data['user_proposal']['gender_name'] = "Female";
        //            }
        //        }
        
        
        if(isset($user_requestd_data['user_proposal']['additional_details']))
        {
            $additional_details = json_decode($user_requestd_data['user_proposal']['additional_details'],TRUE);
        }        
        if(isset($additional_details['owner']['genderName']))
        {
            $company_alias = $user_requestd_data['quote_log']['premium_json']['company_alias'] ?? NULL;
            $genderName = $additional_details['owner']['genderName'];
            $gender = Gender::where('company_alias', $company_alias)
                                    ->where('gender', $genderName)
                                    ->pluck('gender_code')
                                    ->first();
            $additional_details['owner']['gender'] = $gender;
            $user_requestd_data['user_proposal']['additional_details'] = json_encode($additional_details);
        }
        
        if ( ! is_null($userRequestedData->user_proposal) && ! is_null($userRequestedData->user_proposal->breakin_status))
        {
            $user_requestd_data['cvBreakinDetails']['breakinGenerationDate'] = $userRequestedData->user_proposal->breakin_status->created_at->format('Y-m-d H:i:s');
            $payment_end_date = $userRequestedData->user_proposal->breakin_status->payment_end_date;
            if(strtotime($payment_end_date) === false || Carbon::hasRelativeKeywords($payment_end_date)) {
                $user_requestd_data['cvBreakinDetails']['breakinExpiryDate'] = null;
            } else {
                $user_requestd_data['cvBreakinDetails']['breakinExpiryDate'] = Carbon::parse($payment_end_date)->format('Y-m-d H:i:s');
            }
            if(!empty($userRequestedData->user_proposal->breakin_status['ic_breakin_url'])){
                $user_requestd_data['cvBreakinDetails']['icBreakinUrl'] = base64_encode($userRequestedData->user_proposal->breakin_status['ic_breakin_url']);
            }
        }

        if ($userRequestedData->user_proposal && $userRequestedData->user_proposal->updated_at) {
            $user_requestd_data['lastProposalModifiedTime']=strtotime($userRequestedData->user_proposal->updated_at);

            if (config('MULTIPLE_JOURNEY_DISABLE') == 'Y') {
                $proposal=UserProposal::where('user_product_journey_id', $userRequestedData->user_proposal->user_product_journey_id)
                ->first();
                $proposal->update(['updated_at' => now()],['updated_at']);
                $user_requestd_data['lastProposalModifiedTime']=strtotime($proposal->updated_at);
            }
            
        }

        $user_requestd_data['discounts'] = [];
        $discount = AgentDiscountController::getDiscounts($request);
        if ($discount['status'] ?? false) {
            $user_requestd_data['discounts'] = $discount['discounts'];
        }

        if (($user_requestd_data['corporate_vehicles_quote_request']['is_renewal'] ?? 'N') == 'Y') {
            $applicable_ncb = RenewalController::calculateNcb($user_requestd_data['corporate_vehicles_quote_request']['previous_ncb']);
            if ($applicable_ncb['status'] ?? false) {
                $user_requestd_data['old_journey_data'] = [
                    'old_ncb' => $applicable_ncb['ncb']
                ];
            }
        }

        if (config('constants.brokerConstant.ENABLE_RENEWAL_CONFIG') == 'Y') {
            if (($user_requestd_data['corporate_vehicles_quote_request']['is_renewal'] ?? 'N') == 'Y') {
                RenewalController::renewalAttributes($user_requestd_data);
            }
        } else {
            $user_requestd_data['is_ncb_editable'] = true;

            $old_lead_source = null;
            if (!empty($user_requestd_data['old_journey_id'])) {
                $old_lead_source = UserProductJourney::where('user_product_journey_id', $user_requestd_data['old_journey_id'])
                ->pluck('lead_source')
                ->first();
            }
            //ncb selection will be editable, in case of renewal uploads
            if ($old_lead_source == 'RENEWAL_DATA_UPLOAD') {
                $user_requestd_data['is_renewal_upload'] = config('IDV_EDITABLE_FOR_UPLOAD_DATA') == 'Y' ? false : true;
                $user_requestd_data['is_ncb_editable'] = true;
            } elseif (($user_requestd_data['corporate_vehicles_quote_request']['is_renewal'] ?? 'N') == 'Y') {
                $user_requestd_data['is_ncb_editable'] = false;
            }   
        }

        $additional_details = $user_requestd_data['user_proposal']['additional_details'] ?? [];
        if (!is_array($additional_details)) {
            $additional_details = json_decode($additional_details, true);
        }
        if (
            !empty($user_requestd_data['quote_log']['premium_json']['company_alias']) &&
            !empty($user_requestd_data['corporate_vehicles_quote_request']['vehicle_owner_type']) &&
            !empty($additional_details['nominee']) &&
            count($additional_details['nominee']) <= 2 &&
            strtolower($additional_details['nominee']['compulsory personal accident'] ?? '') == 'no'
        ) {
            $section = [
                1 => 'car',
                2 => 'bike'
            ];
            $field = ProposalFields::where([
                'company_alias' => $user_requestd_data['quote_log']['premium_json']['company_alias'],
                'section' => $section[$user_requestd_data['product_sub_type_id']] ?? 'cv',
                'owner_type' => $user_requestd_data['corporate_vehicles_quote_request']['vehicle_owner_type']
            ])
            ->pluck('fields')
            ->first();
            if (!empty($field)) {
                $field = json_decode($field, true);
                $field = $field['fields'] ?? $field;
                if (array_search('cpaOptIn', $field) === false) {
                    unset($additional_details['nominee']);
                    $user_requestd_data['user_proposal']['additional_details'] = json_encode($additional_details);
                }
            }
        }
        
        //For inspection waiver case, frontend has asked us to pass policy_start_date and end_date as null.
        if (
            !empty($user_requestd_data['user_proposal']['policy_start_date']) &&
            ($user_requestd_data['quote_log']['premium_json']['isInspectionWaivedOff'] ?? false) &&
            !empty($user_requestd_data['quote_log']['premium_json']['waiverExpiry']) &&
            date('Y-m-d') > date('Y-m-d', strtotime($user_requestd_data['quote_log']['premium_json']['waiverExpiry']))
        ) {
            $user_requestd_data['user_proposal']['policy_start_date'] = null;
            $user_requestd_data['user_proposal']['policy_end_date'] = null;
        }
        
        if(!empty($user_requestd_data['user_proposal']['tp_end_date']) && empty($user_requestd_data['user_proposal']['tp_start_date']) && false )//user will select the start date
        {
            $is_od = $user_requestd_data['corporate_vehicles_quote_request']['policy_type'] == 'own_damage';
            $is_nb = $user_requestd_data['corporate_vehicles_quote_request']['business_type'] == 'newbusiness';
            $remove_age = '-1 year +1 day';
            if($user_requestd_data['product_sub_type_id'] == 1 && ($is_od || $is_nb))
            {
                $remove_age = '-3 year +1 day';
            }
            else if($user_requestd_data['product_sub_type_id'] == 2 && ($is_od || $is_nb))
            {
                $remove_age = '-5 year +1 day';
            }
            $premium_type_id = $user_requestd_data['quote_log']['master_policy']['premium_type_id'] ?? NULL;
            if(!empty($premium_type_id))
            {
                if(in_array($premium_type_id,[5,9]))
                {
                    $remove_age = '-3 month +1 days';
                }
                else if(in_array($premium_type_id,[8,10]))
                {
                    $remove_age = '-6 month +1 days';
                }
            }
            $previous_tp_end_date = $user_requestd_data['user_proposal']['tp_end_date'];
            $user_requestd_data['user_proposal']['tp_start_date'] = $previous_tp_start_date = date('d-m-Y', strtotime($remove_age, strtotime($previous_tp_end_date)));
            UserProposal::where('user_product_journey_id', $user_requestd_data['user_proposal']['user_product_journey_id'])->update([
                'tp_start_date' => $previous_tp_start_date
            ]);
        }
        list($status, $msg, $data) = $userRequestedData
            ? [true, 'success', camelCase($user_requestd_data)]
            : [false, 'No data found', null];
        // as per new requirement passing as empty # 34881
        $data['Source_IP'] = $request->ip();
        if(!empty($data['proposalExtraFields']['frontendHandling'])){
            $data['proposalExtraFields']['frontendHandling'] = json_decode($data['proposalExtraFields']['frontendHandling']);
        }
        return response()->json([
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    public function updateQuoteRequestData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $data = [];
        if (isset($request->rtoNumber) && $request->rtoNumber != '') {
            $rto_data = DB::table('master_rto')
                ->where('rto_number', $request->rtoNumber)
                ->first();
            if (empty($rto_data)) {
                return response()->json(
                    [
                        'status' => false,
                        'msg' => $request->rtoNumber . ' Invalid RTO Number'
                    ]
                );
            }
        }
        if(!empty($request->manufactureYear) && !empty($request->vehicleRegisterDate) && $request->vehicleRegisterDate != 'NULL')
        {
            $disable_month_validation = !(config('DISABLE_MANUFACTURE_MONTH_VALIDATION') == 'Y');
            $disable_year_validation  = !(config('DISABLE_MANUFACTURE_YEAR_VALIDATION') == 'Y');
            [$manufactureYear_month,$manufactureYear_year] = explode('-',$request->manufactureYear);
            [$vehicleRegisterDate_date,$vehicleRegisterDate_month,$vehicleRegisterDate_year] = explode('-',$request->vehicleRegisterDate);
            if($manufactureYear_year > $vehicleRegisterDate_year  && $disable_year_validation)
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Manufacture Year Should be less than or equal to Vehicle Registration Year'
                ]);
            }
            else if($manufactureYear_year == $vehicleRegisterDate_year)
            {
                if($manufactureYear_month > $vehicleRegisterDate_month && $disable_month_validation)
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Manufacture Month Should be less than or equal to Vehicle Registration Month'
                    ]);
                }
            }
        }
        $enquiryId = customDecrypt($request->enquiryId);
        if(isset($request->previousPolicyTypeIdentifierCode, $request->policyType) && in_array($request->previousPolicyTypeIdentifierCode, ['13', '15']) && $request->policyType == 'own_damage')
        {
            $UserProposal = UserProposal::where('user_product_journey_id', $enquiryId)->select('tp_end_date')->first();

            if(!empty($UserProposal->tp_end_date))
            {
                if($request->previousPolicyTypeIdentifierCode = '13')
                {
                    UserProposal::where('user_product_journey_id', $enquiryId)->update([
                        'tp_start_date' => date('d-m-Y', strtotime('-3 year +1 day', strtotime($UserProposal->tp_end_date)))
                    ]);
                }
                else if($request->previousPolicyTypeIdentifierCode = '15')
                {
                    UserProposal::where('user_product_journey_id', $enquiryId)->update([
                        'tp_start_date' => date('d-m-Y', strtotime('-5 year +1 day', strtotime($UserProposal->tp_end_date)))
                    ]);
                }
            }
        }
        if (isset($request->version)) {
            $data['version_id'] = $request->version;
            $data['version_name'] = $request->versionName;
        }
        if (isset($request->manufactureYear)) {
            $data['manufacture_year'] = $request->manufactureYear;
        }
        if (isset($request->fuelType)) {
            $data['fuel_type'] = $request->fuelType;
        }
        if (isset($request->editChassisIdv)) {
            $data['edit_chassis_idv'] = $request->editChassisIdv;
        }
        if (isset($request->isChassisIdvChanged)) {
            $data['is_chassis_idv_changed'] = $request->isChassisIdvChanged;
        }
        if (isset($request->editBodyIdv)) {
            $data['edit_body_idv'] = $request->editBodyIdv;
        }
        if (isset($request->isBodyIdvChanged)) {
            $data['is_body_idv_changed'] = $request->isBodyIdvChanged;
        }
        if (isset($request->vehicleIdv)) {
            $data['is_idv_changed'] = $request->isIdvChanged; //'Y';
            $data['edit_idv'] = $request->vehicleIdv;
            $data['idv_changed_type'] = $request->idvChangedType;
        }
        if (isset($request->vehicleRegisterDate)) {
            $data['vehicle_register_date'] = $request->vehicleRegisterDate;
        }
        if (isset($request->manufactureYear)) {
            $data['manufacture_year'] = $request->manufactureYear;
        }
        if (isset($request->isClaim)) {
            $data['is_claim'] = $request->isClaim;
        }
        if (isset($request->previousNcb)) {
            $data['previous_ncb'] = $request->previousNcb;
        }
        if (isset($request->applicableNcb)) {
            $data['applicable_ncb'] = $request->applicableNcb;
        }
        if (isset($request->policyExpiryDate)) {
            $data['previous_policy_expiry_date'] = $request->policyExpiryDate;
        }
        if (isset($request->previousPolicyType)) {
            $data['previous_policy_type'] = $request->previousPolicyType;
        }
        if (isset($request->previousInsurer)) {
            $data['previous_insurer'] = $request->previousInsurer;
            $data['previous_insurer_code'] = $request->previousInsurerCode;
        }
        if (isset($request->vehicleElectricAccessories)) {
            $data['electrical_acessories_value'] = $request->vehicleElectricAccessories;
        }
        if (isset($request->vehicleNonElectricAccessories)) {
            $data['nonelectrical_acessories_value'] = $request->vehicleNonElectricAccessories;
        }
        if (isset($request->vehicleAntiTheft)) {
            $data['anti_theft_device'] = $request->vehicleAntiTheft;
        }
        if (isset($request->isOdDiscountApplicable)) {
            $data['is_od_discount_applicable'] = $request->isOdDiscountApplicable;
        }
        if (isset($request->vehicleOdDiscount)) {
            $data['edit_od_discount'] = $request->vehicleOdDiscount;
        }
        if (isset($request->changeDefaultDiscount)) {
            $data['change_default_discount'] = $request->changeDefaultDiscount;
        }
        if (isset($request->externalBiFuelKit)) {
            $data['bifuel_kit_value'] = $request->externalBiFuelKit;
        }
        if (isset($request->voluntarydeductableAmount)) {
            $data['voluntary_excess_value'] = $request->voluntarydeductableAmount;
        }
        if (isset($request->OwnerDriverPaCover)) {
            $data['pa_cover_owner_driver'] = $request->OwnerDriverPaCover;
        }
        if (isset($request->UnnamedPassengerPaCover)) {
            $data['unnamed_person_cover_si'] = $request->UnnamedPassengerPaCover;
        }
        if (isset($request->antiTheft)) {
            $data['anti_theft_device'] = $request->antiTheft;
        }
        if (!empty($request->ownershipChanged)) {
            $data['ownership_changed'] = $request->ownershipChanged;
        }
        if (isset($request->previousNcb)) {
            $data['previous_ncb'] = $request->previousNcb;
        }
        if (isset($request->applicableNcb)) {
            $data['applicable_ncb'] = $request->applicableNcb;
        }
        if (isset($request->isNcbVerified)) {
            $data['is_ncb_verified'] = $request->isNcbVerified;
        }
        if (isset($request->zeroDepInLastPolicy)) {
            $data['zero_dep_in_last_policy'] = $request->zeroDepInLastPolicy;
        }
        if (isset($request->vehicleRegisterDate)) {
            $data['vehicle_register_date'] = $request->vehicleRegisterDate;
        }
        if (isset($request->isClaim)) {
            $data['is_claim'] = $request->isClaim;
        }
        if (isset($request->previousPolicyType)) {
            if(strtolower($request->previousPolicyType) == 'not sure') {
                //Gave priority to user preference over OnGrid/Fastlane/Adrila - #8415
                //Commenting below line because to stop the auto updation of updated_at column
                //It was giving issue in proposal integrity check
                // UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->update([ 
                UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->update([
                    'previous_insurance_company' => null,
                    'previous_policy_number' => null,
                ]);
            }
            $data['previous_policy_type'] = $request->previousPolicyType;
        }
        if (isset($request->policyExpiryDate)) {
            $data['previous_policy_expiry_date'] = $request->policyExpiryDate;
        }
        if (isset($request->policyType)) {
            $data['policy_type'] = $request->policyType;
        }
        if (isset($request->businessType)) {
            $data['business_type'] = $request->businessType;
        }
        if (isset($request->vehicleOwnerType)) {
            $data['vehicle_owner_type'] = $request->vehicleOwnerType;
        }

        if (isset($request->gcvCarrierType)) {
            $data['gcv_carrier_type'] = $request->gcvCarrierType;
        }

        if (isset($request->isPopupShown)) {
            $data['is_popup_shown'] = $request->isPopupShown; // Value should be 'Y' or 'N'
        }

        if (isset($request->rtoCode)) {
            $data['rto_code'] = $request->rtoCode; // Value should be 'Y' or 'N'
            $data['rto_city'] = $rto_data->rto_name; // Value should be 'Y' or 'N'
        }
        if (isset($request->prevShortTerm)) {
            $data['prev_short_term'] = $request->prevShortTerm;
        }
        if (isset($request->SelectedPolicyType)) {
            $data['selected_policy_type'] = $request->SelectedPolicyType;
        }
        if (isset($request->isClaimVerified)) {
            $data['is_claim_verified'] = $request->isClaimVerified;
        }
        if (isset($request->isIdvSelected)) {
            $data['is_idv_selected'] = $request->isIdvSelected;
        }
        if (isset($request->isToastShown)) {
            $data['is_toast_shown'] = $request->isToastShown;
        }
        // if (isset($request->infoToaster)) {
            $data['info_toaster'] = $request->infoToaster ?? false;
        // }
        if (isset($request->isRedirectionDone)) 
        {
            $data['is_redirection_done'] = $request->isRedirectionDone;
        }
        if (isset($request->isRenewalRedirection)) {
            $data['is_renewal_redirection'] = $request->isRenewalRedirection;
        }
        if (isset($request->prefillPolicyNumber)) {
            $data['prefill_policy_number'] = $request->prefillPolicyNumber;
        }
        if (isset($request->selectedGvw)) 
        {
            $data['selected_gvw'] = $request->selectedGvw ?? null;
        }
        if (isset($request->defaultGvw)) {
            $data['default_gvw'] = $request->defaultGvw ?? null;
        }
        if (isset($request->previousPolicyTypeIdentifier)) {
            $data['previous_policy_type_identifier'] = $request->previousPolicyTypeIdentifier;
        }
        if (isset($request->previousPolicyTypeIdentifierCode)) {
            $data['previous_policy_type_identifier_code'] = $request->previousPolicyTypeIdentifierCode;
        }
        if (isset($request->isMultiYearPolicy)) {
            $data['is_multi_year_policy'] = $request->isMultiYearPolicy;
        }        
        if (isset($request->isNcbConfirmed)) {
            $data['is_ncb_confirmed'] = $request->isNcbConfirmed;
        }
        if (isset($request->renewalRegistration)) {
            $data['renewal_registration'] = $request->renewalRegistration;
        }
        if (isset($request->sortBy)) {
            $data['sort_by'] = $request->sortBy;
        }
        if(isset($request->vehicleInvoiceDate))
        {
            if($request->vehicleInvoiceDate != 'NULL' || !empty($request->vehicleInvoiceDate))
            {
                $data['vehicle_invoice_date'] = $request->vehicleInvoiceDate;
            }
        }
        if(isset($request->frontendHandling) && !empty($request->frontendHandling)){
            ProposalExtraFields::updateOrCreate(['enquiry_id' => $enquiryId],['frontend_handling' => $request->frontendHandling]);
        }
        if (!empty($data)) {
            $corpRequestId = CorporateVehiclesQuotesRequest::where(["user_product_journey_id" => customDecrypt($request->enquiryId)])->update($data);

            $quote = QuoteLog::where(["user_product_journey_id" => customDecrypt($request->enquiryId)])->first();

            $quote_data = json_decode($quote->quote_data, TRUE);
            
            if (isset($request->manfactureId))
            {
                $quote_data['manfacture_id'] = $request->manfactureId;
                $quote_data['manfacture_name'] = $request->manfactureName;
            }

            if (isset($request->model))
            {
                $quote_data['model'] = $request->model;
                $quote_data['model_name'] = $request->modelName;
            }

            if(isset($request->chasisIdv)){
                $quote_data['chasis_idv'] = $request->chasisIdv;
            }
            if(isset($request->bodyIdv)){
                $quote_data['body_idv'] = $request->bodyIdv;
            }

            if (isset($request->policyType)) 
            {
                if ($request->policyType === 'own_damage') 
                {
                    $previous_selected_addons = SelectedAddons::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                    $additional_covers = ($previous_selected_addons->additional_covers == null ? [] : $previous_selected_addons->additional_covers);
                    $discounts = ($previous_selected_addons->discounts == null ? [] : $previous_selected_addons->discounts);
                    $additional_covers_changed = false;
                    $discounts_changed = false;
                    foreach ($additional_covers as  $additional_cover) 
                    {
                        if($additional_cover['name'] === 'LL paid driver')
                        {
                            array_splice($additional_covers, array_search('LL paid driver', array_column($additional_covers, 'name')), 1);
                            $additional_covers_changed = true;
                        }

                        if($additional_cover['name'] === 'PA cover for additional paid driver')
                        {
                            array_splice($additional_covers, array_search('PA cover for additional paid driver', array_column($additional_covers, 'name')), 1);
                            $additional_covers_changed = true;
                        }

                        if($additional_cover['name'] === 'Unnamed Passenger PA Cover')
                        {
                            array_splice($additional_covers, array_search('Unnamed Passenger PA Cover', array_column($additional_covers, 'name')), 1);
                            $additional_covers_changed = true;
                        }
                    }

                    foreach ($discounts as  $discount) 
                    {
                        if($discount['name'] === 'TPPD Cover')
                        {
                            array_splice($discounts, array_search('TPPD Cover', array_column($discounts, 'name')), 1);
                            $discounts_changed = true;
                        }

                    }

                    if($additional_covers_changed == true)
                    {
                        $previous_selected_addons->additional_covers = $additional_covers;
                    }

                    if($discounts_changed)
                    {
                        $previous_selected_addons->discounts = $discounts;
                    }

                    if($discounts_changed == true ||$additional_covers_changed == true)
                    {
                        $previous_selected_addons->save();
                    }
                }   
            }

            $quote->update(['quote_data' => json_encode($quote_data),'updated_at'=>now()]);

            return response()->json([
                'status' => true,
                'msg' => 'Data saved successfully..!'
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No data found to update.'
            ], 200);
        }
    }

    public function updateUserJourney(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'leadStageId' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        try {
            UserProductJourney::where('user_product_journey_id', customDecrypt($request->enquiryId))->update(['lead_stage_id' => $request->leadStageId]);
            return response()->json([
                'status' => true,
                'msg' => 'User Journey Updated Successfully...!'
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function createEnquiryId(Request $request)
    { 
        $validator = Validator::make($request->all(), [
            'firstName' => 'nullable',
            'lastName' => 'nullable',
            'mobileNo' => 'nullable|numeric|digits:10',
            'emailId' => ( ( config( 'email.dns.validation.enabled' ) == "Y" ) ? 'nullable|email:rfc,dns' : 'nullable|email:rfc' ),
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        if (isset($request->tokenResp)) {
            $request['tokenResp'] = gettype($request->tokenResp) != 'array' ? json_decode($request->tokenResp, true) : $request->tokenResp;
        }

        if(config('VALIDATE_MOBILE_TO_POS') == 'Y')
        {
            $validateMobileSellerType = [];

            if (!empty(config('VALIDATE_MOBILE_SELLER_TYPE'))) {
                $validateMobileSellerType = explode(',', config('VALIDATE_MOBILE_SELLER_TYPE'));
            }
            
            if(!empty($request->mobileNo) && !empty($request->tokenResp['mobile'])  && $request->tokenResp['mobile'] == $request->mobileNo && isset($request->tokenResp['seller_type']) && in_array($request->tokenResp['seller_type'], $validateMobileSellerType))
            {
                return response()->json([
                    'status' => false,
                    'msg' => "Cutomer mobile should be different from POS",
                ]);
            }
        }

        $allowedSellerType = [];

        if (!empty(config('ALLOWED_OTP_SELLER_TYPE'))) {
            $allowedSellerType = explode(',', config('ALLOWED_OTP_SELLER_TYPE'));
        }

        if ((in_array('b2c', $allowedSellerType) && empty($request->tokenResp))
            || (isset($request->tokenResp) && isset($request->tokenResp['seller_type']) && in_array($request->tokenResp['seller_type'], $allowedSellerType))
        ) {

            $mailController = new MailController;

            /* Send OTP */
            if ($request->isOtpRequired == "Y") {
                return $mailController->sendLeadOtp($request);
            }

            /* Verify Lead Otp */
            if($request->has('otp')){
                $isOtpVerified = $mailController->verifyLeadOtp($request);

                if (!$isOtpVerified) {
                    return response()->json([
                        "status" => false,
                        "message" => "OTP is not Verified.",
                    ]);
                }
            }
        }

        $userProductJourneyData = [
            'product_sub_type_id' => $request->productSubTypeId,
            'user_fname' => $request->firstName,
            'user_lname' => $request->lastName,
            'user_email' => $request->emailId,
            'user_mobile' => $request->mobileNo,
            'lead_source' => $request->source ?? NULL
        ];

        if (isset($request->userId) && $request->userId != '') {
            $userProductJourneyData['user_id'] = $request->userId;
        }

        $UserProductJourney = UserProductJourney::create($userProductJourneyData);
        JourneyStage::create([
            'user_product_journey_id'   => $UserProductJourney->user_product_journey_id,
            //'stage'                     => STAGE_NAMES['LEAD_GENERATION']
        ]);

        $user = \App\Models\Users::updateorCreate(
            ['mobile_no' => $request->mobileNo],
            [
                'mobile_no' => $request->mobileNo,
                'email' => $request->emailId,
                'first_name' => $request->firstName,
                'last_name' => $request->lastName,
            ]
        );

        if (config('constants.motorConstant.corporate_domain_validation_api') == "Y" && request()->emailId != null && request()->mobileNo != null) {
            $domain_validation = httpRequest('corporate_domain_validation_api', [
                "domain" => request()->emailId,
                "mobile_no" => request()->mobileNo,
                "section" => get_parent_code(request()->productSubTypeId)
            ]);
            if (isset($domain_validation['response']['status']) && $domain_validation['response']['status'] == 'true'){
                if(isset($domain_validation['response']['data']['domain_id']) && $domain_validation['response']['data']['domain_id'] != null ){
                    $corporate_email = $domain_validation['response']['data']['domain_id'];
                }
                $UserProductJourney->corporate_id =  $domain_validation['response']['data']['corporate_id'];
                $UserProductJourney->domain_id =  $domain_validation['response']['data']['domain_id'];
                $UserProductJourney->save();
            }
        }

        if (config('constants.motorConstant.IS_USER_ENABLED') == "Y" && $request->mobileNo != null && $request->userId == null) {
            if (isset($request->tokenResp) && $request->tokenResp['usertype'] == 'U')
            {
                $user_data['user_id'] = $request->tokenResp['seller_id'];
                $user->update(['user_id' => $user_data['user_id']]);
            }
            else
            {
                // $user_data = httpRequestNormal(config('constants.motorConstant.BROKER_USER_CREATION_API'), 'post', [
                //     'mobile_no' => $request->mobileNo,
                //     'email' => $request->emailId,
                //     'first_name' => $request->firstName,
                //     'last_name' => $request->lastName,
                // ])['response'];
                $user_creation_url = config('constants.motorConstant.BROKER_USER_CREATION_API');
                $user_creation_data = [
                    'first_name' => $request->firstName ?? "",
                    'last_name' => $request->lastName ?? "",
                    'mobile_no' => $request->mobileNo,
                    'email' => $request->emailId ?? ""                    
                ];
                if(config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true')
                {
                    $user_data = HTTP::withoutVerifying()->asForm()->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'),$user_creation_data)->json();
                } 
                else 
                {
                    $user_data = HTTP::withoutVerifying()->asForm()->withOptions([ 'proxy' => config('constants.http_proxy') ])->acceptJson()->post(config('constants.motorConstant.BROKER_USER_CREATION_API'),$user_creation_data)->json();
                }
                
                $insert_vahan_data = [
                    'mobile_no'         => $user_creation_data['mobile_no'],
                    'request'           => json_encode($user_creation_data),
                    'response'          => is_array($user_data) ? json_encode($user_data) : $user_data,
                    'url'               => $user_creation_url,
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s')
                ];            
                DB::table('user_creation_request_response')->insert($insert_vahan_data);   
                
                $error_msg = '';
                if($user_data['status'] == 'false')
                {
                    foreach ($user_data['data'] as $key => $value) {
                        $key = str_replace('_', ' ', $key);
                        $error_msg .= ucwords($key ." : ".$value."\n");
                    }

                    return response()->json([
                        'status' => false,
                        'msg' => $error_msg,
                    ]);

                }else
                {
                    $user->update(['user_id' => $user_data['user_id']]);
                }
            }
            if(config('constants.motorConstant.USER_CREATION_MAIL') == "Y"){
                if (config('constants.motorConstant.SMS_FOLDER') == 'abibl') {

                    $name = $request->firstName . ' ' . $request->lastName;
                    $email = is_array($request->email) ? $request->email[0] : $request->email;
                    $mailData = ['name' => $name, 'subject' => "Account Successfully Created"];

                    $html_body = (new \App\Mail\UserCreationMail($mailData))->render();

                    $input_data = [
                        "content" => [
                            "from" =>  [
                                "name" => config('mail.from.name'),
                                "email" => config('mail.from.address')
                            ],
                            "subject" => $mailData['subject'],
                            "html" => $html_body,
                        ],
                        "recipients" => [
                            [
                                "address" => $email
                            ]
                        ]
                    ];
                    httpRequest('abibl_email', $input_data);

                }elseif(config('constants.motorConstant.SMS_FOLDER') == 'sriyah'){
                    $mailData = [
                        'logo' => $request->logo ?? "",
                        'subject' => "Customer Login in nammacover.com"
                    ];
                    \Illuminate\Support\Facades\Mail::to($request->emailId)->send(new UserCreationMail($mailData));
                }
            }
            CvAgentMapping::updateorCreate(
                ['user_product_journey_id' => $UserProductJourney->user_product_journey_id],
                [
                    'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                    // 'seller_type' => 'U',
                    // 'agent_name' => $request->firstName,
                    'user_id' => $user_data['user_id'],
                    // 'agent_mobile' => $request->mobileNo,
                    // 'agent_email' => $request->emailId,
                    'stage'         => "quote"
                ]
            );
        }
        
        event(new \App\Events\PushDashboardData($UserProductJourney->user_product_journey_id));

        return response()->json([
            'status' => true,
            'msg' => 'Enquiry Id generated successfully...!',
            'data' => [
                'enquiryId' => $UserProductJourney->journey_id,
                'user_id' => $user_data['user_id'] ?? $user->user_id,
                'corporate_id' => $corporate_email ?? null
            ]
        ]);
    }

    public function saveAddonData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'addonData' => 'required'
        ]);   
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        foreach ($request->addonData as $key => $value) {
            $data[$key] = $value;
        }
  
        if (isset($request->frontend_tags)) {
            $data['frontend_tags'] = $request->frontend_tags;     
         }
        $enquiryId = customDecrypt($request->enquiryId);

        $addData = SelectedAddons::updateOrCreate(['user_product_journey_id' => $enquiryId], $data);

        if(isset($request->isDefaultCoverChanged) && $request->isDefaultCoverChanged == 'Y')
        {
            CorporateVehiclesQuotesRequest::where([
                "user_product_journey_id" => $enquiryId
            ])->update([
                'is_default_cover_changed' => 'Y'
            ]);
        }

        return response()->json([
            'status' => true,
            'msg' => 'Add-on saved successfully..!'
        ], 200);
    }

    public function previousInsurers(Request $request)
    {
        $seller_type  = '';
        if(isset($request->enquiryId))
        {
            $enquiryId = customDecrypt($request->enquiryId);
            $pos_data = CvAgentMapping::where('user_product_journey_id',$enquiryId)
                            ->whereIn('seller_type',['E','P'])
                            ->exists();
            if($pos_data)
            {
                $seller_type = 'B2B';
            }
            else{
                $seller_type = 'B2C';
            }

        }

        $exist = InsurerLogoPriorityList::where('seller_type', $seller_type)->exists();
        if($exist)
        {
            $data = MasterCompany::join('insurer_logo_priority_list as pl', 'pl.company_id', '=', 'master_company.company_id')->where('pl.seller_type', $seller_type)->orderBy('pl.priority', 'asc')
            ->select('master_company.company_id','master_company.company_name','master_company.company_alias','master_company.logo','master_company.company_name as previous_insurer')
            ->get()
            ->toArray();
            return response()->json([
            'status' => true,
            'msg' => "success",
            'data' => camelCase($data)
        ]);
        }
        else
        {
        return response()->json([
            'status' => true,
            'msg' => "success",
            'data' => camelCase(PreviousInsurer::all()->toArray())
        ]);
    }
    }

    public function getVoluntaryDiscounts(Request $request)
    {
        if(isset($request->productSubTypeId) && $request->productSubTypeId != '')
        {
            $parent_code = get_parent_code($request->productSubTypeId);
            if($parent_code == 'BIKE')
            {
                $data = VoluntaryDeductible::where('product_sub_type_code', $parent_code)
                ->select('discount_in_percent', 'max_amount', 'deductible_amount')
                ->get()
                ->toArray();

            }
            else
            {
                $data = VoluntaryDeductible::where('product_id', 1)
                ->select('discount_in_percent', 'max_amount', 'deductible_amount')
                ->get()
                ->toArray();
            }
        }
        else
        {
            $data = VoluntaryDeductible::where('product_id', 1)
            ->select('discount_in_percent', 'max_amount', 'deductible_amount')
            ->get()
            ->toArray();
        }

        return response()->json([
            'status' => true,
            'msg' => "success",
            'data' => camelCase($data)
        ]);
    }

    public function getIcPincode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pincode' => ['required'],
            'companyAlias' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if ((config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y' || config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_BIKE') == 'Y') && $request->companyAlias == 'hdfc_ergo')
        {
            $product_sub_type_id = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))->pluck('product_sub_type_id')->first();
            $parent_code = get_parent_code($product_sub_type_id);
            if(in_array($parent_code, ['PCV', 'GCV']))
            {
                $request->companyAlias = 'hdfc_ergo';
            }
            else
            {
                $request->companyAlias = 'hdfc_ergo_v2';
            }
        }
        if ($request->companyAlias == 'hdfc_ergo_v2') {
            $corporateData = CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
            if (!empty($corporateData) && ($corporateData->is_renewal ?? '') == 'Y' && ($corporateData->rollover_renewal ?? '') != 'Y') {
                $request->companyAlias == 'hdfc_ergo';
            }
        }
        try {
            switch ($request->companyAlias) {
                case 'icici_lombard':
                    $payload = IciciLombardPincodeMaster::select('il_state_id', 'il_citydistrict_id', 'il_pincode_locality', 'il_country_id')
                        ->where('num_pincode', $request->pincode)
                        ->first();

                    $data = [];

                    if (!empty($payload)) {

                        $city_payload = DB::table('icici_lombard_city_disctrict_master')
                        ->where('IL_STATE_CD', $payload->il_state_id)
                        ->where('IL_CITYDISTRICT_CD', $payload->il_citydistrict_id)
                        ->first();
                        $city_payload = keysToLower($city_payload);

                        if ($city_payload) {
                            $data['city'][0]['city_name'] = $city_payload->txt_citydistrict;
                            $data['city'][0]['city_id'] = $payload->il_citydistrict_id;
                            $data['state']['state_id'] = $payload->il_state_id;
                            $data['state']['state_name'] = $city_payload->gst_state;

                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);

                        }

                    }

                    /* if ($payload)
                    {
                        foreach ($payload as $key => $value)
                        {

                        $state_id = $value->state->il_state_id;
                        $state_name = $value->state->gst_state;
                        $data['city'][$key]['city_id'] = $value->il_citydistrict_id;
                        $data['city'][$key]['city_name'] = $value->il_pincode_locality;

                        }
                        $data['state']['state_id'] = $state_id;
                        $data['state']['state_name'] =$state_name;

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    } */
                    break;
                case 'hdfc_ergo':
                    //$payload = HdfcErgoMotorPincodeMaster::with('city:num_citydistrict_cd,txt_citydistrict as name', 'state')
                    //    ->where('num_pincode', $request->pincode)
                    //    ->get();
                    $payload = HdfcErgoMotorPincodeMaster::join('hdfc_ergo_motor_city_master AS city', 'hdfc_ergo_motor_pincode_master.num_citydistrict_cd', '=', 'city.num_citydistrict_cd')
                        ->leftJoin('hdfc_ergo_motor_state_master AS state', 'city.num_state_cd', '=', 'state.num_state_cd')
                        ->where('num_pincode', $request->pincode)
                        ->get();

                    $data = [];
                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $key => $pincode) {
                            $data['city'][$key]['city_id'] = $pincode->num_citydistrict_cd;
                            $data['city'][$key]['city_name'] = $pincode->txt_citydistrict;
                            $data['state']['state_id'] = $pincode->num_state_cd;
                            $data['state']['state_name'] = $pincode->txt_state;
                        }
                        $unique = collect($data['city'])->unique('city_name')->toArray();
                        $data['city'] = array_values($unique);
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;

                case 'hdfc_ergo_v2':
                    $data = [];
                    $useCommonPincode = config('constants.hdfc_ergo_v2.useCommonPincodeMaster');
                    if($useCommonPincode == 'Y' && Schema::hasTable('hdfc_ergo_v2_single_pincode_master')) {
                        $result = DB::table('hdfc_ergo_v2_single_pincode_master')->where('pincode', $request->pincode)->get();
                        if($result->isNotEmpty()) {
                            foreach ($result as $key => $pincode) {
                                $data['city'][$key]['city_id'] = $pincode->num_citydistrict_cd;
                                $data['city'][$key]['city_name'] = $pincode->txt_citydistrict;
                                $data['state']['state_id'] = $pincode->num_state_cd;
                                $data['state']['state_name'] = $pincode->txt_state;
                            }
                            $unique = collect($data['city'])->unique('city_name')->toArray();
                            $data['city'] = array_values($unique);
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        }
                    } else {
                        $payload = HdfcErgoV2MotorPincodeMaster::where('pincode', $request->pincode)->get();
                        if ($payload->isNotEmpty()) {
                            foreach ($payload as $key => $pincode) {
                                $data['city'][$key]['city_id'] = $pincode->city->city_id;
                                $data['city'][$key]['city_name'] = $pincode->city->city_name;
                                $data['state']['state_id'] = $pincode->state->value;
                                $data['state']['state_name'] = $pincode->state->name;
                            }
                            $unique = collect($data['city'])->unique('city_name')->toArray();
                            $data['city'] = array_values($unique);
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        }
                    }
                    break;

                case 'godigit':
                case 'acko':
                    $payload = GodigitPincodeStateCityMaster::select('state', 'city', 'statecode')
                        ->where('pincode', $request->pincode)
                        ->orderBy('state')
                        ->get();
                    $data = [];
                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $key => $pincode) {
                            $data['city'][0]['city_name'] = $pincode->city;
                            $data['city'][0]['city_id'] = $pincode->city;
                            $data['state']['state_id'] = $pincode->statecode;
                            $data['state']['state_name'] = $pincode->state;
                        }
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;
                case 'reliance':
                    $payload = ReliancePincodeStateCityMaster::select('city_or_village_id_pk as city_id', 'city_or_village_name as city_name', 'state_id_pk as state_id', 'state_name')
                        ->where('pincode', $request->pincode)
                        ->orderBy('city_or_village_name')
                        ->distinct()
                        ->get();
                        // ->first();

                    $data = [];
                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $key => $pincode) 
                        {
                            $data['city'][$key]['city_name'] = $pincode->city_name;
                            $data['city'][$key]['city_id'] = $pincode->city_id;
                            $data['state']['state_id'] = $pincode->state_id;
                            $data['state']['state_name'] = $pincode->state_name;
                        }
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;
                case 'shriram':
                    $payload = ShriramPinCityState::select('state as state_id', 'state_desc as state_name', 'city as city_name')
                        ->where('pin_code', $request->pincode)
                        ->first();
                    if (!empty($payload)) {
                        $data['city'][0]['city_name'] = $payload->city_name;
                        $data['city'][0]['city_id'] = $payload->city_name;
                        $data['state']['state_id'] = $payload->state_id;
                        $data['state']['state_name'] = $payload->state_name;
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;

                case 'liberty_videocon':
                    $payload = DB::table('liberty_videocon_state_master AS lvsm')
                        ->leftJoin('liberty_videocon_city_master AS lvcm', 'lvsm.num_state_cd', '=', 'lvcm.num_state_cd')
                        ->leftJoin('liberty_videocon_pincode_master AS lvpm', 'lvcm.city_code', '=', 'lvpm.city_id')
                        ->where('lvpm.PinCode', $request->pincode)
                        ->select('lvpm.state_id', 'lvsm.txt_state AS state_name', 'lvcm.city_name')
                        ->first();

                    if (!empty($payload)) {
                        $data['city'][0]['city_name'] = $payload->city_name;
                        $data['city'][0]['city_id'] = $payload->city_name;
                        $data['state']['state_id'] = $payload->state_id;
                        $data['state']['state_name'] = $payload->state_name;
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;

                case 'tata_aig':
                    $payload = TataAigPincodeMaster::where('num_pincode', $request->pincode)
                        ->get();
                    if ($payload->isNotEmpty()) {

                        foreach ($payload as $key => $pincode) {
                            $data['city'][$key]['city_name'] = $pincode->txt_pincode_locality;
                            $data['city'][$key]['city_id'] = $pincode->num_city_cd;
                            $data['state']['state_id'] = $pincode->num_state_cd;
                            $data['state']['state_name'] = $pincode->txt_state;
                        }
                        $unique = collect($data['city'])->unique('city_name')->toArray();
                        $data['city'] = array_values($unique);
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    // if (!empty($payload)) {
                    //     $data['city'][0]['city_name'] = $payload->txt_pincode_locality;
                    //     $data['city'][0]['city_id'] = $payload->num_city_cd;
                    //     $data['state']['state_id'] = $payload->num_state_cd;
                    //     $data['state']['state_name'] = $payload->txt_state;
                    //     return response()->json([
                    //         'status' => true,
                    //         'message' => "Found",
                    //         "data" => trim_array($data)
                    //     ]);
                    // }
                    break;


                case 'cholla_mandalam':
                    $payload = chollamandalamPincodeMaster::where('pincode', $request->pincode)
                        ->get();
                    $data = [];
                    if ($payload->isNotEmpty()) {

                        foreach ($payload as $key => $pincode) {
                            $data['city'][$key]['city_name'] = $pincode->pincode_locality;
                            $data['city'][$key]['city_id'] = $pincode->city_district_cd;
                            $data['state']['state_id'] = $pincode->state_cd;
                            $data['state']['state_name'] = $pincode->state_desc;
                        }
                        $unique = collect($data['city'])->unique('city_name')->toArray();
                        $data['city'] = array_values($unique);
                        /* $data['city'][0]['city_name'] = $payload->pincode_locality;
                        $data['city'][0]['city_id'] = $payload->city_district_cd;
                        $data['state']['state_id'] = $payload->state_cd;
                        $data['state']['state_name'] = $payload->state_desc; */
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;

                case 'future_generali':
                    $payload = DB::table('future_generali_pincode_master')
                        ->where('pincode', $request->pincode)
                        ->first();
                    if (!empty($payload)) {
                        $data['city'][0]['city_name'] = $payload->city;
                        $data['city'][0]['city_id'] = $payload->city;
                        $data['state']['state_id'] = $payload->statecode;
                        $data['state']['state_name'] = $payload->state;
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;

                case 'bharti_axa':
                    $payload = DB::table('bharti_axa_state_city_pincode_master')->where('Pincode', $request->pincode)->first();

                    if (!empty($payload)) {
                        $data['city'][0]['city_name'] = $payload->City_Bharti_Axa_Supership;
                        $data['city'][0]['city_id'] = $payload->City_Bharti_Axa_Supership;
                        $data['state']['state_id'] = $payload->State_Bharti_Axa_Supership;
                        $data['state']['state_name'] = $payload->State_Bharti_Axa_Supership;
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;
                    case 'kotak':
                    $payload = kotakPincodeMaster::where('NUM_PINCODE', $request->pincode)
                        ->get();
                    $state = GodigitPincodeStateCityMaster::where('pincode', $request->pincode)
                        ->select('state', 'city')->first();

                    if (!empty($payload) && !empty($state)) {
                        foreach ($payload as $key => $pincode) {
                            $data['city'][0]['city_name'] = $state->city;
                            $data['city'][0]['city_id'] = $pincode->NUM_CITYDISTRICT_CD;
                            $data['state']['state_id'] = $pincode->NUM_STATE_CD;
                            $data['state']['state_name'] = $state->state;
                        }
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }


                case 'iffco_tokio':
                    $payload = DB::table('iffco_pincode_master')
                        ->where('pincode', $request->pincode)
                        ->select('city_code', 'city_name', 'state_code', 'state_name')
                        ->first();
                    if (!empty($payload)) {
                            $data['city'][0]['city_name'] = $payload->city_name;
                            $data['city'][0]['city_id'] = $payload->city_code;
                            $data['state']['state_id'] = $payload->state_code;
                            $data['state']['state_name'] = $payload->state_name;
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                    } else {
                        $payload = DB::table('bajaj_allianz_motor_state_city_master as a')
                            ->leftJoin('royal_sundaram_state_mapping_with_bajaj_master as b', 'a.state', '=', 'b.bajaj_state_name')
                            ->leftJoin('iffco_tokio_state_master AS c', 'b.iffco_tokio_state_code', '=', 'c.state_code')
                            ->leftJoin('iffco_tokio_city_master AS d', 'b.iffco_tokio_state_code', '=', 'd.state_code')
                            ->where('a.pincode', $request->pincode)
                            ->select(DB::raw('DISTINCT d.rto_city_code AS city_code'), DB::raw('d.rto_city_name AS city_name'), 'c.*')
                            ->orderBy('city_code', 'ASC')
                            ->get();

                        if ($payload->isNotEmpty()) {
                            $data = [
                                'city' => [],
                                'state' => []
                            ];
                            foreach ($payload as $key => $pincode) {
                                $data['state']['state_id'] = $pincode->state_code;
                                $data['state']['state_name'] = $pincode->state_name;
                                $data['city'][] = [
                                    'city_name' => $pincode->city_name,
                                    'city_id' => $pincode->city_code
                                ];
                            }
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        }
                    }
                    break;
                case 'magma':
                    $payload = MagmaMotorPincodeMaster::with('city:num_citydistrict_cd,txt_citydistrict as name', 'state')
                        ->where('num_pincode', $request->pincode)
                        ->get();
                    $data = [];
                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $key => $pincode) {
                            $data['city'][$key]['city_id'] = $pincode->city->num_citydistrict_cd;
                            $data['city'][$key]['city_name'] = $pincode->city->name;
                            $data['state']['state_id'] = $pincode->state->num_state_cd;
                            $data['state']['state_name'] = $pincode->state->txt_state;
                        }
                        $unique = collect($data['city'])->unique('city_name')->toArray();
                        $data['city'] = array_values($unique);
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;
                case 'bajaj_allianz':
                    $payload = DB::table('bajaj_allianz_motor_state_city_master')
                        ->where('pincode', $request->pincode)
                        ->first();
                    if (!empty($payload)) {
                        $data['city'][0]['city_name'] = $payload->city;
                        $data['city'][0]['city_id'] = $payload->city;
                        $data['state']['state_id'] = $payload->state;
                        $data['state']['state_name'] = $payload->state;
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;

                case 'united_india':
                    $payload = DB::table('united_india_pincode_state_city_master')
                        ->where('NUM_PINCODE', $request->pincode)
                        ->first();
                    $payload = keysToLower($payload);
                    if (!empty($payload)) {
                        $data['city'][0]['city_name'] = $payload->txt_citydistrict;
                        $data['city'][0]['city_id'] = $payload->txt_citydistrict;
                        $data['state']['state_id'] = $payload->txt_state;
                        $data['state']['state_name'] = $payload->txt_state;
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;


                case 'universal_sompo':
                    $payload = DB::table('universal_sompo_pincode_master As pm')
                        ->join('universal_sompo_city_master As cm', 'pm.CityCode', '=', 'cm.num_citydistrict_cd')
                        ->join('universal_sompo_state_master As sm', 'sm.num_state_cd', '=', 'pm.StateCode')
                        ->where('Pincode', $request->pincode)->get();
                    $payload = keysToLower($payload);

                    $data = [];
                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $key => $pincode) {
                            $data['city'][$key]['city_name'] = $pincode->txt_citydistrict;
                            $data['city'][$key]['city_id'] = $pincode->num_citydistrict_cd;
                            $data['state']['state_id'] = $pincode->statecode;
                            $data['state']['state_name'] = $pincode->txt_state;
                        }
                        $unique = collect($data['city'])->unique('city_name')->toArray();
                        $data['city'] = array_values($unique);
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }

                    break;
                case 'raheja':
                    $payload = DB::table('raheja_motor_pincode_master as rjmpm')
                        ->where('rjmpm.pincode', $request->pincode)
                        ->first();
                    if (!empty($payload)) {
                        $data['city'][0]['city_name'] = $payload->city;
                        $data['city'][0]['city_id'] = $payload->city_id;
                        $data['state']['state_id'] = $payload->state_id;
                        $data['state']['state_name'] = $payload->state;
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }

                    break;
                case 'edelweiss':
                    $payload = DB::table('edelweiss_pincode_master')
                        ->where('Pincode', $request->pincode)
                        ->first();
                    $data = [];
                    if (!empty($payload)) {
                        $data['city'][0]['city_name'] = $payload->city;
                        $data['city'][0]['city_id'] = $payload->rg;
                        $data['state']['state_id'] = $payload->rg;
                        $data['state']['state_name'] = $payload->region;
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }

                    break;

                    case 'new_india':
                        $payload = DB::table('new_india_pincode_master')
                            ->where('pin_code', $request->pincode)
                            ->first();
                        if ($payload) {
                            $data['city'][0]['city_name'] = $payload->geo_area_name;
                            $data['city'][0]['city_id'] = $payload->geo_area_code;
                            $data['state']['state_id'] = $payload->geo_area_code_1;
                            $data['state']['state_name'] = $payload->geo_area_name_1;

                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        }
                        break;
                case 'oriental':
                        $payload = DB::table('oriental_state_city_pincode_master')
                            ->where('pincode', $request->pincode)
                            ->first();
                        if (!empty($payload)) {
                            $data['city'][0]['city_name'] = $payload->city;
                            $data['city'][0]['city_id'] = $payload->city_code;
                            $data['state']['state_id'] = $payload->state_code;
                            $data['state']['state_name'] = $payload->state;

                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        }
                        break;
                    case 'sbi':
                        $payload = DB::table('sbi_pincode_state_city_master')
                            ->where('PIN_CD', $request->pincode)
                            ->first();
                        $payload = keysToLower($payload);
                            if ($payload) {
                                $data['city'][0]['city_name'] = $payload->city_name;
                                $data['city'][0]['city_id'] = $payload->city_cd;
                                $data['state']['state_id'] = $payload->state_code;
                                $data['state']['state_name'] = $payload->state_name;
        
                                return response()->json([
                                    'status' => true,
                                    'message' => "Found",
                                    "data" => trim_array($data)
                                ]);
                            }
                        /* $payload = DB::table('sbi_pincode_state_city_master as smpm')
                        ->select('smsm.state_name','smsm.state_id','smcm.city_cd','smcm.city_nm')
                        ->join('sbi_motor_state_master as smsm', 'smpm.STATE_CD', '=', 'smsm.state_id')
                        ->join('sbi_motor_city_master as smcm', 'smpm.CITY_CD', '=', 'smcm.city_cd')
                        ->where('PIN_CD', $request->pincode)
                        ->get(); 
                        $data = [];
                        if ($payload->isNotEmpty()) {
                            foreach ($payload as $key => $pincode) {
                                $data['city'][$key]['city_name'] = $pincode->city_nm;
                                $data['city'][$key]['city_id'] = $pincode->city_cd;
                                $data['state']['state_id'] = $pincode->state_id;
                                $data['state']['state_name'] = $pincode->state_name;
                            }
                            $unique = collect($data['city'])->unique('city_name')->toArray();
                            $data['city'] = array_values($unique);
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        } */

                        break;
                case 'nic':
                    $payload = DB::table('nic_pincode_master AS npm')
                        ->where('npm.pin_cd', $request->pincode)
                        ->join('nic_state_master AS nsm', 'npm.state_id', '=', 'nsm.state_id')
                        ->join('nic_district_master AS ndm', 'npm.district_id', '=', 'ndm.district_id')
                        ->select(DB::raw('DISTINCT npm.district_id'), 'npm.state_id', 'nsm.state_name', 'ndm.district_name')
                        ->get();

                    if ($payload->isNotEmpty()) {
                        $data = [
                            'city' => [],
                            'state' => []
                        ];
                        foreach ($payload as $key => $pincode) {
                            $data['state']['state_id'] = $pincode->state_id;
                            $data['state']['state_name'] = $pincode->state_name;
                            $data['city'][] = [
                                'city_id' => $pincode->district_id,
                                'city_name' => $pincode->district_name,
                            ];
                        }
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Not Found",
                        "data" => []
                    ]);
                    break;

                case 'tata_aig_v2':
                    $payload = DB::table('tata_aig_v2_city_master AS tacm')
                        ->leftJoin('tata_aig_v2_pincode_master AS tapm', 'tapm.p_city_id', '=', 'tacm.c_city_id')
                        ->leftJoin('tata_aig_v2_state_master AS tasm', 'tasm.state_id', '=', 'tapm.p_state_id')
                        ->where('tapm.p_pincode', $request->pincode)
                        ->get();

                    if ($payload->isNotEmpty()) {
                        $data = [
                            'city' => [],
                            'state' => []
                        ];
                        foreach ($payload as $key => $pincode) {
                            $data['state']['state_id'] = $pincode->state_id;
                            $data['state']['state_name'] = $pincode->state_name;
                            $data['city'][] = [
                                'city_id' => $pincode->c_city_id,
                                'city_name' => $pincode->c_city,
                            ];
                        }
                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            "data" => trim_array($data)
                        ]);
                    }
                    break;

                    case 'royal_sundaram':
                        $payload = DB::table('godigit_pincode_state_city_master_for_royal_sundaram AS s')
                            ->join('royal_sundaram_rto_pincode_master AS p', 'p.DIGIT_STATE_CODE', '=', 's.statecode')
                            ->where('s.pincode', $request->pincode)
                            ->orderBy('p.CITY_NAME')
                            ->select('p.*')
                            ->get();
                        $data = [];
                        if ($payload->isNotEmpty())
                        {
                            foreach ($payload as $key => $value)
                            {
                                $data['state']['state_id'] = $value->STATE_CODE;
                                $data['state']['state_name'] = $value->STATE_NAME;
                                $data['city'][] = [
                                    'city_id' => $value->CITY_CODE,
                                    'city_name' => $value->CITY_NAME
                                ];
                            }
                            $unique = collect($data['city'])->unique('city_name')->toArray();
                            $data['city'] = array_values($unique);
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        }
                    if (config('constants.IcConstants.royal_sundaram.FETCH_CITY_BY_RTO_MASTER') == 'Y') {
                        if (empty($request->rtoCode)) {
                            return response()->json([
                                'status' => false,
                                'msg' => "Rto Code not found"
                            ], 422);
                        }
                        $rtoCode = RtoCodeWithOrWithoutZero($request->rtoCode, true);
                        $rtoData = DB::table('royal_sundaram_rto_master AS rsrm')
                        ->where('rsrm.rto_no', str_replace('-', '', $rtoCode))
                        ->select('rsrm.city_name', 'rsrm.state_name')
                        ->groupBy('rsrm.city_name')
                        ->get();

                        if (count($rtoData) > 0) {
                            $data = [
                                'city' => []
                            ];
                            foreach ($rtoData as $pincode) {
                                array_push($data['city'], ['city_name' => $pincode->city_name, 'city_id' => $pincode->city_name]);
                                $data['state']['state_id'] = $pincode->state_name;
                                $data['state']['state_name'] = $pincode->state_name;
                            }
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        }
                    } else {
                        $payload = RoyalSundaramPincodeMaster::select('state', 'city', 'statecode')
                        ->where('pincode', $request->pincode)
                        ->orderBy('state')
                            ->get();
                        $data = [];
                        if ($payload->isNotEmpty()) {
                            foreach ($payload as $key => $pincode) {
                                $data['city'][0]['city_name'] = $pincode->city;
                                $data['city'][0]['city_id'] = $pincode->city;
                                $data['state']['state_id'] = $pincode->statecode;
                                $data['state']['state_name'] = $pincode->state;
                            }
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                "data" => trim_array($data)
                            ]);
                        }
                    }
                        break;

                default:
                    return response()->json([
                        'status' => false,
                        'msg' => "Incorrect Company alias"
                    ], 422);
                    break;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return response()->json([
                'status' => false,
                'msg' => "Pincode detail not available",
                'data' => null,
                'dev' =>  $e->getLine() . ' - ' . $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => false,
            'msg' => "No data found for this pincode",
            'data' => null,
        ]);
    }

    public function getOccupation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if(config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_ENABLED') == 'Y' && $request->companyAlias == 'tata_aig')
        {
            $request->companyAlias = 'tata_aig_v2';
        }

        $occupations = MasterOccupation::select('occupation_code as id', 'occupation_name as name')
            ->whereNotNull('occupation_code')
            ->where('company_alias', $request->companyAlias)
            ->get();
    $return_data = [];
    foreach ($occupations as $key => $value) {
        if($value->name == 'Others')
        {
            $value->priority = '1';         
        }
        else
        {
            $value->priority = '0';  
        }
        
        $return_data[] = $value;
    }

        if (!empty($return_data)) {
            return response()->json([
                'status' => true,
                'message' => "Found",
                "data" => $return_data
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => "No Occupations for the following ",
                'data' => null
            ]);
        }
    }
    public function getGender(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if ((config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y' || config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_BIKE') == 'Y') && $request->companyAlias == 'hdfc_ergo')
        {
            $product_sub_type_id = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))->pluck('product_sub_type_id')->first();
            $parent_code = get_parent_code($product_sub_type_id);
            if(in_array($parent_code, ['PCV', 'GCV']))
            {
                $request->companyAlias = 'hdfc_ergo';
            }
            else
            {
                $request->companyAlias = 'hdfc_ergo_v2';
            }
        }

        $genders = Gender::select('gender_code as code', 'gender as name')
            ->whereNotNull('gender_code')
            ->orderBy('name')
            ->where('company_alias', $request->companyAlias)
            ->get();

        if ($genders->isNotEmpty()) {
            return response()->json([
                'status' => true,
                'message' => "Found",
                "data" => $genders->toArray()
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => "No Gender found "
            ]);
        }
    }
    public function getNomineeRelationship(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_MISCD_ENABLED') == 'Y') {
            $product_sub_type_id = UserProductJourney::where('user_product_journey_id', customDecrypt($request->enquiryId))
                ->pluck('product_sub_type_id')
                ->first();
            if (in_array($product_sub_type_id, [17, 18]) && $request->companyAlias === 'hdfc_ergo') {
                $request->companyAlias = 'hdfc_ergo_miscd';
            }
        }

        if ((config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y' || config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_BIKE') == 'Y') && $request->companyAlias == 'hdfc_ergo')
        {
            $product_sub_type_id = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))->pluck('product_sub_type_id')->first();
            $parent_code = get_parent_code($product_sub_type_id);
            if(in_array($parent_code, ['PCV', 'GCV']))
            {
                $request->companyAlias = 'hdfc_ergo';
            }
            else
            {
                $request->companyAlias = 'hdfc_ergo_v2';
            }
        }

        
        if(config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_ENABLED') == 'Y' && $request->companyAlias == 'tata_aig_v2')
        {
            $request->companyAlias = 'tata_aig_v2';
        }

        $nominee_relationship = NomineeRelationship::select('relation_code as code', 'relation as name')
            ->whereNotNull('relation_code')
            ->orderBy('name')
            ->where('company_alias', $request->companyAlias)
            ->get();

        if ($nominee_relationship->isNotEmpty()) {
            return response()->json([
                'status' => true,
                'message' => "Found",
                "data" => $nominee_relationship->toArray()
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => "No nominee relationship found"
            ]);
        }
    }
    public function getPreviousInsurerList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $is_hdfc_ergo_miscd = false;
        if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_MISCD_ENABLED') == 'Y' && $request->companyAlias == 'hdfc_ergo') {
            $product_sub_type_id = UserProductJourney::where('user_product_journey_id', customDecrypt($request->enquiryId))
                ->pluck('product_sub_type_id')
                ->first();
            if (in_array($product_sub_type_id, [17, 18]) && $request->companyAlias === 'hdfc_ergo') {
                $request->companyAlias = 'hdfc_ergo_miscd';
                $is_hdfc_ergo_miscd = true;
            }
        }
        //For handling previous insurer list for Short term renewal for Tata aig Hero.
        $product_sub_type_id = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))->pluck('product_sub_type_id')->first();
        $quote_log = QuoteLog::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();      
        $parent_code = get_parent_code($product_sub_type_id);
        
        $is_hdfc_ergo_v2 = false;
        $is_tata_aig_v2 = false;
        if ((config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y' || config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_BIKE') == 'Y') && $request->companyAlias == 'hdfc_ergo')
        {
            // $quote_log = QuoteLog::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();      
            // $parent_id = get_parent_code($quote_log->master_policy->product_sub_type_code->product_sub_type_id);

            // if (! in_array($parent_id, ['PCV', 'GCV']))
            // {
               // $request->companyAlias = 'hdfc_ergo_v2';
            // }
            $product_sub_type_id = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))->pluck('product_sub_type_id')->first();
            $parent_code = get_parent_code($product_sub_type_id);
            if(in_array($parent_code, ['PCV', 'GCV']))
            {
                $request->companyAlias = 'hdfc_ergo';
            }
            else
            {
                $is_hdfc_ergo_v2 = true;
                $request->companyAlias = 'hdfc_ergo_v2';
            }
        }

        if(config('constants.IcConstants.tata_aig_v2.IS_TATA_AIG_V2_CAR_ENABLED') == 'Y' && $request->companyAlias == 'tata_aig')
        {
            if(isset($request->enquiryId))
            {
                $quote_log = QuoteLog::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();      
                $parent_id = get_parent_code($quote_log->master_policy->product_sub_type_code->product_sub_type_id);

                if (in_array($parent_id, ['CAR','GCV']))
                {
                    $request->companyAlias = 'tata_aig_v2';
                    $is_tata_aig_v2 =  true;
                }
               
            }
        }

        $is_new_india_tp =  false;
        if ($request->companyAlias == 'new_india') {
            if (isset($request->isTp) && $request->isTp) {
                $request->companyAlias = 'new_india_tp';
                $is_new_india_tp =  true;
            }
        }
        $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
//        $insurers = PreviousInsurerList::select('code', 'name')
//            ->whereNotNull('code')
//            ->where('code', '<>', '')
//            ->orderBy('name')
//            ->where('company_alias', $request->companyAlias)
//            ->get();
            $date_difference_year = get_date_diff('year', $CorporateVehiclesQuotesRequest['vehicle_register_date']);
            $insurers = PreviousInsurerList::select('code', 'name')
                ->whereNotNull('code')
                ->where('code', '<>', '')
                ->orderBy('name')
                ->where('company_alias', $request->companyAlias);

            $current_ic = MasterCompany::select('company_name')
                ->where('company_alias', 'LIKE', "%{$request->companyAlias}%");
                if($is_hdfc_ergo_v2)
                {
                    $current_ic->orWhere('company_alias', 'LIKE', "%hdfc_ergo%");
                }
                if($is_hdfc_ergo_miscd)
                {
                    $current_ic->orWhere('company_alias', 'LIKE', "%hdfc_ergo%");
                }
                if($is_tata_aig_v2)
                {
                    $current_ic->orWhere('company_alias', 'LIKE', "%tata_aig%");
                }
                if($is_new_india_tp)
                {
                    $current_ic->orWhere('company_alias', 'LIKE', "%new_india%");
                }
                $current_ic = $current_ic->get();
                $ic_checker = $request->companyAlias;
                if($CorporateVehiclesQuotesRequest['is_renewal'] == 'Y')
                {
                    if($request->companyAlias == 'hdfc_ergo_v2')
                    {
                        $ic_checker = 'hdfc_ergo';
                    }

                    if($request->companyAlias == 'tata_aig_v2')
                    {
                        $ic_checker = 'tata_aig';
                    }
                }

                if(config('RENEWAL_PREVIOS_INSURER_LIST_FILTER') == 'Y')
                {
                  return $this->get_insurers($request, $CorporateVehiclesQuotesRequest, $insurers);
                }

                if($CorporateVehiclesQuotesRequest['is_renewal'] == 'Y' && ($ic_checker == $CorporateVehiclesQuotesRequest['previous_insurer_code']) && (!isset($request->isTp) || $request->isTp === false) && $CorporateVehiclesQuotesRequest['business_type'] != 'breakin')
                {
                    if($ic_checker == 'godigit')
                    {
                       $companyAlias[0] = 'go digit'; 
                    }
               
                    else
                    {
                       $companyAlias =  explode('_',$ic_checker); 
                    }
                    
                   if(in_array($CorporateVehiclesQuotesRequest['policy_type'], ['own_damage', 'comprehensive']))
                   {
                       $insurers = $insurers->where('name', 'LIKE', "%{$companyAlias[0]}%");                       
                   }                   
                } else if($CorporateVehiclesQuotesRequest['is_renewal'] == 'Y' && $CorporateVehiclesQuotesRequest['rollover_renewal'] == 'Y' && config('IS_ROLLOVER_RENEWAL_ENABLED') == 'Y' && (!isset($request->isTp) || $request->isTp === false)) {
                    $unique_identifier = \App\Models\FastlanePreviousIcMapping::where('company_alias', $CorporateVehiclesQuotesRequest['previous_insurer_code'])->first();
                    if (!empty($unique_identifier)) {
                        $insurers = $insurers->where('name', 'LIKE', "%{$unique_identifier->identifier}%");
                    } else {
                        if($CorporateVehiclesQuotesRequest['previous_insurer_code'] == 'godigit') {
                            $companyAlias[0] = 'go digit'; 
                        } else {
                            $companyAlias =  explode('_', $CorporateVehiclesQuotesRequest['previous_insurer_code']);
                        }
                        $insurers = $insurers->where('name', 'LIKE', "%{$companyAlias[0]}%");
                    }
                } else {
                    unset($insurers);
                    if($request->isTp == true)
                    {
                        $insurers = PreviousInsurerList::select('code', 'name')
                        ->whereNotNull('code')
                        ->where('code', '<>', '')
                        ->where('company_alias', $request->companyAlias)
                        ->orderBy('name');
                    }
                    else
                    {
                        $insurers = PreviousInsurerList::select('code', 'name')
                        ->whereNotNull('code')
                        ->where('code', '<>', '')
                        ->where('company_alias', $request->companyAlias);
                        if(config('ENABLE_ALL_PREVIOUS_INSURER_LIST') != 'Y' && !in_array($quote_log->master_policy['premium_type_id'], [4,5,8,9,10]))
                        {
                            $insurers = $insurers->where('name', '!=' , $current_ic[0]->company_name);
                        }
                        $insurers = $insurers->orderBy('name');
                    }
                }
            $insurers = $insurers->get();
        if ($insurers->isNotEmpty()) {
            return response()->json([
                'status' => true,
                'message' => "Found",
                "data" => $insurers->toArray()
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => "No Inusurer found"
            ]);
        }
    }

    public function get_insurers($request, $CorporateVehiclesQuotesRequest, $insurers)
    {
        if ($CorporateVehiclesQuotesRequest['is_renewal'] == 'Y') {
            if ((!isset($request->isTp) || $request->isTp === false)) {

                $previous_ic_code = $CorporateVehiclesQuotesRequest['previous_insurer_code'] ?? '';

            } else {

                $tp_previous_insurer = UserProposal::select('tp_insurance_company')
                ->where('user_product_journey_id', customDecrypt($request->enquiryId))
                    ->first();

                $previous_ic_code = json_decode(($tp_previous_insurer->tp_insurance_company), TRUE)['company_alias'] ?? '';

            }
            if (!empty($previous_ic_code)) {
                switch ($previous_ic_code) {
                    case 'godigit' :
                        $companyAlias[0] = 'go digit';
                        break;
                    case 'cholla_mandalam' :
                        $companyAlias[0] = 'chola';
                        break;
                    case 'nic' :
                        $companyAlias[0] = 'national';
                        break;
                    default :
                        $companyAlias =  explode('_', $previous_ic_code);
                        break;
                }

                $insurers = $insurers->where('name', 'LIKE', "%{$companyAlias[0]}%");
            }
        }
        $insurers = $insurers->get();

        if ($insurers->isNotEmpty()) {
            return response()->json([
                'status' => true,
                'message' => "Found",
                "data" => $insurers->toArray()
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => "No Inusurer found"
            ]);
        }
    }

    public function getFinancerAgreementType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $financer_agreement_type = FinancierAgreementType::select('name', 'code')
            ->whereNotNull('code')
            ->orderBy('name')
            ->where('company_alias', $request->companyAlias)
            ->get();

        if ($financer_agreement_type->isNotEmpty()) {
            return response()->json([
                'status' => true,
                'message' => "Found",
                "data" => $financer_agreement_type->toArray()
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => "No Financer Agreement Type found "
            ]);
        }
    }
    public function getFinancerList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required'],
            'searchString' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if ((config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y' || config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_BIKE') == 'Y') && $request->companyAlias == 'hdfc_ergo')
        {
            $product_sub_type_id = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))->pluck('product_sub_type_id')->first();
            $parent_code = get_parent_code($product_sub_type_id);
            if(in_array($parent_code, ['PCV', 'GCV']))
            {
                $request->companyAlias = 'hdfc_ergo';
            }elseif($parent_code == 'MISC'){
                $request->companyAlias = 'hdfc_ergo_misc';
            }
            else
            {
                $request->companyAlias = 'hdfc_ergo_v2';
            }
        }

        switch ($request->companyAlias) {
            // We'll be using Shriram Master as common master for IC's not having financier master - 23-05-2022
            case 'reliance':
            case 'bajaj_allianz':
            case 'future_generali':
            case 'oriental':
            case 'acko':
            case 'icici_lombard':
            case 'new_india':
            case 'universal_sompo':
            case 'sbi':
            case 'royal_sundaram':
            case 'shriram':
                $payload = ShriramFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->limit(100)
                    ->get()
                    ->toArray();

                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];
                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);

                break;
                
            case 'godigit':
                $payload = ShriramFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->limit(100)
                    ->get()
                    ->toArray();

                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];
                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);

                break;

            case 'hdfc_ergo':
                $payload = HdfcErgoFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->limit(100)
                    ->get()
                    ->toArray();

                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];
                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);

                break;
            case 'tata_aig':
                $payload = DB::table('tata_aig_finance_master AS tafm')
                ->select('tafm.name as name', 'tafm.name as code')
                    ->where('name', 'LIKE', "%{$request->searchString}%")
                    ->limit(100)
                    ->get()
                    ->toArray();
            
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];
                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);

                break;
            /* case 'reliance':
            case 'bajaj_allianz':
            case 'future_generali':
            case 'oriental':
            case 'acko':
            case 'icici_lombard':
            case 'new_india':
            case 'universal_sompo':
            case 'sbi':
            case 'godigit':
                $payload = CommonFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%");

                if ($request->companyAlias == 'acko') {
                    $payload = $payload->whereRaw('LENGTH(code) > 4');
                }

                $payload = $payload->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break; */

            case 'cholla_mandalam':
                $payload = chollaMandalamFinancierMaster::where('txt_financier_name', 'LIKE', "%{$request->searchString}%")
                    ->distinct('txt_financier_name')
                    ->select('txt_financier_name as name', 'txt_financier_name as code')#txt_financier_cd
                    ->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);

                break;

            //case 'royal_sundaram':
            case 'liberty_videocon':
                $payload = LibertyVideoconFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->distinct()
                    ->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;

            case 'kotak':
                $payload = kotakFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;
            case 'iffco_tokio':
                $payload = iffco_tokioFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->select('name','name as code')
                    ->distinct()
                    ->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;

            case 'united_india':
                $payload = UnitedIndiaFinancierMaster::where('financier_name', 'LIKE', "%{$request->searchString}%")
                    ->select('financier_name as name', 'financier_code as code')
                    ->distinct()
                    ->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;
            case 'raheja':
                $payload = RahejaFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;
            case 'edelweiss':
                $payload = edelweissFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->distinct()
                    ->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;
            case 'nic':
                $payload = [];
                $searchString = trim($request->searchString);
                if (Schema::hasTable('nic_finance_master')) {
                    $payload = nicFinancierMaster::where('party_name', 'LIKE', "%{$searchString}%")
                        ->select('party_name as name', 'role_id as code')
                        ->distinct()
                        ->limit(100)
                        ->get()
                        ->toArray();
                }
                if(empty($payload)){
                    $payload = [[
                        'code' => $searchString,
                        'name' => $searchString
                    ]];
                }

                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;

            case 'magma':
                $payload = MagmaFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->distinct()
                    ->limit(100)
                    ->get()
                    ->toArray();


                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);

                break;

            case 'hdfc_ergo_v2':
                $payload = HdfcErgoV2FinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->limit(100)
                    ->get()
                    ->toArray();

                
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;

            case 'hdfc_ergo_misc':
                $payload = HdfcErgoMiscFinancierMaster::where('name', 'LIKE', "%{$request->searchString}%")
                    ->limit(100)
                    ->get()
                    ->toArray();

                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];
                return response()->json([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]);
                break;

            default:
                return response()->json([
                    'status' => false,
                    'msg' => "Incorrect Company alias"
                ], 422);

                break;
        }
    }
    public function getFinancerBranch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required'],
            'financierCode' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        switch ($request->companyAlias) {
            case 'united_india':
                $payload = UnitedIndiaFinancierBranchMaster::where('financier_code', $request->financierCode)
                    ->distinct()
                    ->limit(100)
                    ->get()
                    ->toArray();
                    
                list($status, $data, $msg) = $payload ? [true, $payload, 'found'] : [true, [], null];

                return response()->json(camelCase([
                    'status' => $status,
                    'msg' => $msg,
                    'data' => $data
                ]));
                break;

            default:
                return response()->json(camelCase([
                    'status' => false,
                    'msg' => "Incorrect Company alias"
                ]), 422);

                break;
        }
    }

    public function getPolicyDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userProductJourneyId' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $policy_details = UserProposal::with('policy_details:proposal_id,policy_number,pdf_url,status,created_on')
            ->select('user_proposal_id','final_payable_amount','ic_id','is_ckyc_details_rejected','is_ckyc_verified')
            ->where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
            ->first();
        $agentDetail = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
                           ->pluck('seller_type')->toArray();
        if (empty($policy_details->policy_details->created_on)){
            $paymentdate = PaymentRequestResponse::where([
                ['user_product_journey_id',customDecrypt($request->userProductJourneyId)],
                ['status', STAGE_NAMES['PAYMENT_SUCCESS']],
                ['active',1]
            ])
            ->select('created_at')
            ->first();
        }
        if(!empty($policy_details->policy_details->created_on)){
            $paydate = $policy_details->policy_details->created_on;
        } elseif (!empty($paymentdate) ){
            $paydate = $paymentdate->created_at;
        } else {
            $paydate = null;
        }
        $paydate_date = $current_date = '';
        $validatepageTime = true;
        if (!empty($paydate)) {
            $paydate_date = date('Y-m-d', strtotime($paydate));
            $current_date = date('Y-m-d');
            $validatepageTime = ($paydate_date == $current_date) ? true : false;
        } 
        if(count($agentDetail) > 1 && in_array('U',$agentDetail))
        {
            foreach ($agentDetail as $key => $value)
            {
                if($value == 'U')
                {
                   unset($agentDetail[$key]);
                }
            }

        }
        $redirection_data = [];
        foreach ($agentDetail as $key => $value) {
            if($value == 'P')
            {
              $redirection_data[$value] = config('POS_DASHBOARD_LINK');
            }
            else if($value == 'E')
            {
                $redirection_data[$value] = config('EMPLOYEE_DASHBOARD_LINK');
            }
            else if($value == 'U')
            {
                 $redirection_data[$value] = config('USER_DASHBOARD_LINK');
            }
            else if($value == 'Partner')
            {
                 $redirection_data[$value] = config('PARTNER_DASHBOARD_LINK');
            }

        }
        if(empty($redirection_data))
        {
            $redirection_data['U'] = config('USER_DASHBOARD_LINK');
        }
        if(config('constants.motorConstant.GRAMCOVER_DATA_PUSH_ENABLED') == 'Y'){
            // \App\Jobs\GramcoverDataPush::dispatch(customDecrypt($request->userProductJourneyId));
        }
        if (config('constants.IS_CKYC_ENABLED') == 'Y') 
        {
            if(!empty($policy_details) && !empty($policy_details->ic_id) && $policy_details->ic_id == 36)
            {
                $CkycGodigitFailedCasesData = CkycGodigitFailedCasesData::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->where('status','failed')->first();
                if(!empty($CkycGodigitFailedCasesData))
                {
                    $data = !empty($policy_details->policy_details) ? camelCase($policy_details->policy_details->makeHidden(['proposal_id'])->toArray()) : [];
                    $data['final_payable_amount'] =$policy_details->final_payable_amount;
                    $data['status'] = 'FAILURE';
                    $redirection_data['kyc_url'] = (filter_var($CkycGodigitFailedCasesData->kyc_url, FILTER_VALIDATE_URL)) ? $CkycGodigitFailedCasesData->kyc_url : '';
                    $redirection_data['proceed'] = $CkycGodigitFailedCasesData->return_url;
                    $redirection_data['post_data_proceed'] = json_decode($CkycGodigitFailedCasesData->post_data, TRUE);
                    $data['redirection_data'] = $redirection_data;
                    if(config('constants.motorConstant.CLEVERTAP_DUMMY_PDF.ENABLED') == 'Y'){
                    $data['pdfUrl'] = $data['pdfUrl'] . "&policy-doc.pdf";
                    }
                    if (!$validatepageTime) {
                        unset($data['pdf_url']);
                    }

                    return response()->json([
                        'status' => false,
                        'msg' => "Found",
                        'data' => $data

                    ]);
                }
            }
            else if(!empty($policy_details) && !empty($policy_details->ic_id) && $policy_details->ic_id == 39)
            {
                $CkycAckoFailedCasesData = CkycAckoFailedCasesData::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->where('status','false')->first();
                if(!empty($CkycAckoFailedCasesData))
                {
                    $data = !empty($policy_details->policy_details) ? camelCase($policy_details->policy_details->makeHidden(['proposal_id'])->toArray()) : [];
                    $data['final_payable_amount'] =$policy_details->final_payable_amount;

                    $data['status'] = 'SUCCESS';
                    $redirection_data['kyc_url'] = (filter_var($CkycAckoFailedCasesData->kyc_url, FILTER_VALIDATE_URL)) ? $CkycAckoFailedCasesData->kyc_url : '';
                    $data['redirection_data'] = $redirection_data;
                    if(config('constants.motorConstant.CLEVERTAP_DUMMY_PDF.ENABLED') == 'Y'){
                    $data['pdfUrl'] = $data['pdfUrl'] . "&policy-doc.pdf";
                    }
                    if (!$validatepageTime) {
                        unset($data['pdf_url']);
                    }
                    return response()->json([
                        'status' => false,
                        'msg' => "Found",
                        'data' => $data

                    ]);
                }
            }
            else if(!empty($policy_details) && !empty($policy_details->ic_id) && $policy_details->ic_id == 34)
            {
                if($policy_details->is_ckyc_details_rejected == 'Y' || $policy_details->is_ckyc_verified == 'N')
                {
                    $data = !empty($policy_details->policy_details) ? camelCase($policy_details->policy_details->makeHidden(['proposal_id'])->toArray()) : [];
                    $data['final_payable_amount'] = $policy_details->final_payable_amount;
                    $data['status'] = 'SUCCESS';
                    $redirection_data['kyc_url'] = 'https://www.sbigeneral.in/home/kyc/manual?source='.strtoupper(config('constants.motorConstant.SMS_FOLDER')).'&policyNumber='.($data['policyNumber'] ?? null);
                    $data['redirection_data'] = $redirection_data;
                    if(config('constants.motorConstant.CLEVERTAP_DUMMY_PDF.ENABLED') == 'Y'){
                    $data['pdfUrl'] = $data['pdfUrl'] . "&policy-doc.pdf";
                    }
                    if(empty($data['policyNumber'])){
                        $data['custom_message'] = 'Payment Success but Policy is not generated';
                    }
                    if ( $paydate != null) {
                        $data['created_at'] = $paydate;
                    }
                    if (!$validatepageTime) {
                        unset($data['pdf_url']);
                    }

                    return response()->json([
                            'status' => true,
                            'msg' => "Found",
                            'data' => $data

                    ]);
                } 
            }
        }
        if (!empty($policy_details->policy_details)) {
            $data = camelCase($policy_details->policy_details->makeHidden(['proposal_id'])->toArray());
          $data['final_payable_amount'] =$policy_details->final_payable_amount;
            $data['redirection_data'] = $redirection_data;
            if(config('constants.motorConstant.CLEVERTAP_DUMMY_PDF.ENABLED') == 'Y'){
            $data['pdfUrl'] = $data['pdfUrl'] . "&policy-doc.pdf";
            }

            if ( $paydate != null) {
                $data['created_at'] = $paydate;
                // if (date('Y-m-d') !== (new DateTime($paydate))->format('Y-m-d'))
                // {
                //     foreach (['policyNumber', 'pdfUrl'] as $key)
                //     {
                //         unset($data[$key]);
                //     }
                // }
            }
            if (!$validatepageTime) {
                unset($data['pdf_url']);
            }
            return response()->json([
                'status' => true,
                'msg' => "Found",
                'data' => $data

            ]);
        }
        $data['redirection_data'] = $redirection_data;
        if(empty($data['policyNumber'])){
            $data['custom_message'] = 'Payment Success but Policy is not generated';
            if(!empty($policy_details->ic_id))
            {
                $company_name = MasterCompany::where('company_id',$policy_details->ic_id)
                                ->pluck('company_name')
                                ->first();
                $data['custom_message'] = 'Payment Success but Policy is not generated - '.$company_name;
            }
        }
        if(config('constants.motorConstant.CLEVERTAP_DUMMY_PDF.ENABLED') == 'Y'){
        $data['pdfUrl'] = $data['pdfUrl'] . "&policy-doc.pdf";
        }
        if (!$validatepageTime) {
            unset($data['pdf_url']);
        }
        return response()->json([
            'status' => true,
            'msg' => 'Policy details not received from insurance company',
            'data' => $data
        ]);
    }

    public function feedback(Request $request)
    {

        if ($request->method() == 'GET'){

            $validator = Validator::make($request->all(), [
                'userProductJourneyId' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "message" => $validator->errors(),
                ]);
            }

            if (is_numeric($request['userProductJourneyId'])){
                $enquiryId =$request['userProductJourneyId'];
            } else {
                $enquiryId = customDecrypt($request['userProductJourneyId']);
            }
            $feedback = Feedback::where('user_product_journey_id','=', $enquiryId)->get();

            if (!empty($feedback) && count($feedback) > 0){

                return response()->json([
                    'status' => true,
                    'msg' => "Data Found",
                    'data' => $feedback
                ]);
            }

            return response()->json([
                'status' => false,
                'msg' => "No Data Found",
                'data' => null
            ]);
        } 

        $validator = Validator::make($request->all(), [
            'userProductJourneyId' => ['nullable'],
            'overallExperience' => ['nullable', 'integer', 'numeric', 'between:1,5'],
            'easyToBuy' => ['nullable', 'integer', 'numeric', 'between:1,5'],
            'recommendUs' => ['nullable', 'integer', 'numeric', 'between:1,5'],
            'customerSupportExperience' => ['nullable', 'integer', 'numeric', 'between:1,5'],
            'comments' => ['nullable'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
    
        $payload = snakeCase($request->all());
        $payload['user_product_journey_id'] = customDecrypt($payload['user_product_journey_id']);

        $feedback = Feedback::updateOrCreate(['user_product_journey_id' => $payload['user_product_journey_id']], $payload);
        list($status, $msg, $data) = $feedback
            ? [true, 'feedback saved successfully', $feedback]
            : [false, 'something went wrong', null];
        return response()->json([
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ]);

    }

    public function tokenValidate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required'],
            'journeyType' => ['nullable']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if(isset($request->journeyType) && $request->journeyType == 'Z3JhbWNvdmVyLWFwcC1qb3VybmV5'){
            $response = httpRequest('login_token_validate', ['token' => $request->token]);
            UserTokenRequestResponse::create([
                'user_type' => base64_decode($request->journeyType),
                'request' => json_encode($response['request']),
                'response' => json_encode($response['response']),
            ]);
            $response['response']['result']['userdata']['dp_token']=$request->journeyType;
            if(isset($response['response']) && $response['response']['status'] && $response['response']['status_code'] == 3001 ){
                return response()->json([
                    'status' => true,
                    'msg' => $response['response']['result']['message'] ?? null,
                    'data' => $response['response']['result']['userdata'],
                ]);

            } else{
                return response()->json([
                    "status" => false,
                    "msg" => $response['response']['result'],
                ]);
            }
        }

        if(isset($request->journeyType) && $request->journeyType == 'b2Vt'){
            // return $data = Http::withoutVerifying()->post(config('constants.motorConstant.TOKEN_VALIDATE_URL'), ['token' => $request->token])->json();
            return $data = httpRequestNormal(config('constants.motorConstant.TOKEN_VALIDATE_URL'),'POST', ['token' => $request->token]);
        }
        if (config('constants.motorConstant.IS_PINC_INSURANCE') && config('constants.motorConstant.IS_PINC_INSURANCE') == 'Y')
        {
            
            $data = Curl::to(config('constants.motorConstant.PINC_TOKEN_VALIDATION_URL'))
                ->withAuthorization('Basic '.base64_encode(config('constants.motorConstant.PINC_API_USERNAME') . ':' . config('constants.motorConstant.PINC_API_PASSWORD')))
                ->withData(['pos_id' => $request->token, 'token' => $request->token])
                ->asJson()
                ->post();

            $data = (array) $data;
        }
        else
        {
            $tokenData = $request->token;
            $tokenData = DashboardController::encryptToken($tokenData);
            if(config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == "true"){
                $data = Http::withoutVerifying()->post(config('constants.motorConstant.TOKEN_VALIDATE_URL'), ['token' => $tokenData])->json();
            } else {
                $data = httpRequestNormal(config('constants.motorConstant.TOKEN_VALIDATE_URL'), 'POST', ['token' => $tokenData])['response'];
            }
        }

        UserTokenRequestResponse::create([
            'user_type' => $data['data']['usertype'] ?? NULL,
            'request' => json_encode($request->all()),
            'response' => json_encode($data),
        ]);

        if (config('constants.motorConstant.IS_PINC_INSURANCE') && config('constants.motorConstant.IS_PINC_INSURANCE') == 'Y')
        {
            $data = [
                'status' => $data['result'] ? 'true' : 'false',
                'data' => $data['result'] ? [
                    'seller_id' => $data['response']->pos_detail->pos_id,
                    'seller_type' => 'P',
                    'seller_name' => $data['response']->pos_detail->first_name . ' ' . $data['response']->pos_detail->last_name,
                    'user_name' => $data['response']->pos_detail->pos_id,
                    'email' => $data['response']->pos_detail->email,
                    'mobile' => $data['response']->pos_detail->contact_no,
                    'aadhar_no' => $data['response']->pos_detail->aadhar_number,
                    'pan_no' => $data['response']->pos_detail->pan_number,
                    'redirection_link' => '',
                    'unique_number'  => $data['response']->pos_detail->pos_id
                ] : []
            ];
        }
        
        if( ! isset($data['data']['seller_name']) || $data['data']['seller_name'] == NULL)
        {
            return response()->json([
                "status" => false,
                "msg" => 'Invalid Token...!',
            ]);
        }

        $data['status'] = ($data['status'] == 'true' ? true : false);
        $data['msg'] = isset($data['message']) ? $data['message'] : 'NULL';
        
        if(config('constants.motorConstant.SMS_FOLDER') == 'ace')
        {
            $ALLOWED_SELLER_TYPES = explode(',',config('ALLOWED_SELLER_TYPES'));
            if(!($data['status'] == true && isset($data['data']['seller_type']) && in_array($data['data']['seller_type'],$ALLOWED_SELLER_TYPES)))
            {
                $data['status'] = false;
                $data['msg'] = 'Invalid Seller...!';
                return response()->json($data);
            }
        }
            
        return response()->json($data);
    }

    public function getBreakinCompany(Request $request)
    {
        $data = DB::table('master_policy as mp')
            ->where('mp.premium_type_id', 4)
            ->where('mp.status', 'Active')
            ->join('master_company as mc', 'mc.company_id', '=', 'mp.insurance_company_id')
            ->select('mc.company_alias as code', 'mc.company_name as name')
            ->get();
        return response()->json([
            "status" => 'true',
            "msg" => '',
            "data" => $data
        ]);
    }

    public function masterCompanyLogos(Request $request)
    {
                $seller_type = $company_logos = '';
        if(isset($request->enquiryId))
        {
            $enquiryId = customDecrypt($request->enquiryId);
            $pos_data = CvAgentMapping::where('user_product_journey_id',$enquiryId)
                            ->whereIn('seller_type',['E','P'])
                            ->exists();
            if($pos_data)
            {
                $seller_type = 'B2B';
            }
            else{
                $seller_type = 'B2C';
            }

        }

        $exist = InsurerLogoPriorityList::where('seller_type', $seller_type)->exists();
        if($exist)
        {
            $company_logos = DB::table('master_policy AS mp')
            ->join('master_company AS mc', 'mp.insurance_company_id', '=', 'mc.company_id')
            ->join('insurer_logo_priority_list AS pl', 'pl.company_id', '=', 'mp.insurance_company_id')
            // ->where('mp.status', 'Active')
            ->where('pl.seller_type', $seller_type)
            ->select(DB::raw('DISTINCT mp.insurance_company_id AS icId'), 'mc.company_name AS icName','pl.priority' , 'mc.company_alias AS companyAlias', /* DB::raw("CONCAT('" . file_url(config('constants.motorConstant.logos')) . "','/'," . DB::raw('mc.logo') . ") AS logoUrl"), */ DB::raw('mc.branch AS Address'))
            ->orderby('pl.priority', 'asc')
            ->get();

        }
        else{
        $company_logos = DB::table('master_policy AS mp')
            ->join('master_company AS mc', 'mp.insurance_company_id', '=', 'mc.company_id')
            ->where('mp.status', 'Active')
            ->select(DB::raw('DISTINCT mp.insurance_company_id AS icId'), 'mc.company_name AS icName', 'mc.company_alias AS companyAlias', /* DB::raw("CONCAT('" . file_url(config('constants.motorConstant.logos')) . "','/'," . DB::raw('mc.logo') . ") AS logoUrl"), */ DB::raw('mc.branch AS Address'))
            ->get();
        }
        if($company_logos)
        {
            foreach($company_logos as $key => $company_logo)
            {
                $company_logos[$key]->logoUrl = url(config('constants.motorConstant.logos')).'/'.strtolower($company_logo->companyAlias).'.png';//file_url(config('constants.motorConstant.logos').strtolower($company_logo->companyAlias).'.png');
            }
            return response()->json([
                'status' => true,
                'data' => $company_logos
            ]);
        }
        else
        {
            return response()->json([
                "status" => false,
                "msg" => 'No Data FOund',
            ]);
        }
        
    }

    public function cvApplicableAddons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'icId' => 'required|numeric',
            'enquiryId' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $enquiryId = customDecrypt($request->enquiryId);
        $corpData = DB::table('corporate_vehicles_quotes_request')
            ->where('user_product_journey_id', $enquiryId)
            ->select('product_id', 'vehicle_owner_type','is_renewal','rollover_renewal')
            ->first();
        $section = get_parent_code($corpData->product_id);
        $where = [
            ['ic_id', '=', $request->icId],
            ['is_applicable', '=', 'Y']
        ];
        //SBI doesn't provide 'Unnamed Passenger PA Cover' if owner type is Company
        if ($request->icId == 34 && $corpData->vehicle_owner_type == 'C' && in_array($section, ['CAR', 'BIKE'])) {
            $where[] = ['addon_name', '<>', 'Unnamed Passenger PA Cover'];
        }
        //HDFC doesn't provide 'PA cover for additional paid driver' &  'External Bi-Fuel Kit CNG/LPG'
        if ($request->icId == 11 && $corpData->vehicle_owner_type == 'I' && in_array($section, ['MISC'])) {
            $where[] = ['addon_name', '<>', 'External Bi-Fuel Kit CNG/LPG'];
            $where[] = ['addon_name', '<>', 'PA paid driver/conductor/cleaner'];
        }
        if(in_array($request->icId,[28,32])  && $corpData->is_renewal == 'Y'){
            $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)->select('premium_json')->first();
            if(!empty($quoteLog) && !empty($quoteLog->premium_json)){
                $premiumArray = $quoteLog->premium_json;
                $addonsData = [
                    'defaultPaidDriver' => 'LL paid driver',
                    'coverUnnamedPassengerValue' => 'Unnamed Passenger PA Cover',
                    'motorElectricAccessoriesValue' => 'Electrical Accessories',
                    'motorNonElectricAccessoriesValue' => 'Non-Electrical Accessories',
                    'motorLpgCngKitValue' => 'External Bi-Fuel Kit CNG/LPG'

                ];
                if($request->icId == 32)
                {
                    $addonsData['voluntaryExcess'] = 'voluntary_insurer_discounts';
                    $addonsData['antitheftDiscount'] = 'anti-theft device';
                }
                foreach ($addonsData as $key => $addonName) {
                    if (empty($premiumArray[$key])) {
                        $where[] = ['addon_name', '<>', $addonName];
                    }
                }
            }
        }
        $ApplicableAddon = self::getAddons($section, $where);

        $AddonList = ['Zero Depreciation', 'Road Side Assistance', 'Consumable', 'Key Replacement', 'Engine Protector', 'NCB Protection', 'Tyre Secure', 'Return To Invoice', 'Loss of Personal Belongings', 'Emergency Medical Expenses'];
        $cnt = 0;
        $PremiumType = [2,7];
        $isTp = QuoteLog::select( 'master_policy_id', 'premium_type_id' )
        ->where('user_product_journey_id', $enquiryId)
        ->leftJoin("master_policy", "master_policy.policy_id", "=", "quote_log.master_policy_id")->get()->toArray();
        if(in_array($isTp[0]['premium_type_id'], $PremiumType)){

            $where = [
                ['ic_id', '=', $request->icId],
                ['is_applicable', '=', 'Y']
            ];

            for($i=0; $i<sizeof($AddonList);$i++){
                array_push($where, ['addon_name', '!=', $AddonList[$i]]);
            }
            if($request->icId == 28 && $corpData->is_renewal == 'Y'){
                $quoteLog = QuoteLog::where('user_product_journey_id', $enquiryId)->select('premium_json')->first();
                if(!empty($quoteLog) && !empty($quoteLog->premium_json)){
                    $premiumArray = $quoteLog->premium_json;
                    $addonsData = [
                        'defaultPaidDriver' => 'LL paid driver',
                        'coverUnnamedPassengerValue' => 'Unnamed Passenger PA Cover',
                        'motorElectricAccessoriesValue' => 'Electrical Accessories',
                        'motorNonElectricAccessoriesValue' => 'Non-Electrical Accessories',
                        'motorLpgCngKitValue' => 'External Bi-Fuel Kit CNG/LPG'
                    ];
                    foreach ($addonsData as $key => $addonName) {
                        if (empty($premiumArray[$key])) {
                            $where[] = ['addon_name', '<>', $addonName];
                        }
                    }
                }
            }
            $ApplicableAddon = self::getAddons($section, $where);
        }
        if($corpData->is_renewal == 'Y' && $corpData->rollover_renewal != 'Y')
        {
            $parent_code = $section;
            if(config('IC_FETCH_API_RENEWAL_PREFILL_ENABLE') == 'Y')
            {
                $company_alias = MasterCompany::where('company_id',$request->icId)
                            ->pluck('company_alias')
                            ->first();
                $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
                $prefillAllowedIc = config(strtoupper($parent_code).'_RENEWAL_ALLOWED_IC_PREFILL');
                $prefill_allowed_ic_array = explode(',',$prefillAllowedIc);
                if(in_array($company_alias,$prefill_allowed_ic_array))
                {
                    $renewal_class = 'App\Http\Controllers\RenewalService\\'.ucfirst(strtolower($parent_code)).'\\'.$company_alias;
                    if(class_exists($renewal_class))
                    {
                        $renewl_get_data = [
                            'user_product_journey_id'       => $enquiryId,
                            'company_alias'                 => $company_alias,
                            'vehicale_registration_number'  => $user_proposal->vehicale_registration_number,
                            'engine_number'                 => $user_proposal->engine_number,
                            'chassis_number'                => $user_proposal->chassis_number,
                            'prev_policy_expiry_date'       => $user_proposal->prev_policy_expiry_date,
                            'previous_policy_number'        => $user_proposal->previous_policy_number
                        ];
                        $renewal_ref = new $renewal_class();
                        $policy_data_response = $renewal_ref->IcServicefetchData($renewl_get_data);
                        $renewl_get_data['policy_data_response'] = $policy_data_response;
                        $reposne_renewal_data = $renewal_ref->prepareFetchData($renewl_get_data,$db_save=false);
                        unset($renewal_ref);
                        if(!empty($reposne_renewal_data['removal_addons']))
                        {
                            foreach($ApplicableAddon as $key => $value)
                            {
                                if(in_array($value['addon_name'],$reposne_renewal_data['removal_addons']))
                                {
                                    unset($ApplicableAddon[$key]);
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($ApplicableAddon) {
            return response()->json([
                "status" => true,
                "data" => array_values($ApplicableAddon),
            ]);
        } else {
            return response()->json([
                "status" => false,
                "msg" => 'No addons available for given ic.',
            ]);
        }
    }

    public function getAddons($section, $where){
        switch ($section) {
            case 'CAR':
                $ApplicableAddon = CarApplicableAddon::where($where)->select('addon_name','ic_id','ic_alias','addon_age','is_applicable')->distinct('addon_name')->get()->toArray();
                break;
            case 'BIKE':
                $ApplicableAddon = BikeApplicableAddon::where($where)->select('addon_name','ic_id','ic_alias','addon_age','is_applicable')->distinct('addon_name')->get()->toArray();
                break;
            case 'PCV':
                $ApplicableAddon = PcvApplicableAddon::where($where)->select('addon_name','ic_id','ic_alias','addon_age','is_applicable')->distinct('addon_name')->get()->toArray();
                break;
            case 'MISC':
                $ApplicableAddon = MiscdApplicableAddons::where($where)
                ->select('addon_name','ic_id','ic_alias','addon_age','is_applicable')
                ->distinct('addon_name')
                ->get()->toArray();
                break;
            default:
                $ApplicableAddon = GcvApplicableAddon::where($where)->select('addon_name','ic_id','ic_alias','addon_age','is_applicable')->distinct('addon_name')->get()->toArray();
                break;
        }
        return $ApplicableAddon;

    }


    public function getEnq(Request $request)
    {
        if ($request->type == 'encrypt') {
            $enquiryId = customEncrypt($request->enquiryId);
        }

        if ($request->type == 'decrypt') {
            $enquiryId = customDecrypt($request->enquiryId);
        }

        return response()->json([
            "enquiryId" => $enquiryId,
        ]);
    }

    public function getProductSubType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productType' => ['nullable', 'string']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        if ($request->productType == '') {
            $data = MasterProductSubType::where('parent_id', '0')
                ->where('status', 'Active')
                ->get();
        } else {
            $data = MasterProductSubType::where('product_sub_type_code', $request->productType)
                ->where('parent_id', '0')
                ->where('status', 'Active')
                ->first();
        }

        list($status, $msg, $data) = $data
            ? [true, 'found', $data]
            : [false, 'not found', null];

        return response()->json([
            "status" => $status,
            "msg" => $msg,
            "data" => $data,
        ]);
    }

    public function getWordingsPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $user_product_journey_id = customDecrypt($request->enquiryId);
        $payload = UserProductJourney::with([
                'quote_log',
                'corporate_vehicles_quote_request',
            ])
            ->where('user_product_journey_id',$user_product_journey_id)
            ->first();

            if($payload->corporate_vehicles_quote_request['business_type'] == 'breakin')
            {
                $payload->corporate_vehicles_quote_request['business_type'] = 'rollover';
            }
        $file_name = 'policy_wordings/'. $payload->quote_log['premium_json']['company_alias'] . '-'. strtolower($payload->corporate_vehicles_quote_request->product_sub_type['product_sub_type_code']) .'-'.$payload->corporate_vehicles_quote_request['business_type']. '-' . strtolower($payload->quote_log['premium_json']['policyType']) . '.pdf';

        if (Storage::exists(strtolower($file_name))) {
            // if(config('filesystems.default') == 's3'){
            //    $file_url = \Illuminate\Support\Facades\Storage::url($file_name) . '#';
            // }else{
            //     $file_url =  file_url($file_name);
            // }
            $file_url = file_url($file_name);
            //return response()->file($file_name);
            return response()->json([
                'status' => true,
                'data' => [
                    "pdfUrl" => /* Storage::url */$file_url,
                ],
                'message' => "Found"
            ]);
        } else {
            return response(/*'Policy Wording not Found', 500*/)->json([
                'status' => false,
                'data' => null,
                'message' => "not Found"
            ], 500);
        }
    }

    public function updateJourneyUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userProductJourneyId' => ['required'],
            'quoteUrl' => ['nullable', 'url'],
            'proposalUrl' => ['nullable', 'url']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $data = [];
        if ($request->proposalUrl != '') {
            $data['proposal_url'] = $request->proposalUrl;
        }
        if ($request->quoteUrl != '') {
            $data['quote_url'] = $request->quoteUrl;
        }
        if ($request->stage != '') {
            $data['stage'] = $request->stage;
        }
        $JourneyStage_data = JourneyStage::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();

        if ($JourneyStage_data->stage == STAGE_NAMES['PROPOSAL_ACCEPTED'] && $data['stage'] == STAGE_NAMES['PROPOSAL_DRAFTED']) {
            $data['stage'] = STAGE_NAMES['PROPOSAL_ACCEPTED'];
        }

        if( strtolower( $JourneyStage_data->stage ) === strtolower( STAGE_NAMES['PAYMENT_FAILED'] ) )
        {
            return response()->json( [
                'status' => false,
                'msg' => STAGE_NAMES['PAYMENT_FAILED'],
                'data' => $JourneyStage_data
            ] );
            exit;
        }

        if( isset( $JourneyStage_data->stage ) && in_array( strtolower( $JourneyStage_data->stage ), array_map('strtolower', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_FAILED'] , STAGE_NAMES['POLICY_CANCELLED'] ] ) ) )
        { 
            return response()->json( [
                'status' => false,
                'msg' => 'Transaction Already Completed',
                'data' => $JourneyStage_data
            ] );
            exit;
        }

        // we don't need to update the journey stage to lower stages. - 25-01-2024
        $post_proposal_stages = array_map('strtolower', [ STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_REJECTED'], STAGE_NAMES['INSPECTION_REJECTED'], STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_FAILED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['POLICY_ISSUED'] ] );

        $pre_proposal_stages = array_map('strtolower', [ STAGE_NAMES['QUOTE'], STAGE_NAMES['PROPOSAL_DRAFTED'], STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['LEAD_GENERATION']] );
        
        $current_stage = strtolower($JourneyStage_data->stage ?? null);
        
        $incoming_stage = strtolower($data['stage']);
        
        if ( in_array( $current_stage, $post_proposal_stages) && in_array($incoming_stage, $pre_proposal_stages) ) 
        {
            return response()->json([
                'status' => false,
                'msg' => 'It seems that there is a conflict while updating some data. Please refresh the page and try again.'
            ]);
        }

        $is_saved = JourneyStage::updateOrCreate(
            ['user_product_journey_id' => customDecrypt($request->userProductJourneyId)],
            $data
        );
        
        if ($request->has('skipCrm') && $request->skipCrm == 'Y') {

        } else if (config('bajaj_crm_data_push') == "Y") {
            
            bajajCrmDataUpdate($request);
        }

        list($status, $msg, $data) =  $is_saved
            ? [true, 'stage updated successfully', $is_saved]
            : [false, 'something went wrong', null];
        
        
        $Calling = config('HANDSHAKE_API_CALLING');
        if($Calling == 'Y')
        {
            $ace_data = [
                'enquiryId' => customDecrypt($request->userProductJourneyId),
                'stage'     => $request->stage            
            ];
            UpdateEnquiryStatusByIdAce($ace_data);
        }

        if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
        {
            $enquiryId = customDecrypt($request->userProductJourneyId);

            $user_product_journey = UserProductJourney::find($enquiryId);
            $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;
            $journey_stage = $user_product_journey->journey_stage;
            $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;
            $agent_details = $user_product_journey->agent_details;

            if ($lsq_journey_id_mapping && ! is_null($lsq_journey_id_mapping->lsq_stage) && $lsq_journey_id_mapping->lsq_stage != $journey_stage->stage)
            {
                if ($lsq_journey_id_mapping->lsq_stage != 'Lead Page Submitted')
                {
                    if ( ! is_null($corporate_vehicles_quote_request->vehicle_registration_no))
                    {
                        updateLsqOpportunity($enquiryId);
                        createLsqActivity($enquiryId);
                    }
                    else
                    {
                        createLsqActivity($enquiryId, 'lead');
                    }
                }
                else
                {
                    if ($journey_stage->stage == STAGE_NAMES['PROPOSAL_DRAFTED'] && ! is_null($agent_details) && isset($agent_details[count($agent_details) - 1]->agent_name) && in_array($agent_details[count($agent_details) - 1]->agent_name, ['embedded_admin', 'embedded_scrub']))
                    {
                        updateLsqOpportunity($enquiryId);
                        createLsqActivity($enquiryId);
                    }  
                }
            }
        }

        return response()->json([
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    public function truncateTable(Request $request, $email)
    {
        if (!app()->environment('local')) {
            abort(500, 'The Enviroment is not Development..!');
        }
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        UserProposal::truncate();
        QuoteLog::truncate();
        UserProductJourney::truncate();
        JourneyStage::truncate();
        WebserviceRequestResponseDataOptionList::truncate();
        WebServiceRequestResponse::truncate();
        SelectedAddons::truncate();
        CorporateVehiclesQuotesRequest::truncate();
        PolicyDetails::truncate();
        CvAgentMapping::truncate();
        return response()->json([
            'status' => true,
            'msg' => 'UserProposal \n
            QuoteLog \n
            UserProductJourney \n
            JourneyStage \n
            WebserviceRequestResponseDataOptionList \n
            WebServiceRequestResponse \n
            SelectedAddons \n
            CorporateVehiclesQuotesRequest \n
            PolicyDetails \n
            CvAgentMapping \n  are Truncated..!'
        ], 200);
    }

    public function getVehicleCategories()
    {
        $data = VehicleCategoriesModel::get()->toArray();

        if (!empty($data)) {
            return response()->json([
                'status' => true,
                'msg' => 'Data found',
                'data' => camelCase($data)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No data found',
                'data' => []
            ]);
        }
    }

    public function getVehicleUsageTypes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicleCategoryId' => ['required', 'numeric']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $data = VehicleUsageTypesModel::where('vehicle_category_id', $request->vehicleCategoryId)->get()->toArray();

        if (!empty($data)) {
            return response()->json([
                'status' => true,
                'msg' => 'Data found',
                'data' => camelCase($data)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'No data found',
                'data' => []
            ]);
        }
    }

    public function cashlessGarage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => ['required'],
            'company_alias' => ['required']
         ]);

         if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        //$enquiryId = customDecrypt($request>enquiryId);
        $product_sub_type_id  = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))
                ->pluck('product_sub_type_id')
                ->first();
        $parent_code = strtolower(get_parent_code($product_sub_type_id));
        $table_name = $request->company_alias.'_'.$parent_code.'_cashless_garage';
        if (Schema::hasTable($table_name))
        {
            $data = DB::table($table_name)
                ->where('address', 'like',  '%' . request()->city_name . '%')
                ->orWhere('pincode', request()->pincode)
                ->get();
            if(empty($data))
            {
                return response()->json([
                    'status' => false,
                    'msg' => 'Cashless Garage List Not Found..!',
                ]);
            }
            $response = [];
            foreach ($data as $key => $value) {
                $response[] = [
                    'garage_name'    => $value->garage_name,
                    'mobile_no'      => $value->mobile,
                    'garage_address' => $value->address,
                    'pincode'        => $value->pincode
                ];
            }

            return response()->json([
                'status' => true,
                'data' => camelCase($response),
                'count' => is_array($response) ? count($response) : 0,
                'msg' => 'Cashless Garage List Retrived Successfully..!',
            ]);
        }
        
        switch($parent_code)
        {
            case 'bike' :
                switch ($request->company_alias) {

                        case 'hdfc_ergo':
                            $data = DB::table('bike_hdfc_ergo_cashless_garage')
                                    ->where('GARAGE_TYPE','Two Wheeler');
                            // ->where(['PIN_CODE' => $request->pincode])
                            if ($request->city_name != '') {
                                $data = $data->where('CITY', 'like', '%' . $request->city_name . '%');
                            } else {
                                $data = $data->where('ADDRESS', 'like', '%' . $request->pincode . '%');
                            }
                            $data = $data->get();
                            $response = [];
                            foreach ($data as $key => $value) {
                                $response[] = [
                                    'garage_name'    => $value->WORKSHOP_NAME,
                                    'mobile_no'      => null,
                                    'garage_address' => $value->ADDRESS,
                                    'pincode'        => $value->PIN_CODE
                                ];
                            }
                            break;

                        case 'future_generali':
                            $data = DB::table('bike_future_generali_cashless_garage')
                                        ->where('Vehicle_Category','Two Wheeler');
                            if ($request->city_name != '') {
                                $data = $data->where('Collated_Address', 'like', '%' . $request->city_name . '%');
                            } else {
                                $data = $data->where('Collated_Address', 'like', '%' . $request->pincode . '%');
                            }
                            $data = $data->get();
                            $response = [];
                            foreach ($data as $key => $value) {
                                $response[] = [
                                    'garage_name' => $value->Workshop_Name_Display_Name,
                                    'mobile_no' => $value->WorkTelephone,
                                    'garage_address' => $value->Collated_Address,
                                    'pincode'        => $value->Postcode
                                ];
                            }
                            break;
                        case 'liberty_videocon':
                            $data = DB::table('bike_liberty_videocon_cashless_garage');
                            if ($request->city_name != '') {
                                $data = $data->where('City_District_Name', 'like', '%' . $request->city_name . '%');
                            } else {
                                $data = $data->where('Pincode', $request->pincode);
                            }
                            $data = $data->get();
                            $response = [];
                            foreach ($data as $key => $value) {
                                $response[] = [
                                    'garage_name' => $value->Workshop_Name,
                                    'mobile_no' => $value->Mobile_No,
                                    'garage_address' => $value->Address,
                                    'pincode'        => $value->Pincode
                                ];
                            }
                            break;
                        case 'godigit':
                            $data = DB::table('bike_godigit_cashless_garage');
                            if ($request->city_name != '') {
                                $data = $data->where('city', 'like', '%' . $request->city_name . '%');
                            } else {
                                $data = $data->where('pincode', $request->pincode);
                            }
                            $data = $data->get();
                            $response = [];
                            foreach ($data as $key => $value) {
                                $response[] = [
                                    'name' => $value->dealer_name,
                                    'mobile_no' => 'NA',
                                    'address' => $value->workshop_address,
                                ];
                            }
                            break;

                        case 'iffco_tokio':
                            $data = DB::table('bike_iffco_tokio_cashless_garage');
                            if ($request->city_name != '') {
                                $data = $data->where('city', 'like', '%' . $request->city_name . '%');
                            } else {
                                $data = $data->where('pincode', $request->pincode);
                            }
                            $data = $data->get();

                            $response = [];
                            foreach ($data as $key => $value) {
                                $response[] = [
                                    'name' => $value->garage_name,
                                    'mobile_no' => $value->mobile,
                                    'address' => $value->address,
                                ];
                            }
                            break;

                        default:
                            return response()->json([
                                'starus' => false,
                                'msg' => 'Company Alias is Invalid...!'
                            ], 500);
                            break;
                    }

                break;//end bike case
            case 'car' :

                switch ($request->company_alias) {

                    case 'bharti_axa':
                    case 'godigit':
                    case 'magma':
                    case 'tata_aig':

                        $ic_name = [
                            'bharti_axa' => 'Bharti Axa',
                            'godigit'    => 'GO DIGIT',
                            'magma'      => 'Magma',
                            'tata_aig'   => 'TATA AIG',
                        ];
                        $ic_name = $ic_name[$request->company_alias];
                        $data = DB::table('car_tata_aig_cashless_garage')
                            ->where('CashLGar_Ic', $ic_name);

                        if ($request->city_name != '') {
                            $data = $data->where('CashLGar_WSAddress', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('CashLGar_WSPincode', $request->pincode);
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->CashLGar_WSName,
                                'mobile_no' => $value->CashLGar_WSCntNumber,
                                'garage_address' => $value->CashLGar_WSAddress,
                                'pincode'        => $value->CashLGar_WSPincode
                            ];
                        }
                        break;
                    case 'hdfc_ergo':
                        $data = DB::table('car_hdfc_ergo_cashless_garage');
                        // ->where(['PIN_CODE' => $request->pincode])
                        if ($request->city_name != '') {
                            $data = $data->where('CITY', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('ADDRESS', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name'    => $value->WORKSHOP_NAME,
                                'mobile_no'      => null,
                                'garage_address' => $value->ADDRESS,
                                'pincode'        => $value->PIN_CODE
                            ];
                        }
                        break;

                    case 'future_generali':
                        $data = DB::table('car_future_generali_cashless_garage');
                        //->where(['Postcode' => $request->pincode])
                        if ($request->city_name != '') {
                            $data = $data->where('Collated_Address', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('Collated_Address', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->Workshop_Name_Display_Name,
                                'mobile_no' => $value->WorkTelephone,
                                'garage_address' => $value->Collated_Address,
                                'pincode'        => $value->Postcode
                            ];
                        }
                        break;
                    case 'liberty_videocon':
                        $data = DB::table('car_liberty_videocon_cashless_garage');
                        if ($request->city_name != '') {
                            $data = $data->where('City_District_Name', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('Pincode', $request->pincode);
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->Workshop_Name,
                                'mobile_no' => $value->Mobile_No,
                                'garage_address' => $value->Address,
                                'pincode'        => $value->Pincode
                            ];
                        }
                        break;

                    case 'shriram':
                        $data = DB::table('shriram_cashless_garage');
                        if ($request->city_name != '') {
                            $data = $data->where('OffCity', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('CustOffAddress', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name'    => $value->Name,
                                'mobile_no'      => $value->MobileNo,
                                'garage_address' => $value->CustOffAddress . ' ' . $value->OffCity . ' ' . $value->State,
                                'pincode'        => null
                            ];
                        }
                        break;

                        default:
                            return response()->json([
                                'starus' => false,
                                'msg' => 'Company Alias is Invalid...!'
                            ], 500);
                            break;
                    }

                break;//end car case
            case 'pcv' :

                switch ($request->company_alias) {

                    case 'bharti_axa':
                    case 'godigit':
                    case 'magma':
                    case 'tata_aig':

                        $ic_name = [
                            'bharti_axa' => 'Bharti Axa',
                            'godigit'    => 'GO DIGIT',
                            'magma'      => 'Magma',
                            'tata_aig'   => 'TATA AIG',
                        ];
                        $ic_name = $ic_name[$request->company_alias];
                        if (!Schema::hasTable('pcv_tata_aig_cashless_garage')) {
                            return response()->json([
                                'status' => false,
                                'msg' => 'No records found.'
                            ]);
                        }
                        $data = DB::table('pcv_tata_aig_cashless_garage')
                            ->where('CashLGar_Ic', $ic_name);

                        if ($request->city_name != '') {
                            $data = $data->where('CashLGar_WSAddress', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('CashLGar_WSPincode', $request->pincode);
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->CashLGar_WSName,
                                'mobile_no' => $value->CashLGar_WSCntNumber,
                                'garage_address' => $value->CashLGar_WSAddress,
                                'pincode'        => $value->CashLGar_WSPincode
                            ];
                        }
                        break;
                    case 'hdfc_ergo':
                        $data = DB::table('pcv_hdfc_ergo_cashless_garage');
                        // ->where(['PIN_CODE' => $request->pincode])
                        if ($request->city_name != '') {
                            $data = $data->where('CITY', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('ADDRESS', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name'    => $value->WORKSHOP_NAME,
                                'mobile_no'      => null,
                                'garage_address' => $value->ADDRESS,
                                'pincode'        => $value->PIN_CODE
                            ];
                        }
                        break;

                    case 'future_generali':
                        $data = DB::table('pcv_future_generali_cashless_garage');
                        //->where(['Postcode' => $request->pincode])
                        if ($request->city_name != '') {
                            $data = $data->where('Collated_Address', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('Collated_Address', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->Workshop_Name_Display_Name,
                                'mobile_no' => $value->WorkTelephone,
                                'garage_address' => $value->Collated_Address,
                                'pincode'        => $value->Postcode
                            ];
                        }
                        break;
                    case 'liberty_videocon':
                        $data = DB::table('pcv_liberty_videocon_cashless_garage');
                        if ($request->city_name != '') {
                            $data = $data->where('City_District_Name', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('Pincode', $request->pincode);
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->Workshop_Name,
                                'mobile_no' => $value->Mobile_No,
                                'garage_address' => $value->Address,
                                'pincode'        => $value->Pincode
                            ];
                        }
                        break;


                    case 'shriram':
                        $data = DB::table('pcv_shriram_cashless_garage');
                        if ($request->city_name != '') {
                            $data = $data->where('OffCity', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('CustOffAddress', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name'    => $value->Name,
                                'mobile_no'      => $value->MobileNo,
                                'garage_address' => $value->CustOffAddress . ' ' . $value->OffCity . ' ' . $value->State,
                                'pincode'        => null
                            ];
                        }
                        break;
                    
                    case 'reliance':
                        $data = DB::table('pcv_reliance_cashless_garage');
                        if ($request->city_name != '') {
                            $data = $data->where('OffCity', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('CustOffAddress', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name'    => $value->Name,
                                'mobile_no'      => $value->MobileNo,
                                'garage_address' => $value->CustOffAddress . ' ' . $value->OffCity . ' ' . $value->State,
                                'pincode'        => null
                            ];
                        }
                        break;

                    default:
                        return response()->json([
                            'starus' => false,
                            'msg' => 'Company Alias is Invalid...!'
                        ], 500);
                        break;
                }
                break;//end pc case
            case 'gcv' :

                switch ($request->company_alias) {

                    case 'bharti_axa':
                    case 'godigit':
                    case 'magma':
                    case 'tata_aig':

                        $ic_name = [
                            'bharti_axa' => 'Bharti Axa',
                            'godigit'    => 'GO DIGIT',
                            'magma'      => 'Magma',
                            'tata_aig'   => 'TATA AIG',
                        ];
                        $ic_name = $ic_name[$request->company_alias];
                        if (!Schema::hasTable('gcv_tata_aig_cashless_garage')) {
                            return response()->json([
                                'status' => false,
                                'msg' => 'No records found.'
                            ]);
                        }
                        $data = DB::table('gcv_tata_aig_cashless_garage')
                            ->where('CashLGar_Ic', $ic_name);

                        if ($request->city_name != '') {
                            $data = $data->where('CashLGar_WSAddress', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('CashLGar_WSPincode', $request->pincode);
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->CashLGar_WSName,
                                'mobile_no' => $value->CashLGar_WSCntNumber,
                                'garage_address' => $value->CashLGar_WSAddress,
                                'pincode'        => $value->CashLGar_WSPincode
                            ];
                        }
                        break;
                    case 'hdfc_ergo':
                        $data = DB::table('hdfc_ergo_cashless_garage');
                        // ->where(['PIN_CODE' => $request->pincode])
                        if ($request->city_name != '') {
                            $data = $data->where('CITY', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('ADDRESS', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name'    => $value->WORKSHOP_NAME,
                                'mobile_no'      => null,
                                'garage_address' => $value->ADDRESS,
                                'pincode'        => $value->PIN_CODE
                            ];
                        }
                        break;

                    case 'future_generali':
                        $data = DB::table('gcv_future_generali_cashless_garage');
                        //->where(['Postcode' => $request->pincode])
                        if ($request->city_name != '') {
                            $data = $data->where('Collated_Address', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('Collated_Address', 'like', '%' . $request->pincode . '%');
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->Workshop_Name_Display_Name,
                                'mobile_no' => $value->WorkTelephone,
                                'garage_address' => $value->Collated_Address,
                                'pincode'        => $value->Postcode
                            ];
                        }
                        break;
                    case 'liberty_videocon':
                        $data = DB::table('gcv_liberty_videocon_cashless_garage');
                        if ($request->city_name != '') {
                            $data = $data->where('City_District_Name', 'like', '%' . $request->city_name . '%');
                        } else {
                            $data = $data->where('Pincode', $request->pincode);
                        }
                        $data = $data->get();
                        $response = [];
                        foreach ($data as $key => $value) {
                            $response[] = [
                                'garage_name' => $value->Workshop_Name,
                                'mobile_no' => $value->Mobile_No,
                                'garage_address' => $value->Address,
                                'pincode'        => $value->Pincode
                            ];
                        }
                        break;

                        case 'shriram':
                            $data = DB::table('gcv_shriram_cashless_garage');
                            if ($request->city_name != '') {
                                $data = $data->where('OffCity', 'like', '%' . $request->city_name . '%');
                            } else {
                                $data = $data->where('CustOffAddress', 'like', '%' . $request->pincode . '%');
                            }
                            $data = $data->get();
                            $response = [];
                            foreach ($data as $key => $value) {
                                $response[] = [
                                    'garage_name'    => $value->Name,
                                    'mobile_no'      => $value->MobileNo,
                                    'garage_address' => $value->CustOffAddress . ' ' . $value->OffCity . ' ' . $value->State,
                                    'pincode'        => null
                                ];
                            }
                            break;

                    default:
                        return response()->json([
                            'status' => false,
                            'msg' => 'Company Alias is Invalid...!'
                        ], 500);
                        break;
                }
                break;//end
        }

        return response()->json([
            'status' => true,
            'data' => camelCase($response ?? []),
            'count' => is_array($response) ? count($response) : 0,
            'msg' => 'Cashless Garage List Retrived Successfully..!',
        ]);
        
        
    }

    public function themeConfig(Request $request)
    {
        $fetch_data = $request->type == 'save' ? false : true;
        if ($fetch_data) {
            $theme_config = \App\Models\ThemeConfig::active()->where('key', $request->key ?? null)->first(['theme_config', 'broker_config', 'otp_config']);
            $theme_config['common_config'] = \App\Models\CommonConfigurations::select('value', 'key')->get()->pluck('value', 'key')->toArray();
            // For now, frontend told to hard code this thing - @AmitE0156 21-11-2023
            $theme_config['feedback'] = [
                'labels' => [
                    "very poor",
                    "poor",
                    "average",
                    "good",
                    "excellent",
                ]
            ];

            if ((config('constants.finsall.IS_FINSALL_ACTIVATED') == 'Y')) {
                $finsall_controller = new \App\Http\Controllers\Admin\Configuration\FinsallConfiguration();
                $theme_config['finsall_config'] = $finsall_controller->getConfigRows();

                // $theme_config['finsall_config'] = json_decode(config('constants.finsall.FINSALL_ALLOWED_PRODUCTS'), 1);

                //finsall configurator is set in route admin/finsall-configuration
            }
            $frontend_constants = \App\Models\FrontendConstant::select('key', 'value', 'datatype')->get()->toArray();
            $data = collect($frontend_constants)->map(function ($v) {
                if ($v['datatype'] == 'boolean') {
                    switch ($v['value']) {
                        case 'true':
                            $v['value'] = true;
                            break;
                        case 'false':
                            $v['value'] = false;
                            break;
                    }
                } elseif ($v['datatype'] == 'null') {
                    $v['value'] = null;
                } elseif ($v['datatype'] == 'Int') {
                    $v['value'] = (int) $v['value'];
                }
                return $v;
            })->pluck('value', 'key');
            $final_data = \Illuminate\Support\Arr::undot($data);
            $brokerConfigAssets = BrokerConfigAsset::select('key', 'value')->get()
                ->pluck('value', 'key')
                ->toArray();
            $commonConfigController = new CommunicationConfigurationController();
            $CommunicationConfiguration = $commonConfigController->getBrokerThemeData();
            $brokerConfigAssets['communication_configuration'] = $CommunicationConfiguration;
            $theme_config = $theme_config->toArray();
            $sellerType = null;

            //Need all seller type url for logo direction when enquiry id is not available on lead page.
            $theme_config['broker_config']['all_logo_url'] = [
                'P' => config('POS_DASHBOARD_LINK'),
                'E' => config('EMPLOYEE_DASHBOARD_LINK'),
                'U' => config('USER_DASHBOARD_LINK'),
                'PARTNER' => config('PARTNER_DASHBOARD_LINK'),
                'MISP' => config('MISP_DASHBOARD_LINK'),
                'B2C' => config('B2C_LEAD_PAGE_URL'),
            ];

            if (isset($request->enquiry_id)) {
                if (is_numeric($request->enquiry_id) && strlen($request->enquiry_id) == 16) {
                    $enquiryId = Str::substr($request->enquiry_id, 8);
                } else {
                    $enquiryId = customDecrypt($request->enquiry_id, true);
                }
                $sellerType = CvAgentMapping::where('user_product_journey_id', $enquiryId)
                    ->pluck('seller_type')
                    ->first();

                if (!empty($sellerType) && strtoupper($sellerType) != 'B2C') {
                    $logoUrlList = [
                        'P' => config('POS_DASHBOARD_LINK'),
                        'E' => config('EMPLOYEE_DASHBOARD_LINK'),
                        'U' => config('USER_DASHBOARD_LINK'),
                        'PARTNER' => config('PARTNER_DASHBOARD_LINK'),
                        'MISP' => config('MISP_DASHBOARD_LINK'),
                    ];
                    if (isset($logoUrlList[strtoupper($sellerType)])) {
                        $theme_config['broker_config']['logo_url']['url'] = $logoUrlList[strtoupper($sellerType)];
                    }
                } else {
                    $theme_config['broker_config']['logo_url']['url'] = config('B2C_LEAD_PAGE_URL');
                }
                //Previous Year Insurer Configurator
                $parentSellerType = HidePyiController::getParentSellerType($sellerType);

                $hidePYI = HidePyi::where('seller_type', $parentSellerType)
                    ->where('status', 'Y')
                    ->first();

                if($hidePYI){
                    $theme_config['broker_config']['ncbconfig'] = 'Yes';
                }
            }

            $vahanConfig = new \App\Http\Controllers\VahanService\VahanServiceController();
            $vahanConfig = $vahanConfig->getVahanServiceList();
            $theme_config['vahanConfig'] = $vahanConfig;
            $theme_config['broker_config']['broker_asset'] = $brokerConfigAssets;

            $godigit_claim_covered = config('godigit_claim_covered');
            if ($godigit_claim_covered) {
                $theme_config['broker_config']['godigit_claim_covered'] = $godigit_claim_covered;
            } else {
                $theme_config['broker_config']['godigit_claim_covered'] = 'ONE';
            }

            if (config('constants.motorConstant.SMS_FOLDER') == 'hero' && $sellerType == "P") {
                $theme_config['vahanConfig']['whatsapp_redirection_pos'] =  "REDIRECT";
            }

            if (config('ENABLE_BROKERAGE_COMMISSION', 'N') == 'Y') {
                $theme_config['broker_config']['isCommissionEnabled'] = true;
            }

            return response()->json([
                'status' => true,
                'msg' => 'Theme Configuration Loaded Successfully...!',
                // 'data' => $request->test == "true" ? $theme_config : $theme_config->theme_config
                'data' => $theme_config,
                'frontend_constants' => (object)$final_data
            ]);
        }
        $validator = Validator::make($request->all(), [
            'theme_config' => 'nullable|json',
            'otp_config' => 'nullable|json',
            'broker_config' => 'nullable|json',
            'key' => 'nullable|string',
            'status' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        // \App\Models\ThemeConfig::truncate();
        $data = [
            'key' => $request->key ?? null,
            'status' => $request->status ?? "Active",
        ];

        if (isset($request->theme_config) && !empty($request->theme_config)) {
            $data = array_merge($data, ['theme_config' => json_decode($request->theme_config, true) ?? []]);
        }

        if (isset($request->otp_config) && !empty($request->otp_config)) {
            $data = array_merge($data, ['otp_config' => json_decode($request->otp_config, true) ?? []]);
        }

        if (isset($request->broker_config) && !empty($request->broker_config)) {
            $data = array_merge($data, ['broker_config' => json_decode($request->broker_config, true) ?? []]);
        }
        $theme_config = \App\Models\ThemeConfig::updateOrCreate(['key' => $request->key ?? null], $data);
        return response()->json([
            'status' => true,
            'msg' => 'Theme Configration Created Successfully...!',
            'data' => [
                'theme_config' =>  $theme_config->theme_config,
                'broker_config' =>  $theme_config->broker_config,
                'otp_config' =>  $theme_config->otp_config,
            ]
            // 'data' => $request->test == "true" ? [
            //     'theme_config' =>  $theme_config->theme_config,
            //     'broker_config' =>  $theme_config->broker_config,
            //     'otp_config' =>  $theme_config->otp_config,
            // ] : $theme_config->theme_config
        ]);
    }

    public function getVehicleDetails(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'enquiryId' => (strlen($request->enquiryId) == 16 && is_numeric($request->enquiryId)) ? 'required|numeric' : 'required|string',
            'registration_no' => 'required',
            'productSubType' => 'nullable|numeric',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        }

        if (checkValidRcNumber($request->registration_no)) {
            return response()->json([
                'status' => false,
                'msg' => 'This Rc Number Blocked On Portal',
            ]);
        }
        $REGISTRATION_NO_CHECK = true;
        if(config('DISABLE_REGISTRATION_NO_CHECK') == 'Y')
        {
            $REGISTRATION_NO_CHECK = false;
        }

        if(!empty($request->skip) && $request->skip == 'Y')
        {
            $REGISTRATION_NO_CHECK = false;
        }
	
        if (!empty($request->registration_no) && $request->registration_no != "NULL" && $REGISTRATION_NO_CHECK) {
            $registrationNoCheck = UtilityApi::registrationNoCheck($request);
            $vehicle_validation = isset($request->vehicleValidation) ? $request->vehicleValidation : 'N';
            if ($registrationNoCheck['status'] == false) {
                $rto_code = $request->registration_no;
                $rto_code_parts = explode('-', $rto_code);
                $rto_code = $rto_code_parts[0] . '-' . $rto_code_parts[1];
                $oldJourneyData = [
                    "enquiryId" => $request->enquiryId,
                    "rto_code" => $rto_code,
                    "registration_no" => $request->registration_no

                ];
                $request = new Request($oldJourneyData);
                $data_response = self::UpdateRegistrationWithNewEnquiryId($request);
                $response_data = $data_response->getData();
                if ($response_data->status === true) {
                    $newTraceId = $response_data->enquiryId ?? null;
                }
                
                if(isset($vehicle_validation) &&  $vehicle_validation == 'Y')
                {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Registration Number Cannot Change For this Enquiry ID',
                        'data' => [
                            'status' => 110,
                            'overrideMsg' => 'Registration Number Cannot Change For this Enquiry ID',
                        ],
                    ]);
                }
                else{
                return response()->json([
                    'show_error' => true,
                    'status' => false,
                    'showMessage' => 'Registration Number Cannot Change For this Enquiry ID',
                    'info' => 'Registration Number Cannot Change For this Enquiry ID',
                    'validation_msg' => $registrationNoCheck['message'],
                    'newEnquiryId'  => $newTraceId ?? null,
                    'data' => [
                        'status' => 101,
                    ],
                ]);
            }
            }
        }

        if (isset($request->tokenResp)) {
            $request['tokenResp'] = gettype($request->tokenResp) != 'array' ? json_decode($request->tokenResp, true) : $request->tokenResp;
        }
        $productSubType = $request->productSubType;
        $registration_no = $request->registration_no;
        $userProductJourneyId = customDecrypt($request->enquiryId);

        if ($request->vehicleValidation == 'Y' && !((isset($request->is_renewal) &&
            $request->is_renewal == 'Y') || (isset($request->action) && $request->action == 'sectionMismatch'))) {
            $agentValidate = new AgentValidateController($userProductJourneyId);
            $agentCheck = $agentValidate->agentValidation($request);

            if ($agentCheck['status'] == false) {
                return response()->json([
                    'status' => false,
                    'overrideMsg' => $agentCheck['message'],
                    "error" => $agentCheck['message']
                ]);
            }
        }
        
        if (config('enableDuplicatePolicyCheck') == 'Y') {
            $check = duplicateVehicleRegistrationNumberPolicyCheck($registration_no, $userProductJourneyId);
            if (!$check['status']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Policy is issued for this vehicle registration number',
                    'data' => [
                        'status' => 102
                    ]
                ]);
            }
        }
        // Need to validate the vehicle
        if($request->vehicleValidation == 'Y' && config('vahanConfiguratorEnabled') == 'Y')
        {
            $vahanController = new \App\Http\Controllers\VahanService\VahanServiceController();
            return $vahanController->hitVahanService($request);
        }
        
        if ($request->vehicleValidation == 'Y' && config('proposalPage.isVehicleValidation') == 'Y') {
            return \App\Http\Controllers\ProposalVehicleValidation::validateVehicle($request);
        }

        // Sending vehicle registration number to LSQ
        if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
        {
            $user_product_journey = UserProductJourney::find($userProductJourneyId);
            $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;
            $journey_stage = $user_product_journey->journey_stage;
            $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;

            if ($lsq_journey_id_mapping && ! is_null($lsq_journey_id_mapping->lead_id) && $journey_stage && isset($journey_stage->stage) && ! in_array($journey_stage->stage, [ STAGE_NAMES['PROPOSAL_DRAFTED'], STAGE_NAMES['PROPOSAL_ACCEPTED'], STAGE_NAMES['PAYMENT_INITIATED']]))
            {
                if ($lsq_journey_id_mapping->lsq_stage != 'Lead Page Submitted')
                {
                    LsqJourneyIdMapping::where('enquiry_id', $userProductJourneyId)
                        ->update([
                            'lsq_stage' => 'Lead Page Submitted'
                        ]);
                }

                if ( ! is_null($lsq_journey_id_mapping->rc_number) && $lsq_journey_id_mapping->rc_number != $request->registration_no)
                {
                    $rc_number = $lsq_journey_id_mapping->rc_number;

                    if ($corporate_vehicles_quote_request && isset($corporate_vehicles_quote_request->vehicle_registration_no) && $rc_number != $corporate_vehicles_quote_request->vehicle_registration_no)
                    {
                        LsqJourneyIdMapping::where('enquiry_id', $userProductJourneyId)
                            ->update([
                                'is_rc_updated' => 1
                            ]);
                    }

                    updateLsqOpportunity($userProductJourneyId, 'RC Submitted', ['rc_number' => $request->registration_no]);
                    createLsqActivity($userProductJourneyId, NULL, 'RC Changed', [
                        'old_rc_number' => $rc_number,
                        'new_rc_number' => $request->registration_no
                    ]); 
                }
                else
                {
                    createLsqOpportunity($userProductJourneyId, 'RC Submitted', ['rc_number' => $request->registration_no]);
                    createLsqActivity($userProductJourneyId, NULL, 'RC Submitted'); 
                }    
            }
        }

        CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update(['journey_without_regno' => 'N']);
        // Need to map agents in this case, as saveQuoteRequestData API is not called - 21-05-2022
        try{
            if ($request->has('tokenResp')) {
                $agentMapping = new AgentMappingController($userProductJourneyId);
                $request->tokenResp = gettype($request->tokenResp) != 'array' ? json_decode($request->tokenResp, true) : $request->tokenResp;
                $agentMapping->mapAgent($request);
            }
        }catch(\Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage()
            ]);
        }

        if (!((isset($request->is_renewal) && $request->is_renewal == 'Y') ||
        (isset($request->action) && $request->action == 'sectionMismatch'))) {
            $agentValidate = new AgentValidateController($userProductJourneyId);
            $agentCheck = $agentValidate->agentValidation($request);

            if ($agentCheck['status'] == false) {
                return response()->json([
                    'status' => false,
                    'overrideMsg' => $agentCheck['message'],
                    "error" => $agentCheck['message']
                ]);
            }
        }
        
        $renewal_check = config('RENEWAL_CHECK');

       
        if(config('constants.motorConstant.SMS_FOLDER') == 'bajaj' && isset($request->is_renewal) && $request->is_renewal == 'Y' && config('FETCH_RENEWAL_DATA_FROM_MIGRATION_TABLE') == 'Y')
        {
            $returned_data =  RenewalController::renewal_data_migration($request);

            if($returned_data['status'])
            {
                $bajaj_renewal_data = $returned_data['data'];
                if(isset($request->isPolicyNumber) && $request->isPolicyNumber == 'Y')
                {
                    $registration_no = getRegisterNumberWithHyphen($bajaj_renewal_data->VEHICLE_REGISTRATION_NUMBER);
                }
            }else
            {
                return $returned_data;
            }

        }
        else if($renewal_check == 'Y')
        {
            if(config('constants.motorConstant.SMS_FOLDER') != 'renewbuy') {
                $return_data = $this->checkRenewal($request);
                if ($return_data instanceof \Illuminate\Http\JsonResponse) {
                    $return_data = json_decode($return_data->getContent(), true);
                }
                if($return_data['status'] == true)
                {
                   return response()->json($return_data);
                }
                else
                {
                    if(isset($return_data['show_error']) && $return_data['show_error'])
                    {
                        return response()->json($return_data);
                    }
                    if(config('constants.motorConstant.ENABLE_VAHAN_AFTER_RENEWAL') != 'Y' && ($request->is_renewal ?? 'N') == 'Y') {
                       return response()->json($return_data);
                    }
                    if (config("RETURN_BACK_IF_NO_VAHAN_SERVICE") == 'Y' ) {
                        return response()->json($return_data);
                    }
                }
            } else if (isset($request->is_renewal) && $request->is_renewal == 'Y')
            {
                if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
                   return $this->checkRenewal($request); 
                }
            }
        }
        
        if(config('vahanConfiguratorEnabled') == 'Y')
        {
            $vahanController = new \App\Http\Controllers\VahanService\VahanServiceController();
            return $vahanController->hitVahanService($request);
        } else {
            return response()->json( [
                'status' => false,
                'msg' => 'Vahan service not configured',
                'data' => [
                    'status' => 101,
                ],
            ]);
        }
        
        if(config('constants.motorConstant.REGISTRATION_DETAILS_SERVICE_TYPE') == 'ongrid'){
            return $this->vehicleDetails($request);
        }

        if(config('constants.motorConstant.REGISTRATION_DETAILS_SERVICE_TYPE') == 'zoop'){
            return $zoopData = ZoopController::getVehicleDetails($request);
        }
        
        if($request->type == 'PRO'){
            return AdrilaController::adrilaProposalRedirection($request);
        }
        if(config('constants.motorConstant.REGISTRATION_DETAILS_SERVICE_TYPE') == 'adrila'){
            return AdrilaController::adrilaJourneyRedirection($request);
        }
        
        if($request->section == 'cv')
        {
            $username = config('cv.constants.fastlane.username_CV') != '' ? config('cv.constants.fastlane.username_CV') : config('constants.fastlane.username');
            $password = config('cv.constants.fastlane.password_CV') != '' ? config('cv.constants.fastlane.password_CV') : config('constants.fastlane.password');
            $url = config('cv.constants.fastlane.url_CV') != '' ? config('cv.constants.fastlane.url_CV') : config('constants.fastlane.url');             
        }
        else
        {
            $username = config('constants.fastlane.username');
            $password = config('constants.fastlane.password');
            $url = config('constants.fastlane.url');
        }
        

        /* $curl = Curl::to($url . '?' . 'regn_no=' . $registration_no)
            ->withHeader('Content-type: application/json')
            ->withHeader('Accept: application/json')
            ->withHeader('Authorization:Basic ' . base64_encode($username . ':' . $password)); */

        $isTenMonthsLogic = config('constants.isTenMonthsLogicEnabled') == 'Y';
        $query = DB::table('registration_details')->where('vehicle_reg_no', $registration_no);
        $query->where(function($subquery) use ($isTenMonthsLogic) {
            $subquery->where('expiry_date', '>=', now()->format('Y-m-d'));
            if ($isTenMonthsLogic) {
                $subquery->orWhere('created_at', '<=', now()->subMonth(10)->format('Y-m-d 00:00:00'));
            }
        });
        $registration_details = $query->latest()->first();
        //$registration_details = NULL;
        if(!empty($registration_details)) {
            $curlResponse = json_decode($registration_details->vehicle_details, true);
        }else{
            // $curlResponse = $curl->get();
                $get_response = getFastLaneData($url . '?' . 'regn_no=' . $registration_no, [
                'enquiryId' => $userProductJourneyId,
                'username' => $username,//config('constants.fastlane.username'),
                'password' => $password,//config('constants.fastlane.password'),
                'reg_no' => $registration_no,
                'section' => trim($request->section)
            ]);
            $curlResponse = $get_response['response'];
            if (empty($curlResponse)) {
                $error_return_data = [];
                $error_return_data['status'] = 101;
                return response()->json([
                    'status' => false,
                    'msg'   => 'API not working.',
                    'data'  => $error_return_data
                ]);
            }
            if(is_string($curlResponse) && str_contains($curlResponse,'HTTP Status 401 - Bad credentials'))
            {
                $error_return_data = [];
                $error_return_data['status'] = 101;
                $error_return_data['data'] = $curlResponse;
                return response()->json([
                    'status' => false,
                    'msg'   => 'HTTP Status 401 - Bad credentials',
                    'data'  => $error_return_data
                ]);
            }
            $curlResponse = json_decode($curlResponse, true);
            if ($curlResponse['status'] != 100) {
                $curlResponse['status'] = 101;
                return response()->json([
                    'status' => false,
                    'msg'   => 'Record not found.',
                    'data'  => $curlResponse
                ]);
            }
            if(isset($curlResponse['results'][0]['vehicle']['vehicle_cd']) && $curlResponse['results'][0]['vehicle']['vehicle_cd'] != NULL)
            {
                DB::table('registration_details')->insert([
                    'vehicle_reg_no' => $registration_no,
                    'vehicle_details' => json_encode($curlResponse),
                    'created_at' => date('Y-m-d H:i:s'),
                    'expiry_date' => isset($curlResponse['results'][0]['insurance']['insurance_upto']) ? Carbon::parse(Str::replace('/', '-', $curlResponse['results'][0]['insurance']['insurance_upto']))->format('Y-m-d') : null,
                ]);                
            }
        }
        if ($curlResponse['status'] != 100) {
                $curlResponse['status'] = 101;
                return response()->json([
                    'status' => false,
                    'msg'   => 'Record not found.',
                    'data'  => $curlResponse
                ]);
        }
        $curlResponse['old_status'] = $curlResponse['status'];
        $vehicle_details = $curlResponse['results'][0];
        $fast_lane_product_code = $curlResponse['results'][0]['vehicle']['fla_vh_class_desc'] ?? '';

        $ft_product_code = '';
        //if($fast_lane_product_code == 'LMV')
        if(in_array($fast_lane_product_code,['LMV','PV']))
        {
           $ft_product_code = 'car';
           $request->productSubType = '1';
        }
        else if($fast_lane_product_code == '2W')
        {
           $ft_product_code = 'bike';
           $request->productSubType = '2';
        }
        else if($fast_lane_product_code == 'PCV')
        {
           $ft_product_code = 'pcv';
           $request->productSubType = '6';
        }
        else if($fast_lane_product_code == 'GCV')
        {
           $ft_product_code = 'gcv';
           $request->productSubType = '9';
        }
        
        // if(config('constants.motorConstant.SMS_FOLDER') == 'abibl' && request()->test == 'demo' ){
        //     $vehicle_details['vehicle']['vehicle_cd'] = '1010101010013011';
        // }

        if (empty($vehicle_details['vehicle']['vehicle_cd'])) {
            if(in_array($ft_product_code,['pcv','gcv']))
            {
               $ft_product_code = 'cv'; 
            }
            $curlResponse['status'] = 101;
            $curlResponse['ft_product_code'] = $ft_product_code;
            return response()->json([
                'status' => false,
                'msg'   => 'Vehicle code not found.',
                'data'  => $curlResponse
            ]);
        }
        //$vehicle_code1 = $vehicle_details['vehicle']['vehicle_cd'];

        $fast_lane_version_code = $vehicle_details['vehicle']['vehicle_cd'];
        $version_code = '';
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
        } else if ($env == 'test' ||  $env == 'live') {
            $env_folder = 'production';
        }
        $product = strtolower(get_parent_code($request->productSubType));
        $product = $product == 'car' ? 'motor' : $product;
        if(empty($product))
        {
           $product = strtolower($ft_product_code);
        }
        
        $path = 'mmv_masters/' . $env_folder . '/';
        $file_name  = $path . $product . '_model_version.json';
        //$data = json_decode(file_get_contents($file_name), true);
        $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
        $result = [];
//        if ($data) {
//            foreach ($data as $value)
//            {
//                if ($product == 'bike' && $value['mmv_fastlane_2wl'] == $fast_lane_version_code) {
//                   $version_code = $value['version_id'];
//                   break;
//                }
//                else if($product == 'motor' && $value['mmv_fastlane_car'] == $fast_lane_version_code) {
//                    $version_code = $value['version_id'];
//                    break;
//                }
//            }
//        }
        $is_version_available = "Y";
        if($ft_product_code == 'car' || $ft_product_code == 'bike')
        {
            $file_name  = $path .'fyntune_fastlane_'.$ft_product_code.'_relation.json';
            //$data = json_decode(file_get_contents($file_name), true);
            $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
            $is_version_available = $data[$fast_lane_version_code]['is_version_available'] ?? null;
            $version_code = $data[$fast_lane_version_code]['fyntune_version_id'] ?? '';
        }
        else
        {
            $version_code = $fast_lane_version_code;
        }

        $registration_date = date('d-m-Y', strtotime(str_replace('/', '-', $vehicle_details['vehicle']['regn_dt'])));
        if (isset($vehicle_details['insurance']['insurance_upto']) && !empty($vehicle_details['insurance']['insurance_upto'])) {
            $policy_expiry = date('d-m-Y', strtotime(str_replace('/', '-', $vehicle_details['insurance']['insurance_upto'])));

            $date1 = new \DateTime($registration_date);
            $date2 = new \DateTime(date('d-m-Y'));
            $interval = $date1->diff($date2);
            $car_age = (($interval->y * 12) + $interval->m) + 1; // In Months
            $car_age = $car_age/12;
            $previous_ncb = 0;

            if($car_age <= 1)
            {
                $previous_ncb = 0;
                $applicable_ncb = 20;
            }
            else if($car_age <= 2)
            {
                $previous_ncb = 20;
                $applicable_ncb = 25;
            }
            else if($car_age <= 3)
            {
                $previous_ncb = 25;
                $applicable_ncb = 35;
            }
            else if($car_age <= 4)
            {
                $previous_ncb = 35;
                $applicable_ncb = 45;
            }
            else if($car_age <= 5)
            {
                $previous_ncb = 45;
                $applicable_ncb = 50;
            }
            else
            {
                $previous_ncb = 50;
                $applicable_ncb = 50;
            }

//            if($car_age < 20){
//                $previous_ncb = 0;
//                $applicable_ncb = 20;
//            }elseif($car_age < 32){
//                $previous_ncb = 20;
//                $applicable_ncb = 25;
//            }elseif($car_age < 44){
//                $previous_ncb = 25;
//                $applicable_ncb = 35;
//            }elseif($car_age < 56){
//                $previous_ncb = 35;
//                $applicable_ncb = 45;
//            }elseif($car_age < 68){
//                $previous_ncb = 45;
//                $applicable_ncb = 50;
//            }else{
//                $previous_ncb = 50;
//                $applicable_ncb = 50;
//            }

        }else{
            $policy_expiry = null;
            $previous_ncb = null;
            $applicable_ncb = null;
        }
        #sometimes we are getting maf year as 0 04-11-2022@hrishikesh
        $manf_year = date('m-Y', strtotime(str_replace('/', '-', $vehicle_details['vehicle']['regn_dt'])));
        if($vehicle_details['vehicle']['manu_yr'] > 0)
        {
         $manf_year = date('m', strtotime($registration_date)) . '-' . $vehicle_details['vehicle']['manu_yr'];
        }
        
        $reg_no = explode('-', $registration_no);
        $rto_code = implode('-', [$reg_no[0], $reg_no[1]]);
        $rto_name = MasterRto::where('rto_code', $rto_code)->pluck('rto_name')->first();
//        if (!$rto_name) {
//            if(in_array($ft_product_code,['pcv','gcv']))
//            {
//               $ft_product_code = 'cv'; 
//            }
//            $curlResponse['status'] = 101;
//            $curlResponse['ft_product_code'] = $ft_product_code;
//            return response()->json([
//                'status' => false,
//                'msg'   => 'RTO details not found',
//                'data'  => $curlResponse
//            ]);
//        }
        // Fetch company alias from
        $previous_insurer = $previous_insurer_code = NULL;
 
        if(isset($vehicle_details['insurance']['insurance_comp']) || isset($vehicle_details['insurance']['fla_insurance_comp'])){           
            $fastlane_ic_name =(isset($vehicle_details['insurance']['insurance_comp']) && !empty($vehicle_details['insurance']['insurance_comp']) ? $vehicle_details['insurance']['insurance_comp']:$vehicle_details['insurance']['fla_insurance_comp']) ;
            if (!empty($fastlane_ic_name)) {
                $fyntune_ic = DB::table('fastlane_previous_ic_mapping as m')->whereRaw('? LIKE CONCAT("%", m.identifier, "%")', $fastlane_ic_name)->first();
                if ($fyntune_ic) {
                    $previous_insurer = $fyntune_ic->company_name;
                    $previous_insurer_code = $fyntune_ic->company_alias;
                }
            }
        }  
        $expiry_date = \Carbon\Carbon::parse($policy_expiry);
        $today_date = now()->subDay(1);

        if ($expiry_date < $today_date) {
            $businessType = 'breakin';
            $diff = $expiry_date->diffInDays(now());
            if($diff > 90)
            {
                $previous_ncb = 0;
                $applicable_ncb = 0;
            }
        }
        else
        {
            $businessType = 'rollover';
        }

        if($ft_product_code == 'car')
        {
            $od_compare_date = now()->subYear(3)->subDay(45);
        }
        else if($ft_product_code == 'bike')
        {
            $od_compare_date = Carbon::parse('01-09-2018')->subDay(1);
        }
        else
        {
          $od_compare_date = now();  
        }

        if(Carbon::parse($registration_date) > $od_compare_date)
        {
            $policy_type = 'own_damage';
        }
        else
        {
           $policy_type = 'comprehensive';
        }
        
        $renewal_days = today()->addDay(60);
        if(Carbon::parse($policy_expiry)->format('Y-m-d') > $renewal_days)
        {
           $policy_expiry = ''; 
        }

        if($policy_type == 'own_damage')
        {
           $policy_expiry = '';
        }
        CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
            'version_id' => $is_version_available == "Y" ? $version_code : null,
            'policy_type' => $policy_type,
            'business_type' => $businessType,
            'vehicle_register_date' => $vehicle_details['vehicle']['regn_dt'] == '' ? NULL : $registration_date,
            'vehicle_registration_no' => $registration_no,
            'previous_policy_expiry_date' => $policy_expiry, //'11-11-2021',
            'previous_policy_type' => 'Comprehensive',
            'fuel_type' => $vehicle_details['vehicle']['fla_fuel_type_desc'], //'PETROL',
            'manufacture_year' => $manf_year,
            'rto_code' => isBhSeries($rto_code) ? null : $rto_code,
            'rto_city' => isBhSeries($rto_name) ? null : $rto_name,
            'previous_ncb' => $previous_ncb,
            'applicable_ncb' => $applicable_ncb,
            'previous_insurer' => $previous_insurer,
            'previous_insurer_code' => $previous_insurer_code,
            'journey_type' => 'fastlane',
            'zero_dep_in_last_policy' => 'Y',
            'is_ncb_verified' => $applicable_ncb > 0 ? 'Y' : 'N'
        ]);

        // TODO : Fetch Commercial vehicle's product subtype id
        $quote_log = QuoteLog::where('user_product_journey_id', $userProductJourneyId)->first();
        if(!$quote_log) {
            $curlResponse['status'] = 101;
            return response()->json([
                'status' => false,
                'msg'   => 'Quote log entry not found.',
                'data' => $curlResponse
            ]);
        }
        
        $mmv_details = get_fyntune_mmv_details($request->productSubType,  $version_code);
        if (!$mmv_details['status']) {
            //If journey is of car and bike RC number is searched then we'll redirect it to bike page and again hit the service, for that we need to pass 'status' as 100 - @Amit:08-03-2022
            //$curlResponse['status'] = $ft_product_code != '' ? 100 : 101;
            if(in_array($ft_product_code,['pcv','gcv']))
            {
               $ft_product_code = 'cv'; 
            }
            $curlResponse['ft_product_code'] = $ft_product_code;
            $status_bool = true;
            $curlResponse['status'] = 100;
            
            if(strtolower($ft_product_code) == strtolower($request->section))
            {
                $status_bool = false;
                $curlResponse['status'] = 101;                
            }
            
            if($version_code == NULL)
            {
                $status_bool = false;
                $curlResponse['status'] = 101;
            }
            return response()->json([
                'status' => $status_bool,//$ft_product_code != '' ? true : false,
                'msg'   => 'MMV details not found. - '. $version_code,
                'data' => $curlResponse
            ]);
        }
        $mmv_details = $mmv_details['data'];
        // $request->productSubType = $productSubType;

        $request->productSubType = !empty($productSubType) ? $productSubType : $request->productSubType;

        $quote_data = [
            "product_sub_type_id" => $request->productSubType,
            "manfacture_id" => $mmv_details['manufacturer']['manf_id'],
            "manfacture_name" => $mmv_details['manufacturer']['manf_name'],
            "model" => $mmv_details['model']['model_id'],
            "model_name" => $mmv_details['model']['model_name'],
            "version_id" => $is_version_available == "Y" ? $version_code :  null,
            "vehicle_usage" => 2,
            "policy_type" => $policy_type,
            "business_type" => $businessType,
            "version_name" => $is_version_available == "Y" ? $mmv_details['version']['version_name'] : null,
            "vehicle_register_date" => $registration_date,
            "previous_policy_expiry_date" => $policy_expiry,
            "previous_policy_type" => "Comprehensive",
            "fuel_type" => $is_version_available == "Y" ? $mmv_details['version']['fuel_type'] : null,
            "manufacture_year" => $manf_year,
            "rto_code" => isBhSeries($rto_code) ? null : $rto_code,
            "vehicle_owner_type" => "I",
            "is_claim" => "N",
            "previous_ncb" => $previous_ncb,
            "applicable_ncb" => $applicable_ncb,
        ];

        $quote_log->quote_data = json_encode($quote_data);
        $quote_log->save();

        $curlResponse['results'][0]['vehicle']['regn_dt'] = $registration_date;
        $curlResponse['results'][0]['vehicle']['vehicle_cd'] = $is_version_available == "Y" ? $version_code : null;
        $curlResponse['results'][0]['vehicle']['fla_maker_desc'] = $quote_data['manfacture_name'];
        $curlResponse['results'][0]['vehicle']['fla_model_desc'] = $quote_data['model_name'];
        $curlResponse['results'][0]['vehicle']['fla_variant'] = $is_version_available == "Y" ? $quote_data['version_name'] : null;
        $curlResponse['results'][0]['insurance']['insurance_upto'] = $policy_expiry;
        $return_data = [
            'firstName'         => $vehicle_details['vehicle']['owner_name'] ?? NULL,            
            'lastName'          => '',
            'fullName'          => $vehicle_details['vehicle']['owner_name'] ?? NULL,            
            'mobileNo'          => '',
            'applicableNcb'     => $applicable_ncb,
            'businessType'      => $businessType,
            'emailId'           => '',
            'enquiryId'         => $request->enquiryId,            
            'fuelType'          => $quote_data['fuel_type'],            
            'hasExpired'        => "no",
            'isClaim'           => "N",
            'isNcb'             => "Yes",
            'leadJourneyEnd'    => true,
            'manfactureId'      => $quote_data['manfacture_id'],
            'manfactureName'    => $quote_data['manfacture_name'],
            'manufactureYear'   => $quote_data['manufacture_year'],
            'model'             => $quote_data['model'],
            'modelName'         => $quote_data['model_name'],
            'ownershipChanged'  => "N",
            'policyExpiryDate'  => $policy_expiry,
            'policyType'        => $policy_type,
            'previousNcb'           => $previous_ncb,
            'previousPolicyType'    => "Comprehensive",
            'productSubTypeId'      => $request->productSubType,
            'rto'                   => isBhSeries($rto_code) ? null : $rto_code,
            'stage'                 => 11,
            'userProductJourneyId'  => $request->enquiryId,
            //'vehicleLpgCngKitValue' => "",
            'vehicleOwnerType'      => "I",
            'vehicleRegisterAt'     => isBhSeries($rto_code) ? null : $rto_code,
            'vehicleRegisterDate'   => $registration_date,
            'vehicleRegistrationNo' => $registration_no,
            'vehicleUsage'          => 2,
            'version'               => $is_version_available == "Y" ? $version_code : null,
            'versionName'           => $is_version_available == "Y" ? $quote_data['version_name'] : null,
            'previous_insurer'      => $previous_insurer,
            'previous_insurer_code' => $previous_insurer_code,
            'address_line1'         => $vehicle_details['vehicle']['cAddress'] ?? NULL,
            'address_line2'         => '',
            'address_line3'         => '',
            'pincode'               => ''
        ];
        $is_vehicle_finance = '';
        $name_of_financer = '';
        if(isset($vehicle_details['hypth']['fncr_name']) && $vehicle_details['hypth']['fncr_name'] != NULL)
        {
            $is_vehicle_finance = 1;
            $name_of_financer = $vehicle_details['hypth']['fncr_name'];
        }
        $curlResponse['additional_details'] = $return_data;
        UserProposal::updateOrCreate(
            [ 'user_product_journey_id' => $userProductJourneyId ],
            [
            'first_name'                    => $vehicle_details['vehicle']['owner_name'] ?? NULL,
            'last_name'                     => '',
            'fullName'                      => $vehicle_details['vehicle']['owner_name'] ?? NULL,
            'applicable_ncb'                => $applicable_ncb,
            'is_claim'                      => 'N',
            'rto_location'                  => isBhSeries($rto_code) ? null : $rto_code,
            // 'additional_details' => json_encode($return_data),
            'vehicale_registration_number'  => $registration_no,
            'vehicle_manf_year'             => $quote_data['manufacture_year'],
            'previous_insurance_company'    => $previous_insurer,
            'prev_policy_expiry_date'       => $policy_expiry,
            'engine_number'                 => removeSpecialCharactersFromString($vehicle_details['vehicle']['eng_no']),
            'chassis_number'                => removeSpecialCharactersFromString($vehicle_details['vehicle']['chasi_no']),
            'previous_policy_number'        => isset($vehicle_details['insurance']['insurance_policy_no']) ? $vehicle_details['insurance']['insurance_policy_no'] : '',
            'vehicle_color'                 => removeSpecialCharactersFromString($vehicle_details['vehicle']['color'], true),
            'is_vehicle_finance'            => $is_vehicle_finance,
            'name_of_financer'              => $name_of_financer,
            'address_line1'                 => $vehicle_details['vehicle']['cAddress'] ?? NULL,
            'address_line2'                 => '',
            'address_line3'                 => '',
            //'pincode'                       => ''
        ]);
        if(config('constants.motorConstant.SMS_FOLDER') == 'bajaj' && isset($request->is_renewal) && $request->is_renewal == 'Y' && config('FETCH_RENEWAL_DATA_FROM_MIGRATION_TABLE') == 'Y')
        {
            $previous_policy_type_renewal = [
                'Comprehensive' => 'COMPREHENSIVE',
                'Third-party' => 'TP',
                'Own-damage' => 'OD',
            ];

            $previouspolicytype = array_search($bajaj_renewal_data->PREV_POLICY_TYPE,$previous_policy_type_renewal);

            $curlResponse['additional_details']['previous_insurer'] = $bajaj_renewal_data->previous_insurer;
            $curlResponse['additional_details']['previous_insurer_code'] = $bajaj_renewal_data->previous_insurer_code;
            $curlResponse['additional_details']['policyExpiryDate'] = $bajaj_renewal_data->POLICY_END_DATE;
            $curlResponse['RenwalData'] = 'Y';
            UserProposal::updateOrCreate(
                [ 'user_product_journey_id' => $userProductJourneyId ],
                [
                'previous_policy_number' => $bajaj_renewal_data->POLICY_NUMBER,
                'prev_policy_expiry_date' => $bajaj_renewal_data->POLICY_END_DATE, 
            ]);

            $expiry_date_renewal = \Carbon\Carbon::parse($bajaj_renewal_data->POLICY_END_DATE);
            $today_date_renewal = now()->subDay(1);
            if ($expiry_date_renewal < $today_date_renewal) 
            {
                $businessType = 'breakin';
            }
            else
            {
                $businessType = 'rollover';
            }
            $curlResponse['additional_details']['businessType'] = $businessType;
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
                'previous_policy_type' => !empty($previouspolicytype) ? $previouspolicytype :  'Comprehensive',
                'previous_policy_expiry_date' => $bajaj_renewal_data->POLICY_END_DATE,
                'business_type' => $businessType,
            ]);
            $curlResponse['additional_details']['previousPolicyType'] =!empty($previouspolicytype) ? $previouspolicytype :  'Comprehensive';
        }
        if(in_array($ft_product_code,['pcv','gcv']))
        {
           $ft_product_code = 'cv'; 
        }
        $curlResponse['ft_product_code'] = $ft_product_code;
        if (isset($policy_type) &&  !empty($registration_date) && strtoupper($policy_type) == 'Own-damage') {
            $registerDate = Carbon::createFromFormat('d-m-Y', $registration_date);
            $now = Carbon::now();
            $monthsSinceRegistration = $registerDate->diffInMonths($now);

            if ($monthsSinceRegistration >= 10 && $monthsSinceRegistration <= 12) {
                $curlResponse['additional_details']['previousPolicyType'] = $previouspolicytype;
            }
        } else {

            $curlResponse['additional_details']['previousPolicyType'] = "";
        }
        return response()->json([
            'status' => true,
            'msg'   => $curlResponse['description'],
            'data'  => $curlResponse
        ]);
    }

    public function policyPdfUpload(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            // 'status' => 'required',
            'pdf_file' => 'required', #required_if:status,success
            'policy_no' => 'required',
            'admin_id' => 'required',
            'admin_name' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        DB::beginTransaction();
        try {

            if(is_numeric($request->enquiryId) && strlen($request->enquiryId) == 16)
            {
                $request->enquiryId = Str::substr($request->enquiryId, 8);
            }
            else
            {
                $request->enquiryId = customDecrypt($request->enquiryId,true);
            }
            //$request->enquiryId = customDecrypt($request->enquiryId);

            $user_proposal = UserProposal::where('user_product_journey_id', $request->enquiryId)->first();

            if (empty($user_proposal)) {
                return response()->json([
                    'status' => false,
                    'msg' => 'User Proposal Data Not Fount...!',
                ]);
            }

            $ic_data = DB::table('master_company')
                ->where('company_id', $user_proposal->ic_id)
                ->select('company_alias')
                ->first();

            if (empty($ic_data)) {
                return response()->json([
                    'status' => false,
                    'msg' => 'ic data Data Not Fount...!',
                ]);
            }

            $section = DB::table('corporate_vehicles_quotes_request')
                ->where('user_product_journey_id', $user_proposal->user_product_journey_id)
                ->join('master_product_sub_type', 'master_product_sub_type.product_sub_type_id', '=', 'corporate_vehicles_quotes_request.product_id')
                ->select('product_sub_type_code')
                ->first();

            $section = \Illuminate\Support\Str::ucfirst($section->product_sub_type_code);

            switch ($section) {
                case 'CAR':
                    $path = config('constants.motorConstant.CAR_PROPOSAL_PDF_URL');
                    break;
                case 'BIKE':
                    $path = config('constants.motorConstant.BIKE_PROPOSAL_PDF_URL');
                    break;
                default:
                    $path = config('constants.motorConstant.CV_PROPOSAL_PDF_URL');
                    break;
            }


            $company_alies = $ic_data->company_alias;
            $file_url = $path . $company_alies . '/'. md5($user_proposal->user_proposal_id). '.pdf';
            Storage::put($file_url , base64_decode($request->pdf_file));
            $data['user_product_journey_id'] = $user_proposal->user_product_journey_id;
            $data['stage'] = STAGE_NAMES['POLICY_ISSUED'];
            updateJourneyStage($data);

            PaymentRequestResponse::where([
                'user_proposal_id' => $user_proposal->user_proposal_id,
                'user_product_journey_id' => $user_proposal->user_product_journey_id,
                'active' => 1
            ])
            ->update([
                'status' => STAGE_NAMES['PAYMENT_SUCCESS']
            ]);
            PolicyDetails::updateOrCreate(['proposal_id' => $user_proposal->user_proposal_id],
                [
                'proposal_id' => $user_proposal->user_proposal_id,
                'policy_number' => $request->policy_no,
                'pdf_url' => $file_url,
                'status' => 'SUCCESS',
                'rehit_source' => 'MANUALLY PDF ADDED'
            ]);
//            where(['proposal_id' => $user_proposal->user_proposal_id])->update([
//                'policy_number' => $request->policy_no,
//                'pdf_url' => $file_url,
//                'status' => 'Success'
//            ]);

            DB::commit();
            return response()->json([
                'status' => true,
                'msg'   => "Policy Pdf Upload Successfully...!",
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => false,
                'msg'   => $e->getMessage() . ' '. $e->getLine() . ' ' . $e->getFile(),
            ]);
        }
    }

    public static function getAgents()
    {
        \Illuminate\Support\Facades\DB::enableQueryLog();
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1800); // 5 minutes
        $dashboard_agent_link = config('DASHBOARD_GET_AGENT_LINK');
        // $all_agents_data = Http::withoutVerifying()->get($dashboard_agent_link)->json();
        //$all_agents_data = httpRequestNormal($dashboard_agent_link, 'GET', [], [], [], [], false);

        $bool = config('constants.motorConstant.BROKER_USER_CREATION_API_no_proxy') == 'true' ? true : false;
        $all_agents_data = $bool
                ? Http::withoutVerifying()->get($dashboard_agent_link)->json()
                : httpRequestNormal($dashboard_agent_link, 'GET', [], [], [], [], false)['response'];
        if (isset($all_agents_data['data']) && !empty($all_agents_data['data'])) {
            foreach ($all_agents_data['data'] as $agent_data) {
                Agents::updateOrCreate( // if agent_id is already exist then update else create 
                    ['agent_id' => $agent_data['agent_id']],
                        [
                            'agent_name'            => $agent_data['agent_name'],
                            'first_name'            => $agent_data['first_name'] ?? null,
                            'middle_name'            => $agent_data['middle_name'] ?? null,
                            'last_name'            => $agent_data['last_name'] ?? null,
                            'unique_number'         => $agent_data['unique_number'],
                            'user_name'             => $agent_data['user_name'],
                            'father_name'           => $agent_data['father_name'],
                            'gender'                => $agent_data['gender'],
                            'phone_no'              => $agent_data['phone_no'],
                            'mobile'                => $agent_data['mobile'],
                            'email'                 => $agent_data['email'],
                            'date_of_birth'         => $agent_data['date_of_birth'],
                            'marital_status'        => $agent_data['marital_status'],
                            'pan_no'                => $agent_data['pan_no'],
                            'aadhar_no'             => $agent_data['aadhar_no'],
                            'address'               => $agent_data['address'],
                            'city'                  => $agent_data['city'],
                            'state'                 => $agent_data['state'],
                            'pincode'               => $agent_data['pincode'],
                            'parent'                => $agent_data['parent'],
                            'level'                 => $agent_data['level'],
                            'usertype'              => $agent_data['usertype'],
                            'supervisoer_name'      => $agent_data['supervisoer_name'] ?? NULL,
                            'supervisoer_emp_code'  => $agent_data['supervisoer_emp_code'] ?? NULL,
                            'supervisoer_mobile'    => $agent_data['supervisoer_mobile'] ?? NULL,
                            'rm_branch'             => $agent_data['rm_branch'] ?? NULL,
                            'allowed_sections'      => $agent_data['allowed_sections'] ?? NULL,
                            'comm_percent'          => $agent_data['comm_percent'] ?? NULL,
                            'status'                => $agent_data['status'],
                            'licence_start_date'    => $agent_data['licence_start_date'] ?? NULL,
                            'licence_end_date'      => $agent_data['licence_end_date'] ?? NULL,
                        ]
                );

                if(isset($agent_data['relation_sbi']) && $agent_data['relation_sbi'] != '')
                {
                    AgentIcRelationship::updateOrCreate(
                        ['agent_id' => $agent_data['agent_id']],
                            [
                                'sbi_code' => $agent_data['relation_sbi'],
                            ]
                    );
                }
                if(isset($agent_data['relation_oriental']) && $agent_data['relation_oriental'] != '')
                {
                    AgentIcRelationship::updateOrCreate(
                        ['agent_id' => $agent_data['agent_id']],
                            [
                                'oriental_code' => $agent_data['relation_oriental'],
                            ]
                    );
                }
                if(isset($agent_data['relation_hdfc_ergo']) && $agent_data['relation_hdfc_ergo'] != '')
                {
                    AgentIcRelationship::updateOrCreate(
                        ['agent_id' => $agent_data['agent_id']],
                            [
                                'hdfc_ergo_code' => $agent_data['relation_hdfc_ergo'],
                            ]
                    );
                }
                if(isset($agent_data['relation_new_india']) && $agent_data['relation_new_india'] != '')
                {
                    AgentIcRelationship::updateOrCreate(
                        ['agent_id' => $agent_data['agent_id']],
                            [
                                'new_india_code' => $agent_data['relation_new_india'],
                            ]
                    );
                }
                if(isset($agent_data['licence_start_date']) && $agent_data['licence_start_date'] != '')
                {
                    Agents::updateOrCreate(
                        ['agent_id' => $agent_data['agent_id']],
                            [
                                'licence_start_date' => $agent_data['licence_start_date'],
                                'licence_end_date' => $agent_data['licence_end_date'],
                            ]
                    );
                }
            }
            return [
                'status' => true,
                'message' => \Illuminate\Support\Facades\DB::getQueryLog(),
            ];
        }
        else
        {
            return [
                'status' => false,
                'message' => "Not getting valid response form dashboard API",
            ];
        }
    }
    public static function findMyWord($s, $w) {
        if (stripos($s, $w) !== false) {
           return true;
        } else {
            return false;
        }
    }
    public function vehicleDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'enquiryId' => 'required|numeric',
            'enquiryId' => (strlen($request->enquiryId) == 16 && is_numeric($request->enquiryId)) ? 'required|numeric' : 'required|string',
            'registration_no' => 'required',
            'productSubType' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $userProductJourneyId = customDecrypt($request->enquiryId);
        $regi_no = explode('-', $request->registration_no);
        if ($regi_no['0'] == 'DL') {
            $regi_no['1'] = $regi_no['1'] * 1;
        }
        $enquiryId = customDecrypt($request->enquiryId);

        $save_mmv = true;

        if (isset($request->vehicleValidation) && $request->vehicleValidation == 'Y' && config('constants.IS_OLA_BROKER') == 'Y')
        {
            $save_mmv = false;
        }

        $registration_no = implode('', $regi_no);
        $startTime = new DateTime(date('Y-m-d H:i:s'));
        // For car, bike and CV, ongrid maintains different mapping ids. We'll have to use those accordingly.
        $sections = [
            'bike' => 'ongrid-bike',
            'car' => 'ongrid-car',
            'cv' => 'ongrid'
        ];
        $section = $sections[trim($request->section)] ?? 'ongrid';
        $isTenMonthsLogic = config('constants.isTenMonthsLogicEnabled') == 'Y';
        $query = \Illuminate\Support\Facades\DB::table('registration_details')->where('vehicle_reg_no', $request->registration_no)->where('vehicle_details', 'LIKE', '%Extracted details.%');
        $query->where(function($subquery) use ($isTenMonthsLogic) {
            $subquery->where('expiry_date', '>=', now()->format('Y-m-d'));
            if ($isTenMonthsLogic) {
                $subquery->orWhere('created_at', '<=', now()->subMonth(10)->format('Y-m-d 00:00:00'));
            }
        });
        $response = $query->latest()->first();
        $array_type = [
            'PCV' => 'cv',
            'GCV' => 'cv',
            'CRP' => 'car',
            'BYK' => 'bike'
        ];
        if ($response == null) {
            $service = 'online';
            $response = httpRequest($section, ['rc_number' => $registration_no], [] ,[], [], false);
            $url = $response["url"];
            $response = $response["response"];
            // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
            // because Ongrid maintains different mapping code for seperate vehicle type - 23-06-2022
            if (isset($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'])) {                
                $sub_string = Str::substr($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'], 0, 3);
                if (isset($array_type[$sub_string]) && $array_type[$sub_string] == trim($request->section)) {
                    DB::table('registration_details')->insert([
                        'vehicle_reg_no' => $request->registration_no,
                        'vehicle_details' => json_encode($response),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? null
                    ]);
                }
            }
        } else {
            $service = 'offline';
            $response = json_decode($response->vehicle_details, true);
            // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
            // because Ongrid maintains different mapping code for seperate vehicle type - 23-06-2022
            if (isset($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'])) {
                $sub_string = Str::substr($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'], 0, 3);
                if (isset($array_type[$sub_string]) && $array_type[$sub_string] != trim($request->section)) {
                    // Delete the existing dirty data and again hit the ongrid - 14-07-2022
                    DB::table('registration_details')->where('vehicle_reg_no', $request->registration_no)->delete();
                    $service = 'online';
                    $response = httpRequest($section, ['rc_number' => $registration_no], [] ,[], [], false);
                    $url = $response["url"];
                    $response = $response["response"];
                    // If journey is of car and user is entering bike RC number then we don't have to save it to DB,
                    // because Ongrid maintains different mapping code for seperate vehicle type - 23-06-2022
                    if (isset($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'])) {
                        $sub_string = Str::substr($response['data']['rc_data']['vehicle_data']['custom_data']['version_id'], 0, 3);
                        if (isset($array_type[$sub_string]) && $array_type[$sub_string] == trim($request->section)) {
                            DB::table('registration_details')->insert([
                                'vehicle_reg_no' => $request->registration_no,
                                'vehicle_details' => json_encode($response),
                                'created_at' => now(),
                                'updated_at' => now(),
                                'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? null
                            ]);
                        }
                    }
                }
            }
        }
        $og_response = $response;
        $endTime = new DateTime(date('Y-m-d H:i:s'));

        $responseTime = $startTime->diff($endTime);
        if ($service == 'online') {
            FastlaneRequestResponse::insert([
                'enquiry_id' => $enquiryId,
                'request' => $request->registration_no,
                'response' => json_encode($og_response),
                'transaction_type' => "Ongrid Service",
                'endpoint_url' => $url,
                'ip_address' => $request->ip(),
                'section' => $request->section,
                'response_time' => $responseTime->format('%Y-%m-%d %H:%i:%s'),
                'created_at' => now(),
            ]);
        }

        if (!isset($response['data'])) {
            return response()->json([
                'status' => false,
                'msg' => $response['error']['message'] ?? null,
            ], 500);
        }

        if (!isset($response['data']['rc_data'])) {
            return response()->json([
                'status' => false,
                'msg' => $response['data']['message'] ?? null,
            ], 500);
        }

        $date1 = new DateTime($response['data']['rc_data']['issue_date']);
        $date2 = new DateTime();
        $interval = $date1->diff($date2);
        // if($interval->y == 0 && $interval->m < 9)
        // {
        //     return response()->json([
        //         'status' => false,
        //         'msg' => 'Vehicle ('.$request->registration_no.') not allowed within 9 Months. Vehicle Registration date is '.$response['data']['rc_data']['issue_date'].' ',
        //         'overrideMsg' => 'Vehicle ('.$request->registration_no.') not allowed within 9 Months. Vehicle Registration date is '.$response['data']['rc_data']['issue_date'].' '
        //     ]);
        // }

        $response = $response['data']['rc_data'];
        $sectiontype = 'cv';

        $section_found = false;
        $vehicle_section = DB::table('fastlane_vehicle_description')->where('description', $response['vehicle_data']['category_description'])->first();
        // Currently Vehicle description is available for Car and bike - 24-06-2022 
        // Description added for CV as well - 18-09-2022
        if (!empty($vehicle_section)) {
            $section_found = true;
            $sectiontype = $vehicle_section->section;
            if (in_array(trim($response['vehicle_data']['category_description']), ['Private Service Vehicle']) && ($response['vehicle_data']['category'] ?? '') == 'Individual Use') {
                $sectiontype = 'car';
            }
        } else if (self::findMyWord($response['vehicle_data']['category_description'], 'LMV') || self::findMyWord($response['vehicle_data']['category_description'], 'Motor Car')) {
            $section_found = true;
            $sectiontype = 'car';
        } else if (self::findMyWord($response['vehicle_data']['category_description'], 'SCOOTER') || self::findMyWord($response['vehicle_data']['category_description'], '2WN')) {
            $section_found = true;
            $sectiontype = 'bike';
        }
        // If bike RC number is entered on car page,
        // then show pop-up(pass 100 and parent status as true) based on vehicle description - 07-10-2022
        if($section_found && $sectiontype != $request->section) {
            return response()->json([
                'status' => true,
                'msg'   => 'Mismtached vehicle type',
                'data' => [
                    'ft_product_code' => $sectiontype,
                    'status' => 100
                ]
            ]);
        }
        if (!isset($response['vehicle_data']['custom_data']['version_id'])) {
            return response()->json([
                "data" => [
                    "ft_product_code" => $sectiontype,
                    "status" => 101
                ],
                'status' => false,
                'msg' => 'Version Id Not Found in Service...!' ?? null,
            ], 200);
        }

        if (empty($response['vehicle_data']['category_description']) && !$section_found) {
            $version_initial = Str::substr($response['vehicle_data']['custom_data']['version_id'], 0, 3);
            if (isset($array_type[$version_initial])) {
                $sectiontype = $array_type[$version_initial];
            }
        }

        switch (\Illuminate\Support\Str::substr($response['vehicle_data']['custom_data']['version_id'], 0, 3)) {
            case 'PCV':
                # code...
                $type = 'pcv';
                break;
            case 'GCV':
                $type = 'gcv';
                break;
            case 'CRP':
                $type = 'motor';
                break;
            case 'BYK':
                $type = 'bike';
                break;
        }
        $journeyType = empty(request()->journeyType) ? 'ongrid' : base64_decode(request()->journeyType);
        $vehicle_request = new \App\Http\Controllers\EnhanceJourneyController();
        $vehicle_request = $vehicle_request->getVehicleDetails($userProductJourneyId, $request->registration_no, $type, $response, $journeyType, false, $save_mmv);
        $vehicle_request['data']['ft_product_code'] = $sectiontype;
        if ($vehicle_request['status']) {
            UserProductJourney::where('user_product_journey_id', $userProductJourneyId)->update([
                'product_sub_type_id' => $vehicle_request['data']['additional_details']['productSubTypeId'],
                'lead_stage_id' => 2,
            ]);
            
            $vehicle_request['data']['status'] = 100;

            if ($sectiontype == 'car' || $sectiontype == 'bike')
            {
                $is_sectiontype_enabled = MasterProductSubType::where('product_sub_type_code', strtoupper($sectiontype))
                    ->where('status', 'Active')
                    ->where('parent_id', 0)
                    ->first();

                if (empty($is_sectiontype_enabled))
                {
                    $vehicle_request['data']['status'] = 101;
                }
            }
        } else {
            $vehicle_request['data']['status'] = 101;
        }
        return $vehicle_request;
    }

    public function createDuplicateJourney(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $oldEnqueryId = customDecrypt($request->enquiryId);

        $JourneyStage_data = JourneyStage::where('user_product_journey_id', $oldEnqueryId)->first();
        
        if(isset($JourneyStage_data->stage) && in_array(strtolower($JourneyStage_data->stage),array_map('strtolower', [ STAGE_NAMES['POLICY_ISSUED'],STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],STAGE_NAMES['PAYMENT_SUCCESS']] ) ))
        {
            // if(strtolower($JourneyStage_data->stage) === STAGE_NAMES['PAYMENT_FAILED'])
            // {
            //     return response()->json([
            //         'status' => false,
            //         'msg' => STAGE_NAMES['PAYMENT_FAILED'],
            //         'data' => $JourneyStage_data
            //     ]);
                
            // }
            // Commented if payment failed to generate duplicate jounery id git id 12856
            
            return response()->json([
                'status' => false,
                'msg' => 'This Transaction Already Completed',
                'data' => $JourneyStage_data
            ]);
        }

        try {
            $oldEnqueryIdData = UserProductJourney::with([
                'user_proposal',
                'quote_log',
                'corporate_vehicles_quote_request',
                'user_proposal.breakin_status',
                'agent_details',
                'journey_stage',
                'sub_product',
                'addons'
            ])->find($oldEnqueryId);
            $user_proposal = $oldEnqueryIdData->user_proposal ? $oldEnqueryIdData->user_proposal->toArray() : null;
            $quote_log = $oldEnqueryIdData->quote_log->toArray();
            $corporate_vehicles_quote_request = $oldEnqueryIdData->corporate_vehicles_quote_request->toArray();
            $agent_details = $oldEnqueryIdData->agent_details->toArray();
            $journey_stage = $oldEnqueryIdData->journey_stage->toArray();
            $addons = $oldEnqueryIdData->addons[0]->toArray();
            unset($user_proposal['additonal_data']);
                DB::beginTransaction();
                $user_product_journey = UserProductJourney::create([
                    "product_sub_type_id" => $oldEnqueryIdData->product_sub_type_id,
                    "corp_id" => $oldEnqueryIdData->corp_id,
                    "user_fname" => $oldEnqueryIdData->user_fname,
                    "user_id" => $oldEnqueryIdData->user_id,
                    "user_mname" => $oldEnqueryIdData->user_mname,
                    "user_lname" => $oldEnqueryIdData->user_lname,
                    "user_email" => $oldEnqueryIdData->user_email,
                    "user_mobile" => $oldEnqueryIdData->user_mobile,
                    "status" => $oldEnqueryIdData->status,
                    "lead_stage_id" => $oldEnqueryIdData->lead_stage_id,
                    "lead_source" => $oldEnqueryIdData->lead_source,
                    "api_token" => $oldEnqueryIdData->api_token,
                    "created_by" => $oldEnqueryIdData->created_by,
                    "created_on" => now(),
                    "old_journey_id" => $oldEnqueryId, // for duplicate enquiry id
                    "lead_id" => $oldEnqueryIdData->lead_id ?? NULL,
                ]);

                $enquiryId = $user_product_journey->user_product_journey_id;
                $addons['user_product_journey_id'] = $journey_stage['user_product_journey_id'] = $user_proposal['user_product_journey_id'] = $quote_log['user_product_journey_id'] = $corporate_vehicles_quote_request['user_product_journey_id'] = $enquiryId;
                $user_proposal['ic_vehicle_details'] = /* json_encode */($user_proposal['ic_vehicle_details'] ?? null);
                $new_proposal = $user_proposal;
                $new_proposal['is_inspection_done'] = 'N';
                $new_proposal['is_breakin_case'] = NULL;
                $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                if(request()->has('isBreakinExpired') || $CorporateVehiclesQuotesRequest->business_type =='breakin') {
                    $new_proposal['policy_start_date'] = $new_proposal['policy_end_date'] = null;
                }
                // Make null below tags to prevent duplicate unique records - @Amit
                $new_proposal['unique_quote'] = $new_proposal['proposal_no'] = $new_proposal['unique_proposal_id'] = NULL;
                $new_proposal['additional_details_data'] = $new_proposal['ckyc_number'] = $new_proposal['ckyc_reference_id'] = null;
                $new_proposal['is_ckyc_verified'] = $new_proposal['is_ckyc_details_rejected'] = 'N';
                unset($new_proposal['breakin_status']);
                unset($new_proposal['user_proposal_id']);

                $new_proposal['created_at'] = now();
                $new_proposal['updated_at'] = now();

                $new_user_proposal = UserProposal::create($new_proposal);
                if (!empty($user_proposal['breakin_status']) && ( ! isset($request->isBreakinExpired) || ! $request->isBreakinExpired)) {
                    $user_proposal['breakin_status']['user_proposal_id'] = $new_user_proposal->user_proposal_id;
                    unset($user_proposal['breakin_status']['cv_breakin_id']);
                    CvBreakinStatus::create($user_proposal['breakin_status']);
                }
                $quote_log['quote_data'] = json_encode($quote_log['quote_details']);
                unset($quote_log['quote_details']);
                unset($quote_log['quote_id']);
                unset($corporate_vehicles_quote_request['quotes_request_id']);
                unset($journey_stage['id']);
                unset($addons['id']);
                unset($addons['selected_addons']);
                $addons['compulsory_personal_accident'] = /* json_encode */($addons['compulsory_personal_accident']);
                // dd($quote_log);
                
                $quote_log['created_at'] = now();
                //$quote_log['updated_at'] = now();
                QuoteLog::create($quote_log);

                //updated at column does not exist in corporate vehicles quote request table.
                // $corporate_vehicles_quote_request['updated_at'] = now();
                $corporate_vehicles_quote_request['created_on'] = now();
                
                CorporateVehiclesQuotesRequest::create($corporate_vehicles_quote_request);
                foreach ($agent_details as $key => $agent_detail) {
                    unset($agent_detail['id']);
                    $agent_detail['user_product_journey_id'] = $enquiryId;
                    $agent_detail['user_proposal_id'] = $new_user_proposal->user_proposal_id;
                    $agent_detail['created_at'] = now();
                    $agent_detail['updated_at'] = now();

                    if(config('CvAgentMapping_FILTERED') == 'Y') {
                        $agentEntryData = createCvAgentMappingEntryForAgent($agent_detail);
                        if(!$agentEntryData['status']) {
                            return response()->json($agentEntryData);
                        }
                    } else {
                        //CvAgentMapping::create($agent_detail);
                        CvAgentMapping::updateOrCreate(
                            ['user_product_journey_id' => $agent_detail['user_product_journey_id']],
                            $agent_detail
                        );
                    }
                } 
                $journey_stage['stage'] = isset($request->isBreakinExpired) && $request->isBreakinExpired ? STAGE_NAMES['QUOTE'] : STAGE_NAMES['PROPOSAL_DRAFTED'];
                $journey_stage['proposal_url'] = \Illuminate\Support\Str::replace(request()->enquiryId, $user_product_journey->journey_id, $journey_stage['proposal_url']);
                $journey_stage['quote_url'] = \Illuminate\Support\Str::replace(request()->enquiryId, $user_product_journey->journey_id, $journey_stage['quote_url']);
              
                $journey_stage['created_at'] = now();
                $journey_stage['updated_at'] = now();
                JourneyStage::create($journey_stage);
                
                $addons['created_at'] = now();
                $addons['updated_at'] = now();
                SelectedAddons::create($addons);
            DB::commit();

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
            {
                createLsqLead($enquiryId, TRUE);

                $user_product_journey = UserProductJourney::find($enquiryId);
                $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;

                if ( ! is_null($corporate_vehicles_quote_request->vehicle_registration_no))
                {
                    createLsqOpportunity($enquiryId, NULL, [
                        'rc_number' => $corporate_vehicles_quote_request->vehicle_registration_no
                    ]);
                    createLsqActivity($enquiryId);
                }
                else
                {
                    createLsqActivity($enquiryId, 'lead');
                }
            }
                event(new \App\Events\PushDashboardData($enquiryId));
                
                return response()->json([
                    'status' => true,
                    'msg' => 'User Product Journey Duplication Successfull....!',
                    'data' => [
                        'enquiryId' => customEncrypt($enquiryId)
                    ]
                ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage() .  $e->getLine() . $e->getFile(),
            ]);
        }
    }  
    public function checkRenewal(Request $request)
    { 
        //renewal hggg
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'registration_no' => 'required',
            'productSubType' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $newEnquiryId = customDecrypt($request->enquiryId);     
        $regi_no = explode('-', $request->registration_no);
        if($regi_no['0'] == 'DL'){
            $regi_no['1'] = (int) $regi_no['1'] * 1;
        }
    
        $registration_no = implode('-', $regi_no);        
        //$UserProposals = UserProposal::where('vehicale_registration_number',$registration_no)->get()->toArray();
        $IS_ABIBL_DATA_MIGRATION = config('IS_ABIBL_DATA_MIGRATION');
        if($IS_ABIBL_DATA_MIGRATION == 'Y')
        {
            
            $policy_details = UserProposal::join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->join('policy_details as pd', 'pd.proposal_id', '=', 'user_proposal.user_proposal_id')
                ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->when($request->isPolicyNumber == 'Y', function($query) use ($request){
                    return $query->where('pd.policy_number','=', $request->policyNumber);
                }, function($query) use ($request){
                    return $query->where('user_proposal.vehicale_registration_number','=', $request->registration_no);
                })->where('s.stage','=',STAGE_NAMES['POLICY_ISSUED'])
                ->select('user_proposal.*','pd.policy_number','pd.created_on as transaction_date')
                ->orderBy('pd.created_on', 'DESC')
                ->first();
            
        }
        // In RB they don't want the renewal data to be fetched from our DB, everytime it has be be fetched from their API - #19055
        else if (strtolower(config('constants.motorConstant.SMS_FOLDER')) == 'renewbuy' && !app()->environment('local')) {
            $policy_details = false;
        }
        else
        {
            $policy_details = UserProposal::join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->join('policy_details as pd', 'pd.proposal_id', '=', 'user_proposal.user_proposal_id')
                ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
                ->when($request->isPolicyNumber == 'Y', function($query) use ($request){
                    //return $query->where('pd.policy_number','=', request()->policyNumber);
                    return $query->where('pd.policy_number','LIKE', '%'.$request->policyNumber.'%');
                }, function($query) use ($request){
                    // return $query->where('user_proposal.vehicale_registration_number','=', request()->registration_no);
                    // $reg_list = [
                    //     request()->registration_no,
                    //     getRegisterNumberWithHyphen(request()->registration_no),
                    //     getRegisterNumberWithOrWithoutZero(request()->registration_no, true), // With zero
                    //     getRegisterNumberWithOrWithoutZero(request()->registration_no, false) // Without zero
                    // ];
                    // $reg_list = array_unique($reg_list);
                    $reg_list = getPossiblevehicleNumberSeries($request->registration_no);
                    return $query->whereIn('user_proposal.vehicale_registration_number', $reg_list);
                })->where('s.stage','=',STAGE_NAMES['POLICY_ISSUED'])
                ->select('user_proposal.*','pd.policy_number', 'ql.master_policy_id')
                // ->orderBy('s.user_product_journey_id', 'DESC')
                ->orderByRaw("STR_TO_DATE(user_proposal.policy_end_date, '%d-%m-%Y') DESC")
                ->first();

                if(!empty($policy_details->vehicale_registration_number) && isset($policy_details->vehicale_registration_number) && strtoupper($policy_details->vehicale_registration_number) !== 'NEW' )
                {
                    $renewalDataWithRegistrationNo = RenewalController::getRenewalDataWithRegistrationNo($request, $policy_details->vehicale_registration_number);
                    if(!$renewalDataWithRegistrationNo['status'] && config("BLOCK_BREAKIN_CASE_WHILE_RENEWAL" != 'Y'))   //if value is N; it will show "Breakin Policy Found. Expiry Date is 16-03-2024. Total Breakin Days is 88" error.
                    {
                        return $renewalDataWithRegistrationNo;
                    }
                    if($renewalDataWithRegistrationNo['status']){
                        $policy_details = $renewalDataWithRegistrationNo['data'];
                    }
                }
               
    
                if(isset($policy_details->ic_id) && $policy_details->ic_id == 24 && $request->isPolicyNumber == 'Y')
                {

                    $policy_parts = explode('/', $policy_details->policy_number);
                    $tata_policy_name = isset($policy_parts[1]) ? $policy_parts[1] : null;

                    if($tata_policy_name && $tata_policy_name[1] == request()->policyNumber)
                    {
                        $policy_details = NULL;
                    } 
                    
                    
                    if($policy_details !== NULL)
                    {
                        $policy_details->policy_number = request()->policyNumber;
                    }
                }
        }
        $renewal_data_found = false;
        $data_source = "";//1. DATABASE 2. API
        //$policy_details = '';
        if(!empty($policy_details))
        {
            $renewal_data_found = true;
            $data_source = 'DATABASE';//1. DATABASE 2. API            
        }
        else
        {
            $IS_FETCH_RENEWAL_DATA_BY_API = config('IS_FETCH_RENEWAL_DATA_BY_API');
            if($IS_FETCH_RENEWAL_DATA_BY_API == 'Y')
            {
                $data_source = 'API';
                $policy_details = RenewalController::fetchRenewalData($request);
                $renewal_data_found = true;
                if($policy_details->status == false)
                {
                   return (array) $policy_details;
                }
            }            
        }        
        if($renewal_data_found)
        {
            if ($data_source == 'API') {
                $policy_details->policy_number = $policy_details->user_proposal['previous_policy_number'];
                $oldEnqueryIdData = $policy_details;
                $user_proposal = $oldEnqueryIdData->user_proposal;
                $quote_log = $oldEnqueryIdData->quote_log;
                $corporate_vehicles_quote_request = $oldEnqueryIdData->corporate_vehicles_quote_request;
                $journey_stage = $oldEnqueryIdData->journey_stage;
                $addons = $oldEnqueryIdData->addons[0];
            } else {
                $oldEnqueryId = $policy_details->user_product_journey_id;
                $oldEnqueryIdData = UserProductJourney::with([
                    'user_proposal',
                    'quote_log',
                    'corporate_vehicles_quote_request',
                    //'user_proposal.breakin_status',
                    'agent_details',
                    'journey_stage',
                    'sub_product',
                    'addons'
                ])->find($oldEnqueryId);
                $user_proposal = $oldEnqueryIdData->user_proposal->toArray();
                $quote_log = $oldEnqueryIdData->quote_log->toArray();
                $corporate_vehicles_quote_request = $oldEnqueryIdData->corporate_vehicles_quote_request->toArray();
                $agent_details = $oldEnqueryIdData->agent_details;
                $journey_stage = $oldEnqueryIdData->journey_stage->toArray();
                $addons = $oldEnqueryIdData->addons[0]->toArray();
            }
            unset($user_proposal['additonal_data']);
            //DB::beginTransaction();
            $user_product_journey_data = 
            [
                "product_sub_type_id" => $oldEnqueryIdData->product_sub_type_id,
                "user_fname" => trim($user_proposal['first_name'].' '.$user_proposal['last_name']),
                "user_id" => $oldEnqueryIdData->user_id,
                //"user_mname" => $oldEnqueryIdData->user_mname,
                "user_lname" => NUll,
                "user_email" => $user_proposal['email'],
                "user_mobile" => $user_proposal['mobile_number'],
                //"status" => $oldEnqueryIdData->status,
                "lead_stage_id" => $oldEnqueryIdData->lead_stage_id,
                //"lead_source" => $oldEnqueryIdData->lead_source,
                "api_token" => $oldEnqueryIdData->api_token,
                "old_journey_id" => $oldEnqueryId ?? NULL,
                //"created_on" => now(),
            ];


            if ($data_source == 'API') {
                //this is tag will be used for renewal config
                $user_product_journey_data['sub_source'] = 'CLIENT_DATA';
            }

            $isNcbVerified = 'N';
            if ($oldEnqueryIdData->lead_source == 'RENEWAL_DATA_UPLOAD') {
                $isNcbVerified = !empty($corporate_vehicles_quote_request['applicable_ncb'] ?? '') ? 'Y' : 'N';
            } 
            $user_product_journey = UserProductJourney::updateOrCreate(
                ['user_product_journey_id' => $newEnquiryId],
                $user_product_journey_data
            );
            $company_details = DB::table('master_company as mc')
                                ->where('company_id',$quote_log['ic_id'])
                                ->select('company_id as ic_id','company_name','company_alias')
                                //->get()
                                ->first();
            $enquiryId = $user_product_journey->user_product_journey_id;
            
            //$addons['user_product_journey_id'] = $journey_stage['user_product_journey_id'] = $user_proposal['user_product_journey_id'] = $quote_log['user_product_journey_id'] = $corporate_vehicles_quote_request['user_product_journey_id'] = $enquiryId;
            $user_proposal['ic_vehicle_details'] = /* json_encode */($user_proposal['ic_vehicle_details']);
            $new_proposal = $user_proposal;
            unset($new_proposal['user_proposal_id']);
            
            $quote_log['quote_data'] = json_encode($quote_log['quote_details']);
            unset($quote_log['quote_details']);
            unset($quote_log['quote_id']);
            
            unset($journey_stage['id']);
            unset($addons['id']);
            unset($addons['selected_addons']);
            $addons['compulsory_personal_accident'] = /* json_encode */($addons['compulsory_personal_accident']);
            unset($corporate_vehicles_quote_request['quotes_request_id']);
            unset($corporate_vehicles_quote_request['user_product_journey_id']);
            // corporate vehicles quote request
            $previous_master_policy_id = $quote_log['master_policy_id'];
            $master_policy = MasterPolicy::find($quote_log['master_policy_id']);
            //$previous_master_policy_id = 455545454545;
            $previous_policy_business_type = $corporate_vehicles_quote_request['business_type'];
            $previous_product_identifier = MasterProduct::where('master_policy_id',$previous_master_policy_id)
                                    ->select('product_identifier')
                                    ->pluck('product_identifier')
                                    ->toArray();
            $previous_product_identifier = $previous_product_identifier[0] ?? NULL;
            
            $previous_policy_type = 'Comprehensive';
            if (($corporate_vehicles_quote_request['policy_type'] ?? '') == 'third_party') {
                $previous_policy_type = 'Third Party';
            }
            if (($corporate_vehicles_quote_request['policy_type'] ?? '') == 'own_damage') {
                $previous_policy_type = 'Own Damage';
            }

            if(isset($master_policy->premium_type_id) && in_array($master_policy->premium_type_id,[1,4]))
            {
                $previous_policy_type = 'Comprehensive';
            }
            else if(isset($master_policy->premium_type_id) && in_array($master_policy->premium_type_id,[2,7]))
            {
                $previous_policy_type = 'Third Party';
            }
            else if(isset($master_policy->premium_type_id) && in_array($master_policy->premium_type_id,[3,6]))
            {
                $previous_policy_type = 'Own Damage';
            }
            $ncb_slab = [
                '0'     => 20,
                '20'    => 25,
                '25'    => 35,
                '35'    => 45,
                '45'    => 50,
                '50'    => 50
            ];
            $policy_end_date = \Carbon\Carbon::parse($user_proposal['policy_end_date']); 
            $breakin_date = now()->subDay(1);
            
            $business_type = 'rollover';
            $previous_ncb_for_short_term = $corporate_vehicles_quote_request['applicable_ncb'];
            $previous_ncb = $corporate_vehicles_quote_request['applicable_ncb'];
            $applicable_ncb = $ncb_slab[round($corporate_vehicles_quote_request['applicable_ncb'])];
            $is_breakin = false;
            $is_renewal_case_Y = 'Y';
            if ($policy_end_date < $breakin_date) 
            {
                $is_breakin = true;
                $previous_master_policy_id = NULL;
                $business_type = 'breakin';
                $diff = $policy_end_date->diffInDays(now());
                if($diff >= (config("BREAKIN_DAYS_FOR_RENEWAL", 60))){
                    $is_renewal_case_Y = 'N';
                }
                if($diff > 90)
                {
                    $previous_ncb = 0;
                    $applicable_ncb = 0;
                }
            }
            // if($corporate_vehicles_quote_request['is_claim'] == 'Y')
            // {
            //     $previous_ncb = 0;
            //     $applicable_ncb = 0;
            // }
            $applicable_premium_type = [
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 1,
                5 => 5,
                6 => 3,
                7 => 2,
                8 => 8,
                9 => 5,
                10 => 8
            ];
            if(isset($master_policy->premium_type_id))
            {
              $applicable_premium_type_id  = $applicable_premium_type[$master_policy->premium_type_id];  
            }
            else
            {
               $applicable_premium_type_id  = NULL; 
            }
            
            if($previous_policy_business_type == 'breakin')
            {
                $ZD = ['1', 'NA'];
                if(isset($addons['applicable_addons']))
                {
                    foreach ($addons['applicable_addons'] as $key => $value) 
                    {
                        if(isset($value['name']) && $value['name'] == 'Zero Depreciation')
                        {
                           $ZD = ['0'];
                           break;
                        }
                    }                
                }
                $applicable_policy_id = MasterPolicy::where('product_sub_type_id',$master_policy->product_sub_type_id)
                                        ->where('insurance_company_id',$master_policy->insurance_company_id)
                                        ->where('premium_type_id',$applicable_premium_type_id)
                                        ->whereIn('zero_dep',$ZD)
                                        ->get()
                                        ->first();
                $previous_master_policy_id = $applicable_policy_id->policy_id;
            }
            
            $company_details->company_alias;
            $product = get_parent_code($oldEnqueryIdData->product_sub_type_id);
            $product_sub_type_code = DB::table('master_product_sub_type')
                                ->where('product_sub_type_id', $oldEnqueryIdData->product_sub_type_id)
                                ->pluck('product_sub_type_code')
                                ->first();
            $productData = getProductDataByIc($quote_log['master_policy_id']);
//            if($company_details->company_alias == 'kotak' && false)
//            {
//                $renewalData = [
//                    'enquiryId'     => $newEnquiryId,
//                    'product'       => $product,
//                    'sub_product'   => $product_sub_type_code
//                ];
//                $confirmRenewalData = RenewalController::kotakConfirmRenewalData($productData,$renewalData);
//            }
            // $registration_no = $user_proposal['vehicale_registration_number'];
            // $od_compare_date = now();
            // if($oldEnqueryIdData->product_sub_type_id == 1)
            // {
            //     $od_compare_date = now()->subYear(2)->subDay(180);  //for car
            // }else if($oldEnqueryIdData->product_sub_type_id == 2)
            // {
            //     $od_compare_date = now()->subYear(4)->subDay(180);  //for bike
            // }
            // $registration_date = $corporate_vehicles_quote_request['vehicle_register_date'];
            // if(Carbon::parse($registration_date) > $od_compare_date && ! in_array($product, ['PCV', 'GCV', 'MISCELLANEOUS-CLASS']))
            // {
            //     $policy_type = 'own_damage';
            //     if(strtolower($corporate_vehicles_quote_request['policy_type']) === 'own_damage')
            //     {
            //        $previous_policy_type =  'Own-damage';
            //     }
            //     else if(strtolower($corporate_vehicles_quote_request['policy_type']) === 'comprehensive')
            //     {
            //        $policy_type = 'comprehensive';
            //     }
            // }
            // else
            // {
            //    $policy_type = 'comprehensive';
            // }

            //The below changes are done as per fornt end suggestion
            $applicable_premium_type_id = 1;
            $policy_type = 'comprehensive';
            $previousNotSureCase = false;
            $product_sub_type_id = $oldEnqueryIdData->product_sub_type_id;
            if (
                !empty($corporate_vehicles_quote_request['vehicle_register_date']) &&
                in_array($product_sub_type_id, [1, 2])
            ) {
                $registratiom_date = Carbon::parse($corporate_vehicles_quote_request['vehicle_register_date']);
                $od_compare_years = $product_sub_type_id == 1 ? 2 : 4;
                $od_compare_date = now()->subYear($od_compare_years)->subDay(180);

                // current - registration <=400 -> 1st renewal
                $date1 = new DateTime(date('Y-m-d', strtotime($registratiom_date)));
                $date2 = new DateTime(date('Y-m-d'));
                $interval = $date1->diff($date2);

                if ($previous_policy_type == 'Third Party') {
                    $policy_type = 'third_party';
                }

                if ($registratiom_date > $od_compare_date) {
                    $policy_type = 'own_damage';
                    $applicable_premium_type_id = 3;

                    //if customer selected Not sure in previous policy
                    if (
                        in_array($previous_policy_type, ['Comprehensive', 'Third Party']) &&
                        $interval->days >= 400
                    ) {
                        $previousNotSureCase = true;
                        $applicable_premium_type_id = 1;
                        $policy_type = 'comprehensive';

                        if ($previous_policy_type == 'Third Party') {
                            $policy_type = 'third_party';
                        }
                    }
                }
            }
            
            $is_prev_policy_short_term = NULL;
            if(isset($master_policy->premium_type_id) && in_array($master_policy->premium_type_id,[5,8,9,10]))
            {
                $is_prev_policy_short_term = '1';
                $applicable_ncb = $previous_ncb = $previous_ncb_for_short_term;
            }
            $agentValidate = new AgentValidateController($newEnquiryId);
            $isEvPos = $agentValidate->isEvPos();
            if($isEvPos)
            {
                $EV_SUB_PRODUCT_TYPE = explode(',', config('EV_SUB_PRODUCT_TYPE'));
                if(!in_array($corporate_vehicles_quote_request['product_id'],$EV_SUB_PRODUCT_TYPE))
                { 
                    return [
                        'status' => false,
                        'msg' => 'Category Not Allowed',
                        'show_error' => true
                    ];
                }
                else if($corporate_vehicles_quote_request['fuel_type'] != 'ELECTRIC')
                {
                    return [
                        'status' => false,
                        'msg' => 'Fuel type '.$corporate_vehicles_quote_request['fuel_type'].' is not allowed',
                        'show_error' => true
                    ];
                }
            }
            
            $is_rollover_renewal = 'N';
            $parent_code = strtolower(get_parent_code($oldEnqueryIdData->product_sub_type_id));
            // if ($previous_policy_type == 'Third Party') {
            //     $applicable_premium_type_id = 2;
            // }
            if(!in_array($company_details->company_alias,explode(',',config(strtoupper($parent_code).'_RENEWAL_ALLOWED_IC'))))
            {
                if (config('IS_ROLLOVER_RENEWAL_ALLOWED') != 'Y') {
                    return [
                        'status' => false,
                        'msg' => 'Rollover Renewal is disabled.'
                    ];
                }
                $is_rollover_renewal = 'Y';
            }
            
            $was_tw = ($corporate_vehicles_quote_request['product_id'] == 2);
            $was_fw = ($corporate_vehicles_quote_request['product_id'] == 1);
            $was_cv = (!($was_tw || $was_fw));
        
            //The below changes are done as per fornt end suggestion
            if(config('PREVIOUS_POLICY_ID_CHANGES') == "Y") {
                $was_new = ($previous_policy_business_type == 'newbusiness');

                $corporate_vehicles_quote_request["previous_policy_type_identifier"] = 'N';
                if ($was_fw || $was_tw) {
                    if ($was_new ) {
                        $corporate_vehicles_quote_request["previous_policy_type_identifier_code"] = $was_tw ? '15' : '13';
                    } else {
                        $corporate_vehicles_quote_request["previous_policy_type_identifier_code"] = '11';
                        if ($previousNotSureCase) {
                            $corporate_vehicles_quote_request["previous_policy_type_identifier"] = 'Y';
                        }
                        if ($previous_policy_type == 'Own Damage') {
                            $corporate_vehicles_quote_request["previous_policy_type_identifier_code"] = '10';
                            if($policy_type == 'comprehensive'){
                                $corporate_vehicles_quote_request["previous_policy_type_identifier"] = 'Y';
                            }
                        } elseif ($previous_policy_type == 'Third Party') {
                            $corporate_vehicles_quote_request["previous_policy_type_identifier_code"] = '01';
                        }
                    }
                }
            
                // $previousPolicyTypeIdThirdParty = ($was_new ? ($was_tw ? '05' : ($was_fw ? '03' : '01')) : '01');
                // $previousPolicyTypeIdComprehensive = ($was_new ? ($was_tw ? '15' : ($was_fw ? '13' : '11')) : '11');

                // $previousPolicyTypeIdCodeArray = [
                //     'comprehensive' => $previousPolicyTypeIdComprehensive,
                //     'third_party' => $previousPolicyTypeIdThirdParty,
                //     'own_damage' => 10,
                // ];

                // $corporate_vehicles_quote_request["previous_policy_type_identifier"] = (($was_new && ($was_tw || $was_fw)) ? 'N' : 'Y');
                // $corporate_vehicles_quote_request["previous_policy_type_identifier_code"] = ($previousPolicyTypeIdCodeArray[$policy_type] ?? '11');
            }
            
            $previous_policy_type = ($previous_policy_type == 'Third Party') ? 'Third-party' : $previous_policy_type;
            $previous_policy_type = ($previous_policy_type == 'Own Damage') ? 'Own-damage' : $previous_policy_type;
            if($previous_policy_type == 'Third-party'){
                $applicable_ncb = $previous_ncb = 0;
            }
            $corporate_vehicles_quote_request['previous_policy_type']           = $previous_policy_type;
            $corporate_vehicles_quote_request['business_type']                  = $business_type;
            $corporate_vehicles_quote_request['policy_type']                    = $policy_type;
            $corporate_vehicles_quote_request['previous_insurer']               = $company_details->company_name;
            $corporate_vehicles_quote_request['previous_insurer_code']          = $company_details->company_alias;
            $corporate_vehicles_quote_request['insurance_company_id']           = $quote_log['ic_id'];
            $corporate_vehicles_quote_request['vehicle_registration_no']        = strtoupper($registration_no) == 'NEW' ? '' : $registration_no;
            $corporate_vehicles_quote_request['previous_policy_expiry_date']    = $user_proposal['policy_end_date'];
            $corporate_vehicles_quote_request['previous_ncb']                   = round($previous_ncb);//$corporate_vehicles_quote_request['applicable_ncb'];
            $corporate_vehicles_quote_request['applicable_ncb']                 = $applicable_ncb;//$ncb_slab[$corporate_vehicles_quote_request['applicable_ncb']];
            $corporate_vehicles_quote_request['is_renewal']                     = $is_renewal_case_Y;
            $corporate_vehicles_quote_request['is_fastlane']                    = 'N';
            $corporate_vehicles_quote_request['is_popup_shown']                 = 'N';
            $corporate_vehicles_quote_request['is_ncb_verified']                =  ($isNcbVerified ?? 'N');
            $corporate_vehicles_quote_request['is_idv_changed']                 = 'N';
            // $corporate_vehicles_quote_request['is_claim']                       = $corporate_vehicles_quote_request['is_claim'];
            $corporate_vehicles_quote_request['is_claim']                       = 'N';
            $corporate_vehicles_quote_request['ownership_changed']              = 'N';
            $corporate_vehicles_quote_request['zero_dep_in_last_policy']        = 'Y';
            $corporate_vehicles_quote_request['applicable_premium_type_id']     = $applicable_premium_type_id;
            $corporate_vehicles_quote_request['previous_master_policy_id']      = $previous_master_policy_id;
            $corporate_vehicles_quote_request['previous_product_identifier']    = $previous_product_identifier;
            $corporate_vehicles_quote_request['manufacture_year']               = $user_proposal['vehicle_manf_year'];
            $corporate_vehicles_quote_request['vehicle_owner_type']             = $corporate_vehicles_quote_request['vehicle_owner_type'];
            // $corporate_vehicles_quote_request['is_idv_changed']                 = 'Y';
            $corporate_vehicles_quote_request['prev_short_term']                = $is_prev_policy_short_term;
            $corporate_vehicles_quote_request['rollover_renewal']               = $is_rollover_renewal;
            $corporate_vehicles_quote_request['journey_without_regno']          = (strtoupper($registration_no) == 'NEW' ||  $registration_no == null ) ? 'Y' : 'N';

            # FOR UPDATING IDV
            if ($quote_log['idv'] > 0) {

                $corporate_vehicles_quote_request['is_idv_changed']  = 'Y';
                $corporate_vehicles_quote_request['edit_idv'] = $quote_log['idv'];
            }
                      
            if(config('ACE_RENEWAL_REMOVE_JOURNEY_TYPE') == 'Y'){
                $corporate_vehicles_quote_request['journey_type'] = NULL;
            }
            if(config('REMOVE_HIDE_RENEWAL_FRONTEND_TAG') == 'Y'){
                $corporate_vehicles_quote_request['frontend_tags'] = NULL;
            }
            CorporateVehiclesQuotesRequest::updateOrCreate(
                ['user_product_journey_id' => $newEnquiryId],
                $corporate_vehicles_quote_request
            );
            $old_addional_details = json_decode($new_proposal['additional_details'], true);
            if(isset($old_addional_details['owner']['firstName']))
            {
                $old_addional_details['owner']['fullName'] = trim($user_proposal['first_name'].' '.$user_proposal['last_name']);
                $old_addional_details['owner']['firstName'] = trim($user_proposal['first_name']);
                $old_addional_details['owner']['lastName'] = trim($user_proposal['last_name']);
                // $old_addional_details['owner']['address'] = trim(trim($old_addional_details['owner']['addressLine1']).' '.trim($old_addional_details['owner']['addressLine2']).' '.trim($old_addional_details['owner']['addressLine3']));
                $old_addional_details['owner']['address'] = (isset($old_addional_details['owner']['addressLine1']) ? trim($old_addional_details['owner']['addressLine1']) : '');
                $old_addional_details['owner']['address'] .= (isset($old_addional_details['owner']['addressLine2']) ? ' ' . trim($old_addional_details['owner']['addressLine2']) : '');
                $old_addional_details['owner']['address'] .= (isset($old_addional_details['owner']['addressLine3']) ? ' ' . trim($old_addional_details['owner']['addressLine3']) : '');
            }
            if(empty($old_addional_details)){
                $old_addional_details = [];
            }
            // dd(json_decode($new_proposal['additional_details'], true), $new_proposal);
            
            $fetch_insurer_code = PreviousInsurerList::where('name',$company_details->company_name)
                        ->where('company_alias',$company_details->company_alias)
                        ->get()
                        ->first();

            $tp_insurance_company = '';
            if (!empty($new_proposal['tp_insurance_company']) && (env('APP_ENV') == 'local')) {
                $tp_insurance_company = PreviousInsurerList::where('company_alias', $company_details->company_alias)
                    // ->where('name', $new_proposal['tp_insurance_company'])
                    // ->orWhere('code', $new_proposal['tp_insurance_company'])
                    ->where(function ($query) use ($new_proposal) {
                        $query->where('name', $new_proposal['tp_insurance_company'])
                              ->orWhere('code', $new_proposal['tp_insurance_company']);
                    })
                    ->select('name')
                    ->first();
            }
            $additional_details = [
                "prepolicy" => [
                    "previousInsuranceCompany"  => $fetch_insurer_code->code ?? '',
                    "InsuranceCompanyName"      => $company_details->company_name,
                    "previousPolicyStartDate"   => $new_proposal['policy_start_date'],
                    "previousPolicyExpiryDate"  => $new_proposal['policy_end_date'],
                    "previousPolicyNumber"      => $policy_details->policy_number ?? null,
                    "isClaim"                   => "N",
                    "claim"                     => "NO",
                    "previousNcb"               => $previous_ncb,
                    "applicableNcb"             => $applicable_ncb,
                    "prevPolicyExpiryDate"      => $new_proposal['policy_end_date'],
                    "tpInsuranceCompany"        => !empty($tp_insurance_company->name ?? null) ? $tp_insurance_company->name : $new_proposal['tp_insurance_company'],
                    "tpInsuranceCompanyName"    => $new_proposal['tp_insurance_company'] ?? null,
                    "tpEndDate"                 => !empty($new_proposal['tp_end_date'] ?? null) ? $new_proposal['tp_end_date'] : null,
                    "tpStartDate"               => !empty($new_proposal['tp_start_date'] ?? null) ? $new_proposal['tp_start_date'] : null,
                    "tpInsuranceNumber"         => !empty($new_proposal['tp_insurance_number'] ?? null) ? $new_proposal['tp_insurance_number'] : null
                ]
            ];

            if ($new_proposal['business_type'] == 'newbusiness') {
                $unique_identifier = $company_details->company_alias == 'godigit' ? ['go digit'] : explode('_', $company_details->company_alias);
                $tp_insurance_company = PreviousInsurerList::where('company_alias', $company_details->company_alias)
                    ->where('name', 'LIKE', "%{$unique_identifier[0]}%")
                    ->select('name', 'code')
                    ->first();
                if (!empty($tp_insurance_company)) {
                    $additional_details['prepolicy']['tpInsuranceCompany'] = $tp_insurance_company->name;
                    $additional_details['prepolicy']['tpInsuranceCompanyName'] = $tp_insurance_company->code;
                }
                $additional_details['prepolicy']['tpEndDate'] = $new_proposal['policy_end_date'];
                $additional_details['prepolicy']['tpStartDate'] = $new_proposal['policy_start_date'];
                $additional_details['prepolicy']['tpInsuranceNumber'] = $policy_details->policy_number ?? null;
                $new_proposal['tp_start_date'] = $additional_details['prepolicy']['tpStartDate'];
                $new_proposal['tp_end_date'] = $additional_details['prepolicy']['tpEndDate'];
                $new_proposal['tp_insurance_company'] = $additional_details['prepolicy']['tpInsuranceCompany'];
                $new_proposal['tp_insurance_number'] = $additional_details['prepolicy']['tpInsuranceNumber'];
            }
            
            if($corporate_vehicles_quote_request['vehicle_owner_type'] == 'I')
            {
                if(isset($addons['compulsory_personal_accident']['0']['name']))
                {
                  $old_addional_details['nominee']['compulsory personal accident'] = NULL;  
                }
                else
                {
                    $old_addional_details['nominee']['compulsory personal accident'] = 'NO';
                    $old_addional_details['nominee']['cpa'] = 'NO';
                }                
            }
            $gender_name = NULL;
            if(!empty($new_proposal['gender']))
            {
                $gender_code = Gender::where('company_alias', $company_details->company_alias)
                                       ->get()
                                       ->toArray();
                if(!empty($gender_code))
                {
                    if(strtolower($new_proposal['gender']) == 'm')
                    {
                        $new_gender = 'Male';
                    }
                    else if(strtolower($new_proposal['gender']) == 'f')
                    {
                        $new_gender = 'Female';
                    }else
                    {
                        $new_gender = $new_proposal['gender'];
                    }
                    foreach($gender_code as $g_key => $g_value)
                    {
                        if((isset($g_value['gender']) && isset($new_gender)) && (strtolower($g_value['gender']) == strtolower($new_gender)) )
                        {
                            $gender_name = $g_value['gender'];
                        }
                    }

                }
                // $gender_name = getGenderName($company_details->company_alias,$new_proposal['gender']);
            }
            if(empty($new_proposal['gender_name']))
            {
                $new_proposal['gender_name'] = $gender_name; 
            }
            if(isset($old_addional_details['owner']['prevOwnerType']) && $old_addional_details['owner']['prevOwnerType'] == 'I')
            {
                $old_addional_details['owner']['gender'] = $new_proposal['gender'];         
                $old_addional_details['owner']['genderName'] = $gender_name;           
            }
            
            $additional_details = json_encode(array_merge($old_addional_details, $additional_details));
            
            $proposal_redirection = config('IS_RENEWAL_REDIRECTION_TO_PROPOSAL');
            //Clone Proposal
            
            $fullName = ($new_proposal['first_name'] . (($corporate_vehicles_quote_request['vehicle_owner_type'] == 'I' && !empty($new_proposal['last_name'])) ? ' '. $new_proposal['last_name'] : '' ));

            $proposal_data = [
                'user_product_journey_id'               => $newEnquiryId,
                'first_name'                            => $new_proposal['first_name'],
                'last_name'                             => $new_proposal['last_name'],
                'fullName'                              => $fullName,
                'email'                                 => $new_proposal['email'],
                'office_email'                          => $new_proposal['office_email'],
                'marital_status'                        => $new_proposal['marital_status'],
                'mobile_number'                         => $new_proposal['mobile_number'],
                'dob'                                   => $new_proposal['dob'],
                'occupation'                            => $new_proposal['occupation'],
                'occupation_name'                       => $new_proposal['occupation_name'],
                'gender'                                => $new_proposal['gender'],
                'gender_name'                           => $gender_name,
                'pan_number'                            => $new_proposal['pan_number'],
                'gst_number'                            => $new_proposal['gst_number'],
                'address_line1'                         => trim(trim($new_proposal['address_line1']).' '.trim($new_proposal['address_line2']).' '.trim($new_proposal['address_line3'])),
                //'address_line2'                         => $new_proposal['address_line2'],
                //'address_line3'                         => $new_proposal['address_line3'],
                'pincode'                               => $new_proposal['pincode'],
                'state'                                 => $new_proposal['state'],
                'city'                                  => $new_proposal['city'],
                'rto_location'                          => $new_proposal['rto_location'],
                'vehicle_color'                         => removeSpecialCharactersFromString($new_proposal['vehicle_color'], true),
                'financer_agreement_type'               => $new_proposal['financer_agreement_type'],
                'financer_location'                     => $new_proposal['financer_location'],
                'is_car_registration_address_same'      => $new_proposal['is_car_registration_address_same'],
                'car_registration_address1'             => $new_proposal['car_registration_address1'],
                'car_registration_address2'             => $new_proposal['car_registration_address2'],
                'car_registration_address3'             => $new_proposal['car_registration_address3'],
                'car_registration_pincode'              => $new_proposal['car_registration_pincode'],
                'car_registration_state'                => $new_proposal['car_registration_state'],
                'car_registration_city'                 => $new_proposal['car_registration_city'],
                'vehicale_registration_number'          => strtoupper($new_proposal['vehicale_registration_number']) == 'NEW' ? '' : $registration_no,//$new_proposal['vehicale_registration_number'],
                'vehicle_manf_year'                     => $new_proposal['vehicle_manf_year'],
                'engine_number'                         => removeSpecialCharactersFromString($new_proposal['engine_number']),
                'chassis_number'                        => removeSpecialCharactersFromString($new_proposal['chassis_number']),
                'is_vehicle_finance'                    => $new_proposal['is_vehicle_finance'],
                'name_of_financer'                      => $new_proposal['name_of_financer'],
                'hypothecation_city'                    => $new_proposal['hypothecation_city'],
                'prev_policy_start_date'                => $new_proposal['policy_start_date'] ?? NULL,
                'prev_policy_expiry_date'               => $new_proposal['policy_end_date'],
                'previous_policy_number'                => $policy_details->policy_number ?? null,
                'previous_insurance_company'            => $company_details->company_name,
                'nominee_name'                          => $new_proposal['nominee_name'],
                'nominee_age'                           => $new_proposal['nominee_age'],
                'nominee_dob'                           => $new_proposal['nominee_dob'],
                'nominee_relationship'                  => $new_proposal['nominee_relationship'],
                'tp_start_date'                         => $new_proposal['tp_start_date'],
                'tp_end_date'                           => $new_proposal['tp_end_date'],
                'tp_insurance_company'                  => !empty($tp_insurance_company->name) ? $tp_insurance_company->name : $new_proposal['tp_insurance_company'],
                'tp_insurance_company_name'             => $new_proposal['tp_insurance_company'] ?? NULL,
                'tp_insurance_number'                   => $new_proposal['tp_insurance_number'],
                'state_id'                              => $new_proposal['state_id'],
                'city_id'                               => $new_proposal['city_id'],
                //'engine_number'                         => $policy_details->previous_policy_number
                //'additional_details'                    => $proposal_redirection == 'Y' ? $additional_details : NULL,
                'additional_details'                    => $additional_details ?? NULL,
                'previous_insurance_company'            => $fetch_insurer_code->code ?? '',
                'insurance_company_name'                => $company_details->company_name,
                'ic_name'                               => $company_details->company_name,
                'ic_id'                                 => $quote_log['ic_id'],
                'business_type'                         => $business_type,
                'product_type'                          => $new_proposal['product_type'],
                'previous_ncb'                          => round($previous_ncb),
                'applicable_ncb'                        => $applicable_ncb,
                // 'is_claim'                              => $corporate_vehicles_quote_request['is_claim'],
                'is_claim'                              => 'N',
                'vehicle_usage_type'                    => $new_proposal['vehicle_usage_type'] ?? NULL,
                'vehicle_category'                      => $new_proposal['vehicle_category'] ?? NULL,
                'full_name_finance'                     => $new_proposal['name_of_financer'],
            ];
            
            
            UserProposal::updateOrCreate(
                ['user_product_journey_id' => $newEnquiryId],
                $proposal_data
            );
//            foreach ($agent_details as $key => $agent_detail) {
//                unset($agent_detail['id']);
//                $agent_detail['user_product_journey_id'] = $enquiryId;
//                CvAgentMapping::create($agent_detail);
//            }
            //$journey_stage['stage'] = STAGE_NAMES['PROPOSAL_DRAFTED'];
            //$journey_stage['proposal_url'] = \Illuminate\Support\Str::replace(request()->enquiryId, $user_product_journey->journey_id, $journey_stage['proposal_url']);
            //$journey_stage['quote_url'] = \Illuminate\Support\Str::replace(request()->enquiryId, $user_product_journey->journey_id, $journey_stage['quote_url']);
            //JourneyStage::create($journey_stage);
            $addons['user_product_journey_id'] = $newEnquiryId;
            $selected_addons = [
                'adoons' => json_encode($addons)
            ];
            SelectedAddons::updateOrCreate(
                    ['user_product_journey_id' => $newEnquiryId],
                    $addons
            );
            
            $parent_code = strtolower(get_parent_code($oldEnqueryIdData->product_sub_type_id)); 
            $redirection_bool = false;
            $redirection_url = '';
            $proposal_redirection = config('IS_RENEWAL_REDIRECTION_TO_PROPOSAL');
            // $renewal_journey = 'Y';
            if($parent_code == 'bike')
            {
                $allowed_ic = config('BIKE_RENEWAL_ALLOWED_IC');
                $allowed_ic_array = explode(',',$allowed_ic); 
                if(!in_array($company_details->company_alias,$allowed_ic_array))
                {
                    $proposal_redirection = NULL;
                }

            }
            else if($parent_code == 'car')
            {
                $allowed_ic = config('CAR_RENEWAL_ALLOWED_IC');
                $allowed_ic_array = explode(',',$allowed_ic);
                if(!in_array($company_details->company_alias,$allowed_ic_array))
                {
                    $proposal_redirection = NULL;
                }
            }
            else if($parent_code == 'pcv' || $parent_code == 'gcv')
            {
                $allowed_ic = config('CV_RENEWAL_ALLOWED_IC');
                $allowed_ic_array = explode(',',$allowed_ic);
                if(!in_array($company_details->company_alias,$allowed_ic_array))
                {
                    $proposal_redirection = NULL;
                }
            }
            
            if(config('IC_FETCH_API_RENEWAL_PREFILL_ENABLE') == 'Y')
            {
                $prefillAllowedIc = config(strtoupper($parent_code).'_RENEWAL_ALLOWED_IC_PREFILL');
                $prefill_allowed_ic_array = explode(',',$prefillAllowedIc);
                if(in_array($company_details->company_alias,$prefill_allowed_ic_array))
                {
                    if (config('IC.HDFC_ERGO.V1.CAR.RENEWAL.ENABLE') == 'Y') {
                        $renewal_class = 'App\Http\Controllers\RenewalService\\'.ucfirst($parent_code).'\\'.'V1'.'\\'.$company_details->company_alias;
                    }else{
                        $renewal_class = 'App\Http\Controllers\RenewalService\\'.ucfirst($parent_code).'\\'.$company_details->company_alias;
                    }
                    if(class_exists($renewal_class))
                    {
                        $renewl_get_data = [
                            'user_product_journey_id'       => $newEnquiryId,
                            'company_alias'                 => $company_details->company_alias,
                            'vehicale_registration_number'  => $registration_no,
                            'engine_number'                 => $new_proposal['engine_number'],
                            'chassis_number'                => $new_proposal['chassis_number'],
                            'prev_policy_expiry_date'       => $new_proposal['prev_policy_expiry_date'],
                            'previous_policy_number'        => $policy_details->policy_number
                        ];
                        $renewal_ref = new $renewal_class();
                        $policy_data_response = $renewal_ref->IcServicefetchData($renewl_get_data);
                        $renewl_get_data['policy_data_response'] = $policy_data_response;
                        if (config('IC.HDFC_ERGO.V1.CAR.RENEWAL.ENABLE') != 'Y') {
                            $reposne_renewal_data = $renewal_ref->prepareFetchData($renewl_get_data);
                        }
                        unset($renewal_ref);
                    }
                }
            }

            if (isset($corporate_vehicles_quote_request) && empty($corporate_vehicles_quote_request['version_id'])) {
                return [
                    'status' => false,
                    'msg' => 'Version Id not found'
                ];
            }
            $mmv_details = get_fyntune_mmv_details($oldEnqueryIdData->product_sub_type_id, $corporate_vehicles_quote_request['version_id']);
            $mmv_details = $mmv_details['data'];
            if (empty($mmv_details)) {
                if (config('vahanConfiguratorEnabled') == 'Y'  && config('constants.motorConstant.SMS_FOLDER') == 'bajaj') {
                    $vahanController = new \App\Http\Controllers\VahanService\VahanServiceController();
                    $vahan_data = $vahanController->hitVahanService($request);
                    if($vahan_data['status'] && $vahan_data['data']['status'] == 100 && $request->section == $vahan_data['data']['ft_product_code'] && !empty($vahan_data['data']['results']['0']['vehicle']['vehicle_cd']))
                    {
                        $corporate_vehicles_quote_request_bajaj['previous_insurer']               = $company_details->company_name;
                        $corporate_vehicles_quote_request_bajaj['previous_insurer_code']          = $company_details->company_alias;
                        $corporate_vehicles_quote_request_bajaj['insurance_company_id']           = $company_details->ic_id;
                        $corporate_vehicles_quote_request_bajaj['previous_policy_expiry_date']    = $user_proposal['policy_end_date'];
                        $corporate_vehicles_quote_request['version_id']                     	  = $vahan_data['data']['results']['0']['vehicle']['vehicle_cd'];

                        CorporateVehiclesQuotesRequest::updateOrCreate(
                                ['user_product_journey_id' => $newEnquiryId],
                                $corporate_vehicles_quote_request_bajaj
                        );
                        $corporate_vehicles_quote_request['version_id'] = $vahan_data['data']['results']['0']['vehicle']['vehicle_cd'];
                        if(!empty($policy_details->policy_number))
                        {
                            UserProposal::updateOrCreate(
                                ['user_product_journey_id' => $newEnquiryId],
                                ['previous_policy_number' => $policy_details->policy_number]
                            );
                        }
                        $mmv_details = get_fyntune_mmv_details($oldEnqueryIdData->product_sub_type_id, $corporate_vehicles_quote_request['version_id']);
                        $mmv_details = $mmv_details['data'];
                    }
                    else
                    {
                        return $vahan_data;
                    }
                }
            }
            if($proposal_redirection == 'Y' && $is_breakin == false || (isset($request->is_premium_call) && $request->is_premium_call  == 'true'))
            {
                if(in_array(strtolower($parent_code),['car','bike']))
                {
                  $url = url('/api/'.strtolower($parent_code).'/premiumCalculation/'.$company_details->company_alias);                  
                }
                else
                {
                   $url = url('/api/premiumCalculation/'.$company_details->company_alias);   
                }
                
                $quote_fetch_data = [
                    'policyId'      => $previous_master_policy_id,
                    'enquiryId'     => customEncrypt($newEnquiryId),
                    'is_renewal'    => 'Y'
                ];
                // $quote_fetch_data = [
                //     'policyId'      => 667,
                //     'enquiryId'     => 2022050700000234,
                //     'is_renewal'    => 'Y'
                // ];
                // $url = 'https://apimotor.fynity.in/api/bike/premiumCalculation/reliance';
                //     $url = 'https://api-ola-uat.fynity.in/api/premiumCalculation/godigit';
                $quote_response = Http::withoutVerifying()->post($url,$quote_fetch_data)->json();
                if(isset($quote_response['status']) && $quote_response['status'] == true)
                {
                    $mmv_details = get_fyntune_mmv_details($oldEnqueryIdData->product_sub_type_id, $corporate_vehicles_quote_request['version_id']);
                    $mmv_details = $mmv_details['data'];
                    $premium_response = $quote_response['data'];
                    
                    $quote_data = [
                        "product_sub_type_id"           => $oldEnqueryIdData->product_sub_type_id,
                        "manfacture_id"                 => $mmv_details['manufacturer']['manf_id'],
                        "manfacture_name"               => $mmv_details['manufacturer']['manf_name'],
                        "model"                         => $mmv_details['model']['model_id'],
                        "model_name"                    => $mmv_details['model']['model_name'],
                        "version_id"                    => $corporate_vehicles_quote_request['version_id'],
                        "vehicle_usage"                 => 2,
                        "policy_type"                   => $corporate_vehicles_quote_request['policy_type'],
                        "business_type"                 => $business_type,
                        "version_name"                  => $mmv_details['version']['version_name'],
                        "vehicle_register_date"         => $corporate_vehicles_quote_request['vehicle_register_date'],
                        "previous_policy_expiry_date"   => $user_proposal['policy_end_date'],
                        "previous_policy_type"          => $previous_policy_type,
                        "fuel_type"                     => $mmv_details['version']['fuel_type'],
                        "manufacture_year"              => $corporate_vehicles_quote_request['manufacture_year'],
                        "rto_code"                      => $corporate_vehicles_quote_request['rto_code'],
                        "vehicle_owner_type"            => $corporate_vehicles_quote_request['vehicle_owner_type'],
                        // "is_claim"                      => $corporate_vehicles_quote_request['is_claim'],
                        "is_claim"                      => 'N',
                        "previous_ncb"                  => $previous_ncb,
                        "applicable_ncb"                => $applicable_ncb,
                    ];
                    $quote_response['data']['company_alias'] = $company_details->company_alias;
                    $quote_response['data']['companyId'] = $quote_log['ic_id'];
                  
                    $quote_log_data = [
                        'user_product_journey_id' => $newEnquiryId,
                        'product_sub_type_id' => $quote_log['product_sub_type_id'],
                        'ex_showroom_price_idv'     => round($premium_response['idv']),
                        'idv'                       => round($premium_response['idv']),
                        'od_premium'                => round($premium_response['finalOdPremium']),
                        'tp_premium'                => round($premium_response['finalTpPremium']) + round($premium_response['compulsoryPaOwnDriver']),
                        'service_tax'               => round($premium_response['finalGstAmount']) + round($premium_response['compulsoryPaOwnDriver'])* 0.18,
                        'addon_premium'             => round($premium_response['addOnPremiumTotal']),
                        'final_premium_amount'      => round($premium_response['finalPayableAmount']) + round($premium_response['compulsoryPaOwnDriver'])* 1.18, 
                        'revised_ncb'               => round($premium_response['deductionOfNcb']),
                        'ic_id'                     => $quote_log['ic_id'],
                        'ic_alias'                  => $company_details->company_name,  
                        'master_policy_id'          => $previous_master_policy_id,
                        'quote_data'                => json_encode($quote_data),
                        'premium_json'              => $quote_response['data'],
                    ];
                    $redirection_bool = true;
                    if(strtolower($parent_code) == 'car')
                    {                        
                      $redirection_url = config('constants.motorConstant.CAR_FRONTEND_URL');               
                    }
                    else if(strtolower($parent_code) == 'bike')
                    {                        
                      $redirection_url = config('constants.motorConstant.BIKE_FRONTEND_URL');               
                    }
                    else
                    {                        
                       $redirection_url = config('constants.motorConstant.CV_FRONTEND_URL'); 
                    }
                    $redirection_url = $redirection_url.'/proposal-page?enquiry_id='.customEncrypt($newEnquiryId). '&dropout=true';
                }
                else
                {
                    //$renewal_journey = 'N';
                    $quote_log_data = [
                        'product_sub_type_id' => $quote_log['product_sub_type_id'],
                        'quote_data' => null
                    ];
                }
            }
            else
            {
                //$renewal_journey = 'Y';
                $quote_log_data = [
                    'product_sub_type_id' => $quote_log['product_sub_type_id'],
                    'quote_data' => null
                ];
//                if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy')
//                {
                    $quote_data = [
                        "product_sub_type_id"           => $oldEnqueryIdData->product_sub_type_id,
                        "manfacture_id"                 => $mmv_details['manufacturer']['manf_id'],
                        "manfacture_name"               => $mmv_details['manufacturer']['manf_name'],
                        "model"                         => $mmv_details['model']['model_id'],
                        "model_name"                    => $mmv_details['model']['model_name'],
                        "version_id"                    => $corporate_vehicles_quote_request['version_id'],
                        "vehicle_usage"                 => 2,
                        "policy_type"                   => $corporate_vehicles_quote_request['policy_type'],
                        "business_type"                 => $business_type,
                        "version_name"                  => $mmv_details['version']['version_name'],
                        "vehicle_register_date"         => $corporate_vehicles_quote_request['vehicle_register_date'],
                        "previous_policy_expiry_date"   => $user_proposal['policy_end_date'],
                        "previous_policy_type"          => $previous_policy_type,
                        "fuel_type"                     => $mmv_details['version']['fuel_type'],
                        "manufacture_year"              => $corporate_vehicles_quote_request['manufacture_year'],
                        "rto_code"                      => $corporate_vehicles_quote_request['rto_code'],
                        "vehicle_owner_type"            => $corporate_vehicles_quote_request['vehicle_owner_type'],
                        // "is_claim"                      => $corporate_vehicles_quote_request['is_claim'],
                        "is_claim"                      => 'N',
                        "previous_ncb"                  => $previous_ncb,
                        "applicable_ncb"                => $applicable_ncb,
                    ];
                    $quote_log_data = [
                        'product_sub_type_id' => $quote_log['product_sub_type_id'],
                        'quote_data' => json_encode($quote_data)
                    ];
                //}
            } 
            $corporate_vehicles_quote_request['is_renewal'] = $is_renewal_case_Y;
            CorporateVehiclesQuotesRequest::updateOrCreate(
                ['user_product_journey_id' => $newEnquiryId],
                $corporate_vehicles_quote_request
            );
            QuoteLog::updateOrCreate(
                ['user_product_journey_id' => $newEnquiryId],
                $quote_log_data
            );
            
            $response_data = [
                'product'               => get_parent_code($oldEnqueryIdData->product_sub_type_id),
                'sub_product'           => $product_sub_type_code,
                'product_sub_type_id'   => $oldEnqueryIdData->product_sub_type_id,
                'mmv_version_id'        => $corporate_vehicles_quote_request['version_id'],
                'prev_policy_number'    => $policy_details->previous_policy_number ?? null                
            ];
            
            $vehicle_quote_data = json_decode($quote_log['quote_data'],true);
            if(!isset($vehicle_quote_data['manfacture_name']))
            {
                $vehicle_quote_data['manfacture_id']    = $mmv_details['manufacturer']['manf_id'];
                $vehicle_quote_data['manfacture_name']  = $mmv_details['manufacturer']['manf_name'];
                $vehicle_quote_data['model_name']       = $mmv_details['model']['model_name'];
                $vehicle_quote_data['model']            = $mmv_details['model']['model_id'];
                $vehicle_quote_data['version_name']     = $mmv_details['version']['version_name'];
            }
            $manufactureMonthYear = explode('-',$corporate_vehicles_quote_request['manufacture_year']);
            $vehicleInvoiceDate = '01' .'-'. $manufactureMonthYear[0] . '-' . $manufactureMonthYear[1];

            //If current date - registration date >=10months || current date - registration date <= 1year , in this case send previous policy type. In the rest of the cases send the previous policy type as null

            if (isset($corporate_vehicles_quote_request['policy_type']) &&  !empty($corporate_vehicles_quote_request['vehicle_register_date']) && strtoupper($corporate_vehicles_quote_request['policy_type']) == 'Own-damage') {
                $registerDate = Carbon::createFromFormat('d-m-Y', $corporate_vehicles_quote_request['vehicle_register_date']);
                $now = Carbon::now();
                $monthsSinceRegistration = $registerDate->diffInMonths($now);

                if ($monthsSinceRegistration >= 10 && $monthsSinceRegistration <= 12) {
                    $previous_policy_type_logic = $corporate_vehicles_quote_request['policy_type'];
                }
            } else {

                $previous_policy_type_logic = $previous_policy_type;
            }

            $new_response_array = [
                'results' => 
                [
                    [
                        "vehicle" => 
                        [
                            "regn_dt"           => $corporate_vehicles_quote_request['vehicle_register_date'],
                            "vehicle_cd"        => $corporate_vehicles_quote_request['version_id'],
                            "fla_maker_desc"    => $vehicle_quote_data['manfacture_name'] ?? $mmv_details['manufacturer']['manf_name'] ,
                            "fla_model_desc"    => $vehicle_quote_data['model_name'] ?? $mmv_details['model']['model_name'] ,
                            "fla_variant"       => $vehicle_quote_data['version_name'] ?? $mmv_details['version']['version_name']
                        ],

                        "insurance" => 
                        [
                            "insurance_upto" => $policy_end_date->format('d-m-Y')
                        ]
                    ]

                ],
                'additional_details' => [
                    "applicableNcb"         => $corporate_vehicles_quote_request['applicable_ncb'],
                    "businessType"          => $business_type,
                    "product_sub_type_id"   => $user_product_journey_data["product_sub_type_id"],
                    "emailId"               => "",
                    "enquiryId"             => $request->enquiryId,
                    "firstName"             => $proposal_data['first_name'],
                    "lastName"              => $proposal_data['last_name'],
                    "fullName"              => $proposal_data['first_name'].' '.$proposal_data['last_name'], 
                    "emailId"               => $new_proposal['email'],
                    "mobileNo"              => $new_proposal['mobile_number'],  
                    "fuelType"              => $corporate_vehicles_quote_request["fuel_type"],                   
                    "hasExpired"            => "no",
                    "isClaim"               => 'N',
                    "isNcb"                 => "Yes",                    
                    "leadJourneyEnd"        => 1,
                    "manfactureId"          => $vehicle_quote_data['manfacture_id'],
                    "manfactureName"        => $vehicle_quote_data['manfacture_name'] ?? $mmv_details['manufacturer']['manf_name'],
                    //"manufactureYear"       => $vehicle_quote_data['manufacture_year']?? NULL,
                    "model"                 => $vehicle_quote_data['model'],
                    "modelName"             => $vehicle_quote_data['model_name'] ?? $mmv_details['model']['model_name'],
                    "ownershipChanged"      => "N",
                    "policyExpiryDate"      => $policy_end_date->format('d-m-Y'),                    
                    "previousInsurer"       => $company_details->company_name,
                    "previousInsurerCode"   => $company_details->company_alias,
                    "previousNcb"           => $corporate_vehicles_quote_request['previous_ncb'],
                    "policyType"            => $corporate_vehicles_quote_request['policy_type'],
                    "previousPolicyType"    => $previous_policy_type_logic,
                    "productSubTypeId"      => $user_product_journey_data["product_sub_type_id"],
                    "manufactureYear"       => $corporate_vehicles_quote_request['manufacture_year'],
                    "rto"                   => $corporate_vehicles_quote_request['rto_code'],
                    "stage"                 => 11,
                    "userProductJourneyId"  => $request->enquiryId,
                    "vehicleOwnerType"      => $corporate_vehicles_quote_request['vehicle_owner_type'],
                    "vehicleRegisterAt"     => $corporate_vehicles_quote_request['rto_code'],
                    "vehicleRegisterDate"   => $corporate_vehicles_quote_request['vehicle_register_date'],
                    "vehicleRegistrationNo" => $registration_no,
                    "vehicleUsage"          => 2,
                    "version"               => $corporate_vehicles_quote_request['version_id'],
                    "versionName"           => $vehicle_quote_data['version_name'] ?? $mmv_details['version']['version_name'],
                    "address_line1"         => "",
                    "address_line2"         => "",
                    "address_line3"         => "",
                    "engine_number"         => "",
                    "chassis_number"        => "",
                    "pincode"               => $new_proposal['pincode'],
                    "oldenquiryId"        => isset($oldEnqueryId) ? customEncrypt($oldEnqueryId) : null,
                    'vehicleInvoiceDate' => !empty($corporate_vehicles_quote_request['vehicle_invoice_date']) ? $corporate_vehicles_quote_request['vehicle_invoice_date'] :  $vehicleInvoiceDate,
                ],    
                'redirection_data' => [
                    'is_redirection' => $redirection_bool,
                    'redirection_url' => $redirection_url              
                ],
                'RenwalData' => "Y",
                'status' => 100
            ];
            
            if(isset($policy_details->pos_details['pos_code']) && !empty($policy_details->pos_details['pos_code']))
            {
               $new_response_array['pos_code'] = $policy_details->pos_details['pos_code'];
            }

            if ((config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') && (($data_source ?? '') == 'DATABASE')) {
                if (($agent_details[0] ?? false) && in_array($agent_details[0]->seller_type ?? '', ['P'])) {
                    $new_response_array['pos_code'] = $agent_details[0]->agent_id;
                } else {
                    return [
                        'status' => false,
                        'msg' => 'Agent Details not found'
                    ];
                }
            }else if(config('constants.brokerName') == 'ACE'){
                $agent_details = $agent_details->toArray();
                foreach ($agent_details as $key => $agent_detail) {
                      unset($agent_detail['id']);
                      $agent_detail['user_product_journey_id'] = $newEnquiryId;
                      $agent_detail['user_proposal_id'] = 'NULL';
                      $agent_detail['created_at'] = now();
                      $agent_detail['updated_at'] = now();
                      if(config('CvAgentMapping_FILTERED') == 'Y') {
                          $agentEntryData = createCvAgentMappingEntryForAgent($agent_detail);
                          if(!$agentEntryData['status']) {
                              return response()->json($agentEntryData);
                          }
                      } else {
                        //   CvAgentMapping::create($agent_detail);
                        CvAgentMapping::updateOrCreate(
                            ['user_product_journey_id' => $agent_detail['user_product_journey_id']],
                            $agent_detail
                        );
                      }
                  }
            }
            return [
                    'status' => true,
                    'msg' => 'Data Found',
                    'data'  => $new_response_array
            ]; 
        }
        else
        {
            return [
                'status' => false,
                'msg' => 'No Data found for Renewal'
            ];
        }
    }
    public function linkDelivery(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => ['nullable', 'uuid']
        ]);

        if (config('ABIBL_DROPOUT_JOURNEY_JOB') == "Y") {
            if ($request->dropout == 'dropout' || $request->dropout == 'timeout') {
                \App\Jobs\AbiblDropoutJourneyJob::dispatch($request->user_product_journey_id,$request->dropout)->delay(now());
                return response()->json([
                    'status' => true,
                    'msg' => 'Journey Status Updated...!'
                ], 200);
            }

            if ($request->dropout == 'continue') {
                \App\Models\AbiblDropoutJourney::where('user_product_journey_id', customDecrypt($request->user_product_journey_id))->update(['status' => 'continue']);
                return response()->json([
                    'status' => true,
                    'msg' => 'Journey Status Updated...!'
                ], 200);
            }
        }

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $link = \App\Models\LinkDeliverystatus::where(['key' => $request->key, 'status' => 'delivered'])->update([
            'status' => 'opened'
        ]);
            return response()->json([
                'status' => true,
                'msg' => 'Link Status Updated...!'
            ], 200);
    }

    public function frontendUrl(Request $request)
    {
        
        $bike_frontend_url  = config('constants.motorConstant.BIKE_FRONTEND_URL');
        $car_frontend_url   = config('constants.motorConstant.CAR_FRONTEND_URL');
        $cv_frontend_url    = config('constants.motorConstant.CV_FRONTEND_URL');
        $b2b = false;
        if(!empty($request->enquiryId))
        {
            $enquiryId = customDecrypt($request->enquiryId);
            $pos_data = CvAgentMapping::where('user_product_journey_id',$enquiryId)
                            ->whereIn('seller_type',['E','P'])
                            ->exists();
            if($pos_data)
            {   
                $b2b = true;
                $bike_frontend_url  = config('BIKE_B2B_FRONTEND_URL') !== NULL ? config('BIKE_B2B_FRONTEND_URL') : $bike_frontend_url;
                $car_frontend_url   = config('CAR_B2B_FRONTEND_URL') !== NULL ? config('CAR_B2B_FRONTEND_URL') : $car_frontend_url;
                $cv_frontend_url    = config('CV_B2B_FRONTEND_URL') !== NULL ? config('CV_B2B_FRONTEND_URL') : $cv_frontend_url;
            }
        }
        
        return response()->json([
            'status' => true,
            'isb2b'   => $b2b,
            'msg' => 'Url Retrived Successfully...!',
            'data' => [               
                'bike_frontend_url' => $bike_frontend_url,//config('constants.motorConstant.BIKE_FRONTEND_URL'),
                'car_frontend_url' => $car_frontend_url,//config('constants.motorConstant.CAR_FRONTEND_URL'),
                'cv_frontend_url' => $cv_frontend_url,//config('constants.motorConstant.CV_FRONTEND_URL'),
            ]
        ]);
    }
    public function getIcList(Request $request)
    {
       $company = MasterCompany::where('company_alias','!=',null,'or','company_alias','!=','')
                        ->where('status','Active')
                        ->select('company_alias')
                        ->pluck('company_alias')
                        ->toArray();

        return response()->json([
            'status' => true,
            'msg' => 'Received Company List Successfully...!',
            'data' => $company
        ]);
    }

    public function getDefaultCovers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $enquiryId = customDecrypt($request->enquiryId);

        $selectedAddons = selectedAddons::where('user_product_journey_id', $enquiryId)->first();

        $additional_covers = $discounts = $accessories = [];

        $corporateData = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)
            ->select('product_id','is_default_cover_changed')
            ->first()
            ->toArray();

        if(!$corporateData)
        {
            return response()->json([
                "status" => false,
                "msg" => 'Product Id not Found.',
            ]);
        }
        if($corporateData['is_default_cover_changed'] == 'Y')
        {
            return response()->json([
                "status" => false,
                "msg" => 'Default cover Already changed by user.',
            ]);
        }

        // // This event will be triggered when user visits on quote page 1st time.
        // event(new \App\Events\LandedOnQuotePage($enquiryId));

        $section = get_parent_code($corporateData['product_id']);

        $applicableCovers = DefaultApplicableCover::where('section', $section)
            ->where('status', 'Y')
            ->get();

        if($applicableCovers->isEmpty())
        {
            return response()->json([
                "status" => false,
                "msg" => 'No default Cover.',
            ]);
        }


        foreach ($applicableCovers as $key => $cover) {
            if($cover->sum_insured > 0)
            {
                ${$cover->cover_type}[] = [
                    'name' => $cover->cover_name,
                    'sumInsured' => $cover->sum_insured
                ];
            }
            else
            {
                ${$cover->cover_type}[] = [
                    'name' => $cover->cover_name
                ];
            }
        }

        selectedAddons::where([
            "user_product_journey_id" => $enquiryId
        ])->update([
            'accessories' => $accessories,
            'additional_covers' => $additional_covers,
            'discounts' => $discounts,
        ]);

        return response()->json([
            "status" => true,
            "data" => $applicableCovers,
        ]);
    }
    public function GetIssuePolicyList()
    {
        $data = DB::table('payment_request_response as prr')
                      ->join('policy_details as pd','pd.proposal_id','=','prr.user_proposal_id')
                      ->join('cv_journey_stages as cv','cv.user_product_journey_id','=','prr.user_product_journey_id')
                      ->leftjoin('gramcover_post_data_apis as gcpd','gcpd.user_product_journey_id','=','prr.user_product_journey_id' )
                      ->where('prr.active',1)
                      ->where('cv.stage',STAGE_NAMES['POLICY_ISSUED'])
                      ->where('pd.created_on','<','2022-03-22')
                      ->whereRaw('gcpd.user_product_journey_id is null')
                      ->select('prr.user_product_journey_id')
                      ->limit(1)
                      ->get()->pluck('user_product_journey_id');
    
        if(config('constants.motorConstant.GRAMCOVER_DATA_PUSH_ENABLED') == 'Y' && $data){
            foreach($data as $enquiryId)
                {
                    // \App\Jobs\GramcoverDataPush::dispatch($enquiryId);
                }
            }
            else{
                return response()->json([
                    "status" => false,
                    "msg" => 'No Record Found',
                ]);
            }
        }

    public static function getRcData(){
      
        $response=FastlaneRequestResponse::whereTime('response_time', '>', '00:00:10')->whereDate('created_at',Carbon::now())->get()->toArray();
        if(empty($response)){
        return [
        "status"=>false,
        "Message"=>"Data Not found"
        ];

        }
        $table="<html><head>
        <title>Rc Servicet</title>
      </head><body><table border='1' style='border:1px solid black'>";
      $table.="<thead><tr style='border:1px solid black'>";
      $table.="<th>Request</th>";
      $table.="<th>Response</th>";
      $table.="<th>Endpoint url</th>";
      $table.="<th>Response Time</th>";
      $table.="</tr></thead>";
        foreach($response as $row){
            $table.="<tr style='border:1px solid black'>";
            $table.="<td>{$row['request']}</td>";
            $table.="<td>{$row['response']}</td>";
            $table.="<td>{$row['endpoint_url']}</td>";
            $table.="<td>{$row['response_time']}</td>";
            $table.="</tr>";
        }
        $table.="</table></body></html>";
        // return $table;
          return  Mail::send([],[], function ($m) use ($table) {
            $m->to('vnathn.fyntune@gmail.com')->subject('')->setbody($table,'text/html');
            });
           
    }

    public function GetReturnUrl()
    {
        $data = [
            'car' => config('BIKE_FRONTEND_URL'),
            'bike' => config('CAR_FRONTEND_URL'),
            'cv' =>  config('CV_FRONTEND_URL'),
            'pcv' => config('PCV_FRONTEND_URL'),
            'gcv' => config('GCV_FRONTEND_URL')
        ];
        return response()->json([
            'status' => true,
            'msg' => 'Url Rwetrived Successfully...!',
            'data' => $data,
        ]);
    }
    
    public function logout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'token'     => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        //$enquiryId = customDecrypt($request->enquiryId);
        
        $logout_data = [
            'token' => $request->token
        ];
        $url = config('PINC_API_LOGOUT');
        $PINC_API_USERNAME = config('constants.motorConstant.PINC_API_USERNAME');
        $PINC_API_PASSWORD = config('constants.motorConstant.PINC_API_PASSWORD');
        $logout_response = Http::withBasicAuth($PINC_API_USERNAME, $PINC_API_PASSWORD)->post($url, $logout_data)->json();
        if($logout_response['result'] == true)
        {
            return response()->json([
                'status' => true,
                'msg' => 'Logout successfully',
                'data' => [
                    'redirectionUrl' => config('PINC_AFTER_LOGOUT_REDIRECTION')
                ]
            ]);
        }
        else
        {
            return response()->json([
                'status' => false,
                'msg' => $logout_response['message']
            ]);
        }
        
    }

    public function urlRequest(Request $request)
    {
        $validateData = $request->validate([
            'url' => 'required',
            'method' => 'required',
            'body' => 'nullable|array',
            'attachement' => 'nullable|array',
            'input_header' => 'nullable|array',
            'options' => 'nullable|array',
        ]);
        return httpRequestNormal($request->url, $request->method, $request->body, $request->attachement, $request->input_header, $request->options); 
    }

    /* public function sbiColor (Request $request){
    $color =  SbiColor::select('sbi_color')->pluck('sbi_color');
   


    return response()->json([
        'status' => true,
        "data" => $color,
    ]);
    
    } */

    public function checkLive()
    {
        switch (request()->type) {
            case 'case1':
                return response()->json([
                    'status' => false,
                    'msg' => "Invalid Request",
                    'type' => request()->type
                ]);
                break;
            case 'case2':
                return response()->json([
                    'status' => true,
                    "msg" => "Success",
                    'type' => request()->type
                ]);
                break;
            case 'case3':
                return response()->json([
                    'status' => false,
                    'msg' => "Database Connection Failed",
                    'type' => request()->type
                ]);
                break;
        }
    
        if (request()->header('lanninsport') != env('CHECK_LIVE_CREDS')) {
            return response()->json([
                'status' => false,
                'msg' => "Invalid Request"
            ]);
        }
        try {
            // \Illuminate\Support\Facades\DB::connection()->getPDO();
            // \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
            return response()->json([
                'status' => true,
                "msg" => "Success"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => "Database Connection Failed"
            ]);
        }
    }

    public function renewalDataUpload(Request $request)
    {
        if (config('constants.motorConstant.OFFLINE_DATA_UPLOAD_ALLOWED') != 'Y') {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ]);
        }

        $isFullDataUpload = config('constants.motorConstant.IS_FULL_DATA_UPLOAD') == 'Y';
        $isPartialDataUpload = config('constants.motorConstant.IS_PARTIAL_DATA_UPLOAD') == 'Y';

        $isAgentTransferAllowed = config('constants.motorConstant.IS_AGENT_TRANSFER_ALLOWED_IN_DATA_UPLOAD') == 'Y';

        if (!($isFullDataUpload || $isPartialDataUpload)) {
            return response()->json([
                'status' => false,
                'message' => 'Please configure the data upload type'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'policies' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ], 400);
        }

        $isValidated = false;

        $product_name = MasterProductSubType::select('product_sub_type_code')->whereNotNull('product_sub_type_code')
        ->pluck('product_sub_type_code')
        ->toArray();

        $masterCompany = MasterCompany::whereNotNull('company_alias')
        ->pluck('company_alias')
        ->toArray();
        $masterCompany = implode(',', $masterCompany);


        $product_name = implode(',', $product_name);
        $policies = [];
        foreach ($request->policies as $key => $value) {
            $policies[$key] = array_map(function ($v) {
                return ($v == "NULL") ? NULL : $v;
            }, $value);
        }

        $request->replace(['policies' => $policies]);
        unset($policies);

        if ($isFullDataUpload) {
            $validationCriteria = [
                'policies.*.action' => ['required', 'in:migrate'],
                'policies.*.vehicle_registration_number' => ['nullable', 'required_if:policies.*.action,migrate'],
                'policies.*.prev_policy_type' => ['nullable', 'required_if:policies.*.action,migrate', 'in:OD,COMPREHENSIVE,TP'],
                'policies.*.previous_insurer_company' => ['required_if:policies.*.action,migrate', 'nullable', 'in:'.$masterCompany],
                'policies.*.previous_policy_number' => ['required'],
                'policies.*.previous_policy_end_date' => ['required_if:policies.*.action,migrate', 'nullable', 'date_format:Y-m-d'],
                'policies.*.previous_policy_start_date' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.previous_tp_end_date' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.previous_tp_insurer_company' => ['nullable'],
                'policies.*.previous_tp_policy_number' => ['nullable'],
                'policies.*.tppd_cover' => ['nullable'],
                'policies.*.product_name' => ['nullable', 'required_if:policies.*.action,migrate','in:'.$product_name],
                'policies.*.rto_code' => ['nullable'],
                'policies.*.engine_no' => ['nullable','required_if:policies.*.previous_insurer_company,icici_lombard,kotak'],
                'policies.*.chassis_no' => ['nullable','required_if:policies.*.previous_insurer_company,icici_lombard,kotak'],
                'policies.*.registration_date' => ['nullable'],
                'policies.*.manufacturer_date' => ['nullable'],
                'policies.*.vehicle_colour' => ['nullable'],
                'policies.*.idv' => ['nullable'],
                'policies.*.owner_type' => ['nullable','in:individual,company'],
                'policies.*.full_name' => ['nullable'],
                'policies.*.mobile_no' => ['nullable', 'numeric'],
                'policies.*.email_address' => ['nullable', "email"],
                'policies.*.occupation' => ['nullable'],
                'policies.*.marital_status' => ['nullable'],
                'policies.*.dob' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.nominee_dob' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.gender' => ['nullable'],
                'policies.*.communication_address' => ['nullable'],
                'policies.*.communication_pincode' => ['nullable'],
                'policies.*.communication_city' => ['nullable'],
                'policies.*.communication_state' => ['nullable'],
                'policies.*.vehicle_registration_address' => ['nullable'],
                'policies.*.vehicle_registration_pincode' => ['nullable'],
                'policies.*.vehicle_registration_state' => ['nullable'],
                'policies.*.vehicle_registration_city' => ['nullable'],
                'policies.*.seller_type' => ['nullable'],
                'policies.*.seller_id' => ['nullable'],
                'policies.*.seller_username' => ['nullable'],
                'policies.*.seller_mobile' => [ 'nullable', 'numeric'],
                'policies.*.seller_aadhar_no' => ['nullable'],
                'policies.*.seller_pan_no' => ['nullable'],
                'policies.*.rm_username' => ['nullable'],
                'policies.*.rm_mobile' => ['nullable', 'numeric'],
                'policies.*.rm_email' => ['nullable', 'email'],
                'policies.*.rm_id' => ['nullable'],
                'policies.*.partner_id_code' => ['nullable'],
                'policies.*.partner_username' => ['nullable'],
                'policies.*.partner_mobile' => ['nullable', 'numeric'],
                'policies.*.partner_email' => ['nullable', 'email'],
                'policies.*.pos_code' => ['nullable'],
                'policies.*.pos_username' => ['nullable'],
                'policies.*.pos_aadhar_no' => ['nullable'],
                'policies.*.pos_pan_no' => ['nullable'],
                'policies.*.policy_issue_date' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.business_type' => ['nullable', 'in:rollover,newbusiness,breakin'],
            ];

            if ($isAgentTransferAllowed) {
                $validationCriteria['action'] = ['required', 'in:migrate,update'];
            }
            
        } elseif($isPartialDataUpload) {
            $validationCriteria = [
                'policies.*.action' => ['required', 'in:migrate'],
                'policies.*.vehicle_registration_number' => ['nullable', 'required_if:policies.*.action,migrate'],
                'policies.*.prev_policy_type' => ['nullable', 'required_if:policies.*.action,migrate', 'in:OD,COMPREHENSIVE,TP'],
                'policies.*.previous_insurer_company' => ['required_if:policies.*.action,migrate', 'nullable', 'in:'.$masterCompany],
                'policies.*.previous_policy_number' => ['required'],
                'policies.*.previous_policy_end_date' => ['required_if:policies.*.action,migrate', 'nullable', 'date_format:Y-m-d'],
                'policies.*.previous_policy_start_date' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.previous_tp_end_date' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.previous_tp_insurer_company' => ['nullable'],
                'policies.*.previous_tp_policy_number' => ['nullable'],
                'policies.*.tppd_cover' => ['nullable'],
                'policies.*.product_name' => ['nullable', 'required_if:policies.*.action,migrate','in:'.$product_name],
                'policies.*.rto_code' => ['nullable'],
                'policies.*.engine_no' => ['nullable','required_if:policies.*.previous_insurer_company,icici_lombard,kotak'],
                'policies.*.chassis_no' => ['nullable','required_if:policies.*.previous_insurer_company,icici_lombard,kotak'],
                'policies.*.registration_date' => ['nullable'],
                'policies.*.manufacturer_date' => ['nullable'],
                'policies.*.vehicle_colour' => ['nullable'],
                'policies.*.idv' => ['nullable'],
                'policies.*.owner_type' => ['nullable','in:individual,company'],
                'policies.*.full_name' => ['nullable'],
                'policies.*.mobile_no' => ['nullable', 'numeric'],
                'policies.*.email_address' => ['nullable', "email"],
                'policies.*.occupation' => ['nullable'],
                'policies.*.marital_status' => ['nullable'],
                'policies.*.dob' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.gender' => ['nullable'],
                'policies.*.communication_address' => ['nullable'],
                'policies.*.communication_pincode' => ['nullable'],
                'policies.*.communication_city' => ['nullable'],
                'policies.*.communication_state' => ['nullable'],
                'policies.*.vehicle_registration_address' => ['nullable'],
                'policies.*.vehicle_registration_pincode' => ['nullable'],
                'policies.*.vehicle_registration_state' => ['nullable'],
                'policies.*.vehicle_registration_city' => ['nullable'],
                'policies.*.seller_type' => ['nullable'],
                'policies.*.seller_id' => ['nullable'],
                'policies.*.seller_username' => ['nullable'],
                'policies.*.seller_mobile' => [ 'nullable', 'numeric'],
                'policies.*.seller_aadhar_no' => ['nullable'],
                'policies.*.seller_pan_no' => ['nullable'],
                'policies.*.rm_username' => ['nullable'],
                'policies.*.rm_mobile' => ['nullable', 'numeric'],
                'policies.*.rm_email' => ['nullable', 'email'],
                'policies.*.rm_id' => ['nullable'],
                'policies.*.partner_id_code' => ['nullable'],
                'policies.*.partner_username' => ['nullable'],
                'policies.*.partner_mobile' => ['nullable', 'numeric'],
                'policies.*.partner_email' => ['nullable', 'email'],
                'policies.*.pos_code' => ['nullable'],
                'policies.*.pos_username' => ['nullable'],
                'policies.*.pos_aadhar_no' => ['nullable'],
                'policies.*.pos_pan_no' => ['nullable'],
                'policies.*.policy_issue_date' => ['nullable', 'date_format:Y-m-d'],
                'policies.*.business_type' => ['nullable', 'in:rollover,newbusiness,breakin'],
            ];

            if ($isAgentTransferAllowed) {
                $validationCriteria['policies.*.action'] = ['required', 'in:migrate,update'];
            }

            if(stripos(\Illuminate\Support\Facades\URL::current(), 'bcl-wm/renewal-data-upload') !== false) {
                
                $result = \App\Helpers\Broker\DataUploadHelper::validationForBcl($request, $validationCriteria);

                if (!$result['status']) {
                    return response()->json($result);
                }

                $isValidated = true;
            }
        }

        if (!$isValidated) {
            $validator = Validator::make($request->all(), $validationCriteria);
            if ($validator->fails()) {
                $errors = $validator->errors();
                $messages = $errors->getMessages();
                $errorMessages = [];
                foreach ($messages as $key => $error_messages) {
                    foreach ($error_messages as $error_message) {
                        $row_no = explode('.', $key);
                        $row_no = $row_no[1] ?? $row_no[0];
                        $policy_row_no = "." . ($request->policies[$row_no]['policy_row_no'] ?? '') . ".";
                        $row_no = "." . $row_no . ".";
                        $errorMessages[str_replace($row_no, $policy_row_no, $key)][] = str_replace($row_no, $policy_row_no, $error_message);
                    }
                }
                return response()->json([
                    "status" => false,
                    "message" => $errorMessages,
                ]);
            }
        }
        
        try {
            $file_name = time().rand(1111, 9999) . '_';
            if (!empty($request->policies[0]['previous_policy_number'])) {
                $policy_number = $request->policies[0]['previous_policy_number'];
                $policy_number = preg_replace('/[^a-zA-Z0-9]/', '', $policy_number);
                $file_name.=$policy_number;
            }
            $count = 0;
            $unique_name = $file_name;
            $file_name = 'renewalDataUpload/' . $unique_name . '.json';
            
            while (Storage::exists($file_name)) {
                $count++;
                $file_name = 'renewalDataUpload/' . $unique_name . '_'. $count . '.json';
            }
            Storage::put($file_name, json_encode($request->all()));
            return response()->json([
                "status" => true,
                "total_records" => count($request->policies ?? []),
                "msg" => "Data uploaded Successfully...!"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "msg" => $e->getMessage()
            ]);
        }
    }

    public function GodigitKycStatus(Request $request)
    {
        if(isset($request->UserProductJourneyId))
        {
            $userProductJourneyId = customDecrypt($request->UserProductJourneyId);
            $proposal = UserProposal::where('user_product_journey_id',$userProductJourneyId)
                        ->first();
            $policy_id = QuoteLog::where('user_product_journey_id', $userProductJourneyId)->select('master_policy_id')->first();
            $product = getProductDataByIc($policy_id->master_policy_id);
            $oneapi = false;
            if ($product->product_sub_type_code == 'CAR') {
                $oneapi = config('IC.GODIGIT.V2.CAR.ENABLE') == 'Y';
            } else if ($product->product_sub_type_code == 'BIKE') {
                $oneapi = config('IC.GODIGIT.V2.BIKE.ENABLE') == 'Y';
            } else {
                $oneapi = config('IC.GODIGIT.V2.CV.ENABLE') == 'Y';
            }
            if(!empty($proposal))
            {
                if(config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y')
                {
                    if (config('constants.IS_CKYC_ENABLED') == 'Y') 
                    {
                        if ($oneapi) {
                            include_once app_path() . '/Helpers/IcHelpers/GoDigitHelper.php';
                            $KycStatusApiResponse = GetKycStatusGoDIgitOneapi($proposal->user_product_journey_id, $proposal->proposal_no,  "second verification ", $proposal->user_proposal_id, $request->UserProductJourneyId, $product);
                        } else {
                            $KycStatusApiResponse = GetKycStatusGoDIgit($proposal->user_product_journey_id, $proposal->proposal_no,  "second verification ", $proposal->user_proposal_id, $request->UserProductJourneyId);
                        }
                        if($KycStatusApiResponse['status'] !== true)
                        {
                            CkycGodigitFailedCasesData::updateOrCreate(
                                ['user_product_journey_id' => $proposal->user_product_journey_id],
                                [
                                    'policy_no' => $proposal->proposal_no,
                                    'kyc_url' => $KycStatusApiResponse['message'] ?? '',
                                    'return_url' => '',
                                    'status' => 'failed',
                                    'post_data' => ''
                                ]
                            );
                            UserProposal::where('user_product_journey_id',$proposal->user_product_journey_id)
                            ->where('user_proposal_id' ,$proposal->user_proposal_id)
                            ->update(['is_ckyc_verified' => 'N']);
                            
                            $message = '';
                            $KycError = [
                                            'S'	=> 'Success',
                                            'F'	=> 'Fail',
                                            'P'	=> 'Name Mismatch',
                                            'A'	=> 'Address Mismatch',
                                            'B'	=> 'Name & Address Mismatch'
                                        ];
                            if(isset($KycStatusApiResponse['response']) && !empty($KycStatusApiResponse['response']->mismatchType))
                            {
                                $message = in_array($KycStatusApiResponse['response']->mismatchType,['P','A','B']) ? "Your kyc verification failed due to ".$KycError[$KycStatusApiResponse['response']->mismatchType]." , after successful kyc completion please fill proposal data as per KYC documents" : $KycError[$KycStatusApiResponse['response']->mismatchType];
                            }else if(isset($KycStatusApiResponse['response']) && empty($KycStatusApiResponse['response']->mismatchType) && filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL))
                            {
                                $message = 'Please fill correct proposal data as per documents provided for KYC verification';
                            }

                            if((filter_var($KycStatusApiResponse['message'], FILTER_VALIDATE_URL)))
                            {
                                return response()->json([
                                    'status' => true,
                                    'msg' => "Proposal Submitted Successfully!",
                                    'data' => [                            
                                        'proposalId' => $proposal->user_proposal_id,
                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                        'proposalNo' => $proposal->unique_proposal_id,
                                        'finalPayableAmount' => $proposal->final_payable_amount, 
                                        'is_breakin' => ($proposal->is_breakin_case == 'N') ? false : true,
                                        'inspection_number' => $proposal->proposal_no,
                                        'kyc_url' => $KycStatusApiResponse['message'],
                                        'is_kyc_url_present' => true,
                                        'kyc_message' => $message,
                                        'kyc_status' => false,
                                    ]
                                ]);
                            }else
                            {
                                // if no URL RETURNED BY IC FOR KYC
                                return response()->json([
                                    'status' => true,
                                    'msg' => "Proposal Submitted Successfully!",
                                    'data' => [                            
                                        'proposalId' => $proposal->user_proposal_id,
                                        'userProductJourneyId' => $proposal->user_product_journey_id,
                                        'proposalNo' => $proposal->unique_proposal_id,
                                        'finalPayableAmount' => $proposal->final_payable_amount, 
                                        'is_breakin' => ($proposal->is_breakin_case == 'N') ? false : true,
                                        'inspection_number' => $proposal->proposal_no,
                                        'kyc_url' => '',
                                        'is_kyc_url_present' => false,
                                        'kyc_message' => $message,
                                        'kyc_status' => false,
                                    ]
                                ]);
                            }
            
                        }else
                        {       
        
                            CkycGodigitFailedCasesData::updateOrCreate(
                                ['user_product_journey_id' => $proposal->user_product_journey_id],
                                [
                                    'policy_no' => $proposal->proposal_no,
                                    'status' => 'success',
                                    'return_url' => '',
                                    'kyc_url' => '',
                                    'post_data' => ''

                                ]
                            );
        
                            UserProposal::where('user_product_journey_id',$proposal->user_product_journey_id)
                            ->where('user_proposal_id' ,$proposal->user_proposal_id)
                            ->update(['is_ckyc_verified' => 'Y']);
                            // Need to update the CKYC status for RB
                            event(new \App\Events\CKYCInitiated($proposal->user_product_journey_id));

                            $selected_addons = SelectedAddons::where('user_product_journey_id', $proposal->user_product_journey_id)
                                        ->select('addons','compulsory_personal_accident', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
                                        ->first();

                            $sortedDetails = sortKeysAlphabetically(json_decode($proposal->additional_details));

                            $hashData = collect($sortedDetails)->merge($proposal->ic_name)->merge($selected_addons->addons)->merge($selected_addons->compulsory_personal_accident)->merge($selected_addons->accessories)->merge($selected_addons->additional_covers)->merge($selected_addons->voluntary_insurer_discounts)->merge($selected_addons->discounts)->all();
                                
                            $kycHash = hash('sha256', json_encode($hashData));

                            ProposalHash::create(
                                [
                                    'user_product_journey_id' => $proposal->user_product_journey_id,
                                    'user_proposal_id' => $proposal->user_proposal_id,
                                    'additional_details_data' => is_array($proposal->additional_details) ?  json_encode($proposal->additional_details) : $proposal->additional_details,
                                    'hash' => $kycHash ?? null,
                                ]
                            );

                            return response()->json([
                                'status' => true,
                                'msg' => "Proposal Submitted Successfully!",
                                'data' => [                            
                                    'proposalId' => $proposal->user_proposal_id,
                                    'userProductJourneyId' => $proposal->user_product_journey_id,
                                    'proposalNo' => $proposal->unique_proposal_id,
                                    'finalPayableAmount' => $proposal->final_payable_amount, 
                                    'is_breakin' => ($proposal->is_breakin_case == 'N') ? false : true,
                                    'inspection_number' => $proposal->proposal_no,
                                    'kyc_status' => (config('GODIGIT_KYC_VERIFICATION_API_PREPAYMENT_ENABLE') === 'Y') ? true : false,
                                ]
                            ]);
            
                        }
                    }

                }
                

            }else
            {
                return response()->json([
                    "status" => false,
                    "msg" => "No proposal found against given user_product_journey_id"
                ]);
            }
        }else
        {
            return response()->json([
                "status" => false,
                "msg" => "Please provide user_product_journey_id"
            ]);
        }
    }
    public function royalSundaramKycStatus(Request $request)
    {
        if (isset($request->userProductJourneyId)) {
            $userProductJourneyId = customDecrypt($request->userProductJourneyId);
            $proposal = UserProposal::where('user_product_journey_id', $userProductJourneyId)
                ->first();

            if (!empty($proposal->proposal_no) && $proposal->is_ckyc_verified == 'Y') {
                if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                    $request_data = [
                        'company_alias' => 'royal_sundaram',
                        'type' => 'redirection',
                        'mode' => 'fetch_api',
                        'section' => 'motor',
                        'trace_id' => $request->userProductJourneyId,
                        'meta_data' => [
                            'uniqueId' => $proposal->proposal_no
                        ]
                    ];

                    $response = httpRequestNormal(config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/ckyc-verifications', 'POST', $request_data, [], [
                        'Content-Type' => 'application/json'
                    ], [], false, false);
                    if ($response['status'] == 200 && isset($response['response']['data']['verification_status']) && $response['response']['data']['verification_status']) {

                        UserProposal::where('user_product_journey_id', $proposal->user_product_journey_id)
                            ->where('user_proposal_id', $proposal->user_proposal_id)
                            ->update(['is_ckyc_verified' => 'Y']);

                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => $proposal->unique_proposal_id,
                                'finalPayableAmount' => $proposal->final_payable_amount,
                                'is_breakin' => ($proposal->is_breakin_case == 'N') ? false : true,
                                'inspection_number' => $proposal->proposal_no,
                                'kyc_status' => true,
                            ]
                        ]);
                    }else{
                        return response()->json([
                            'status' => true,
                            'msg' => "Proposal Submitted Successfully!",
                            'data' => [
                                'proposalId' => $proposal->user_proposal_id,
                                'userProductJourneyId' => $proposal->user_product_journey_id,
                                'proposalNo' => $proposal->unique_proposal_id,
                                'finalPayableAmount' => $proposal->final_payable_amount,
                                'is_breakin' => ($proposal->is_breakin_case == 'N') ? false : true,
                                'inspection_number' => $proposal->proposal_no,
                                'kyc_status' => false,
                                'kyc_url' => filter_var($proposal->additional_details_data, FILTER_VALIDATE_URL) ? $proposal->additional_details_data : '',
                                'is_kyc_url_present' => true,
                            ]
                        ]);
                    }
                }
            } else {
                return response()->json([
                    "status" => false,
                    "data" => [
                        "junk" => ""
                    ]
                ]);
            }
        } else {
            return response()->json([
                "status" => false,
                "data" => [
                    "junk" => ""
                ]
            ]);
        }
    }

    public function comparePageSms(Request $request)
    {
        if(!empty($request->data['userProductJourneyId']))
        {
            $enquiryId = customDecrypt($request->data['userProductJourneyId']);
        }
        else if(!empty($request->userProductJourneyId))
        {
            $enquiryId = customDecrypt($request->userProductJourneyId);
        }

        if (!empty($request->data['type']) && $request->data['type'] == 'fetch') {
            sleep(3);
            $data = CompareSms::where('user_product_journey_id', $enquiryId)->first();
            sleep(1);
            return response()->json([
                "status" => true,
                "msg" => "Data Fetch Successfully...!",
                "data" => $data
            ]);
        }
        $data = $request->data;
        unset($data['enquiry_id'],$data['userProductJourneyId']);
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (isset($value['mmvDetail'])) {
                    $data[$key]['mmvDetail']['versionName'] = ($value['mmvDetail']['versionName'] ?? '');
                }
            }
        }
        $data = CompareSms::updateOrCreate(["user_product_journey_id"=> $enquiryId], [
            "data" => $data
        ]);
        sleep(1);
        return response()->json([
            "status" => true,
            "msg" => "Data Stored Successfully...!",
            "data" => $data
        ]);
    }

    public function generateLeadByVehicleDetails(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'vehicle_details.vehicle_registration_number' => ['required'],
            'vehicle_details.section' => 'required|in:cv,car,bike',
            'customer_details.email' => ['email', 'nullable'],
            'customer_details.mobile' => ['numeric', 'nullable']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        try {

            
            DB::beginTransaction();

            $new_rc_number = \Illuminate\Support\Str::upper($request?->vehicle_details["vehicle_registration_number"]);
            $rto_code = explode('-', $new_rc_number);
            $rto_code = implode('-', [$rto_code[0], $rto_code[1]]);

        

            $userProductJourneyData = [
                'user_fname' => $request?->customer_details["first_name"] ?? NULL,
                'user_lname' => $request?->customer_details['last_name'] ?? NULL,
                'user_email' => $request?->customer_details['email'] ?? NULL,
                'user_mobile' => $request?->customer_details['mobile'] ?? NULL
            ];

            $UserProductJourney = UserProductJourney::create($userProductJourneyData);

            $agent_details =  $UserProductJourney->agent_details()->create([
                'seller_type'   => $request?->agent_details['seller_type'] ?? NULL,
                'agent_id'      => $request?->agent_details['agent_id'] ?? NULL,
                'user_name'     => $request?->agent_details['user_name'] ?? NULL,
                'agent_name'    => $request?->agent_details['agent_name'] ?? NULL,
                'agent_mobile'  => $request?->agent_details['agent_mobile'] ?? NULL,
                'agent_email'   => $request?->agent_details['agent_email'] ?? NULL,
                'unique_number' => $request?->agent_details['unique_number'] ?? NULL,
                'aadhar_no'     => $request?->agent_details['aadhar_no'] ?? NULL,
                'pan_no'        => $request?->agent_details['pan_no'] ?? NULL,
                "source"        => $request?->agent_details['source'] ?? NULL,
            ]);

            $sectionList = [
                'car' => '1',
                'bike' => '2',
                'cv' => '5', 
                'cv' =>  '6', 
                'cv' =>  '7',
                'cv' =>  '8',
                'cv' =>  '10',
                'cv' => '11',
                'cv' => '12',
                'cv' => '4', 
                'cv' => '9',
                'cv' =>  '13',
                'cv' => '14',
                'cv' =>  '15',
                'cv' =>  '16',
            ];

            $enquiryId = $UserProductJourney?->journey_id;

            $stageOneData = [
                "stage" => 1,
                "userProductJourneyId" => $enquiryId ?? NULL,
                "enquiryId" => $enquiryId ?? NULL,
                "productSubTypeId" => (int) $sectionList[$request->vehicle_details['section']] ?? '',
            ];

            # json_decode(self::saveQuoteRequestData($requestStageOne)->getContent(), true);

            $requestStageOne = new \Illuminate\Http\Request($stageOneData);
            self::saveQuoteRequestData($requestStageOne);

            $stageTwoData = [
                "stage" => 2,
                "vehicleRegistrationNo" => $new_rc_number,
                "rtoNumber" => $rto_code,
                "rto" => $rto_code,
                "userProductJourneyId" => $enquiryId ?? NULL,
                "vehicleRegisterAt" => $rto_code,
                "enquiryId" => $enquiryId ?? NULL,
                "vehicleRegisterDate" => NULL,
                "policyExpiryDate" => NULL,
                "previousInsurerCode" => NULL,
                "previousInsurer" => NULL,
                "previousPolicyType" => NULL,
                "businessType" => NULL,
                "policyType" => NULL,
                "previousNcb" => NULL,
                "applicableNcb" => NULL,
                "fastlaneJourney" => true
            ];

            $requestStageTwo = new \Illuminate\Http\Request($stageTwoData);
            self::saveQuoteRequestData($requestStageTwo);

            $stageThreeData = [
                "stage" => 3,
                "userProductJourneyId" => $enquiryId ?? null,
                "enquiryId" => $enquiryId ?? null,
                "productSubTypeName" => $request?->vehicle_details['section'],
                "productSubTypeId" => (int) $sectionList[$request->vehicle_details['section']] ?? '',
            ];

            $requestStageThree = new \Illuminate\Http\Request($stageThreeData);
            self::saveQuoteRequestData($requestStageThree);

            $getVehicleDetails = [
                "enquiryId" => $enquiryId,
                "registration_no" => $new_rc_number,
                "productSubType" => $sectionList[$request->vehicle_details['section']] ?? '',
                "section" => $request?->vehicle_details['section'],
                "is_renewal" => "N"
            ];

            $old_request = request()->input();
            $requestVehicles = new \Illuminate\Http\Request($getVehicleDetails);
            $requestVehicles->method('get');
            request()->replace($getVehicleDetails);
            $response = self::getVehicleDetails($requestVehicles);
            request()->replace($old_request);
            // if ($response instanceof \Illuminate\Http\JsonResponse) {
            //     $get_vehicle_details = json_decode($response->getContent(), true);
            // } else {
            //     return response()->json([
            //         "status" => false,
            //         "msg" => 'Something Went Wrong!'
            //     ]);
            // }

            $get_vehicle_details = $response;
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $get_vehicle_details = json_decode($response->getContent(), true);
            }
            if (empty($get_vehicle_details)) {
                return response()->json([
                    "status" => false,
                    "msg" => 'Something Went Wrong!'
                ]);
            }

            UserProductJourney::where(['user_product_journey_id' => customDecrypt($enquiryId)])->update(['api_token' => $request->token]);

            if (isset($get_vehicle_details['data']['additional_details']) && !empty($get_vehicle_details['data']['additional_details'])) {
                $stageElevenData = [
                    "enquiryId" => $enquiryId,
                    "vehicleRegistrationNo" => $get_vehicle_details['data']['additional_details']['vehicleRegistrationNo'],
                    "userProductJourneyId" => $enquiryId,
                    "corpId" => null,
                    "userId" => null,
                    "fullName" => $get_vehicle_details['data']['additional_details']['fullName'] ?? '',
                    "firstName" => $get_vehicle_details['data']['additional_details']['firstName'] ?? '',
                    "lastName" => $get_vehicle_details['data']['additional_details']['lastName'] ?? '',
                    "emailId" => $get_vehicle_details['data']['additional_details']['emailId'] ?? '',
                    "mobileNo" => $get_vehicle_details['data']['additional_details']['mobileNo'] ?? '',
                    "policyType" => $get_vehicle_details['data']['additional_details']['policyType'] ?? '',
                    "businessType" => $get_vehicle_details['data']['additional_details']['businessType'] ?? '',
                    "rto" => $get_vehicle_details['data']['additional_details']['rto'] ?? '',
                    "manufactureYear" => $get_vehicle_details['data']['additional_details']['manufactureYear'] ?? '',
                    "version" => $get_vehicle_details['data']['additional_details']['version'] ?? '',
                    "versionName" => $get_vehicle_details['data']['additional_details']['versionName'] ?? '',
                    "vehicleRegisterAt" => $get_vehicle_details['data']['additional_details']["vehicleRegisterAt"] ?? '',
                    "vehicleRegisterDate" => $get_vehicle_details['data']['additional_details']["vehicleRegisterDate"] ?? '',
                    "vehicleOwnerType" => $get_vehicle_details['data']['additional_details']["vehicleOwnerType"] ?? '',
                    "hasExpired" => $get_vehicle_details['data']['additional_details']['hasExpired'] ?? '',
                    "isNcb" => $get_vehicle_details['data']['additional_details']['isNcb'] ?? '',
                    "isClaim" => $get_vehicle_details['data']['additional_details']['isClaim'] ?? '',
                    "fuelType" => $get_vehicle_details['data']['additional_details']['fuelType'] ?? '',
                    "vehicleUsage" =>  $get_vehicle_details['data']['additional_details']['vehicleUsage'] ?? '',
                    "vehicleLpgCngKitValue" => "",
                    "previousInsurer" => $get_vehicle_details['data']['additional_details']["previousInsurer"] ?? '',
                    "previousInsurerCode" => $get_vehicle_details['data']['additional_details']['previousInsurerCode'] ?? '',
                    "previousPolicyType" => $get_vehicle_details['data']['additional_details']["previousPolicyType"] ?? '',
                    "modelName" => $get_vehicle_details['data']['additional_details']["modelName"] ?? '',
                    "manfactureName" => $get_vehicle_details['data']['additional_details']["manfactureName"] ?? '',
                    "ownershipChanged" => $get_vehicle_details['data']['additional_details']["ownershipChanged"] ?? '',
                    "leadJourneyEnd" => $get_vehicle_details['data']['additional_details']["leadJourneyEnd"] ?? '',
                    "stage" => 11,
                    "applicableNcb" => $get_vehicle_details['data']['additional_details']["applicableNcb"] ?? '',
                    "product_sub_type_id" => $get_vehicle_details['data']['additional_details']["product_sub_type_id"] ?? '',
                    "manfactureId" => $get_vehicle_details['data']['additional_details']["manfactureId"] ?? '',
                    "model" => $get_vehicle_details['data']['additional_details']["model"] ?? '',
                    "previousNcb" => $get_vehicle_details['data']['additional_details']["previousNcb"] ?? '',
                    "productSubTypeId" => $get_vehicle_details['data']['additional_details']["productSubTypeId"] ?? '',
                    "engine_number" => $get_vehicle_details['data']['additional_details']["engine_number"] ?? '',
                    "chassis_number" => $get_vehicle_details['data']['additional_details']["chassis_number"] ?? '',
                    "pincode" => $get_vehicle_details['data']['additional_details']["pincode"] ?? '',
                    "oldenquiryId" => $get_vehicle_details['data']['additional_details']["oldenquiryId"] ?? null
                ];

                $requestStageEleven = new \Illuminate\Http\Request($stageElevenData);
                self::saveQuoteRequestData($requestStageEleven);
            }

            $query_data = [
                'enquiry_id' => $enquiryId,
                # 'token'      => $request->token,
                # 'lead_id'    => $request->crm_lead_id
            ];

            if ($request->vehicle_details['section'] === "cv") {
                $domain = config('constants.motorConstant.CV_FRONTEND_URL');
            } elseif ($request->vehicle_details['section'] === "bike") {
                $domain = config('constants.motorConstant.BIKE_FRONTEND_URL');
            } elseif ($request->vehicle_details['section'] === "car") {
                $domain = config('constants.motorConstant.CAR_FRONTEND_URL');
            }

            $journey_stage['user_product_journey_id'] = customDecrypt($enquiryId);
            $journey_stage['stage'] = STAGE_NAMES['LEAD_GENERATION'];
            $journey_stage['quote_url'] = $domain . '/quotes?' . http_build_query($query_data);

            JourneyStage::updateOrCreate(
                ['user_product_journey_id' => customDecrypt($enquiryId)],
                $journey_stage
            );

            updateJourneyStage($journey_stage);

            # Transaction Successful.
            DB::commit();

            //vahan will return 100 for success case and other will return status in boolean
            $status = $get_vehicle_details['data']['status'] ?? $get_vehicle_details['status'] ?? false;

            if (in_array($status, [100, true])) {
                return response()->json([
                    "status" => true,
                    "redirect_url" => $domain . '/quotes?' . http_build_query($query_data)
                ]);
            }

            return response()->json([
                "status" => true,
                "redirect_url" => $domain . '/vehicle-details?' . http_build_query($query_data)
            ]);
        } catch (\Exception $e) {
            info($e);
            return $e->getMessage();
            DB::rollBack();
        }
    }

    public function getOrganizationTypes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ]);
        }


        if($request->companyAlias == 'sbi' && config('ENABLE_ORGANIZATION_FILTER_FOR_SBI') == 'Y') {
            $organizationType = MasterOrganizationTypes::with('poi_documents')->with('poa_documents')
                ->where('company_alias', 'sbi')->get()->toArray();
            $organization_types = [];
            foreach ($organizationType as $key => $value) {
                if(!(empty($value['poi_documents']) || empty($value['poi_documents']))) {
                    $organization_types[] = $value;
                }
            }
        } else {
            $organization_types = MasterOrganizationTypes::where('company_alias', $request->companyAlias)->get()->toArray();
        }

        list($status, $message, $data) = $organization_types ? [true, 'Organization types found', $organization_types] : [false, 'No organization types found', []];

        return response()->json(compact('status', 'message', 'data'));
    }

    public function getIndustryTypes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ]);
        }

        $industry_types = MasterIndustryType::where('company_alias', $request->companyAlias)->get()->toArray();

        list($status, $message, $data) = $industry_types ? [true, 'Industry types found', $industry_types] : [false, 'No industry types found', []];

        return response()->json(compact('status', 'message', 'data'));
    }

    public function getColor(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        switch($request->companyAlias)
        {
            case 'sbi' :
                $color =  SbiColor::select('sbi_color')->pluck('sbi_color')->toArray();
                if(empty($color))
                {
                    return response()->json([
                        'status' => false,
                        "data" => [],
                        "msg"  =>'Data not found'
                    ]);
                }
                return response()->json([
                    'status' => true,
                    "data" => $color,
                    "msg"  =>'Data found'
                ]);
            break;

            case 'universal_sompo' :
                $color =  usgiColorMaster::pluck('ColorName')->toArray();
                if (empty($color)) {
                    return response()->json([
                        'status' => false,
                        "data" => [],
                        "msg"  =>'Data not found'
                    ]);
                }

                return response()->json([
                        'status' => true,
                        "data" => $color,
                        "msg"  =>'Data found'
                    ]);
            break;

            case 'new_india':
                $color =  newIndiaColorMaster::pluck('color_code')->toArray();
                if (empty($color)) {
                    return response()->json([
                        'status' => false,
                        "data" => [],
                        "msg"  =>'Data not found'
                    ]);
                }

                return response()->json([
                        'status' => true,
                        "data" => $color,
                        "msg"  =>'Data found'
                    ]);
            break;

            case 'nic':
                $color = NicVehicleColorMaster::pluck('color_name')->toArray();
                if (empty($color)) {
                    return response()->json([
                        'status' => false,
                        "data" => [],
                        "msg"  => 'Data not found'
                    ]);
                }

                return response()->json([
                    'status' => true,
                    "data" => $color,
                    "msg"  => 'Data found'
                ]);
            break;

            default:
                return response()->json([
                    'status' => false,
                    "data" => [],
                    "msg"  =>'Data not found'
                ]);
            break;
        }
        
    }

    public function getAllBrokerName(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'env' => ['required', Rule::in(['uat', 'preprod', 'prod', 'all'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'msg' => $validator->errors()
            ]);
        }
        $broker_data = BrokerDetail::where('status', 'active')
        ->when($request->env != 'all', function ($query) {
            return $query->where('environment', '=', request()->env);
        })
            ->select('name', 'backend_url as url', 'environment')
            ->get()->toArray();

        if (!empty($broker_data)) {
            return response()->json([
                'status' => true,
                'environment' => request()->env,
                'data' => $broker_data
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => "No data found"
            ]);
        }
    }

    public function updateTataAigCkycDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'policy_no' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()
            ]);
        }

        $policy_details = PolicyDetails::where('policy_number', 'like', '%' . $request->policy_no . '%')->first();

        if ($policy_details) {
            if ($policy_details->user_proposal->ic_id == 24) {
                $verification_types = [
                    'aadhar' => 'AADHAR',
                    'aadhar_card' => 'AADHAR',
                    'driving_license' => 'DL',
                    'passport_no' => 'PASSPORT',
                    'voter_id' => 'VOTERID'
                ];

                if ( ! in_array($policy_details->user_proposal->ckyc_type, array_keys($verification_types))) {
                    return response()->json([
                        'status' => false,
                        'message' => "Verification type " . $policy_details->user_proposal->ckyc_type . " doesn't belongs to verification type array"
                    ]);
                }

                $update_request = [
                    'T' => config('constants.IcConstants.tata_aig.TOKEN'),
                    'product_code' => config('constants.IcConstants.tata_aig.cv.PRODUCT_ID'),
                    // 'quote_id' => $policy_details->user_proposal->unique_proposal_id,
                    'proposal_no' => $policy_details->user_proposal->proposal_no,
                    'p_ckyc_pan' => '',
                    'p_ckyc_no' => '',
                    'p_ckyc_id_type' => $verification_types[$policy_details->user_proposal->ckyc_type],
                    'p_ckyc_id_no' => $policy_details->user_proposal->ckyc_type_value,
                    'timestamp' => date('Y-m-d\TH:i:sP'),
                    'kyc_status' => 'SUCCESS',
                    'kyc_flow' => 'API'
                ];

                include app_path('Helpers/CvWebServiceHelper.php');

                $response = getWsData(config('constants.IcConstants.tata_aig.TATA_AIG_UPDATE_CKYC_DETAILS'), $update_request, 'tata_aig', [
                    'enquiryId' => $policy_details->user_proposal->user_product_journey_id,
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded'
                    ],
                    'requestMethod' => 'post',
                    'requestType' => 'json',
                    'section' => $policy_details->user_proposal->quote_log->master_policy->product_sub_type_code->product_sub_type_code,
                    'method' => 'Update CKYC Details By Script',
                    'transaction_type' => 'proposal',
                    'productName' => $policy_details->user_proposal->quote_log->master_policy->master_product->product_name,
                ]);

                if ( ! empty($response['response'])) {
                    $response_data = json_decode($response['response'], true);
            
                    if (($response_data['errcode'] ?? '') == 'KYC002') {
                        return [
                            'status' => true,
                            'message' => 'KYC details updated successfully!!!'
                        ];
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => 'An error occurred while updating kyc details.'
                        ]);
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'Unable to verify CKYC, please try again after some time.'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'message' => "Policy number doesn't belongs to TATA AIG."
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Policy details not found.'
            ]);
        }
    }

    public function getFaq(Request $request) { 
        $content = DB::table('theme_configs')->first(); 
       
        return response()->json([ 
            'status' => true, 
            'data' => [ 
                'content' => $content->content ?? ''
            ] 
        ]); 
    } 
 
    public function postFaq(Request $request) { 
       
        $payload = $request->all();

        $content = DB::table('theme_configs') 
            ->update(['content' => $payload['data']['content']]); 
 
        return response()->json([ 
            'status' => true, 
            'message' => 'Data updated successfully.'
        ]); 
    }
    public function checkValidPolicyViaStartDate($policy_details)
    {
        $date1 = new \DateTime();
        $date2 = new \DateTime($policy_details->policy_start_date);
        $interval = $date1->diff($date2);
        if($interval->days < 60)
        {
            return [
                'status' => false,
                'msg' => 'Policy already issued and Policy Period is '.$policy_details->policy_start_date.' to '.$policy_details->policy_end_date,
                'overrideMsg' => 'Policy already issued and Policy Period is '.$policy_details->policy_start_date.' to '.$policy_details->policy_end_date,
                'show_error' => true
            ];
        }
        return ['status' => true];
    }

    public function agent_data_cleanup(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1800);
        if(config('constants.motorConstant.SMS_FOLDER') == 'ace' && config('DATA_CLEAN_UP_ACTIVE') == 'Y')
        {
            //$exculde_data = DB::table('data_cleanup_logs')->pluck('user_product_journey_id');        
            // $agentDetail =  CvAgentMapping::whereNull('seller_type')
            //                                 //->whereNotIn('user_product_journey_id',$exculde_data)
            //                                 ->where('processed','N')
            //                                 ->limit(10000)
            //                                 ->get();
            if($request->update_token == 'Y')
            {
                CvAgentMapping::whereNull('seller_type')
                //->whereNotIn('user_product_journey_id',$exculde_data)
                ->where('processed','N')
                //->where('user_product_journey_id',300470)
                ->whereNotNull('agent_id')
                ->chunk(1000,function($agentDetail)
                {
                    foreach ($agentDetail as $key => $value) 
                    {
                        $deleted = 'N';
                        if(!empty($value->agent_id))
                        {
                            $is_updated = CvAgentMapping::
                            where('user_product_journey_id',$value->user_product_journey_id)
                            //->whereIn('seller_type', ['E','U'])
                            ->update([
                                'token' => NULL
                            ]);

                            $is_updated = CvAgentMapping::
                            where('user_product_journey_id',$value->user_product_journey_id)
                            //->whereIn('seller_type', ['E','U'])
                            ->update([
                                'token' => $value->agent_id
                            ]);
                        }                            
                        
                        if($is_updated)
                        {
                            $data_delete = CvAgentMapping::where('id',$value->id)->delete();
                            $deleted = $data_delete ? 'Y' : 'N';
                        }                        
                        CvAgentMapping::where(['id' => $value->id])->update(['processed' => "Y"]);
                        DB::table('data_cleanup_logs')->insert([
                        'user_product_journey_id'    =>  $value->user_product_journey_id,
                        'agent_id'                   =>  $value->agent_id,
                        'deleted'                    =>  $deleted
                        ]);
                    }
                    echo "Update Token Done";
                });
                echo "Samapt update_token";
                die;
            }


            if($request->delete_duplicate == 'Y')
            {
                $duplicate_data = DB::table('cv_agent_mappings as a')
                ->selectRaw('COUNT(a.user_product_journey_id) as count,a.user_product_journey_id')
                ->join('cv_journey_stages as s','s.user_product_journey_id' ,'=','a.user_product_journey_id')
                //->where('s.stage',STAGE_NAMES['POLICY_ISSUED'])
                ->groupBy('a.user_product_journey_id')
                ->havingRaw('COUNT(a.user_product_journey_id) > 1')
                ->orderBy('a.user_product_journey_id')
                ->chunk(1000,function($duplicate_data)
                {
                    foreach($duplicate_data as $key => $value)
                    {
                        $fetch_enquiry_data = DB::table('cv_agent_mappings as a')
                        ->where('a.user_product_journey_id',$value->user_product_journey_id)
                        ->get();

                        DB::table('cv_agent_mappings')
                        ->where('user_product_journey_id',$value->user_product_journey_id)
                        ->where('id','!=',$fetch_enquiry_data[0]->id)
                        ->delete();

                        DB::table('data_cleanup_logs')->insert([
                            'user_product_journey_id'    =>  $value->user_product_journey_id,
                            'type'                       => 'Duplicate Remove',
                            'deleted'                    =>  'Y'
                        ]);
                    } 
                    echo "Data Deletion Done";
                });

                echo "Samapt delete_duplicate";
                die;
            }

            if($request->email_update == 'Y')
            {
                CvAgentMapping::whereNull('seller_type')
                ->where('agent_email', 'LIKE', '%@aceinsurance.com%')
                ->select('agent_email')
                ->distinct('agent_email')
                ->chunk(100,function($agentEmails)
                {                    
                    foreach ($agentEmails as $key => $value) 
                    {
                    // dd($value->agent_email);
                        if(!empty($value->agent_email))
                        {
                            $seller_details = httpRequest('get_seller_details', ["email" => $value->agent_email])['response'];
                            
                            if($seller_details['status'] == 'true' && $seller_details['data']['seller_type'] == 'E')
                            {
                                $updated = CvAgentMapping::whereNull('seller_type')
                                ->where('agent_email',$value->agent_email)
                                ->update([
                                    'agent_id'   => $seller_details['data']['seller_id'],
                                    'seller_type'   => $seller_details['data']['seller_type'],
                                    'user_name'     => $seller_details['data']['user_name'],
                                    'agent_name'    => $seller_details['data']['seller_name'],
                                    'agent_mobile'  => $seller_details['data']['mobile']
                                ]);
                                if($updated)
                                {
                                    DB::table('data_cleanup_logs')->insert([
                                        'email'             =>  $value->agent_email,
                                        'agent_type'        =>  $seller_details['data']['seller_type'],
                                        'update_email_data' =>  'Y',
                                        'agent_logs'        => json_encode($seller_details),
                                        'type'              => 'Email'
                                    ]);
                                    echo ' '.$value->agent_email.' => Updated ';
                                }                            
                            }
                            else
                            {
                                DB::table('data_cleanup_logs')->insert([
                                    'email'             =>  $value->agent_email,
                                    'agent_type'        =>  $seller_details['data']['seller_type'] ?? NULL,
                                    'update_email_data' =>  'N',
                                    'agent_logs'        => json_encode($seller_details),
                                    'type'              => 'Email'
                                ]);
                                echo ' '.$value->agent_email.' => Not Updated ';
                            }
                        }                    
                    }
                });
                echo "Samapt email_update";
                die;
            }
        }
    }
    //CKYC DOC UPLOAD
    public static function sbiDocumentUpload(Request $trace_id)
    {
        include_once app_path() . '/Helpers/CkycHelpers/SbiCkycHelper.php';
        $enquiry_id = customDecrypt($trace_id['enquiry_id']);
        $proposal = UserProposal::where('user_product_journey_id', $enquiry_id)->first();
        $partner_name = (strtoupper(config('constants.motorConstant.SMS_FOLDER')));
        $todayDate = str_replace("-", "", (Carbon::now()->format('d-m-Y')));
        $hms = str_replace(":", "", (Carbon::now()->format('h:m:s')));
        $UniqueId = $partner_name . $todayDate . $hms;
        $additional_details = json_decode($proposal->additional_details, true);
        $additional_details['CKYCUniqueId'] = $UniqueId;
        $proposal->additional_details = json_encode($additional_details, true);
        $proposal->save();  
        $document_upload_data = ckycUploadDocuments::where('user_product_journey_id', $enquiry_id)->first();
        $document_upload_data = json_decode($document_upload_data);
        if (empty($document_upload_data->cky_doc_data) && $document_upload_data == null && empty($document_upload_data['doc_type']) && empty($document_upload_data['doc_name'])) {
            return response()->json([
                'data' => [
                    'message' => 'No documents found for CKYC Verification. Please upload any and try again.',
                    'verification_status' => false,
                ],
            ]);
        }
        $get_doc_data = json_decode($document_upload_data->cky_doc_data, true);
        if (empty($get_doc_data)) {
            return response()->json([
                'data' => [
                    'message' => 'No documents found for CKYC Verification. Please upload any and try again.',
                    'verification_status' => false,
                ],
                'status' => false,
                'message' => 'No documents found for CKYC Verification. Please upload any and try again.'
            ]);
        } else {
            try {
                if ($proposal->proposer_ckyc_details?->is_document_upload  == 'Y') {
                    $ckyc_Verifications_response =  ckycVerifications($proposal);
                    dd($ckyc_Verifications_response);
                }
            } catch (Exception $e) {
                \Illuminate\Support\Facades\Log::error('SBI KYC EXCEPTION trace_id=' . customEncrypt($proposal->user_product_journey_id), array($e));
            }
        }
    }


    public function stageChangeViaEnquiryId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'stage'     => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $cheksum = 'HGFDF@#$%^&878454545#@%$#@GJG$#$%^%$#45454544544^%%#$#%$^$$4HGG@#$%^&(*&^%';
        if($request->checksum_string != $cheksum)
        {
            return response()->json([
                "status" => false,
                "message" => "Invalid Request",
            ]);
        }
        $enquiryId = customDecrypt($request->enquiryId);
        $data = [
            "user_product_journey_id"   => $enquiryId,
            "stage" => $request->stage
        ];
        JourneyStage::updateOrCreate(['user_product_journey_id' => $data['user_product_journey_id']], $data);
        return response()->json([
            "status" => true,
            "message" => "Stage Updated",
        ]);
    }

    //IC SAMPLING
    public static function IcSampling(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'policy_type' => 'required',
            'business_type' => 'required',
            'company'     => 'required',
            'section'     => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }


        $json_response = DB::table('quote_webservice_request_response_data')
        ->join(
            'corporate_vehicles_quotes_request',
            'quote_webservice_request_response_data.enquiry_id',
            '=',
            'corporate_vehicles_quotes_request.user_product_journey_id'
        )
        ->where('corporate_vehicles_quotes_request.policy_type', $request->policy_type)
        ->where('corporate_vehicles_quotes_request.business_type', $request->business_type)
        ->where('quote_webservice_request_response_data.message', 'Found')
        ->where('quote_webservice_request_response_data.section', $request->section)
        ->where('quote_webservice_request_response_data.company', $request->company)
        ->orderBy('id', 'DESC')
        ->limit(1)
        ->select('quote_webservice_request_response_data.response')
        ->first();

        if ($json_response == null
        ) {
            return response()->json([
                "status" => false,
                "message" => "No Record Found..!",
            ]);
        }

        if (in_array($request->company, explode(',', config('IC.SAMPLING_XML.FORMAT')))) {
            $quote_output = html_entity_decode($json_response->response);
            $final_data = XmlToArray::convert($quote_output);
        } else {
            $final_data = json_decode($json_response->response, true);
        }

        function buildHierarchy($array, $parentKey = '')
        {
            $result = [];

            foreach ($array as $key => $value) {
                $currentKey = $parentKey ? $parentKey . '.' . $key : $key;

                if (is_array($value)) {
                    $result[$currentKey] = 'Array';
                    $result = array_merge($result, buildHierarchy($value, $currentKey));
                } else {
                    $result[$currentKey] = gettype($value);
                }
            }

            return $result;
        }

        $hierarchy = buildHierarchy($final_data);

        return response()->json([
            "status" => true,
            "message" => "Comparison data",
            "section" => $final_data,
            "hierarchy" => $hierarchy,
        ]);
    }

    public static function logDownload($file_name)
    {
        $file_path = storage_path('logs/' . $file_name);
        $file_list = [];

        $files = array_diff(scandir(storage_path('logs/')), ['.', '..', '.gitignore']);

        foreach ($files as $file) {
            $file_list[] = $file;
        }

        // Check if the file exists
        if (file_exists($file_path)) {
            return response()->download($file_path, $file_name, [
                'Content-Type' => 'text/plain',
            ]);
        } else {
            return response()->json([
                'message' => 'File not found.',
                'file_list' => $file_list,
            ], 404);
        }
    }

    public static function getRtoMaster(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required'],
            'segment' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        try {
            switch ($request->companyAlias) {

                case 'royal_sundaram':
                    $payload = DB::table('royal_sundaram_rto_master')->orderBy('rto_no', 'asc')->get();

                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {
                            $formatted_rto_name = substr($value->rto_no, 0, -2) . '-' . substr($value->rto_no, -2);
                            $data[] = [
                                'rto_no'   => $formatted_rto_name ?? '',
                                'city_name'  => $value->city_name ?? '',
                                'state_name' => $value->state_name ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'united_india':
                    $payload = DB::table('united_india_rto_master')->orderBy('TXT_RTA_CODE', 'asc')->get();

                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {
                            $formatted_rto_name = substr($value->TXT_RTA_CODE, 0, -2) . '-' . substr($value->TXT_RTA_CODE, -2);
                            $data[] = [
                                'rto_no'   => $formatted_rto_name ?? '',
                                'city_name'  => $value->city_name ?? '',
                                'state_name' => $value->RTO_LOCATION_DESC ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'tata_aig':
                    $payload = DB::table('tata_aig_v2_rto_master')->orderBy('txt_registration_code1', 'asc')->get();


                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {
                            $formatted_rto_name = ($value->txt_registration_code1) . '-' . ($value->txt_registration_code2);

                            $data[] = [
                                'rto_no'   => $formatted_rto_name ?? '',
                                'city_name'  => $value->txt_rtolocation_name ?? '',
                                'state_name' => $value->txt_state ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'universal_sompo':
                    $payload = DB::table('universal_sompo_rto_master')->orderBy('Region_Code', 'asc')->get();


                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {
                            $formatted_rto_name = substr($value->Region_Code, 0, -2) . '-' . substr($value->Region_Code, -2);


                            $data[] = [
                                'rto_no'   => $formatted_rto_name ?? '',
                                'city_name'  => $value->RTO_Location ?? '',
                                'state_name' => $value->State ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'reliance':
                    $payload = DB::table('reliance_rto_master')->orderBy('region_code', 'asc')->get();

                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {
                            $data[] = [
                                'rto_no'   => $value->region_code ?? '',
                                'city_name'  => $value->city_or_village_name ?? '',
                                'state_name' => $value->state_name ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'oriental':
                    $payload = DB::table('oriental_rto_master')->orderBy('rto_code', 'asc')->get();

                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {
                            $data[] = [
                                'rto_no'   => $value->rto_code ?? '',
                                'city_name'  => $value->rto_description ?? '',
                                'state_name' => $value->state_name ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'nic':
                    $payload = DB::table('nic_rto_master')->orderBy('rto_number', 'asc')->get();

                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {
                            $formatted_rto_name = substr($value->rto_number, 0, -2) . '-' . substr($value->rto_number, -2);

                            $data[] = [
                                'rto_no'   => $formatted_rto_name ?? '',
                                'city_name'  => $value->rto_name ?? '',
                                'state_name' => $value->state_name ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'liberty':
                    $payload = DB::table('liberty_videocon_rto_master')->orderBy('rtocode', 'asc')->get();


                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {

                            $data[] = [
                                'rto_no'   => $value->rtocode ?? '',
                                'city_name'  => $value->rta ?? '',
                                'state_name' => $value->statename ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'future_generali':
                    $payload = DB::table('future_generali_rto_master')->orderBy('rta_code', 'asc')->get();


                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {

                            $formatted_rto_name = substr($value->rta_code, 0, -2) . '-' . substr($value->rta_code, -2);

                            $data[] = [
                                'rto_no'   => $formatted_rto_name ?? '',
                                'city_name'  => $value->longdesc ?? '',
                                'state_name' => $value->cltaddr05 ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'edelweiss':
                    $payload = DB::table('edelweiss_rto_master')->orderBy('rto_code', 'asc')->get();


                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {


                            $data[] = [
                                'rto_no'   => $value->rto_code ?? '',
                                'city_name'  => $value->city ?? '',
                                'state_name' => $value->state ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'cholla_mandalam':
                    $payload = DB::table('cholla_mandalam_rto_master')->orderBy('rto', 'asc')->get();


                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {


                            $data[] = [
                                'rto_no'   => $value->rto ?? '',
                                'city_name'  => $value->rto_location_for_schedule_printing ?? '',
                                'state_name' => $value->district_name ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'icici':

                    if ($request->segment == "cv") {
                        $payload = DB::table('master_rto')
                        ->join('master_state', 'master_rto.state_id', '=', 'master_state.state_id')
                        ->join('icici_lombard_city_disctrict_master', function ($join) {
                            $join->on(DB::raw("UPPER(icici_lombard_city_disctrict_master.TXT_CITYDISTRICT)"), '=', DB::raw("UPPER(master_rto.rto_name)"))
                            ->on(DB::raw("UPPER(icici_lombard_city_disctrict_master.GST_STATE)"), '=', DB::raw("UPPER(master_state.state_name)"));
                        })
                            ->select(
                                'master_rto.rto_code',
                                'icici_lombard_city_disctrict_master.TXT_CITYDISTRICT as rto_name',
                                'icici_lombard_city_disctrict_master.GST_STATE as state_name'
                            )
                            ->orderBy('rto_code', 'asc')
                            ->get();
                    }

                    if ($request->segment == "car") {
                        $payload = DB::table('master_rto')
                        ->join('master_state', 'master_rto.state_id', '=', 'master_state.state_id')
                        ->join('car_icici_lombard_rto_location', 'master_rto.icici_4w_location_code', '=', 'car_icici_lombard_rto_location.txt_rto_location_code')
                        ->select(
                            'master_rto.rto_code',
                            'master_rto.rto_name',
                            'master_state.state_name',
                        )
                            ->orderBy('rto_code', 'asc')
                            ->get();
                    }

                    if ($request->segment == "bike") {
                        $payload = DB::table('master_rto')
                        ->join('master_state', 'master_rto.state_id', '=', 'master_state.state_id')
                        ->join('bike_icici_lombard_rto_location', 'master_rto.icici_2w_location_code', '=', 'bike_icici_lombard_rto_location.txt_rto_location_code')
                        ->select(
                            'master_rto.rto_code',
                            'master_rto.rto_name',
                            'master_state.state_name',
                        )
                            ->orderBy('rto_code', 'asc')
                            ->get();
                    }

                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {

                            $data[] = [
                                'rto_no'   => $value->rto_code ?? '',
                                'city_name'  => $value->rto_name ?? '',
                                'state_name' => $value->state_name ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'sbi':
                    $payload = DB::table('sbi_rto_location')->orderBy('rto_dis_code', 'asc')->get();


                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {


                            $data[] = [
                                'rto_no'   => $value->rto_dis_code ?? '',
                                'city_name'  => $value->rto_location_description ?? '',
                                'state_name' => $value->state_name ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'iffco_tokio':
                    $payload = DB::table('master_rto as mr')
                    ->join('iffco_tokio_city_master as ift', 'mr.iffco_city_code', '=', 'ift.rto_city_code')
                    ->join('iffco_tokio_state_master as ifs', 'ift.state_code', '=', 'ifs.state_code')
                    ->select('mr.*', 'ift.*', 'ifs.*')
                    ->orderBy('rto_code', 'asc')
                    ->get();

                    $data = [];

                    if ($payload->isNotEmpty()) {
                        foreach ($payload as $value) {
                            $data[] = [
                                'rto_no'   => $value->rto_code ?? '',
                                'city_name'  => $value->rto_city_name ?? '',
                                'state_name' => $value->state_name ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                case 'godigit':

                    $payload = $payload = DB::table('godigit_rto_location')
                    ->orderBy('rto_code', 'asc')
                        ->get();

                    $data = [];
                    if ($payload->isNotEmpty()) {


                        foreach ($payload as $value) {

                            $formatted_rto_name = substr($value->rto_code, 0, -2) . '-' . substr($value->rto_code, -2);


                            $data[] = [
                                'rto_no'   => $formatted_rto_name ?? '',
                                'city_name'  => $value->reg_city ?? '',
                                'state_name' => $value->reg_state ?? ''
                            ];
                        }
                        $data = collect($data)->unique(function ($item) {
                            return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                        })->values()->toArray();

                        return response()->json([
                            'status' => true,
                            'message' => "Found",
                            'data' => trim_array($data)
                        ]);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => "Pincode detail not available",
                        'data' => null
                    ]);
                    break;

                    case 'hdfc_ergo':

                        $payload = DB::table('hdfc_ergo_rto_location as rto')
                        ->join('hdfc_ergo_motor_state_master as state', 'rto.state_code', '=', 'state.num_state_cd')
                        ->select('rto.*','state.txt_state')
                        ->orderBy('rto_code', 'asc')
                        ->get();
    
                        $data = [];
                        if ($payload->isNotEmpty()) {
    
    
                            foreach ($payload as $value) {
    
    
    
                                $data[] = [
                                    'rto_no'   => $value->rto_code?? '',
                                    'city_name'  => $value->rto_location_description ?? '',
                                    'state_name' => $value->txt_state ?? ''
                                ];
                            }
                            $data = collect($data)->unique(function ($item) {
                                return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                            })->values()->toArray();
    
                            return response()->json([
                                'status' => true,
                                'message' => "Found",
                                'data' => trim_array($data)
                            ]);
                        }
    
                        return response()->json([
                            'status' => false,
                            'message' => "Pincode detail not available",
                            'data' => null
                        ]);
                        break;

                        case 'shriram':

                            $payload = DB::table('shriram_rto_location as rto')
                            ->join('shriram_pin_city_state as state', DB::raw('UPPER(rto.rtoname)'), '=', 'state.pin_desc')
                            ->select('state.*','rto.*')
                            ->orderBy('rto.rtocode', 'asc')
                            ->get();
        
                            $data = [];
                            if ($payload->isNotEmpty()) {
                                foreach ($payload as $value) {
                                    $data[] = [
                                        'rto_no'   => $value->rtocode?? '',
                                        'city_name'  => $value->rtoname ?? '',
                                        'state_name' => $value->state_desc ?? ''
                                    ];
                                }
                                $data = collect($data)->unique(function ($item) {
                                    return $item['rto_no'] . '|' . $item['city_name'] . '|' . $item['state_name'];
                                })->values()->toArray();
        
                                return response()->json([
                                    'status' => true,
                                    'message' => "Found",
                                    'data' => trim_array($data)
                                ]);
                            }
        
                            return response()->json([
                                'status' => false,
                                'message' => "Pincode detail not available",
                                'data' => null
                            ]);
                        break;    

                default:
                    return response()->json([
                        'status' => false,
                        'msg' => "Invalid - IC"
                    ], 422);
                    break;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return response()->json([
                'status' => false,
                'msg' => "Pincode detail not available",
                'data' => null,
                'dev' =>  $e->getLine() . ' - ' . $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => false,
            'msg' => "Master Not Available",
            'data' => null,
        ]);
    }

    public static function getSegment()
    {
        $data = MasterProductSubType::where('parent_id', '0')
        ->where('status', 'Active')
        ->get();


        list($status, $msg, $data) = $data
            ? [true, 'found', $data]
            : [false, 'not found', null];

        return response()->json([
            "status" => $status,
            "msg" => $msg,
            "data" => $data,
        ]);
    }

    public static function getInsurersCompnay()
    {
        $data = MasterCompany::all();

        list($status, $msg, $data) = $data
            ? [true, 'found', $data]
            : [false, 'not found', null];

        return response()->json([
            "status" => $status,
            "msg" => $msg,
            "data" => $data,
        ]);
    }


    public function UpdateRegistrationWithNewEnquiryId(Request $request)
    {
        $data_response = self::createDuplicateJourney($request);
        $response_data = $data_response->getData();


        if ($response_data->status === true && $response_data->msg === 'User Product Journey Duplication Successfull....!') {
            $NewGeneratedEnquiryId = $response_data->data->enquiryId;
            $oldEnqueryId = customDecrypt($NewGeneratedEnquiryId);

            $oldEnqueryIdData = UserProductJourney::with([
                'user_proposal',
                'quote_log',
                'corporate_vehicles_quote_request',
            ])->find($oldEnqueryId);

            // Update user proposal
            $user_proposal = $oldEnqueryIdData->user_proposal;
            if ($user_proposal) {
                $user_proposal->rto_location = $request->rto_code ?? null;
                $user_proposal->vehicale_registration_number = $request->registration_no ?? null;

                $new_proposal_additional_details = json_decode($user_proposal->additional_details, true);
                $new_proposal_additional_details['vehicle']['regNo1'] = $request->rto_code;
                $new_proposal_additional_details['vehicle']['vehicaleRegistrationNumber'] = $request->registration_no;
                $new_proposal_additional_details['vehicle']['rtoLocation'] = $request->rto_code;
                $new_proposal_additional_details['liberty']['proposal_request']['RtoCode'] = $request->rto_code;
                $user_proposal->additional_details = json_encode($new_proposal_additional_details);

                $user_proposal->save();
            }

            // Update quote log
            $quote_log = $oldEnqueryIdData->quote_log;
            if ($quote_log) {
                $new_quote_details = json_decode($quote_log->quote_data, true);
                $new_quote_details['rto_code'] = $request->rto_code;
                $new_quote_details['vehicle_registration_no'] = $request->registration_no;
                $quote_log->quote_data = json_encode($new_quote_details);

                $quote_log->save();
            }

            // Update corporate vehicle quote request
            $corporate_vehicles_quote_request = $oldEnqueryIdData->corporate_vehicles_quote_request;
            if ($corporate_vehicles_quote_request) {
                $corporate_vehicles_quote_request->vehicle_registration_no = $request->registration_no;
                $corporate_vehicles_quote_request->rto_code = $request->rto_code;

                $corporate_vehicles_quote_request->save();
            }

            return response()->json([
                'status' => true,
                'msg' => 'Data Updated Successfully with New Enquiry ID',
                'enquiryId' => $NewGeneratedEnquiryId
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Error in Generating New Enquiry ID',
            ]);
        }
    }

    public function vahanDataImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:json|max:2048',
        ]);

        try {

            $file = $request->file('file');

            $content = json_decode(file_get_contents($file->getRealPath()), true);

            if (!is_array($content)) {
                return response()->json([
                    'status' => false,
                    'message' => "Invalid JSON format in file.",
                ], 400);
            }

            $data = array_chunk($content, 500, true);
            $RC_NO = DB::table('vahan_upload_logs')->where('source','Offline')->get()->pluck('vehicle_reg_no')->toArray();
            
            foreach ($data as $set) {
                VahanUploadMigration::dispatch($set,$RC_NO);
            }

            return response()->json([

                'status' => true,
                'message' => "Data insert successfully",
            ], 200);
            
        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e);
            return response()->json([
                'status' => false,
                'msg' => $e->getLine() . ' - ' . $e->getMessage(),
            ],400);
        }
    }

    #naming convention
    public static function parseFullName($request)
    {
        $getFullName = explode(' ', trim($request));
        $lastName = count($getFullName) > 1 ? array_pop($getFullName) : '';
        $firstName = implode(' ', $getFullName);

        return[
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];
    }
    public function VahanConfigurationSetting(Request $request)
    {
        $vehicleTypes = ['4W' => 'car', '2W' => 'bike', 'CV' => 'cv'];
        $registration = $proposal = ['car' => false, 'bike' => false, 'cv' => false];
        $gvwRange = config('IC.GVW_RANGE.ENABLE.CONFIG')  == "Y";

        foreach (VahanServicePriorityList::all() as $value) {
            $type = $vehicleTypes[$value['vehicle_type']] ?? null;

            if ($type) {
                switch ($value['journey_type']) {
                    case 'input_page':
                        $registration[$type] = true;
                        break;
                    case 'proposal_page':
                        $proposal[$type] = true;
                        break;
                }
            }
        }
        $data = [
            "vahan" => [
                "registration" => $registration,
                "proposal" => $proposal
            ],
            "gvwRange" => $gvwRange
        ];
        $response = json_encode([
            'status' => true,
            'msg' => "App Data Loaded Successfully",
            'data' => $data
        ]);

        $encrypted_data = base64_encode(openssl_encrypt($response, 'aes-256-cbc', '01234567890123456789012345678901', OPENSSL_RAW_DATA, '1234567890123412'));
        return response()->json(['data' => $encrypted_data]);
    }

    public function vahanDataImportV1(Request $request)
    {
        try {

            if ($request->header('Content-Type') !== 'application/json' || empty($request->all()) ) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Content-Type. Expected application/json.'
                ], 400);
            }

            foreach ($request->all() as $key => $value) {
                if (!empty($key)) {

                    $vehicle_reg_no = getRegisterNumberWithHyphen($key);
                    $existing_response = DB::table('registration_details')
                        ->where('vehicle_reg_no', $vehicle_reg_no)
                        ->orderBy('expiry_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->value('vehicle_details');
                        
                    $incoming_response = json_encode($value);

                    function sortArrayRecursive(&$array) {
                        if (is_array($array)) {
                            ksort($array);
                            foreach ($array as &$value) {
                                if (is_array($value)) {
                                    sortArrayRecursive($value);
                                }
                            }
                        }
                    }
                
                    $existing_array = json_decode($existing_response, true);
                    sortArrayRecursive($existing_array);
                    
                    $incoming_array = $value;
                    sortArrayRecursive($incoming_array);
                
                    $existing_response = json_encode($existing_array);
                    $incoming_response = json_encode($incoming_array);

                    if ($existing_response != $incoming_response) {

                        $RD['vehicle_reg_no'] = getRegisterNumberWithHyphen($key) ?? null;
                        $RD['vehicle_details'] = json_encode($value) ?? null;
                        $RD['source'] = 'Offline';
                        $RD['created_at'] = now();
                        $RD['updated_at'] = now();
                        $RD['expiry_date'] = $value['result']['vehicleInsuranceUpto'] ? Carbon::parse(str_replace('/', '-', $value['result']['vehicleInsuranceUpto']))->format('Y-m-d') : null;

                        $insertRegistrationDetails[] = $RD;
                        $rc_no_list[] = $RD['vehicle_reg_no'] ?? null;
                    }
                }
            }

            if (!empty($insertRegistrationDetails ?? [] )) {
                DB::table('registration_details')->insert($insertRegistrationDetails);
            }

            return response()->json([
                'status' => true,
                'message' => "Data insert successfully",
                'Registration_number' => $rc_no_list ?? []
            ], 200);
            
        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e);
            return response()->json([
                'status' => false,
                'msg' => $e->getLine() . ' - ' . $e->getMessage(),
            ], 400);
        }
    }

    public function validatePolicy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userProductJourneyId' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $policy_details = UserProposal::with('policy_details:proposal_id,policy_number,pdf_url,status,created_on')
            ->select('user_proposal_id','final_payable_amount','ic_id','is_ckyc_details_rejected','is_ckyc_verified')
            ->where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
            ->first();
        $agentDetail = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))
                           ->pluck('seller_type')->toArray();
        $paymentdate = null;
        if (empty($policy_details->policy_details->created_on)){
            $paymentdate = PaymentRequestResponse::where([
                ['user_product_journey_id',customDecrypt($request->userProductJourneyId)],
                ['status', STAGE_NAMES['PAYMENT_SUCCESS']],
                ['active',1]
            ])
            ->select('created_at')
            ->first();
        }
        $validatepageTime = false;
        if(!empty($policy_details->policy_details->created_on)){
            $paydate = $policy_details->policy_details->created_on;
        } elseif (!empty($paymentdate) ){
            $paydate = $paymentdate->created_at;
        } else {
            $paydate = null;
        }
        $paydate_date = $current_date = '';

        if (!empty($paydate)) {
            $paydate_date = date('Y-m-d', strtotime($paydate));
            $current_date = date('Y-m-d');
            $validatepageTime = ($paydate_date == $current_date) ? true : false;
        } 
        if(count($agentDetail) > 1 && in_array('U',$agentDetail))
        {
            foreach ($agentDetail as $key => $value)
            {
                if($value == 'U')
                {
                   unset($agentDetail[$key]);
                }
            }

        }
        $redirection_data = [];
        foreach ($agentDetail as $key => $value) {
            if($value == 'P')
            {
              $redirection_data['url'] = config('POS_DASHBOARD_LINK');
            }
            else if($value == 'E')
            {
                $redirection_data['url'] = config('EMPLOYEE_DASHBOARD_LINK');
            }
            else if($value == 'U')
            {
                 $redirection_data['url'] = config('USER_DASHBOARD_LINK');
            }
            else if($value == 'Partner')
            {
                 $redirection_data['url'] = config('PARTNER_DASHBOARD_LINK');
            }

        }
        if(empty($redirection_data))
        {
            $redirection_data['url'] = config('B2C_LEAD_PAGE_URL');
        }
        if (!empty($policy_details->policy_details)) {
            $data = camelCase($policy_details->policy_details->makeHidden(['proposal_id'])->toArray());
            $data['status'] = $validatepageTime;
            $data['redirection_data'] = $redirection_data;

            if ( $paydate != null) {
                $data['created_at'] = $paydate; // policy created on date 
            }
            unset($data['policyNumber']);
            unset($data['pdfUrl']);
            return response()->json([
                'status' => true,
                'msg' => "Found",
                'data' => $data

            ]);
        }
        $data['redirection_data'] = $redirection_data;
        $data['status'] = $validatepageTime;

        return response()->json([
            'status' => true,
            'msg' => 'Page Time Expired to get Policy Details',
            'data' => $data
        ]);
    }
}
