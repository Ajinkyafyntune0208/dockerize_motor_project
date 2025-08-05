<?php
use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
function getRenewalQuote($enquiryId, $requestData, $productData)
{
    include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
    $mmv = get_mmv_details($productData, $requestData->version_id, 'royal_sundaram');
    if ($mmv['status'] == 1) 
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
                'mmv' => $mmv
            ]
        ];
    }

    $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    if (empty($mmv->ic_version_code) || $mmv->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request' => [
                'message' => 'Vehicle Not Mapped',
                'mmv' => $mmv
            ]
        ];
    } elseif ($mmv->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request' => [
                'message' => 'Vehicle code does not exist with Insurance company',
                'mmv' => $mmv
            ]
        ];
    }

    $mmv_data = [
        'manf_name' => $mmv->make,
        'model_name' => $mmv->model,
        'version_name' => $mmv->model,
        'seating_capacity' => $mmv->min_seating_capacity,
        'carrying_capacity' => ((int) $mmv->min_seating_capacity) - 1,
        'cubic_capacity' => $mmv->engine_capacity_amount,
        'fuel_type' =>  $mmv->fuel_type,
        'gross_vehicle_weight' => '',
        'vehicle_type' => 'BIKE',
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
    $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
    
    $fetch_url = config('constants.IcConstants.royal_sundaram.END_POINT_URL_ROYAL_SUNDARAM_FETCH_POLICY_DETAILS').'?policyNumber='.$user_proposal['previous_policy_number'].'&expiryDate='.str_replace('-','/',$requestData->previous_policy_expiry_date).'&lob=motor';
    $get_response = getWsData($fetch_url,$fetch_url, 'royal_sundaram', [
        'enquiryId'         => $enquiryId,
        'requestMethod'     => 'get',
        'productName'       => $productData->product_name,
        'company'           => 'royal_sundaram',
        'section'           => $productData->product_sub_type_code,
        'method'            => 'Fetch Policy Details',
        'transaction_type'  => 'quote'
    ]);  
    $data = $get_response['response'];
    $response_data = json_decode($data,true);    
    if(isset($response_data['statusCode']) && $response_data['statusCode'] == 'S-0001')
    {
        $policy_start_date = str_replace('/','-',$response_data['currentInceptionDate']);
        $policy_end_date = str_replace('/','-',$response_data['currentExpiryDate']);
        
        if($response_data['totalodpremium'] > 0 && $response_data['totaltppremium'] > 0)
        {
           $policyType = 'Comprehensive'; 
        }
        else if ($response_data['totalodpremium'] > 0 && $response_data['totaltppremium'] == 0)
        {
            $policyType = 'Own Damage';
        }
        else if($response_data['totaltppremium'] > 0 && $response_data['totalodpremium'] == 0)
        {
           $policyType = 'Third Party';
        }
        $idv = $response_data['idv'];
        
        $canTakeKeyReplacementCover = $canTakeTyreCoverClause = $canTakeLossofBaggage = $canTakeTyreCoverClause = $canTakeInvoicePrice = 
        $canTakeNCBProtectorCover = $canTakeSpareCar = $canTakeDepreciationWaiver = $canTakeRegistrationchargesRoadtax = 
        $canTakeAggravationCover = $canTakeWindShieldGlass = 0;
        $additional = [];
        if (isset($response_data['addonCoverages'])) {
            foreach ($response_data['addonCoverages'] as $key => $addonCoverages) {
                if ($addonCoverages['name'] == 'KeyReplacementCover') {
                    $canTakeKeyReplacementCover = $addonCoverages['premium'];
                    $additional['key_replace'] = round($addonCoverages['premium']);
                } else if ($addonCoverages['name'] == 'LossofBaggage') {
                    $canTakeLossofBaggage = $addonCoverages['premium'];
                    $additional['lopb'] = round($addonCoverages['premium']);
                } else if ($addonCoverages['name'] == 'TyreCoverClause') {
                    $canTakeTyreCoverClause = $addonCoverages['premium'];
                    $additional['tyre_secure'] = round($addonCoverages['premium']);
                } else if ($addonCoverages['name'] == 'InvoicePrice') {
                    $canTakeInvoicePrice = $addonCoverages['premium'];
                    $additional['return_to_invoice'] = round($addonCoverages['premium']);
                } else if ($addonCoverages['name'] == 'NCBProtectorCover') {
                    $canTakeNCBProtectorCover = $addonCoverages['premium'];
                    $additional['ncb_protection'] = round($addonCoverages['premium']);
                } else if ($addonCoverages['name'] == 'SpareCar') {
                    $canTakeSpareCar = $addonCoverages['premium'];
                } else if ($addonCoverages['name'] == 'DepreciationWaiver') {
                    $canTakeDepreciationWaiver = $addonCoverages['premium'];
                    $additional['zero_depreciation'] = round($addonCoverages['premium']);
                } else if ($addonCoverages['name'] == 'RegistrationchargesRoadtax') {
                    $canTakeRegistrationchargesRoadtax = $addonCoverages['premium'];
                } else if ($addonCoverages['name'] == 'AggravationCover') {
                    $canTakeAggravationCover = $addonCoverages['premium'];
                    $additional['engine_protector'] = round($addonCoverages['premium']);
                } else if ($addonCoverages['name'] == 'WindShieldGlass') {
                    $canTakeWindShieldGlass = $addonCoverages['premium'];
                }
            }
        }
        $VoluntaryDed = $VPC_OwnDamageCover = $VPC_TPBasicCover = $VPC_CompulsoryPA = 
        $ServiceeTax = $AntiTheftDiscount = $VPC_ODBasicCover = $AutoAssociationMembership = 
        $VPC_LiabilityCover = $NoCliamDiscount = $OwnPremisesDiscount = 0;
        
        $VPC_FiberGlass = $SpareCar = $AggravationCover = $DepreciationWaiver = $VPC_ElectAccessories = $VPC_PAPaidDriver = 
        $EnhancedPAUnnamedPassengersCover = $TP_GeoExtension = $GeoExtension = $AdditionalTowingChargesCover = 
        $WindShieldGlass = $LossofBaggage = $EnhancedPAPaidDriverCover = $VPC_PAUnnamed = $VPC_WLLDriver = 
        $EnhancedPANamedPassengersCover = $KeyReplacementCover = $NonElectricalAccessories = $VPC_PANamedOCcupants = 0;
        $NCBProtectorCover = $TyreCoverClause = $InvoicePrice =  0;
        $electrical_accessories_amt = 0;
        $tppd_discount = 0;
        $non_electrical_accessories_amt = 0;
        $cng_lpg = 0;
        $cover_pa_unnamed_passenger_premium = 0;
        $llpaiddriver_premium = 0;
        $cover_pa_paid_driver_premium = 0;
        $cover_pa_owner_driver_premium = 0;
        $cng_lpg_tp = 0;
        $anti_theft = 0;
        $ic_vehicle_discount = 0;
        foreach ($response_data['coverages'] as $key => $coverages) {
            if($coverages['name'] == 'VoluntaryDed')
            {
                $VoluntaryDed = $coverages['premium'];
            }
            else if(in_array($coverages['name'] , ['VPC_OwnDamageCover', 'VMC_ODBasicCover']))
            {
                $VPC_OwnDamageCover = $coverages['premium'];
            }
            else if(in_array($coverages['name'] , ['VPC_TPBasicCover' , 'VMC_LiabilityCover']))
            {
                $VPC_TPBasicCover = $coverages['premium'];
            }
            else if(in_array($coverages['name'], ['VPC_CompulsoryPA', 'VMC_PAOwnerDriverCover']))
            {
                $cover_pa_owner_driver_premium = $coverages['premium'];
            }
            else if($coverages['name'] == 'ServiceeTax')
            {
                $ServiceeTax = $coverages['premium'];
            }
            else if($coverages['name'] == 'AntiTheftDiscount')
            {
                $anti_theft = $coverages['premium'];
            }
            else if(in_array($coverages['name'] , ['VPC_ODBasicCover', 'VMC_ODBasicCover']))
            {
                $VPC_ODBasicCover = $coverages['premium'];
            }
            else if($coverages['name'] == 'AutoAssociationMembership')
            {
                $AutoAssociationMembership = $coverages['premium'];
            }
            else if(in_array($coverages['name'] , ['VPC_LiabilityCover','VMC_LiabilityCover']))
            {
                $VPC_LiabilityCover = $coverages['premium'];
            }
            else if($coverages['name'] == 'NoCliamDiscount')
            {
                $NoCliamDiscount = $coverages['premium'];
            }
            else if($coverages['name'] == 'OwnPremisesDiscount')
            {
                $OwnPremisesDiscount = $coverages['premium'];
            }
            else if($coverages['name'] == 'VPC_FiberGlass')
            {
                $VPC_FiberGlass = $coverages['premium'];
            }
            else if($coverages['name'] == 'SpareCar')
            {
                $SpareCar = $coverages['premium'];
            }
            else if(in_array($coverages['name'], ['DepreciationWaiverforTW', 'DepreciationWaiver']))
            {
                $DepreciationWaiver = $coverages['premium'];
            }
            else if(in_array($coverages['name'] , ['AggravationCover', 'EngineProtectorCover_TW']))
            {
                $AggravationCover = $coverages['premium'];
            }
            else if($coverages['name'] == 'NCBProtectorCover')
            {
                $NCBProtectorCover = $coverages['premium'];
            }
            else if($coverages['name'] == 'InvoicePrice')
            {
                $InvoicePrice = $coverages['premium'];
            }            
            else if($coverages['name'] == 'TyreCoverClause')
            {
                $TyreCoverClause = $coverages['premium'];
            }            
            else if(in_array($coverages['name'],['VPC_ElectAccessories','VMC_ElecAccessoriesCover']))
            {
                $electrical_accessories_amt = $coverages['premium'];
            }
            else if(in_array($coverages['name'], ['NonElectricalAccessories','VMC_NonElecAccessoriesCover']))
            {
                $non_electrical_accessories_amt = $coverages['premium'];
            }
            else if(in_array($coverages['name'], ['VPC_PAPaidDriver','VMC_LLPaidDriverCover']))
            {
                $llpaiddriver_premium = $coverages['premium'];
            }
            else if($coverages['name'] == 'EnhancedPAUnnamedPassengersCover')
            {
                $EnhancedPAUnnamedPassengersCover = $coverages['premium'];
            }
            else if($coverages['name'] == 'TP_GeoExtension')
            {
                $TP_GeoExtension = $coverages['premium'];
            }
            else if($coverages['name'] == 'GeoExtension')
            {
                $GeoExtension = $coverages['premium'];
            }
            else if($coverages['name'] == 'AdditionalTowingChargesCover')
            {
                $AdditionalTowingChargesCover = $coverages['premium'];
            }
            else if($coverages['name'] == 'WindShieldGlass')
            {
                $WindShieldGlass = $coverages['premium'];
            }
            else if($coverages['name'] == 'LossofBaggage')
            {
                $LossofBaggage = $coverages['premium'];
            }
            else if($coverages['name'] == 'EnhancedPAPaidDriverCover')
            {
                $cover_pa_paid_driver_premium = (int) $coverages['premium'];
            }
            else if(in_array($coverages['name'], ['VPC_PAUnnamed','VMC_PAUnnamed']))
            {
                $cover_pa_unnamed_passenger_premium = $coverages['premium'];
            }
            else if($coverages['name'] == 'VPC_WLLDriver')
            {
                $VPC_WLLDriver = $coverages['premium'];
            }
            else if($coverages['name'] == 'EnhancedPANamedPassengersCover')
            {
                $EnhancedPANamedPassengersCover = $coverages['premium'];
            }
            else if($coverages['name'] == 'KeyReplacementCover')
            {
                $KeyReplacementCover = $coverages['premium'];
            }            
            else if($coverages['name'] == 'VPC_PANamedOCcupants')
            {
                $VPC_PANamedOCcupants = $coverages['premium'];
            }
        }
        
        $addons_list = [
            'zeroDepreciation'     => round($DepreciationWaiver),
            'engineProtector'      => round($AggravationCover),
            'ncbProtection'        => round($NCBProtectorCover),
            'keyReplace'           => round($KeyReplacementCover),
            'tyreSecure'           => round($TyreCoverClause),
            'returnToInvoice'      => round($InvoicePrice),
            'lopb'                 => round($LossofBaggage),
            'consumables'          => 0,
            'roadSideAssistance'   => 0,
        ];

        if($productData->zero_dep == 0)
        {
            if($DepreciationWaiver <= 0)
            {
                return [
                    'status' => false,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'premium' => '0',
                    'message' => 'Zero dep cover is not available for renewal',
                    'request' => [
                        'message' => 'Zero dep cover is not available for renewal',
                    ]
                ];

            }
        }elseif($productData->zero_dep !== 0)
        {
            if($DepreciationWaiver > 0)
            {
                return [
                    'status' => false,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'premium' => '0',
                    'message' => 'This renewal is with zero dep kindly select zero dep',
                    'request' => [
                        'message' => 'This renewal is with zero dep kindly select zero dep',
                    ]
                ];

            }

        }

        
        $in_bult = [];
        foreach ($addons_list as $key => $value) 
        {
            if($value > 0)
            {
              $in_bult[$key] =  $value;
            }                        
        }
        $addons_data = [
            'in_built'   => $in_bult,
            'additional' => $additional,
            'other'=>[]
        ];

        $applicable_addons = array_keys($in_bult);
        $motor_manf_date = '01-'.$requestData->manufacture_year;
        $PREMIUM=round($response_data['renewalPremium']);
        
        $od = $VPC_OwnDamageCover;
        $ncb_discount = $NoCliamDiscount;
        $tppd = $VPC_TPBasicCover;
        
        $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
        $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium;
        $final_total_discount = $ncb_discount + $VoluntaryDed + $anti_theft + $ic_vehicle_discount + $tppd_discount;
//        $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
//        $final_gst_amount = round($final_net_premium * 0.18);
//        $final_payable_amount  = $final_net_premium + $final_gst_amount;
        $final_gst_amount = $response_data['serviceTax'];
        $final_net_premium = $response_data['netPremium'];
        $final_payable_amount = $response_data['renewalPremium'];
        
        
        
        return camelCase([
            'status' => true,
            'msg' => 'Found',
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'Data' => [
                'isRenewal'                 => 'Y',
                'quote_id'                  =>  $response_data['quoteId'],
                'PREMIUM'                   =>  (int)$PREMIUM,
                'idv'                       =>  round($idv),
                'min_idv'                   =>  round($idv),
                'max_idv'                   =>  round($idv),
                'default_idv'               =>  round($idv),
                'modified_idv'              =>  round($idv),
                'original_idv'              =>  round($idv),
                'vehicle_idv'               =>  round($idv),
                'showroom_price'            =>  round($idv),
                'pp_enddate'                =>  $requestData->previous_policy_expiry_date,                
                'policy_type'               => $policyType,
                'cover_type'                => '1YC',
                'vehicle_registration_no'   => $requestData->rto_code,
                'voluntary_excess'          => (int) $VoluntaryDed,
                'version_id'                => $mmv->ic_version_code,                
                'fuel_type'                 => $mmv->fuel_type,
                'ncb_discount'              => (int) $requestData->applicable_ncb,
                'company_name'              => $productData->company_name,
                'company_logo'              => url(config('constants.motorConstant.logos').$productData->logo),
                'product_name'              => $productData->product_sub_type_name,
                'mmv_detail'                => $mmv_data,
                'vehicle_register_date'     => $requestData->vehicle_register_date,
                'master_policy_id'          => [
                    'policy_id'             => $productData->policy_id,
                    //'policy_no'           => $productData->policy_no,
                    //'policy_start_date'   => date('d-m-Y', strtotime($policy_start_date)),
                    //'policy_end_date'     => date('d-m-Y', strtotime($policy_end_date)),
                   // 'sum_insured'         => $productData->sum_insured,
                    //'corp_client_id'      => $productData->corp_client_id,
                    'product_sub_type_id'   => $productData->product_sub_type_id,
                    'insurance_company_id'  => $productData->company_id,
                    //'status'              => $productData->status,
                    //'corp_name'           => "Ola Cab",
                    'company_name'          => $productData->company_name,
                    'logo'                  => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_sub_type_name' => $productData->product_sub_type_name,
                    'flat_discount'         => $productData->default_discount,
                    //'predefine_series'    => "",
                    'is_premium_online'     => $productData->is_premium_online,
                    'is_proposal_online'    => $productData->is_proposal_online,
                    'is_payment_online'     => $productData->is_payment_online
                ],
                'motor_manf_date'           => $motor_manf_date,
                'basic_premium'             => (int)$od,
                'deduction_of_ncb'          => (int)$ncb_discount,
                'tppd_premium_amount'       => (int)$tppd,
                'tppd_discount'             => (int)$tppd_discount,
                'motor_electric_accessories_value' => (int)$electrical_accessories_amt,
                'motor_non_electric_accessories_value' => (int)$non_electrical_accessories_amt,
                'motor_lpg_cng_kit_value'   => (int)$cng_lpg,
                'cover_unnamed_passenger_value' => (int)$cover_pa_unnamed_passenger_premium,
                'seating_capacity'          => $mmv->min_seating_capacity,
                'default_paid_driver'       => (int)$llpaiddriver_premium,
                'motor_additional_paid_driver' => (int)$cover_pa_paid_driver_premium,
                'compulsory_pa_own_driver'  => (int)$cover_pa_owner_driver_premium,
                'total_accessories_amount(net_od_premium)' => 0,
                'total_own_damage'          => (int)$final_od_premium,
                'cng_lpg_tp'                => (int)$cng_lpg_tp,
                'total_liability_premium'   => (int)$final_tp_premium,
                'net_premium'               => (int)$final_net_premium,
                'service_tax_amount'        => (int)$final_gst_amount,
                'service_tax'               => 18,
                'total_discount_od'         => 0,
                'add_on_premium_total'      => 0,
                'addon_premium'             => 0,
                'GeogExtension_ODPremium'   => $GeoExtension,
                'GeogExtension_TPPremium'   => $TP_GeoExtension,
                'vehicle_lpg_cng_kit_value' => (int)$requestData->bifuel_kit_value,
                'premium_amount'            => (int)$final_payable_amount,
                'antitheft_discount'        => (int)$anti_theft,
                'final_od_premium'          => (int)$final_od_premium,
                'final_tp_premium'          => (int)$final_tp_premium,
                'final_total_discount'      => round($final_total_discount),
                'final_net_premium'         => (int)$final_net_premium,
                'final_gst_amount'          => (int)$final_gst_amount,
                'final_payable_amount'      => (int)$final_payable_amount,
                'product_sub_type_id'       => $productData->product_sub_type_id,
                'user_product_journey_id'   => $requestData->user_product_journey_id,
                'business_type'             => $businessType,
                'policyStartDate'           => date('d-m-Y', strtotime($policy_start_date)),
                'policyEndDate'             => date('d-m-Y', strtotime($policy_end_date)),
                'ic_of'                     => $productData->company_id,
                'vehicle_in_90_days'        => NULL,
                'vehicle_discount_detail'   => [
                    'discount_id'           => NULL,
                    'discount_rate'         => NULL
                ],
                'is_premium_online'         => $productData->is_premium_online,
                'is_proposal_online'        => $productData->is_proposal_online,
                'is_payment_online'         => $productData->is_payment_online,
                'policy_id'                 => $productData->policy_id,
                'insurane_company_id'       => $productData->company_id,
                'add_ons_data'              => $addons_data,
                'applicable_addons'         => $applicable_addons
            ]
        ]);        
    }
    else
    {
        return [
            'status' => false,
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'premium' => '0',
            'message' => $response_data['message'] ?? 'Insurer not reachable'
        ];        
    }
}