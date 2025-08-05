<?php

use App\Models\CvAgentMapping;
use Carbon\Carbon;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
include_once app_path('Quotes/Renewal/Car/godigitoneapi.php');


function getRenewalQuote($enquiryId, $requestData, $productData)
{
    if(config("ENABLE_GODIGIT_RENEWAL_API") === 'Y')
    {
        if(config('IC.GODIGIT.V2.CAR.RENEWAL.ENABLE') == 'Y')  return getOneApiRenewalQuote($enquiryId, $requestData, $productData);
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        $mmv = get_mmv_details($productData,$requestData->version_id,'godigit');
        if($mmv['status'] == 1)
        {
          $mmv = $mmv['data'];
        }
        else
        {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message'],
                'request' => [
                    'mmv' => $mmv,
                ]
            ];
        }
        $mmv = (object) array_change_key_case((array) $mmv,CASE_LOWER);

        if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
            return [   
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle not mapped',
                ]
            ];        
        } elseif ($mmv->ic_version_code == 'DNE') {
            return [   
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle code does not exist with Insurance company',
                ]
            ];        
        }


        $mmv_data = [
        'manf_name' => $mmv->make_name,
        'model_name' => $mmv->model_name,
        'version_name' => $mmv->variant_name,
        'seating_capacity' => $mmv->seating_capacity,
        'carrying_capacity' => $mmv->seating_capacity - 1,
        'cubic_capacity' => $mmv->cubic_capacity,
        'fuel_type' =>  $mmv->fuel_type,
        'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
        'vehicle_type' => 'CAR',
        'version_id' => $mmv->ic_version_code,
        ];
        
        $prev_policy_end_date = $requestData->previous_policy_expiry_date;
        $date1 = new DateTime($requestData->vehicle_register_date);
        $date2 = new DateTime($prev_policy_end_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);
        
        $businessType = 'Roll Over';
       // include_once app_path() . '/Quotes/Car/' . $productData->company_alias . '.php';
        //$user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();

        
        
        $fetch_url = config('constants.IcConstants.godigit.GODIGIT_BIKE_MOTOR_FETCH_RENEWAL_URL').$user_proposal->previous_policy_number;
        
        $posData = CvAgentMapping::where([
            'user_product_journey_id' => $enquiryId,
            'seller_type' => 'P'
        ])
        ->first();

        $webUserId = config('constants.IcConstants.godigit.GODIGIT_WEB_USER_ID');
        $password = config('constants.IcConstants.godigit.GODIGIT_PASSWORD');

        if (!empty($posData)) {
            $credentials = getPospImdMapping([
                'sellerType' => 'P',
                'sellerUserId' => $posData->agent_id,
                'productSubTypeId' => $productData->product_sub_type_id,
                'ic_integration_type' => $productData->good_driver_discount == 'Yes' ? 'godigit.gdd' : 'godigit'
            ]);

            if ($credentials['status'] ?? false) {
                $webUserId = $credentials['data']['web_user_id'];
                $password = $credentials['data']['password'];
            }
        }
        
        $get_response = getWsData($fetch_url,[], 'godigit', [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'get',
            'productName'       => $productData->product_name,
            'company'           => 'godigit',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Fetch Policy Details',
            'transaction_type'  => 'quote',
            'webUserId' => $webUserId,
            'password' => $password,
        ]);  
        $data = $get_response['response'];
        $response_data = json_decode($data); 
        
     
        if(isset($response_data->error->errorCode) && $response_data->error->errorCode == '0')
        {

            $policy_start_date = str_replace('/','-',$response_data->contract->startDate);
            $policy_end_date = str_replace('/','-',$response_data->contract->endDate);

            if($response_data->contract->insuranceProductCode == '20101')
            {
               $policyType = 'Comprehensive'; 
            }
            else if ($response_data->contract->insuranceProductCode == '20103')
            {
                $policyType = 'Own Damage';
            }
            else if($response_data->contract->insuranceProductCode == '20102')
            {
               $policyType = 'Third Party';
            }

            $premiumType = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
                ->pluck('premium_type_code')
                ->first();
            if($requestData->business_type != 'breakin' && isset($policyType) &&
            strtolower(str_replace(' ', '_', $policyType)) != $premiumType) {
                return [
                    'status' => false,
                    'message' => 'Missmatched policy type',
                    'request' => [
                        'message' => 'Missmatched policy type',
                        'business_type' => $requestData->business_type,
                        'premium_type_code' => $premiumType,
                        'policyType' => $policyType
                    ]
                ];
            }

            $idv = $response_data->vehicle->vehicleIDV->idv;
            $contract = $response_data->contract;

            $tppd = false;
            $zero_depreciation = false;
            $road_side_assistance = false;
            $engine_protection = false;
            $return_to_invoice = false;
            $consumables = false;
            $personal_belonging= false;
            $key_and_lock_protection=false;
            $llpaiddriver = false;
            $cover_pa_owner_driver = false;
            $cover_pa_paid_drive= false;
            $zero_depreciation_claimsCovered = 'ONE';
            $discountPercent = 0;

            foreach ($contract->coverages as $key => $value) 
            {
                switch ($key) 
                {
                    case 'thirdPartyLiability':

                        if (isset($value->netPremium))
                        {
                            $tppd = true;
                        }
                        
                    break;
        
                    case 'addons':
                        foreach ($value as $key => $addon) {
                            switch ($key) {
                                case 'partsDepreciation':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $zero_depreciation = true;
                                        $zero_depreciation_claimsCovered = $addon->claimsCovered;
                                    }
                                    break;

                                case 'roadSideAssistance':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $road_side_assistance = true;
                                    }
                                    break;

                                case 'engineProtection':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $engine_protection = true;
                                    }
                                    break;

                                case 'returnToInvoice':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $return_to_invoice = true;
                                    }
                                    break;

                                case 'consumables':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $consumables = true;
                                    }
                                    break;

                                case 'personalBelonging':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $personal_belonging = true;
                                    }
                                    break;

                                case 'keyAndLockProtect':
                                    if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                        $key_and_lock_protection = true;
                                    }
                                    break;

                                }
                            }
                    break;

                    case 'ownDamage':
                       
                       if(isset($value->netPremium))
                       {
                            $od = round(str_replace("INR ", "", $value->netPremium));
                            foreach ($value->discount->discounts as $key => $type) 
                            {
                                if ($type->discountType == "NCB_DISCOUNT") 
                                {
                                    $discountPercent = round($type->discountPercent);
                                }
                            }
                       } 
                    break;

                    case 'legalLiability' :
                        foreach ($value as $cover => $subcover) {
                            if ($cover == "paidDriverLL") {
                                if($subcover->selection == 1) {
                                    $llpaiddriver = true;
                                }
                            }
                        }
                    break;
                
                    case 'personalAccident':
                        // By default Complusory PA Cover for Owner Driver
                        if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                        {
                            $cover_pa_owner_driver= true;
                        } 
                    break;

                    case 'accessories' :    
                    break;

                    case 'unnamedPA':
                        
                        foreach ($value as $cover => $subcover) 
                        {
                            if ($cover == 'unnamedPaidDriver') 
                            {
                                if (isset($subcover->selection) && $subcover->selection == 1) 
                                {
                                    if (isset($subcover->netPremium)) 
                                    {
                                        $cover_pa_paid_drive= true;
                                    }
                                }
                            }
                        }
                    break;
                }
            }


            $premium_calculation_array = [
                "enquiryId" =>$response_data->enquiryId,
                "contract" => [
                    "insuranceProductCode" => $response_data->contract->insuranceProductCode,
                    "subInsuranceProductCode" => $response_data->contract->subInsuranceProductCode,
                    "startDate" => null,
                    "endDate" => null,
                    "policyHolderType" => $response_data->contract->policyHolderType,
                    "coverages" => [
                        "voluntaryDeductible" => "ZERO",
                        "thirdPartyLiability" => ["isTPPD" => false],
                        "ownDamage" => [
                            "discount" => [
                                "userSpecialDiscountPercent" => 0,
                                "discounts" => [],
                            ],
                            "surcharge" => ["loadings" => []],
                        ],
                        "personalAccident" => [
                            "selection" => $cover_pa_owner_driver,
                            "insuredAmount" => ($cover_pa_owner_driver == true) ? 1500000 : 0,
                            "coverTerm" => null,
                        ],
                        "accessories" => [
                            "cng" => ["selection" => null, "insuredAmount" => null],
                            "electrical" => ["selection" => null, "insuredAmount" => null],
                            "nonElectrical" => [
                                "selection" => null,
                                "insuredAmount" => null,
                            ],
                        ],
                        "addons" => [
                            "partsDepreciation" => [
                                "claimsCovered" => $zero_depreciation_claimsCovered,
                                "selection" => $zero_depreciation,
                            ],
                            "roadSideAssistance" => ["selection" => $road_side_assistance],
                            "engineProtection" => ["selection" => $engine_protection],
                            "tyreProtection" => ["selection" => null],
                            "rimProtection" => ["selection" => null],
                            "returnToInvoice" => ["selection" =>$return_to_invoice],
                            "consumables" => ["selection" => $consumables],
                            "personalBelonging" => ["selection" => $personal_belonging],
                            "keyAndLockProtect" => ["selection" => $key_and_lock_protection],
                        ],
                        "legalLiability" => [
                            "paidDriverLL" => ["selection" => $llpaiddriver, "insuredCount" => ($llpaiddriver == true) ? 1 : 0 ],
                            "employeesLL" => ["selection" => null, "insuredCount" => null],
                            "unnamedPaxLL" => ["selection" => null, "insuredCount" => null],
                            "cleanersLL" => ["selection" => null, "insuredCount" => null],
                            "nonFarePaxLL" => ["selection" => null, "insuredCount" => null],
                            "workersCompensationLL" => [
                                "selection" => null,
                                "insuredCount" => null,
                            ],
                        ],
                        "unnamedPA" => [
                            "unnamedPax" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                            "unnamedPaidDriver" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                            "unnamedHirer" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                            "unnamedPillionRider" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                            "unnamedCleaner" => [
                                "selection" => null,
                                "insuredAmount" => null,
                            ],
                            "unnamedConductor" => [
                                "selection" => null,
                                "insuredAmount" => null,
                                "insuredCount" => null,
                            ],
                        ],
                    ],
                ],
                "vehicle" => [
                    "isVehicleNew" => $response_data->vehicle->isVehicleNew,
                    "vehicleMaincode" => $response_data->vehicle->vehicleMaincode,
                    "licensePlateNumber" => $response_data->vehicle->licensePlateNumber,
                    "registrationAuthority" => $response_data->vehicle->registrationAuthority,
                    "vehicleIdentificationNumber" => $response_data->vehicle->vehicleIdentificationNumber,
                    "manufactureDate" => $response_data->vehicle->manufactureDate,
                    "registrationDate" => $response_data->vehicle->registrationDate,
                    "vehicleIDV" => [
                        "idv" => $response_data->vehicle->vehicleIDV->idv,
                        "defaultIdv" => $response_data->vehicle->vehicleIDV->defaultIdv,
                        "minimumIdv" => $response_data->vehicle->vehicleIDV->minimumIdv,
                        "maximumIdv" => $response_data->vehicle->vehicleIDV->maximumIdv,
                    ],
                ],
                "previousInsurer" => [
                    "isPreviousInsurerKnown" => true,
                    "previousInsurerCode" => "158",
                    "previousPolicyNumber" => $response_data->policyNumber,
                    "previousPolicyExpiryDate" => $response_data->contract->endDate,
                    "isClaimInLastYear" => false,
                    "previousNoClaimBonus" => $response_data->contract->currentNoClaimBonus,
                    "originalPreviousPolicyType" => $response_data->previousInsurer->originalPreviousPolicyType ?? NULL,
                ],
                "pospInfo" => [
                    'isPOSP' => $response_data->pospInfo->isPOSP,
                    'pospName' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospName : "",
                    'pospUniqueNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospUniqueNumber : "",
                    'pospLocation' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospLocation : "",
                    'pospPanNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospPanNumber : "",
                    'pospAadhaarNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospAadhaarNumber : "",
                    'pospContactNumber' => ($response_data->pospInfo->isPOSP == true) ? $response_data->pospInfo->pospContactNumber : "",
                ],
                "pincode" => isset($response_data->persons[0]->addresses[0]->pincode) ? $response_data->persons[0]->addresses[0]->pincode : "400001",
            ];
            
                $get_response = getWsData(config('constants.IcConstants.godigit.GODIGIT_BIKE_QUICK_QUOTE_PREMIUM'),$premium_calculation_array, 'godigit',
                [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'company'  => 'godigit',
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Premium Calculation Renewal',
                    'webUserId' => $webUserId,
                    'password' => $password,
                    'transaction_type' => 'quote',
                ]);

            $data = $get_response['response'];
            if (!empty($data)) 
            {
                $response = json_decode($data);
                if (isset($response->error->errorCode) && $response->error->errorCode == '0') 
                {
                        if ($policyType != 'Third Party'){
                            $vehicle_idv = round($response->vehicle->vehicleIDV->idv);
                            $min_idv = $response->vehicle->vehicleIDV->minimumIdv;#ceil($vehicle_idv * 0.8);
                            $max_idv = $response->vehicle->vehicleIDV->maximumIdv;#floor($vehicle_idv * 1.2);
                            $default_idv = round($response->vehicle->vehicleIDV->defaultIdv);
                        }else{
                            $vehicle_idv = 0;
                            $min_idv = 0;
                            $max_idv = 0;
                            $default_idv = 0;
                        }
                        if (!empty($data)) 
                        {
                            $response = json_decode($data);
                            if ($response->error->errorCode == '0') 
                            {
                                //  $vehicle_idv = round($response->vehicle->vehicleIDV->idv);
                                //  $default_idv = round($response->vehicle->vehicleIDV->defaultIdv);
                            }
                            elseif(!empty($response->error->validationMessages[0]))
                            {
                                return 
                                [
                                    'premium_amount' => 0,
                                    'webservice_id'=> $get_response['webservice_id'],
                                    'table'=> $get_response['table'],
                                    'status' => false,
                                    'message' => str_replace(",","",$response->error->validationMessages[0])
                                ];
                            } 
                            elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
                            {
                                return 
                                [
                                    'premium_amount' => 0,
                                    'webservice_id'=> $get_response['webservice_id'],
                                    'table'=> $get_response['table'],
                                    'status' => false,
                                    'message' => str_replace(",","",$response->error->validationMessages[0])
                                ];
                            }  
                        }
                        else 
                        {
                            return
                            [
                                'premium_amount' => 0,
                                'webservice_id'=> $get_response['webservice_id'],
                                'table'=> $get_response['table'],
                                'status' => false,
                                'message' => 'Insurer not reachable'
                            ];
                        }
                    //}
                    
                    $contract = $response->contract;
                    $llpaiddriver_premium = 0;
                    $llcleaner_premium = 0;
                    $cover_pa_owner_driver_premium = 0;
                    $cover_pa_paid_driver_premium = 0;
                    $cover_pa_unnamed_passenger_premium = 0;
                    $cover_pa_paid_cleaner_premium = 0;
                    $cover_pa_paid_conductor_premium = 0;
                    $voluntary_excess = 0;
                    $ic_vehicle_discount = 0;
                    $cng_lpg_selected = 'N';
                    $electrical_selected = 'N';
                    $non_electrical_selected = 'N';
                    $ncb_discount_amt = 0;
                    $od = 0;
                    $cng_lpg_tp = 0;
                    $zero_depreciation = 0;
                    $road_side_assistance = 0;
                    $engine_protection = 0;
                    $tyre_protection = 0;
                    $return_to_invoice = 0;
                    $consumables = 0;
                    $personal_belonging= 0;
                    $key_and_lock_protection=0;
                    $tppd = 0;
                    $tppd_discount= 0;
                    foreach ($contract->coverages as $key => $value) 
                    {
                        switch ($key) 
                        {
                            case 'thirdPartyLiability':
        
                                if (isset($value->netPremium))
                                {
                                    $tppd = round(str_replace("INR ", "", $value->netPremium));
                                }
                                
                            break;
                
                            case 'addons':
                                foreach ($value as $key => $addon) {
                                    switch ($key) {
                                            case 'partsDepreciation':
                                                if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                    $zero_depreciation = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            case 'roadSideAssistance':
                                                if ($addon->coverAvailability == 'AVAILABLE') {
                                                    $road_side_assistance = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            case 'engineProtection':
                                                if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                    $engine_protection = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            case 'tyreProtection':
                                                if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                    $tyre_protection = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            case 'returnToInvoice':
                                                if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                    $return_to_invoice = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            case 'consumables':
                                                if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                    $consumables = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            case 'personalBelonging':
                                                if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                    $personal_belonging = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
            
                                            case 'keyAndLockProtect':
                                                if ($addon->selection == 'true' && $addon->coverAvailability == 'AVAILABLE') {
                                                    $key_and_lock_protection = round(str_replace('INR ', '', $addon->netPremium));
                                                }
                                                break;
                                        }
                                    }
                            break;
        
                            case 'ownDamage':
                               
                               if(isset($value->netPremium))
                               {
                                    $od = round(str_replace("INR ", "", $value->netPremium));
                                    foreach ($value->discount->discounts as $key => $type) 
                                    {
                                        if ($type->discountType == "NCB_DISCOUNT") 
                                        {
                                            $ncb_discount_amt = round(str_replace("INR ", "", $type->discountAmount));
                                        }
                                    }
                               } 
                            break;
        
                            case 'legalLiability' :
                                foreach ($value as $cover => $subcover) {
                                    if ($cover == "paidDriverLL") {
                                        if($subcover->selection == 1) {
                                            $llpaiddriver_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                        }
                                    }
                                }
                            break;
                        
                            case 'personalAccident':
                                // By default Complusory PA Cover for Owner Driver
                                if (isset($value->selection) && ($value->selection == 1) && (isset($value->netPremium)))
                                {
                                    $cover_pa_owner_driver_premium = round(str_replace("INR ", "", $value->netPremium));
                                } 
                            break;
        
                            case 'accessories' :    
                            break;
        
                            case 'unnamedPA':
                                
                                foreach ($value as $cover => $subcover) 
                                {
                                    if ($cover == 'unnamedPaidDriver') 
                                    {
                                        if (isset($subcover->selection) && $subcover->selection == 1) 
                                        {
                                            if (isset($subcover->netPremium)) 
                                            {
                                                $cover_pa_paid_driver_premium = round(str_replace("INR ", "", $subcover->netPremium));
                                            }
                                        }
                                    }
                                }
                            break;
                        }
                    }

                    $addons_list = [
                        'road_side_assistance' => intval($road_side_assistance),
                        'lopb' => intval($personal_belonging),
                        'key_replace' => intval($key_and_lock_protection),
                        'zero_depreciation' => intval($zero_depreciation),
                        'consumables' => intval($consumables),
                        'return_to_invoice' =>intval ($return_to_invoice),
                        'tyre_secure' => intval($tyre_protection),
                        'engine_protector' => intval($engine_protection)  
                    ];
                    $in_bult = [];
                    $additional = [];
                    $add_on_premium_total = 0;
                    foreach ($addons_list as $key => $value) 
                    {
                        if($value > 0)
                        {
                          $in_bult[$key] =  $value;
                          $add_on_premium_total += $value;
                        }                        
                    }
                    $addons_data = [
                        'in_built'   => $in_bult,
                        'additional' => $additional,
                        'other'=>[]
                    ];
            
                    $applicable_addons = array_keys($in_bult);
               
                    if ((isset($cng_lpg_amt) && !empty($cng_lpg_amt)) || $mmv->fuel_type == 'CNG' || $mmv->fuel_type == 'LPG') {
                        $cng_lpg_tp = 60;
                        $tppd = $tppd - 60;
                    }
                    $ncb_discount = $ncb_discount_amt;
                    $final_od_premium = $od;
                    $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium + $llcleaner_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_paid_cleaner_premium + $cover_pa_paid_conductor_premium +$tppd_discount;
                    $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount + $tppd_discount;
                    $final_net_premium   = round($final_od_premium + $final_tp_premium - $final_total_discount + $add_on_premium_total);

                    $final_gst_amount   = round($final_net_premium * 0.18);

                    $final_payable_amount  = $final_net_premium + $final_gst_amount;

                    $data_response =
                    [
                        'status' => true,
                        'msg' => 'Found',
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'Data' =>
                        [  
                            'isRenewal'                 => 'Y',
                            'idv' => $vehicle_idv,
                            'min_idv' => round($min_idv),
                            'max_idv' => round($max_idv),
                            'default_idv' => $default_idv,
                            'vehicle_idv' => $vehicle_idv,
                            'qdata' => null,
                            'pp_enddate' => $requestData->previous_policy_expiry_date,
                            'addonCover' => null,
                            'addon_cover_data_get' => '',
                            'rto_decline' => null,
                            'rto_decline_number' => null,
                            'rto_no' => $requestData->rto_code,
                            'mmv_decline' => null,
                            'mmv_decline_name' => null,
                            'policy_type' => $policyType,
                            'cover_type' => '1YC',
                            'hypothecation' => '',
                            'hypothecation_name' => '',
                            'vehicle_registration_no' => $requestData->rto_code,//$requestData->vehicle_registration_no,
                            'voluntary_excess' => 0,
                            'version_id' => $mmv->ic_version_code,
                            'selected_addon' => [],
                            'showroom_price' => $vehicle_idv,
                            'fuel_type' => $mmv->fuel_type,
                            'ncb_discount' => $requestData->applicable_ncb,
                            'company_name' => $productData->company_name,
                            'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                            'product_name' => $productData->product_sub_type_name,
                            'mmv_detail' => $mmv_data,
                            'vehicle_register_date' => $requestData->vehicle_register_date,
                            'master_policy_id' =>
                            [   'policy_id' => $productData->policy_id,
                                'policy_no' => $productData->policy_no,
                                'policy_start_date' => date('d-m-Y', strtotime($contract->startDate)),
                                'policy_end_date' => date('d-m-Y', strtotime($contract->endDate)),
                                'sum_insured' => $productData->sum_insured,
                                'corp_client_id' => $productData->corp_client_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'insurance_company_id' => $productData->company_id,
                                'status' => $productData->status,
                                'corp_name' => "Ola Cab",
                                'company_name' => $productData->company_name,
                                'logo' => url(config('constants.motorConstant.logos').$productData->logo),
                                'product_sub_type_name' => $productData->product_sub_type_name,
                                'flat_discount' => $productData->default_discount,
                                'predefine_series' => "",
                                'is_premium_online' => $productData->is_premium_online,
                                'is_proposal_online' => $productData->is_proposal_online,
                                'is_payment_online' => $productData->is_payment_online
                            ],
                            'motor_manf_date' => $response_data->vehicle->manufactureDate,
                            'vehicleDiscountValues' =>
                            [   'master_policy_id' => $productData->policy_id,
                                'product_sub_type_id' => $productData->product_sub_type_id,
                                'segment_id' => 0,
                                'rto_cluster_id' => 0,
                                'car_age' => $vehicle_age,
                                'ic_vehicle_discount' => $ic_vehicle_discount,
                            ],
                            'basic_premium' => $od,
                            'deduction_of_ncb' => $ncb_discount,
                            'tppd_premium_amount' => $tppd + $tppd_discount,
                            'cover_unnamed_passenger_value' => isset($cover_pa_unnamed_passenger_premium) ? $cover_pa_unnamed_passenger_premium : 0,
                            'seating_capacity' => $mmv->fyntune_version['seating_capacity'],
                            'default_paid_driver' => $llpaiddriver_premium,
                            'default_paid_cleaner' => $llcleaner_premium,
                            'motor_additional_paid_driver' => $cover_pa_paid_driver_premium,
                            'motor_additional_paid_cleaner' => $cover_pa_paid_cleaner_premium,
                            'motor_additional_paid_conductor' => $cover_pa_paid_conductor_premium,
                            'compulsory_pa_own_driver' => $cover_pa_owner_driver_premium,
                            'cpa_allowed' => (int) $cover_pa_owner_driver_premium > 0 ? true : false,
                            'total_accessories_amount(net_od_premium)' => 0,
                            'total_own_damage' => $final_od_premium,
                            'total_liability_premium' => $final_tp_premium,
                            'net_premium' => $final_net_premium,
                            'service_tax_amount' => $final_gst_amount,
                            'service_tax' => 18,
                            'total_discount_od' => 0,
                            'add_on_premium_total' => $add_on_premium_total,
                            'addon_premium' => 0,
                            'quotation_no' => '',
                            'premium_amount'  => $final_payable_amount,
                            'antitheft_discount' => 0,
                            'final_od_premium' => $final_od_premium,
                            'final_tp_premium' => $final_tp_premium,
                            'final_total_discount' => $final_total_discount,
                            'final_net_premium' => $final_net_premium,
                            'final_gst_amount' => $final_gst_amount,
                            'final_payable_amount' => $final_payable_amount,
                            'service_data_responseerr_msg' => 'success',
                            'user_id' => $requestData->user_id,
                            'product_sub_type_id' => $productData->product_sub_type_id,
                            'user_product_journey_id' => $requestData->user_product_journey_id,
                            'business_type' => ($requestData->business_type =='newbusiness') ? 'New Business' : (($requestData->business_type == "breakin") ? 'Breakin' : 'Roll over'),
                            'service_err_code' => NULL,
                            'service_err_msg' => NULL,
                            'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),//date('d-m-Y', strtotime($contract->startDate)),
                            'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),//date('d-m-Y', strtotime($contract->endDate)),
                            'ic_of' => $productData->company_id,
                            'vehicle_in_90_days' => NULL,
                            'get_policy_expiry_date' => NULL,
                            'get_changed_discount_quoteid' => 0,
                            'vehicle_discount_detail' => [
                                'discount_id' => NULL,
                                'discount_rate' => NULL
                            ],
                            'is_premium_online' => $productData->is_premium_online,
                            'is_proposal_online' => $productData->is_proposal_online,
                            'is_payment_online' => $productData->is_payment_online,
                            'policy_id' => $productData->policy_id,
                            'insurane_company_id' => $productData->company_id,
                            "max_addons_selection"=> NULL,
                            'add_ons_data'              => $addons_data,
                            'applicable_addons'         => $applicable_addons
                        ],
                    ];
        
                    // if($is_tppd)
                    // {
                    //     $data_response['Data']['tppd_discount'] = round($tppd_discount);
                    // }

                    // if($is_lpg_cng)
                    // {
                    //     $data_response['Data']['vehicle_lpg_cng_kit_value'] = $requestData->bifuel_kit_value;
                    //     $data_response['Data']['cng_lpg_tp'] = $cng_lpg_tp;
                    //     $data_response['Data']['motor_lpg_cng_kit_value'] = 0;
                    // }
                    return camelCase($data_response);
                }
                elseif(!empty($response->error->validationMessages[0]))
                {
                    return 
                    [
                        'premium_amount' => 0,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'status' => false,
                        'message' => str_replace(",","",$response->error->validationMessages[0])
                    ];
                } 
                elseif(isset($response->error->errorCode) && $response->error->errorCode == '400')
                {
                    return 
                    [
                        'premium_amount' => 0,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'status' => false,
                        'message' => str_replace(",","",$response->error->validationMessages[0])
                    ];
                } else {
                    return [
                        'status' => false,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'premium_amount' => 0,
                        'message' => 'Something went wrong'
                    ];
                }
            }
            else 
            {
                return
                [
                    'premium_amount' => 0,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'status' => false,
                    'message' => 'Insurer not reachable'
                ];
            }       
        }
        else
        {
            return [
                'status' => false,
                'premium' => '0',
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => !empty($response_data->error->validationMessages ?? null) ? $response_data->error->validationMessages : 'Insurer not reachable.'
            ];
        }

    }
    else
    {
        include_once app_path() . '/Quotes/Car/' . $productData->company_alias . '.php';
        $quoteData = getQuote($enquiryId, $requestData, $productData);
        if(isset($quoteData['data']))
        {
            $quoteData['data']['isRenewal'] = 'Y';
        }
        return $quoteData;
    }
    
}