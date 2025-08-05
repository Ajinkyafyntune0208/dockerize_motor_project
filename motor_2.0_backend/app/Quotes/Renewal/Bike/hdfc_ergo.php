<?php
use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
function getRenewalQuote($enquiryId, $requestData, $productData)
{
    include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
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
            'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_AGENT_CODE'),//'PBIN0001',//
        ],
        'PreviousPolicyNumber' =>  $user_proposal['previous_policy_number'],
        'VehicleRegistrationNumber' => $user_proposal['vehicale_registration_number']
    ];
        
    // $policy_data = [
    //     'ConfigurationParam' => [
    //         'AgentCode' => 'PBIN0001',//config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE')
    //     ],
    //     'PreviousPolicyNumber' =>  '2319201172935000000',
    //     'VehicleRegistrationNumber' => 'MH-01-CC-1011',
    // ];
    $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_FETCH_POLICY_DETAILS');
    // $url = 'https://uatcp.hdfcergo.com/TWOnline/ChannelPartner/RenewalCalculatePremium ';
   $get_response = getWsData($url, $policy_data, 'hdfc_ergo', [
       'section' => $productData->product_sub_type_code,
       'method' => 'Fetch Policy Details',
       'requestMethod' => 'post',
       'enquiryId' => $enquiryId,
       'productName' => $productData->product_name,
       'transaction_type' => 'quote',
       'headers' => [
           'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY'),//'RENEWBUY',//
           'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN'),//'vaHspz4yj6ixSaTFS4uEVw==',//
           'Content-Type' => 'application/json',
           //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
           'User-Agent' => $_SERVER['HTTP_USER_AGENT']
           //'Accept-Language' => 'en-US,en;q=0.5'
       ]
   ]);
   $policy_data_response = $get_response['response'];
//     $policy_data_response = '
// {
//     "Status": 200,
//     "Message": null,
//     "Data": {
//         "PreviousPolicyNo": "2312201092684500000",
//         "UNNamedpaLastyear": "NO",
//         "TppdDiscountLastYear": null,
//         "PreviousPolicyStartDate": "2018-08-16",
//         "PreviousPolicyEndDate": "2019-08-15",
//         "RegistrationNo": "MH-01-BNG-8768",
//         "PurchaseRegistrationDate": "2017-08-10",
//         "EngineNo": "DG54745VGDF",
//         "ChassisNo": "GRT45645GD",
//         "RtoLocationCode": 11206,
//         "RegistrationCity": "MUMBAI",
//         "ManufacturingYear": "2017",
//         "LLPaidDriverLastyear": "NO",
//         "VehicleCubicCapacity": 124,
//         "VehicleMakeCode": 123,
//         "VehicleMakeName": "SUZUKI",
//         "VehicleModelName": "ZEUS",
//         "VehicleModelCode": 11209,
//         "VehicleSeatingCapacity": 2,
//         "PolicyIssuingAddress": "2ND FLOOR, CHICAGO PLAZA, RAJAJI ROAD, NEAR TO KSRTC BUS STAND ERNAKULAM, 682035.",
//         "PolicyIssuingPhoneno": "+91-484-3934300",
//         "PAOwnerDriverSI": 1500000.0,
//         "UnnamedPersonSI": 0.0,
//         "OldNcbPercentage": 25,
//         "Zerodeplastyear": "NO",
//         "Emergencyassistancelastyear": "NO",
//         "Enggrbxlastyear": "NO",
//         "Cashallowlastyear": "NO",
//         "CustomerDetail": [
//             {
//                 "Title": "MR",
//                 "FirstName": "AMIT KUMAR",
//                 "MiddleName": "SHYAM",
//                 "LastName": "SHARMA",
//                 "Gender": "MALE",
//                 "Pancard": "ATLPM0431B",
//                 "GstInNo": "27AAACB5343E1Z1",
//                 "DateofBirth": "1991-01-01",
//                 "OrganizationName": null,
//                 "OrganizationContactPersonName": "NA"
//             }
//         ],
//         "TwoWheelerRenewalPremiumList": [
//             {
//                 "PremiumYear": 1,
//                 "VehicleIdv": 34583.0,
//                 "BasicODPremium": 591.0,
//                 "BasicTPPremium": 752.0,
//                 "NetPremiumAmount": 1718.0,
//                 "TaxPercentage": 18.0,
//                 "TaxAmount": 309.0,
//                 "TotalPremiumAmount": 2027.0,
//                 "NewNcbDiscountAmount": 0.0,
//                 "NewNcbDiscountPercentage": 0.0,
//                 "VehicleIdvMin": 29396.0,
//                 "VehicleIdvMax": 39770.0,
//                 "VehicleIdvYear2": 31124.7,
//                 "VehicleIdvYear3": 27666.4,
//                 "LLPaidDriverRate": 50.0,
//                 "LLPaidDriverPremium": 50.0,
//                 "UnnamedPassengerRate": 0.0007,
//                 "NewPolicyStartDate": "2020-06-22",
//                 "NewPolicyEndDate": "2021-06-21",
//                 "PACoverOwnerDriverPremium": 375.0,
//                 "AddOnCovers": [
//                     {
//                         "CoverName": "CashAllowance",
//                         "CoverPremium": 100.0
//                     }
//                 ]
//             },
//             {
//                 "PremiumYear": 2,
//                 "VehicleIdv": 34583.0,
//                 "BasicODPremium": 947.0,
//                 "BasicTPPremium": 1504.0,
//                 "NetPremiumAmount": 3131.0,
//                 "TaxPercentage": 18.0,
//                 "TaxAmount": 564.0,
//                 "TotalPremiumAmount": 3695.0,
//                 "NewNcbDiscountAmount": 0.0,
//                 "NewNcbDiscountPercentage": 0.0,
//                 "VehicleIdvMin": 29396.0,
//                 "VehicleIdvMax": 39770.0,
//                 "VehicleIdvYear2": 31124.7,
//                 "VehicleIdvYear3": 27666.4,
//                 "LLPaidDriverRate": 50.0,
//                 "LLPaidDriverPremium": 100.0,
//                 "UnnamedPassengerRate": 0.0007,
//                 "NewPolicyStartDate": "2020-06-22",
//                 "NewPolicyEndDate": "2022-06-21",
//                 "PACoverOwnerDriverPremium": 680.0,
//                 "AddOnCovers": []
//             },
//             {
//                 "PremiumYear": 3,
//                 "VehicleIdv": 34583.0,
//                 "BasicODPremium": 1240.0,
//                 "BasicTPPremium": 2256.0,
//                 "NetPremiumAmount": 4516.0,
//                 "TaxPercentage": 18.0,
//                 "TaxAmount": 813.0,
//                 "TotalPremiumAmount": 5329.0,
//                 "NewNcbDiscountAmount": 0.0,
//                 "NewNcbDiscountPercentage": 0.0,
//                 "VehicleIdvMin": 29396.0,
//                 "VehicleIdvMax": 39770.0,
//                 "VehicleIdvYear2": 31124.7,
//                 "VehicleIdvYear3": 27666.4,
//                 "LLPaidDriverRate": 50.0,
//                 "LLPaidDriverPremium": 150.0,
//                 "UnnamedPassengerRate": 0.0007,
//                 "NewPolicyStartDate": "2020-06-22",
//                 "NewPolicyEndDate": "2023-06-21",
//                 "PACoverOwnerDriverPremium": 1020.0,
//                 "AddOnCovers": []
//             }
//         ]
//     },
//     "UniqueRequestID": "6d983f37-fa0e-4d8e-9141-abf26aa00e2d"
// }';
    
    
    $policy_data_response = json_decode($policy_data_response,true);
    //print_r($policy_data_response);
   // die;
    if($policy_data_response['Status'] == 200)
    {
        $all_data = $policy_data_response['Data'];
        //$AddOnsOptedLastYear = explode(',',$all_data['AddOnsOptedLastYear']);
        $TwoWheelerRenewalPremiumList = $all_data['TwoWheelerRenewalPremiumList'][0];
        $AddOnCovers = $TwoWheelerRenewalPremiumList['AddOnCovers'] ?? '';
        
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
                if(in_array($value['CoverName'], ['ZERODEP','ZeroDepreciation']) && $all_data['Zerodeplastyear'] == 'YES')
                {
                   $zeroDepreciation = $value['CoverPremium'];
                }
                else if($value['CoverName'] == 'NCBPROT')
                {
                    $ncb_protection = $value['CoverPremium'];
                }
                else if(in_array($value['CoverName'], ['ENGEBOX','EngineProtection']) && $all_data['Enggrbxlastyear'] == 'YES')
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
                else if(in_array($value['CoverName'], ['EMERGASSIST','EmergencyAssistance']) && $all_data['Emergencyassistancelastyear'] == 'YES')
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
                else if($value['CoverName'] == 'LOSSUSEDOWN')
                {
                    $lossOfPersonalBelongings = $value['CoverPremium'];
                }                                      
            }                       
        } 
        $addons_list = [
            'zero_depreciation'     => round($zeroDepreciation),
            'engine_protector'      => round($engineProtect),
            'ncb_protection'        => round($ncb_protection),
            'key_replace'           => round($keyProtect),
            'tyre_secure'           => round($tyreProtect),
            'return_to_invoice'     => round($returnToInvoice),
            'lopb'                  => round($lossOfPersonalBelongings),
            'consumables'           => round($consumables),
            'road_side_assistance'  => round($roadSideAssistance)
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
        $basicOD = $TwoWheelerRenewalPremiumList['BasicODPremium'] ?? 0;
        //$ElectricalAccessoriesPremium = $TwoWheelerRenewalPremiumList['ElectricalAccessoriesPremium'];
        //$NonelectricalAccessoriesPremium = $TwoWheelerRenewalPremiumList['NonelectricalAccessoriesPremium'];
        //$LpgCngKitODPremium = $TwoWheelerRenewalPremiumList['LpgCngKitODPremium'];
        
        $fianl_od = $basicOD; //+ $ElectricalAccessoriesPremium  + $NonelectricalAccessoriesPremium + $LpgCngKitODPremium;
        
        //TP Premium           
        $basic_tp = $TwoWheelerRenewalPremiumList['BasicTPPremium'] ?? 0;        
        $LLPaidDriversPremium = $all_data['LLPaidDriverLastyear'] == 'YES' ? $TwoWheelerRenewalPremiumList['LLPaidDriverPremium'] : 0;
        $UnnamedPassengerPremium = $all_data['UNNamedpaLastyear'] == 'YES' ? $TwoWheelerRenewalPremiumList['UnnamedPassengerPremium'] : 0;
        $PAPaidDriverPremium = $TwoWheelerRenewalPremiumList['PAPaidDriverPremium'] ?? 0;       
        //$PremiumNoOfLLPaidDrivers = $TwoWheelerRenewalPremiumList['PremiumNoOfLLPaidDrivers'];
        $LpgCngKitTPPremium = $TwoWheelerRenewalPremiumList['LpgCngKitTPPremium'] ?? 0;
        $PACoverOwnerDriverPremium = $all_data['CPAlastyear'] == 'YES' ? $TwoWheelerRenewalPremiumList['PACoverOwnerDriverPremium'] : 0;
        
        $final_tp = $basic_tp + $LLPaidDriversPremium + $UnnamedPassengerPremium + $PAPaidDriverPremium + $LpgCngKitTPPremium;
                
        //Discount 
        $applicable_ncb = $TwoWheelerRenewalPremiumList['NewNcbDiscountPercentage'] ?? 0;
        $NcbDiscountAmount = $TwoWheelerRenewalPremiumList['NewNcbDiscountAmount'] ?? 0;
        //$OtherDiscountAmount = $TwoWheelerRenewalPremiumList['OtherDiscountAmount'];
        $tppD_Discount = 0;
        $total_discount = $NcbDiscountAmount;// + $OtherDiscountAmount ;
        
        //final calc
        
        // $NetPremiumAmount = $TwoWheelerRenewalPremiumList['NetPremiumAmount'];
        // $TaxAmount = $TwoWheelerRenewalPremiumList['TaxAmount'];
        // $TotalPremiumAmount = $TwoWheelerRenewalPremiumList['TotalPremiumAmount'];       
        $NetPremiumAmount = $fianl_od + $final_tp - $NcbDiscountAmount;
        $TaxAmount = round($NetPremiumAmount * 0.18);
        $TotalPremiumAmount = $NetPremiumAmount + $TaxAmount;
        
        $policy_start_date = $TwoWheelerRenewalPremiumList['NewPolicyStartDate'];
        $policy_end_date = $TwoWheelerRenewalPremiumList['NewPolicyEndDate'];        
        $antiTheftDiscount = 0;
        $idv = $TwoWheelerRenewalPremiumList['VehicleIdv'] ?? 0;
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
                    //'motor_electric_accessories_value' => (int) $ElectricalAccessoriesPremium,
                    //'motor_non_electric_accessories_value' => (int) $NonelectricalAccessoriesPremium,
                    //'motor_lpg_cng_kit_value'   => (int)$LpgCngKitODPremium,
                    'cover_unnamed_passenger_value' => (int) $UnnamedPassengerPremium,
                    'seating_capacity'          => $mmv->seating_capacity,
                    'default_paid_driver'       => (int) $LLPaidDriversPremium,
                    'motor_additional_paid_driver' => 0,
                    'compulsory_pa_own_driver'  => (int) $PACoverOwnerDriverPremium,
                    'cpa_allowed'               => (int) $PACoverOwnerDriverPremium > 0 ? true : false,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage'          => (int) $fianl_od,
                    'cng_lpg_tp'                => (int) $LpgCngKitTPPremium,
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
        return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => $policy_data_response['Message'] ?? 'Service Issue'
        ];         
    }
}