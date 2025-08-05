<?php

namespace App\Http\Controllers\Quotes\Cv;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finsall\FinsallController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\Quotes\Cv\CvQuoteModel;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\MasterPolicy;
use App\Models\MasterCompany;

class CvQuoteController extends Controller
{
	function __construct()
    {
        // $this->CvQuoteModel = new CvQuoteModel;
    }

    public function premiumCalculation(Request $request)
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

        $enquiryId   = customDecrypt($request->enquiryId);
    	$requestData = getQuotation($enquiryId);
    	$productData = getProductDataByIc($request->policyId);
        $requestData->payload_request = $request->all();
        
        if($productData->product_sub_type_status != 'Active')
        {
            return response()->json([
    			'status' => false,
    			'message' => 'Product segment '.$productData->product_sub_type_code.' is inactive'
    		]);
        }

        if( !($productData->is_premium_online == 'Yes' && $productData->is_proposal_online == 'Yes' && $productData->is_payment_online == 'Yes') )
        {
            return response()->json([
    			'status' => false,
    			'message' => 'Invalid Product Configutaion. Kindly select is_premium_online, is_proposal_online, is_payment_online  as Yes'
    		]);
        }

    	if($productData->company_alias != $request->company_alias)
    	{
    		return response()->json([
    			'status' => false,
    			'message' => 'Invalid company alias'
    		]);
    	}
    	
    	$quoteData = [];
        if(!empty($productData))
        {
            if(!(($requestData->product_sub_type_id == $requestData->product_id) && !in_array($requestData->product_sub_type_id,[1,2])))
            {
                return response()->json([
                    'status' => false,
                    'message' => 'Product type mismatch'
                ]);
            }
            if (empty($requestData->previous_policy_expiry_date)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid previous policy expiry date'
                ]);
            }
            if(in_array($requestData->business_type,['rollover','short_term']) && $requestData->previous_policy_expiry_date !== NULL)
            {
                $policy_days = get_date_diff('day', $requestData->previous_policy_expiry_date) * -1;
                $policy_allowed_days = 90;
                if($policy_days > $policy_allowed_days)
                {
                    return response()->json([
                        'status' 	=> false,
                        'message'   => 'Future Policy Expiry date is allowed only upto '.$policy_allowed_days.' days'
                    ]);
                }
            }
            else if($requestData->business_type == 'newbusiness')
            {
                $reg_date = date('Y-m-d', strtotime($requestData->vehicle_register_date));
                $today = date('Y-m-d');
                if($reg_date !== $today)
                {
                    return response()->json([
                        'status'    => false,
                        'message'   => 'Registration date('.$reg_date.') should be today date('. $today.')for Newbusiness'
                    ]);
                }
            }
            $quote_db_cache = config('IC.CACHE.QUOTE.GLOBAL.STATUS') == 'Y' ? true : false;
            //IC.CACHE.QUOTE.ORIENTAL.STATUS
            if(in_array(config('IC.CACHE.QUOTE.'.strtoupper($productData->company_alias).'.STATUS'),['Y','N']))
            {
                $quote_db_cache = config('IC.CACHE.QUOTE.'.strtoupper($productData->company_alias).'.STATUS') == 'Y' ? true : false;
            }
            $productData->db_config =
            [
                'quote_db_cache' => $quote_db_cache
            ];
            $CorporateVehiclesQuotesRequest =  CorporateVehiclesQuotesRequest::where('user_product_journey_id', customDecrypt($request->enquiryId))
                                                ->get()
                                                ->first();
            if(isBhSeries($CorporateVehiclesQuotesRequest->vehicle_registration_no) && !(in_array($productData->company_alias,explode(',',config('CV_BH_SERIES_ALLOWED_IC')))))
            {
                return [
                        'premium_amount'    => 0,
                        'status'            => false,
                        'message'           => 'BH Series number not allowed',
                        ];
            }
                   
            #for allowing ic for Ownership change
            if ($CorporateVehiclesQuotesRequest->ownership_changed == 'Y' && !(in_array($productData->company_alias, explode(',', config('CV_OWNERSHIP_CHANGE_ALLOWED_IC'))))) {
                return [
                    'premium_amount'    => 0,
                    'status'            => false,
                    'message'           => 'Ownership change not allowed',
                ];
            }

            if($request->is_renewal == 'Y' && $CorporateVehiclesQuotesRequest['is_renewal'] == 'Y')
            {
                $quote_file = app_path() . '/Quotes/Renewal/Cv/' . $productData->company_alias . '.php';
                
                if (file_exists($quote_file)) 
                {
                    include_once app_path() . '/Quotes/Renewal/Cv/' . $productData->company_alias . '.php';                
                    $quoteData = getRenewalQuote($enquiryId, $requestData, $productData);
                }  
            }
            else if($productData->is_premium_online == 'Yes')
		    {
		        $quote_file = app_path() . '/Quotes/Cv/' . $productData->company_alias . '.php';
		        if (file_exists($quote_file)) {
		            if (in_array($quote_file, get_included_files())) {
		            } else {
		                include_once app_path() . '/Quotes/Cv/' . $productData->company_alias . '.php';
		            }
                    if(function_exists('getQuote')) {
                        if ((config('IC.GODIGIT.V2.CV.ENABLE') == 'Y') && $productData->company_alias == "godigit") {
                            include_once app_path() . '/Quotes/Cv/V2/PCV/' . $productData->company_alias . '.php';
                            $quoteData = oneApigetQuote($enquiryId, $requestData, $productData);
                        } else {
                            $quoteData = getQuote($enquiryId, $requestData, $productData);
                        }
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => 'No method found to fetch the quote.',
                            'FTS_VERSION_ID' => $requestData->version_id
                        ]);
                    }
		        }
		    }
		    else
		    {
		        $quote_file = app_path() . '/Quotes/Cv/offlinePremiumCalculation.php';
		        if (file_exists($quote_file)) {
		            if (in_array($quote_file, get_included_files())) {
		            } else {
		                include_once app_path() . '/Quotes/Cv/offlinePremiumCalculation.php';
		            }

                    $request = [
                        "no_claim_bonus"=> "",
                        "vehicleUseId" => "1",
                        "ncb_discountNextSlab" => "",
                        "StateId" => "",
                        "StateCode" => "",
                        "RtoId" => "",
                        "showroom_price" => 720000,
                        "addonCover" => []
                    ];

		            $quoteData = getQuoteOfflinePremiumCalculation($enquiryId, $requestData, $productData, $request);
		        }
		    }
            if(isset($quoteData['data']))
            {
                $quoteData['data']['masterPolicyId']['zeroDep'] = $productData->zero_dep;
                $parent_code = get_parent_code($productData->product_sub_type_id);
                $usp_data = $parent_code == 'MISCELLANEOUS-CLASS' ? '' : get_usp($parent_code,$productData->company_alias);
                $quoteData['data']['usp'] = $usp_data;
                $quoteData['data']['company_alias'] = $quoteData['data']['companyAlias'] = $productData->company_alias;
                $quoteData['data']['companyLogo'] = url(config('constants.motorConstant.logos')).'/'.strtolower($productData->company_alias).'.png';
                $quoteData['data']['masterPolicyId']['logo'] = url(config('constants.motorConstant.logos')).'/'.strtolower($productData->company_alias).'.png';

                $cashlessGaragecount = getCashlessGarageCount($productData->company_alias,$productData->product_sub_type_id);
                
                if(isset($cashlessGaragecount['status'])  && $cashlessGaragecount['status'])
                {
                    $quoteData['data']['garageCount'] = $cashlessGaragecount['count'];
                }

                if((env('APP_ENV') == 'local'))
                {
                    $quoteData = additionalActionOnCovers($enquiryId,$quoteData);
                }
            }
            $quoteData['FTS_VERSION_ID'] = $requestData->version_id;
            $quoteData['zeroDep'] = $productData->zero_dep == '0' ? true : false;
        }
        #check addon premium amout in additional addon ,if it's zero remove from applicable addon
        if (isset($quoteData['data'])){
        if (in_array(0, array_values($quoteData['data']['addOnsData']['additional'] ?? []))) {
            $quoteData['data']['oldApplicableAddons'] = $quoteData['data']['applicableAddons'];
            $new_applicable_addons = getApplicableAddons($quoteData['data']['applicableAddons'], $quoteData['data']['addOnsData']['additional'], $quoteData['data']['addOnsData']['inBuilt']);
            $quoteData['data']['applicableAddons'] = $new_applicable_addons;
        }
        if (isset($quoteData['data'])) {
            $quoteData = additionalActionOnCovers($enquiryId,$quoteData);
        }
        foreach($quoteData['data']['addOnsData']['additional'] as $k=>$v){
            if(empty($v)){
                unset($quoteData['data']['addOnsData']['additional'][$k]);
            }
        }
        if($productData->zero_dep == 1 && ($index = array_search('zeroDepreciation', $quoteData['data']['applicableAddons'])) !== false){
            array_splice($quoteData['data']['applicableAddons'], $index, 1);
        }
        if(isset($quoteData['data']['applicableAddons']))
		{
			$quoteData['data']['applicableAddons'] = array_values($quoteData['data']['applicableAddons']);
		}
        if (in_array($productData->premium_type_code, ['third_party', 'third_party_breakin'])){
            $quoteData['data']['addOnsData']['inBuilt'] = [];
            $quoteData['data']['addOnsData']['additional'] = [];
            $quoteData['data']['applicableAddons'] = [];
        }
        if($productData->zero_dep == 0 && isset($quoteData['data']['addOnsData']['inBuilt']['zeroDepreciation']) && in_array($quoteData['data']['addOnsData']['inBuilt']['zeroDepreciation'], [0, 0.0]))
            {
                $quoteData = [
                    'status' => false,
                    'premium' => 0,
                     'message' => 'Zero Dep Premium is not available in Zero Depreciation Product.'
                ];                    
            }
        if(isset($quoteData['data']['finalPayableAmount']) && in_array($quoteData['data']['finalPayableAmount'], [0, 0.0])){
                $quoteData = [
                    'status' => false,
                    'premium' => 0,
                    'message' => 'Premium should not be 0.'
                ]; 
            }    
        }
        if($quoteData)
        {
            if ($quoteData['status'] ?? false) {

                //pass prem calculation formulas
                getPremCalFormula($productData, $quoteData, $request);


                if (!empty($quoteData['Data']['otherCovers']['legalLiabilityToEmployee'])) {
                    $quoteData['Data']['legalLiabilityToEmployee'] = $quoteData['Data']['otherCovers']['legalLiabilityToEmployee'];
                }

                $premium_type_id = null;
                $quoteData['data']['isBreakinApplicable'] = $is_breakin_applicable = false;
                if (in_array($productData->premium_type_id, [1, 4])) {
                    $premium_type_id = 4;
                } else if (in_array($productData->premium_type_id, [3, 6])) {
                    $premium_type_id = 6;
                }
                $is_breakin_applicable = MasterPolicy::where('product_sub_type_id', $productData->product_sub_type_id)
                    ->where('insurance_company_id', $productData->company_id)
                    ->where('premium_type_id', $premium_type_id)
                    ->where('status', 'Active')
                    ->exists();
                $ControlBreakin = config('IS_CV_ROLLOVER_BREAKIN_ALLOWED_ICS');
                if ($is_breakin_applicable == true && !in_array($productData->company_alias, explode(',', $ControlBreakin))) {
                    $is_breakin_applicable = false;
                }
                $quoteData['data']['isBreakinApplicable'] = $is_breakin_applicable;

                if (!empty($productData)) {
                    $finsall_config  = FinsallController::finsallConfig(
                        $productData->company_alias,
                        $productData->product_sub_type_id,
                        $productData->premium_type_code
                    );
                    if (!empty($finsall_config)) {
                        $quoteData['data']['finsall'] = $finsall_config;
                    }

                    if (showLoadingAmount($productData->company_alias)) {
                        $quoteData['data']['showLoadingAmount'] = true;
                    }
                }
            }
            if((isset($quoteData['status']) && $quoteData['status']!='true') && isset($quoteData['request'])) {
                $data = create_webservice([
                    'enquiryId' => $enquiryId,
                    'productName' => $productData->product_name,
                    'transaction_type' => 'Internal Service Error',
                    'section' => 'CV',
                    'method' => 'Quote',
                    'master_policy_id' => $request->policyId,
                    'companyAlias' => $productData->company_alias,
                    'request' => ['request' => $quoteData['request'], 'version_id' => $requestData->version_id],
                    'response' => (isset($quoteData['response']) ? $quoteData['response'] : (isset($quoteData['message']) ? $quoteData['message'] : $quoteData['msg'])),
                ]);

                $webservice_id = $data['webservice_id'] ?? $data['webserviceId'] ?? "";
                $message = $data['message'] ?? $data['msg'];

                if(!empty($webservice_id)){
                    if (($quoteData['webservice_id'] ?? false) && config('ENABLE_DUMMY_TILE_FOR_RENEWAL_UPLOAD') == 'Y' && ($requestData->lead_source ?? '') == 'RENEWAL_DATA_UPLOAD' && ($requestData->previous_insurer_code ?? '') == $productData->company_alias) {
                        $companyDetails = MasterCompany::where('company_alias', $productData->company_alias)->first();
                        $quoteData['data']['dummyTile'] = true;
                        $quoteData['data']['redirection_url'] = $companyDetails->url ?? null;
                        $quoteData['data']['companyLogo'] = $companyDetails->logo ?? null;
                    }
                    update_quote_web_servicerequestresponse($data['table'], $webservice_id, $message, "Failed");
                }
                
            }else if((isset($quoteData['status']) && $quoteData['status']=='true')){
                $webservice_id = $quoteData['webservice_id'] ?? $quoteData['webserviceId'] ?? "";
                $message = $quoteData['message'] ?? $quoteData['msg'];

                if(!empty($webservice_id)){
                    update_quote_web_servicerequestresponse($quoteData['table'], $webservice_id, $message, $quoteData['status'] == true ? "Success" : "Failed" );
                }
            }else if(isset($quoteData['status']) && $quoteData['status'] == false ) {
                $webservice_id = $quoteData['webservice_id'] ?? $quoteData['webserviceId'] ?? "";
                $message = $quoteData['message'] ?? $quoteData['msg'];

                if(!empty($webservice_id)){
                    if (config('ENABLE_DUMMY_TILE_FOR_RENEWAL_UPLOAD') == 'Y' && ($requestData->lead_source ?? '') == 'RENEWAL_DATA_UPLOAD' && ($requestData->previous_insurer_code ?? '') == $productData->company_alias) {
                        $companyDetails = MasterCompany::where('company_alias', $productData->company_alias)->first();
                        $quoteData['data']['dummyTile'] = true;
                        $quoteData['data']['redirection_url'] = $companyDetails->url ?? null;
                        $quoteData['data']['companyLogo'] = $companyDetails->logo ?? null;
                    }
                    update_quote_web_servicerequestresponse($quoteData['table'], $webservice_id, $message, $quoteData['status'] == true ? "Success" : "Failed" );
                }
            }
            return response()->json($quoteData);
        }
        else
        {
            $webservice_id = $quoteData['webservice_id'] ?? $quoteData['webserviceId'] ?? "";
            $message = $quoteData['message'] ?? $quoteData['msg'];

            if(!empty($webservice_id)){
                update_quote_web_servicerequestresponse($quoteData['table'], $webservice_id, $message, $quoteData['status'] == true ? "Success" : "Failed" );
            }
            return response()->json(['status' => false, 'message' => 'No Quote Data Found.', 'FTS_VERSION_ID' => $requestData->version_id]);
        }
    }
}
