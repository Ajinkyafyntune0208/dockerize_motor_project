<?php
include_once app_path() . '/Quotes/Renewal/Car/V1/hdfc_ergo.php';
use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
function getRenewalQuote($enquiryId, $requestData, $productData)
{
    //    echo $_SERVER['HTTP_USER_AGENT'];
    //    die;
    if (config('IC.HDFC_ERGO.V1.CAR.RENEWAL.ENABLE') == 'Y') {
        return getV1RenewalQuote($enquiryId, $requestData, $productData);
    }
    include_once app_path() . '/Helpers/CarWebServiceHelper.php';
    $mmv = get_mmv_details($productData, $requestData->version_id, 'hdfc_ergo');
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
        'manf_name'             => $mmv->vehicle_manufacturer,
        'model_name'            => $mmv->vehicle_model_name,
        'version_name'          => $mmv->variant,
        'seating_capacity'      => $mmv->seating_capacity,
        'carrying_capacity'     => $mmv->carrying_capacity,
        'cubic_capacity'        => $mmv->cubic_capacity,
        'fuel_type'             => $mmv->fuel,
        'gross_vehicle_weight'  => '',
        'vehicle_type'          => 'CAR',
        'version_id'            => $mmv->ic_version_code,
    ];
    
    $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
    
    $policy_data = [
        'ConfigurationParam' => [
            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE'),//'PBIN0001',//
        ],
        'PreviousPolicyNumber' =>  $user_proposal['previous_policy_number'],
        'VehicleRegistrationNumber' => removingExtraHyphen($user_proposal['vehicale_registration_number'])
    ];
        
    // $policy_data = [
    //     'ConfigurationParam' => [
    //         'AgentCode' => 'PBIN0001',//config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE')
    //     ],
    //     'PreviousPolicyNumber' =>  '2319201172935000000',
    //     'VehicleRegistrationNumber' => 'MH-01-CC-1011',
    // ];
    $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_FETCH_POLICY_DETAILS');
    // $url = 'https://uatcp.hdfcergo.com/PCOnline/ChannelPartner/RenewalCalculatePremium';
   $get_response = getWsData($url, $policy_data, 'hdfc_ergo', [
       'section' => $productData->product_sub_type_code,
       'method' => 'Fetch Policy Details',
       'requestMethod' => 'post',
       'enquiryId' => $enquiryId,
       'productName' => $productData->product_name,
       'transaction_type' => 'quote',
       'headers' => [
           'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MERCHANT_KEY'),//'RENEWBUY',//
           'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_SECRET_TOKEN'),//'vaHspz4yj6ixSaTFS4uEVw==',//
           'Content-Type' => 'application/json',
           //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
           'User-Agent' => $_SERVER['HTTP_USER_AGENT']
           //'Accept-Language' => 'en-US,en;q=0.5'
       ]
   ]);
   $policy_data_response = $get_response['response']; 
 
//     $policy_data_response = '{
//     "Status": 200,
//     "UniqueRequestID": "100a0cd5-3928-44c2-a38f-7785e6c85300",
//     "Message": null,
//     "Data": {
//         "PreviousPolicyNo": "2311201091832500000",
//         "VehicleMakeCode": 84,
//         "VehicleMakeName": "MARUTI",
//         "VehicleModelName": "ALTO 800",
//         "VehicleModelCode": 25373,
//         "RegistrationNo": "MH-02-YP-2987",
//         "PurchaseRegistrationDate": "2017-04-30",
//         "ManufacturingYear": "2017",
//         "PreviousPolicyStartDate": "2018-04-30",
//         "PreviousPolicyEndDate": "2019-04-29",
//         "RtoLocationCode": 10406,
//         "RegistrationCity": "MUMBAI",
//         "EngineNo": "4658965874",
//         "ChassisNo": "3658965874",
//         "VehicleCubicCapacity": 796,
//         "VehicleSeatingCapacity": 5,
//         "NcbDiscount": "20",
//         "OldNcbPercentage": 20,
//         "PolicyIssuingAddress": "LEELA BUSINESS PARK, 6TH FLR, ANDHERI - KURLA RD, MUMBAI, 400059.",
//         "PolicyIssuingPhoneno": "+91-22-66383600",
//         "IntermediaryCode": "200278133519",
//         "AddOnsOptedLastYear": "ZERODEP,EMERGASSIST,NCBPROT",
//         "PAPaidDriverLastYear": "NO",
//         "UnnamedPassengerLastYear": "YES",
//         "LLPaidDriverLastyear": "NO",
//         "LLEmployeeLastYear": "NO",
//         "TppdDiscountLastYear": null,
//         "ExLpgCngKitLastYear": null,
//         "ElectricalAccessoriesIdv": 0,
//         "NonelectricalAccessoriesIdv": 0,
//         "LpgCngKitIdv": 0,
//         "PAPaidDriverSI": 0,
//         "PAOwnerDriverSI": 100000,
//         "UnnamedPassengerSI": 40000,
//         "NoOfUnnamedPassenger": 5,
//         "NoOfLLPaidDrivers": 0,
//         "NumberOfLLEmployees": 0,
//         "TppdLimit": 0,
//         "IsBreakin": 1,
//         "IsWaiver": 0,
//         "CustomerDetail": [
//             {
//                 "CustomerType": "INDIVIDUAL",
//                 "Title": "MR",
//                 "Gender": "MALE",
//                 "FirstName": "AMIT",
//                 "MiddleName": "KUMAR",
//                 "LastName": "SHARMA",
//                 "DateofBirth": "1970-01-25",
//                 "EmailAddress": "AMEYA.GOKHALE@HDFCERGO.CO.IN",
//                 "MobileNumber": "9899999999",
//                 "OrganizationName": null,
//                 "OrganizationContactPersonName": "NA",
//                 "Pancard": "BJKLI3653P",
//                 "GstInNo": "27AAACB5343E1Z1"
//             }
//         ],
//         "PrivateCarRenewalPremiumList": [
//             {
//                 "VehicleIdv": 255494,
//                 "BasicODPremium": 5611,
//                 "BasicTPPremium": 1850,
//                 "NetPremiumAmount": 9192,
//                 "TaxPercentage": 18,
//                 "TaxAmount": 1655,
//                 "TotalPremiumAmount": 10847,
//                 "NewNcbDiscountAmount": 1403,
//                 "NewNcbDiscountPercentage": 25,
//                 "VehicleIdvMin": 255494,
//                 "VehicleIdvMax": 268269,
//                 "BestIdv": 255494,
//                 "VehicleIdvYear2": 0,
//                 "VehicleIdvYear3": 0,
//                 "ElectricalAccessoriesPremium": 0,
//                 "NonelectricalAccessoriesPremium": 0,
//                 "LpgCngKitODPremium": 0,
//                 "LpgCngKitTPPremium": 60,
//                 "LLPaidDriverRate": 50,
//                 "LLPaidDriversPremium": 0,
//                 "UnnamedPassengerRate": 0.0005,
//                 "UnnamedPassengerPremium": 100,
//                 "PAPaidDriverRate": 0.0005,
//                 "PAPaidDriverPremium": 0,
//                 "LLEmployeeRate": 50,
//                 "LLEmployeePremium": 0,
//                 "PACoverOwnerDriverPremium": 325,
//                 "NewPolicyStartDate": "2019-05-13",
//                 "NewPolicyEndDate": "2020-05-12",
//                 "CgstPercentage": 9,
//                 "CgstAmount": 827.5,
//                 "SgstPercentage": 9,
//                 "SgstAmount": 827.5,
//                 "IgstPercentage": 0,
//                 "IgstAmount": 0,
//                 "OtherDiscountAmount": 0,
//                 "TotalAppliedDiscount": 29.77,
//                 "DiscountLimit": 70,
//                 "ODRate": 0.02196,
//                 "ElectricalODRate": 0,
//                 "LpgcngodRate": 0,
//                 "NonelectricalODRate": 0,
//                 "OtherDiscountPercentage": 0,
//                 "BreakinAmount": 0,
//                 "BreakinRate": 0,
//                 "PremiumPAPaidDriverSI": 0,
//                 "PremiumNoOfLLPaidDrivers": 0,
//                 "PremiumUnnamedPassengerSI": 40000,
//                 "PremiumNoOfUnnamedPassenger": 5,
//                 "PremiumNumberOfLLEmployees": 0,
//                 "PolicyIssuingBranchOfficeCode": 1000,
//                 "AddOnCovers": [
//                     {
//                         "CoverName": "ZERODEP",
//                         "CoverPremium": 1788
//                     },
//                     {
//                         "CoverName": "NCBPROT",
//                         "CoverPremium": 511
//                     },
//                     {
//                         "CoverName": "ENGEBOX",
//                         "CoverPremium": 511
//                     },
//                     {
//                         "CoverName": "RTI",
//                         "CoverPremium": 639
//                     },
//                     {
//                         "CoverName": "COSTCONS",
//                         "CoverPremium": 1277
//                     },
//                     {
//                         "CoverName": "EMERGASSIST",
//                         "CoverPremium": 350
//                     },
//                     {
//                         "CoverName": "EMERGASSISTWIDER",
//                         "CoverPremium": 250
//                     }
//                 ]
//             }
//         ]
//     }
// }';
    
    $policy_data_response = json_decode($policy_data_response,true);
    if($policy_data_response['Status'] == 200)
    {
        $all_data = $policy_data_response['Data'];
        $AddOnsOptedLastYear = explode(',',$all_data['AddOnsOptedLastYear']);
        $PrivateCarRenewalPremiumList = $all_data['PrivateCarRenewalPremiumList'][0];
        $AddOnCovers = $PrivateCarRenewalPremiumList['AddOnCovers'] ?? '';
        
        $zeroDepreciation           = 0;
        $engineProtect              = 0;
        $keyProtect                 = 0;
        $tyreProtect                = 0;
        $returnToInvoice            = 0;
        $lossOfPersonalBelongings   = 0;
        $roadSideAssistance         = 0;
        $consumables                = 0;
        $ncb_protection             = 0;
        
        if(is_array($AddOnCovers))
        {
            foreach ($AddOnCovers as $key => $value) 
            {
                if(in_array($value['CoverName'], $AddOnsOptedLastYear))
                {
                    if($value['CoverName'] == 'ZERODEP')
                    {
                       $zeroDepreciation = $value['CoverPremium'];
                    }
                    else if($value['CoverName'] == 'NCBPROT')
                    {
                        $ncb_protection = $value['CoverPremium'];
                    }
                    else if($value['CoverName'] == 'ENGEBOX')
                    {
                        $engineProtect = $value['CoverPremium'];
                    }
                    else if($value['CoverName'] == 'RTI')
                    {
                        $returnToInvoice = $value['CoverPremium'];
                    }
                    else if($value['CoverName'] == 'COSTCONS')
                    {
                        $consumables = $value['CoverPremium'];//consumable
                    }
                    else if($value['CoverName'] == 'EMERGASSIST')
                    {
                        $roadSideAssistance = $value['CoverPremium'];//road side assis
                    }
                    else if($value['CoverName'] == 'EMERGASSISTWIDER')
                    {
                       $keyProtect = $value['CoverPremium'];//$key_replacement 
                    }
                    else if($value['CoverName'] == 'TYRESECURE')
                    {
                        $tyreProtect = $value['CoverPremium'];
                    }
                    else if($value['CoverName'] == 'LOSSUSEDOWN') //LOPB cover is discontinued by hdfc
                    {
                        $lossOfPersonalBelongings = $value['CoverPremium'];
                    }               
                }                                 
            }                       
        } 
        $addons_list = [
            'zeroDepreciation'     => round($zeroDepreciation),
            'engineProtector'      => round($engineProtect),
            'ncbProtection'        => round($ncb_protection),
            'keyReplace'           => round($keyProtect),
            'tyreSecure'           => round($tyreProtect),
            'returnToInvoice'     => round($returnToInvoice),
            'lopb'                  => round($lossOfPersonalBelongings),
            'consumables'           => round($consumables),
            'roadSideAssistance'  => round($roadSideAssistance)
        ];
        
        $in_bult = [];
        $additional = [];
        foreach ($addons_list as $key => $value) 
        {
            if($value > 0)
            {
              $in_bult[$key] =  $value;
            }
            else
            {
                $additional[$key] =  $value;
            }
        }
        $addons_data = [
            'in_built'   => $in_bult,
            'additional' => $additional,
            'other'=>[]
        ];
        $applicable_addons = array_keys($in_bult);
        
        
        $premium_type_array = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->select('premium_type_code','premium_type')
            ->first();
        $premium_type = $premium_type_array->premium_type_code;
        $policy_type = $premium_type_array->premium_type;

        if($premium_type == 'breakin')
        {
            $premium_type = 'comprehensive';
            $policy_type = 'Comprehensive';
        }
        if($premium_type == 'third_party_breakin')
        {
            $premium_type = 'third_party';
            $policy_type = 'Third Party';
        }
        if($premium_type == 'own_damage_breakin')
        {
            $premium_type = 'own_damage';
            $policy_type = 'Own Damage';
        }
        $businessType = '';
        switch ($requestData->business_type)
        {
            case 'newbusiness':
                $businessType = 'New Business';
            break;
            case 'rollover':
                $businessType = 'Roll Over';
            break;

            case 'breakin':
                $businessType = 'Break- In';
            break;

        }
        
        $voluntaryDiscount = 0;        
        
        $motor_manf_date = '01-'.$all_data['ManufacturingYear'];
       
        //OD Premium
        $basicOD = $PrivateCarRenewalPremiumList['BasicODPremium'] ?? 0;
        $ElectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['ElectricalAccessoriesPremium'] ?? 0;
        $NonelectricalAccessoriesPremium = $PrivateCarRenewalPremiumList['NonelectricalAccessoriesPremium'] ?? 0;
        $LpgCngKitODPremium = $PrivateCarRenewalPremiumList['LpgCngKitODPremium'] ?? 0;
        
        $fianl_od = $basicOD + $ElectricalAccessoriesPremium  + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;
        
        //TP Premium           
        $basic_tp = $PrivateCarRenewalPremiumList['BasicTPPremium'] ?? 0;        
        $LLPaidDriversPremium = $PrivateCarRenewalPremiumList['LLPaidDriversPremium'] ?? 0;
        $UnnamedPassengerPremium = $PrivateCarRenewalPremiumList['UnnamedPassengerPremium'] ?? 0;
        $PAPaidDriverPremium = $PrivateCarRenewalPremiumList['PAPaidDriverPremium'] ?? 0;       
        $PremiumNoOfLLPaidDrivers = $PrivateCarRenewalPremiumList['PremiumNoOfLLPaidDrivers'] ?? 0;
        $LpgCngKitTPPremium = $PrivateCarRenewalPremiumList['LpgCngKitTPPremium'] ?? 0;
        if (!empty($PrivateCarRenewalPremiumList['BuiltInLpgCngKitPremium'])) {
            $LpgCngKitTPPremium += $PrivateCarRenewalPremiumList['BuiltInLpgCngKitPremium'];
        }
        $PACoverOwnerDriverPremium = $PrivateCarRenewalPremiumList['PACoverOwnerDriverPremium'] ?? 0;

        $final_tp = $basic_tp + $LLPaidDriversPremium + $UnnamedPassengerPremium + $PAPaidDriverPremium + $PremiumNoOfLLPaidDrivers + $LpgCngKitTPPremium;
        $NewNcbDiscountPercentage = $PrivateCarRenewalPremiumList['NewNcbDiscountPercentage'] ?? 0;
        //Discount 
        $applicable_ncb = ($requestData->is_claim == 'Y' && $NewNcbDiscountPercentage == 0) ? 0 : ($PrivateCarRenewalPremiumList['NewNcbDiscountPercentage'] ?? 0);
        $NcbDiscountAmount = ($requestData->is_claim == 'Y' && $NewNcbDiscountPercentage == 0) ? 0 : ($PrivateCarRenewalPremiumList['NewNcbDiscountAmount'] ?? 0);
        $OtherDiscountAmount = $PrivateCarRenewalPremiumList['OtherDiscountAmount'] ?? 0;
        $tppD_Discount = $PrivateCarRenewalPremiumList['TppdAmount'] ?? 0;
        $total_discount = $NcbDiscountAmount + $OtherDiscountAmount + $tppD_Discount;
        
        //final calc
        
        $NetPremiumAmount = $PrivateCarRenewalPremiumList['NetPremiumAmount'];
        $TaxAmount = $PrivateCarRenewalPremiumList['TaxAmount'];
        $TotalPremiumAmount = $PrivateCarRenewalPremiumList['TotalPremiumAmount'];       
        
        
        $policy_start_date = $PrivateCarRenewalPremiumList['NewPolicyStartDate'] ?? $all_data['NewPolicyStartDate'];
        $policy_end_date = $PrivateCarRenewalPremiumList['NewPolicyEndDate'] ?? $all_data['NewPolicyEndDate'];        
        $antiTheftDiscount = 0;
        $idv = $PrivateCarRenewalPremiumList['VehicleIdv'] ?? 0;
        //print_r($mmv);
        $data = [
                'status' => true,
                'msg' => 'Found',
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'Data' => [
                    'isRenewal'                 => 'Y',
                    'idv'                       =>  round($idv),
                    'min_idv'                   =>  round($idv),
                    'max_idv'                   =>  round($idv),
                    'default_idv'               =>  round($idv),
                    'modified_idv'              =>  round($idv),
                    'original_idv'              =>  round($idv),
                    'vehicle_idv'               =>  round($idv),
                    'showroom_price'            =>  round($idv),
                    'pp_enddate'                =>  $requestData->previous_policy_expiry_date,                
                    'policy_type'               => $policy_type,
                    'cover_type'                => '1YC',
                    'vehicle_registration_no'   => $requestData->rto_code,
                    'voluntary_excess'          => (int) $voluntaryDiscount,
                    'version_id'                => $mmv->ic_version_code,                
                    'fuel_type'                 => $mmv->fuel,
                    'ncb_discount'              => (int) $applicable_ncb,
                    'company_name'              => $productData->company_name,
                    'company_logo'              => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name'              => $productData->product_sub_type_name,
                    'mmv_detail'                => $mmv_data,
                    'vehicle_register_date'     => $requestData->vehicle_register_date,
                    'master_policy_id'          => [
                        'policy_id'             => $productData->policy_id,
                        'product_sub_type_id'   => $productData->product_sub_type_id,
                        'insurance_company_id'  => $productData->company_id,                        
                        'company_name'          => $productData->company_name,
                        'logo'                  => url(config('constants.motorConstant.logos').$productData->logo),
                        'product_sub_type_name' => $productData->product_sub_type_name,
                        'flat_discount'         => $productData->default_discount,
                        'is_premium_online'     => $productData->is_premium_online,
                        'is_proposal_online'    => $productData->is_proposal_online,
                        'is_payment_online'     => $productData->is_payment_online
                    ],
                    'motor_manf_date'           => $motor_manf_date,
                    'basic_premium'             => (int) $basicOD,
                    'deduction_of_ncb'          => (int) $NcbDiscountAmount,
                    'tppd_premium_amount'       => (int) $basic_tp,
                    'tppd_discount'             => (int) $tppD_Discount,
                    'total_loading_amount'      => 0,
                    'motor_electric_accessories_value' => (int) $ElectricalAccessoriesPremium,
                    'motor_non_electric_accessories_value' => (int) $NonelectricalAccessoriesPremium,
                    'motor_lpg_cng_kit_value'   => (int)$LpgCngKitODPremium,
                    'cover_unnamed_passenger_value' => (int) $UnnamedPassengerPremium,
                    'seating_capacity'          => $mmv->seating_capacity,
                    'default_paid_driver'       => (int) $LLPaidDriversPremium,
                    'motor_additional_paid_driver' => 0,
                    'compulsory_pa_own_driver'  => (int) $PACoverOwnerDriverPremium,
                    'cpa_allowed'               => (int) $PACoverOwnerDriverPremium > 0 ? true : false,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage'          => (int) $fianl_od,
                    //'cng_lpg_tp'                => (int) $LpgCngKitTPPremium,
                    'total_liability_premium'   => (int) $final_tp,
                    'net_premium'               => (int) $NetPremiumAmount,
                    'service_tax_amount'        => (int) $TaxAmount,
                    'service_tax'               => 18,
                    'total_discount_od'         => 0,
                    'add_on_premium_total'      => 0,
                    'addon_premium'             => 0,
                    'vehicle_lpg_cng_kit_value' => (int)$requestData->bifuel_kit_value,
                    'premium_amount'            => (int) $TotalPremiumAmount,
                    'antitheft_discount'        => (int) $antiTheftDiscount,
                    'final_od_premium'          => (int) $fianl_od,
                    'final_tp_premium'          => (int) $final_tp,
                    'final_total_discount'      => round($total_discount),
                    'final_net_premium'         => (int) $NetPremiumAmount,
                    'final_gst_amount'          => (int) $TaxAmount,
                    'final_payable_amount'      => (int) $TotalPremiumAmount,
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
            ];
            if((int) $LpgCngKitTPPremium > 0)
            {
                $data['Data']['cng_lpg_tp'] = (int) $LpgCngKitTPPremium;
            }

            $included_additional = [
                'included' =>[]
            ];
    
            if(!empty($UnnamedPassengerPremium)) {
                $included_additional['included'][] = 'coverUnnamedPassengerValue';
            }

            $data['Data']['included_additional'] = $included_additional;

            return camelCase($data); 
    }
    else
    {
        $message = $policy_data_response['Message']['ServiceResponse'] ?? $policy_data_response['Message'] ?? 'Service Issue';
        return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => $message
        ];         
    }
}