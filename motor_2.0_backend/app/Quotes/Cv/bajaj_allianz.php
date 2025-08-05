<?php

use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use App\Models\MasterRto;
use App\Models\SelectedAddons;
use App\Models\UserProductJourney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

function getQuote($enquiryId, $requestData, $productData)
{
    // if(($requestData->ownership_changed ?? '' ) == 'Y')
    // {
    //     return [
    //         'premium_amount' => 0,
    //         'status' => false,
    //         'message' => 'Quotes not allowed for ownership changed vehicle',
    //         'request' => [
    //             'message' => 'Quotes not allowed for ownership changed vehicle',
    //             'requestData' => $requestData
    //         ]
    //     ];
    // }
    $product_sub_types = [
        'AUTO-RICKSHAW' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_AUTO_RICKSHAW_KIT_TYPE'),
        'TAXI' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TAXI_KIT_TYPE'),
        'ELECTRIC-RICKSHAW' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_E_RICKSHAW_KIT_TYPE'),
        'PICK UP/DELIVERY/REFRIGERATED VAN' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_PICKUP_DELIVERY_VAN_KIT_TYPE'),
        'DUMPER/TIPPER' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_DUMPER_TIPPER_KIT_TYPE'),
        'TRUCK' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TRUCK_KIT_TYPE'),
        'TRACTOR' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TRACTOR_KIT_TYPE'),
        'TANKER/BULKER' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_TANKER_BULKER_KIT_TYPE')
    ];

    $product_sub_type_code = policyProductType($productData->policy_id)->product_sub_type_code;

    if ($product_sub_types[$product_sub_type_code] == 'JSON')
    {
        return getQuoteJson($enquiryId, $requestData, $productData);
    }
    else
    {
        return getQuoteXml($enquiryId, $requestData, $productData);
    }
}

function getQuoteXml($enquiryId, $requestData, $productData)
{
    $is_GCV = policyProductType($productData->policy_id)->parent_id == 4;
    if ($is_GCV) {
        $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz', $requestData->gcv_carrier_type);
    } else {
        $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz');
    }
    if ($mmv['status'] == 1) {
        $mmv = $mmv['data'];
    } else {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv,
            ],
        ];
    }
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    $masterProduct = MasterProduct::where('master_policy_id', $productData->policy_id)->first();
    if (empty($masterProduct)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Master Product mapping not found',
            'request' => [
                'message' => 'Master Product mapping not found',
                'policy_id' => $productData->policy_id,
            ],
        ];
    } else if ($productData->premium_type_id == '2') {
        $masterProduct = new stdClass();
        $masterProduct->product_identifier = '';
    }
    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
    $car_age = ceil($age / 12);
    // zero depriciation validation
    if ($car_age > 5 && $productData->zero_dep == '0' && in_array($productData->company_alias, explode(',', config('CV_AGE_VALIDASTRION_ALLOWED_IC')))) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
            'request' => [
                'message' => 'Zero dep is not allowed for vehicle age greater than 5 years',
                'car_age' => $car_age,
            ],
        ];
    }
    if ($car_age > 16) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle age should not be greater than 15 year',
            'request' => [
                'message' => 'Vehicle age should not be greater than 15 year',
                'vehicle_age' => $car_age,
            ],
        ];
    }
    if ($requestData->business_type == 'newbusiness') {
        $date_difference = get_date_diff('day', $requestData->vehicle_register_date);
        if ($date_difference > 0) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Backe Date not allowed for New Business policy',
                'request' => [
                    'message' => 'Backe Date not allowed for New Business policy',
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                ],
            ];
        }
    }
    $rto_code = $requestData->rto_code;
    // Re-arrange for Delhi RTO code - start
    $rto_code = explode('-', $rto_code);
    if ((int) $rto_code[1] < 10) {
        $rto_code[1] = '0' . (int) $rto_code[1];
    }
    $rto_code = implode('-', $rto_code);
    // Re-arrange for Delhi RTO code - End
    if ($rto_code == 'HP-45') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Blocked RTO - HP-45',
            'request' => [
                'message' => 'Blocked RTO - HP-45',
                'rto_code' => $rto_code,
            ],
        ];
    }

    $premium_type = DB::table('master_premium_type')
        ->where('id', $productData->premium_type_id)
        ->pluck('premium_type_code')
        ->first();

    $tp_only = ($premium_type == 'third_party') ? 'true' : 'false';

    $vehicle_in_90_days = 'N';
    $break_in = 'NO';
    $extCol24 = '0';
    $cpa = '';
    if ($requestData->vehicle_owner_type == 'I') {
        $extCol24 = '1';
        $cpa = 'MCPA';
    }
    if ($requestData->business_type == 'newbusiness') {
        $BusinessType = '1';
        $polType = '1';
        $policy_start_date = today();
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(3)->subDay(1)->format('d-m-Y');
        $car_age = 0;
    } else {
        $policy_start_date = date('d-M-Y', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
        $date_diff = get_date_diff('day', $requestData->previous_policy_expiry_date);
        if ($date_diff > 0) {
            if ($premium_type == 'third_party') {
                $policy_start_date = Carbon::today()->addDay(2)->format('d-M-Y');
            } else if ($requestData->business_type == 'breakin') {
                $policy_start_date = Carbon::today()->addDay(1)->format('d-M-Y');
            } else {
                return [
                    'premium_amount' => 0,
                    'status' => true,
                    'message' => 'No quotes available',
                    'request' => [
                        'message' => 'No quotes available',
                        'business_type' => $requestData->business_type,
                        'premium_type' => $premium_type,
                    ],
                ];
            }
        }
        $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d-m-Y');
        $BusinessType = '2';
        $polType = '3';
        $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date1 = new DateTime($vehicleDate);
        $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + ($interval->d > 0 ? '1' : '0');
        $car_age = ceil($age / 12);
    }
    $externalCNGKITSI = $NonElectricalaccessSI = $NonElectricalaccessSI = $ElectricalaccessSI = $LLtoPaidDriverYN = $PAforUnnamedPassenger = $PAforUnnamedPassengerSI = 0;
    $cover_data = [];
    // Addons And Accessories
    $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
    $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
    $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
    $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

    foreach ($additional_covers as $key => $value) {
        if (in_array('LL paid driver', $value)) {
            $LLtoPaidDriverYN = 1;
            $cover_data[] = [
                'typ:paramDesc' => 'LLO',
                'typ:paramRef' => 'LLO',
            ];
        }

        /* if (in_array('PA cover for additional paid driver', $value)) {
        $PAPaidDriverConductorCleaner = 1;
        $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
        } */

        if (in_array('Unnamed Passenger PA Cover', $value)) {
            $PAforUnnamedPassenger = 1;
            $PAforUnnamedPassengerSI = $value['sumInsured'];
            $cover_data[] = [
                'typ:paramDesc' => 'PA',
                'typ:paramRef' => 'PA',
            ];
        }
    }
    foreach ($accessories as $key => $value) {
        if (in_array('Electrical Accessories', $value)) {
            $ElectricalaccessSI = $value['sumInsured'];
            $cover_data[] = [
                'typ:paramDesc' => 'ELECACC',
                'typ:paramRef' => 'ELECACC',
            ];
        }

        if (in_array('Non-Electrical Accessories', $value)) {
            $NonElectricalaccessSI = $value['sumInsured'];
            $cover_data[] = [
                'typ:paramDesc' => 'NELECACC',
                'typ:paramRef' => 'NELECACC',
            ];
        }

        if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
            $externalCNGKITSI = $value['sumInsured'];
            $cover_data[] = [
                'typ:paramDesc' => 'CNG',
                'typ:paramRef' => 'CNG',
            ];
        }
    }
    $voluntary_insurer_discounts = 0;
    foreach ($discounts as $key => $value) {
        if (in_array('voluntary_insurer_discounts', $value)) {
            $voluntary_insurer_discounts = isset($value['sumInsured']) ? $value['sumInsured'] : 0;
        }
        if (in_array('TPPD Cover', $value)) {
            $cover_data[] = [
                'typ:paramDesc' => 'TPPD_RES',
                'typ:paramRef' => 'TPPD_RES',
            ];
        }
    }
    if ($voluntary_insurer_discounts != 0) {
        $cover_data[] = [
            'typ:paramDesc' => 'VOLEX',
            'typ:paramRef' => 'VOLEX',
        ];
    }

    $rto_details = DB::table('bajaj_allianz_master_rto')->where('registration_code', str_replace('-', '', $requestData->rto_code))->first();
    if(empty($rto_details))
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'RTO Not Available',
            'request' => [
                'message' => 'RTO Not Available',
                'rto_code' => $requestData->rto_code,
            ],
        ]; 
    }
    $zone_A = ['AHMEDABAD', 'BANGALORE', 'CHENNAI', 'HYDERABAD', 'KOLKATA', 'MUMBAI', 'NEW DELHI', 'PUNE', 'DELHI'];
    $zone = ((in_array(strtoupper($rto_details->city_name), $zone_A)) ? 'A' : 'B');

    
    $extCol38 = $masterProduct->product_identifier == 'DRIVE_ASSURE_WELCOME' ? '1000' : '0';
    if ($premium_type == "third_party") { // Only TP
        $product4digitCode = config("constants.motor.bajaj_allianz.PCV_PRODUCT_CODE_TP_BAJAJ_ALLIANZ_MOTOR");
    } else if ($BusinessType == '1') { // New Business
        $product4digitCode = config("constants.motor.bajaj_allianz.PCV_PRODUCT_CODE_BAJAJ_ALLIANZ_MOTOR_NEW");
    } else if ($premium_type == 'own_damage') { // Stand Alone OD
        $product4digitCode = config("constants.motor.bajaj_allianz.PCV_PRODUCT_CODE_OD_BAJAJ_ALLIANZ_MOTOR");
    } else { // Comprehensive Rollover
        $product4digitCode = config("constants.motor.bajaj_allianz.PCV_PRODUCT_CODE_BAJAJ_ALLIANZ_MOTOR");
    }
    //$product4digitCode = '1803';
    $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_USERNAME");
    $pPassword = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_PASSWORD");

    $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');
    $pos_data = DB::table('cv_agent_mappings')
                ->where('user_product_journey_id', $requestData->user_product_journey_id)
                ->where('seller_type','P')
                ->first();
    $extCol40 = '';

    if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
        if($pos_data) {
            $extCol40 = config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y' ? 'AAAAA1234A' : $pos_data->pan_no;
            $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_POS_USERNAME");
            $pPassword = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_POS_PASSWORD");
        }
    }
    elseif (config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y')
    {
        $extCol40 = 'AAAAA1234A';
        $pUserId = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_POS_USERNAME");
        $pPassword = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_POS_PASSWORD");
    }

    $data = [
        'soapenv:Header' => [],
        'soapenv:Body' => [
            'web:calculateMotorPremiumSig' => [
                'pUserId' => $pUserId,
                'pPassword' => $pPassword,
                'pVehicleCode' => $mmv_data->vehiclecode,
                'pCity' => strtoupper($rto_details->city_name),
                'pWeoMotPolicyIn_inout' => [
                    'typ:contractId' => '0',
                    'typ:polType' => $polType,
                    'typ:product4digitCode' => $product4digitCode,
                    'typ:deptCode' => '18',
                    'typ:branchCode' => config("constants.motor.bajaj_allianz.BRANCH_OFFICE_CODE_BAJAJ_ALLIANZ_MOTOR"),
                    'typ:termStartDate' => Carbon::parse($policy_start_date)->format('d-M-Y'),
                    'typ:termEndDate' => date('d-M-Y', strtotime($policy_end_date)),
                    'typ:tpFinType' => '',
                    'typ:hypo' => '',
                    'typ:vehicleTypeCode' => $mmv_data->vehicletypecode, // 22 is for car and 21 is for two-wheeler
                    'typ:vehicleType' => '',//$mmv_data->vehicletype,
                    'typ:miscVehType' => '0',
                    'typ:vehicleMakeCode' => $mmv_data->vehiclemakecode,
                    'typ:vehicleMake' => $mmv_data->vehiclemake,
                    'typ:vehicleModelCode' => $mmv_data->vehiclemodelcode,
                    'typ:vehicleModel' => $mmv_data->vehiclemodel,
                    'typ:vehicleSubtypeCode' => $mmv_data->vehiclesubtypecode,
                    'typ:vehicleSubtype' => $mmv_data->vehiclesubtype,
                    'typ:fuel' => $mmv_data->fuel,
                    'typ:zone' => $zone,
                    'typ:engineNo' => '123123123',
                    'typ:chassisNo' => '12323123',
                    'typ:registrationNo' => $BusinessType == '1' ? "NEW" : str_replace('-', '', $rto_code),
                    'typ:registrationDate' => date('d-M-Y', strtotime($requestData->vehicle_register_date)),
                    'typ:registrationLocation' => $rto_details->city_name,
                    'typ:regiLocOther' => $rto_details->city_name,
                    'typ:carryingCapacity' => $mmv_data->carryingcapacity,
                    'typ:cubicCapacity' => $mmv_data->cubiccapacity,
                    'typ:yearManf' => explode('-', $requestData->manufacture_year)[1],
                    'typ:color' => 'WHITE',
                    'typ:vehicleIdv' => '',
                    'typ:ncb' => $requestData->applicable_ncb,
                    'typ:addLoading' => '0',
                    'typ:addLoadingOn' => '0',
                    'typ:spDiscRate' => '0',
                    'typ:elecAccTotal' => $ElectricalaccessSI,
                    'typ:nonElecAccTotal' => $NonElectricalaccessSI,
                    'typ:prvPolicyRef' => '',
                    'typ:prvExpiryDate' => (($BusinessType == '1') ? '' : date('d-M-Y', strtotime($requestData->previous_policy_expiry_date))),
                    'typ:prvInsCompany' => '',
                    'typ:prvNcb' => $requestData->previous_ncb,
                    'typ:prvClaimStatus' => (($requestData->is_claim == 'Y') ? '1' : '0'),
                    'typ:autoMembership' => '0',
                    'typ:partnerType' => (($requestData->vehicle_owner_type == 'I') ? 'P' : 'I'),
                ],
                'accessoriesList_inout' => [
                    'typ:WeoMotAccessoriesUser' => [
                        'typ:contractId' => '',
                        'typ:accCategoryCode' => '',
                        'typ:accTypeCode' => '',
                        'typ:accMake' => '',
                        'typ:accModel' => '',
                        'typ:accIev' => '',
                        'typ:accCount' => '',
                    ],
                ],
                'paddoncoverList_inout' => [
                    'typ:WeoMotGenParamUser' => $cover_data,
                ],
                'motExtraCover' => [
                    'typ:geogExtn' => '',
                    'typ:noOfPersonsPa' => $PAforUnnamedPassenger ? $mmv_data->carryingcapacity : '',
                    'typ:sumInsuredPa' => $PAforUnnamedPassenger ? $PAforUnnamedPassengerSI : '',
                    'typ:sumInsuredTotalNamedPa' => '',
                    'typ:cngValue' => $externalCNGKITSI,
                    'typ:noOfEmployeesLle' => '',
                    'typ:noOfPersonsLlo' => $LLtoPaidDriverYN,
                    'typ:fibreGlassValue' => '',
                    'typ:sideCarValue' => '',
                    'typ:noOfTrailers' => '',
                    'typ:totalTrailerValue' => '',
                    'typ:voluntaryExcess' => $voluntary_insurer_discounts,
                    'typ:covernoteNo' => '',
                    'typ:covernoteDate' => '',
                    'typ:subImdcode' => '',
                    'typ:extraField1' => '400002', // Hardcoded
                    'typ:extraField2' => '',
                    'typ:extraField3' => '',
                ],
                'pQuestList_inout' => [
                    'typ:WeoBjazMotQuestionaryUser' => [
                        'typ:questionRef' => '',
                        'typ:contractId' => '',
                        'typ:questionVal' => '',
                    ],
                ],
                'pDetariffObj_inout' => [
                    'typ:vehPurchaseType' => '',
                    // 'typ:vehPurchaseDate' => '',
                    'typ:monthOfMfg' => '',
                    'typ:bodyType' => '',
                    'typ:goodsTransType' => '',
                    'typ:natureOfGoods' => '',
                    'typ:otherGoodsFrequency' => '',
                    'typ:permitType' => '',
                    'typ:roadType' => '',
                    'typ:vehDrivenBy' => '',
                    'typ:driverExperience' => '',
                    'typ:clmHistCode' => '',
                    'typ:incurredClmExpCode' => '',
                    'typ:driverQualificationCode' => '',
                    'typ:tacMakeCode' => '',
                    'typ:registrationAuth' => '',
                    'typ:extCol1' => '',
                    'typ:extCol2' => '',
                    'typ:extCol3' => '',
                    'typ:extCol4' => '',
                    'typ:extCol5' => '',
                    'typ:extCol6' => '',
                    'typ:extCol7' => config("constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR"),
                    'typ:extCol8' => $cpa,
                    'typ:extCol9' => '',
                    'typ:extCol10' => $masterProduct->product_identifier,
                    'typ:extCol11' => '',
                    'typ:extCol12' => '',
                    'typ:extCol13' => '',
                    'typ:extCol14' => '',
                    'typ:extCol15' => '',
                    'typ:extCol16' => '',
                    'typ:extCol17' => '',
                    'typ:extCol18' => 'CPA',
                    'typ:extCol19' => '',
                    'typ:extCol21' => 'Y', // Discount tag needed for Auto-Rickshaw 3WL
                    'typ:extCol20' => '',
                    'typ:extCol22' => '',
                    'typ:extCol23' => '',
                    'typ:extCol24' => $extCol24, //CPA cover
                    'typ:extCol25' => '',
                    'typ:extCol26' => '',
                    'typ:extCol29' => '',
                    'typ:extCol27' => '',
                    'typ:extCol28' => '',
                    'typ:extCol30' => '',
                    'typ:extCol31' => '',
                    'typ:extCol32' => '',
                    'typ:extCol33' => '',
                    'typ:extCol34' => '',
                    'typ:extCol35' => '',
                    'typ:extCol36' => '',
                    'typ:extCol37' => '',
                    'typ:extCol38' => $extCol38,
                    'typ:extCol39' => '',
                    'typ:extCol40' => $extCol40,
                ],
                'premiumDetailsOut_out' => [
                    'typ:serviceTax' => '',
                    'typ:collPremium' => '',
                    'typ:totalActPremium' => '',
                    'typ:netPremium' => '',
                    'typ:totalIev' => '',
                    'typ:addLoadPrem' => '',
                    'typ:totalNetPremium' => '',
                    'typ:imtOut' => '',
                    'typ:totalPremium' => '',
                    'typ:ncbAmt' => '',
                    'typ:stampDuty' => '',
                    'typ:totalOdPremium' => '',
                    'typ:spDisc' => '',
                    'typ:finalPremium' => '',
                ],
                'premiumSummeryList_out' => [
                    'typ:WeoMotPremiumSummaryUser' => [
                        'typ:od' => '',
                        'typ:paramDesc' => '',
                        'typ:paramRef' => '',
                        'typ:net' => '',
                        'typ:act' => '',
                        'typ:paramType' => '',
                    ],
                ],
                'pError_out' => [
                    'typ:WeoTygeErrorMessageUser' => [
                        'typ:errNumber' => '',
                        'typ:parName' => '',
                        'typ:property' => '',
                        'typ:errText' => '',
                        'typ:parIndex' => '',
                        'typ:errLevel' => '',
                    ],
                ],
                'pErrorCode_out' => '',
                'pTransactionId_inout' => '',
                'pTransactionType' => '',
                'pContactNo' => '',
            ],
        ],
    ];
    $additional_data = [
        'enquiryId' => $enquiryId,
        'headers' => [
            'Content-Type' => 'text/xml; charset="utf-8"',
        ],
        'requestMethod' => 'post',
        'requestType' => 'xml',
        'section' => 'PCV',
        'method' => 'Premium Calculation - ' . $masterProduct->product_identifier,
        'product' => 'PCV',
        'transaction_type' => 'quote',
    ];
    $root = [
        'rootElementName' => 'soapenv:Envelope',
        '_attributes' => [
            "xmlns:soapenv" => "http://schemas.xmlsoap.org/soap/envelope/",
            "xmlns:web" => "http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl",
            "xmlns:typ" => "http://com/bajajallianz/motWebPolicy/WebServicePolicy.wsdl/types/",
        ],
    ];
    $input_array = ArrayToXml::convert($data, $root, false, 'utf-8');
    $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_QUOTE_URL'), $input_array, 'bajaj_allianz', $additional_data);
    $response = $get_response['response'];
    if (empty($response)) {
        return [
            'premium_amount' => 0,
            'status' => false,
            'webservice_id'=>$get_response['webservice_id'],
            'table'=>$get_response['table'],
            'message' => 'Insurer not reachable',
        ];
    }
    $response = XmlToArray::convert($response);
    $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
    if ($service_response['pErrorCode_out'] != '0') {
        $error_msg = 'Insurer not reachable';
        if (isset($service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'])) {
            $error_msg = $service_response['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
        } elseif (is_array($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) && count($service_response['pError_out']['typ:WeoTygeErrorMessageUser']) > 0) {
            $error_msg = implode(', ', array_column($service_response['pError_out']['typ:WeoTygeErrorMessageUser'], 'typ:errText'));
        }
        return [
            'premium_amount' => 0,
            'status' => false,
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'message' => $error_msg,
        ];
    }

    if ($tp_only == 'true') {
        $idv = $idv_min = $idv_max = 0;
    } else {
        $idv = $service_response['pWeoMotPolicyIn_inout']['typ:vehicleIdv'];
        $idv_min = (string) $idv;
        $idv_max = (string) floor(1.10 * $idv);
    }
    if ($requestData->is_idv_changed == 'Y' && $tp_only == 'false') {
        if ($requestData->edit_idv >= floor($idv_max)) {
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = floor($idv_max);
        } elseif ($requestData->edit_idv <= ceil($idv_min)) {
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = ceil($idv_min);
        } else {
            $data['soapenv:Body']['web:calculateMotorPremiumSig']['pWeoMotPolicyIn_inout']['typ:vehicleIdv'] = $requestData->edit_idv;
        }
        //$data['soapenv:Body']['web:calculateMotorPremiumSig']['pTransactionId_inout'] = $service_response['pTransactionId_inout'];
        $additional_data['method'] = 'Premium Re Calculation - ' . $masterProduct->product_identifier;
        $input_array = ArrayToXml::convert($data, $root, false, 'utf-8');
        $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_QUOTE_URL'), $input_array, 'bajaj_allianz', $additional_data);
        $response = $get_response['response'];
        if (empty($response)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => 'Insurer not reachable',
            ];
        }
        $response = XmlToArray::convert($response);
        $service_response = $response['env:Body']['m:calculateMotorPremiumSigResponse'];
        if ($service_response['pErrorCode_out'] != '0') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => $service_response['pError_out']['WeoTygeErrorMessageUser']['errText'] ?? $service_response['pError_out']['WeoTygeErrorMessageUser'][0]['errText'] ?? 'Insurer not reachable',
            ];
        }
    }
    $transaction_id = $service_response['pTransactionId_inout'] ?? '';
    $idv = $service_response['pWeoMotPolicyIn_inout']['typ:vehicleIdv'];
    $zero_dep_amount = $key_replacement = $basic_od = $tp_amount = $pa_owner = $non_electrical_accessories = $electrical_accessories = $pa_unnamed = $ll_paid_driver = $lpg_cng = $lpg_cng_tp = $other_discount = $ncb_discount = $engine_protector = $lossbaggage = $rsa = $accident_shield = $conynBenef = $consExps = $voluntary_deductible = $tppd = 0;
    $finalPremium = $service_response['premiumDetailsOut_out']['typ:finalPremium'];
    $base_premium_amount = ($finalPremium / (1.18));
    if (isset($service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'])) {
        $covers = $service_response['premiumSummeryList_out']['typ:WeoMotPremiumSummaryUser'];

        if ($premium_type == 'own_damage' && $requestData->is_claim == 'Y' && $productData->zero_dep == '1' && isset($covers['od'])) {
            $basic_od = ($covers['od']);
        } else {
            foreach ($covers as $key => $cover) {
                if (($productData->zero_dep == '0') && ($cover['typ:paramDesc'] === 'Depreciation Shield')) {
                    $zero_dep_amount = round($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'KEYS AND LOCKS REPLACEMENT COVER') {
                    $key_replacement = round($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'Engine Protector') {
                    $engine_protector = round($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'Basic Own Damage') {
                    $basic_od = round($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'Basic Third Party Liability') {
                    $tp_amount = round($cover['typ:act']);
                } elseif ($cover['typ:paramDesc'] === 'PA Cover For Owner-Driver') {
                    $pa_owner = round($cover['typ:act']);
                } elseif ($cover['typ:paramDesc'] === 'Non-Electrical Accessories') {
                    $non_electrical_accessories = round($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'Electrical Accessories') {
                    $electrical_accessories = round($cover['typ:od']);
                } elseif ($cover['typ:paramDesc'] === 'PA for unnamed Passengers') {
                    $pa_unnamed = round($cover['typ:act']);
                } elseif ($cover['typ:paramDesc'] === 'LL To Person For Operation/Maintenance(IMT.28/39)') {
                    $ll_paid_driver = round($cover['typ:act']);
                } elseif ($cover['typ:paramDesc'] === 'CNG / LPG Unit (IMT.25)') {
                    $lpg_cng = round($cover['typ:od']);
                    $lpg_cng_tp = round($cover['typ:act']);
                } elseif (in_array($cover['typ:paramDesc'], ['Commercial Discount', 'Commercial Discount3'])) {
                    $other_discount = round(abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Bonus / Malus') {
                    $ncb_discount = round(abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Personal Baggage Cover') {
                    $lossbaggage = round(abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === '24x7 SPOT ASSISTANCE') {
                    $rsa = round(abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Accident Sheild') {
                    $accident_shield = round(abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Conveyance Benefit') {
                    $conynBenef = round(abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Consumable Expenses') {
                    $consExps = round(abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Voluntary Excess (IMT.22 A)') {
                    $voluntary_deductible = (abs($cover['typ:od']));
                } elseif ($cover['typ:paramDesc'] === 'Restrict TPPD') {
                    $tppd = (abs($cover['typ:act']));
                }
            }
        }
    }
    $applicable_addons = $add_ons_data = [];
    switch ($masterProduct->product_identifier) {
        case 'DRIVE_ASSURE_PACK_PLUS':
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => $zero_dep_amount,
                    'engine_protector' => $engine_protector,
                    'key_replace' => $key_replacement,
                    'road_side_assistance' => $rsa,
                    'lopb' => $lossbaggage,
                ],
                'additional' => [
                    'zero_depreciation' => 0,
                    'road_side_assistance' => 0,
                    'engine_protector' => 0,
                    'ncb_protection' => 0,
                    'key_replace' => 0,
                    'consumables' => 0,
                    'tyre_secure' => 0,
                    'return_to_invoice' => 0,
                    'lopb' => 0,
                ],
                'other' => [],
            ];
            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb");
            break;

        case 'DRIVE_ASSURE_PACK':
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => $zero_dep_amount,
                    'engine_protector' => $engine_protector,
                    'road_side_assistance' => $rsa,
                ],
                'additional' => [
                    'zero_depreciation' => 0,
                    'road_side_assistance' => 0,
                    'engine_protector' => 0,
                    'ncb_protection' => 0,
                    'key_replace' => 0,
                    'consumables' => 0,
                    'tyre_secure' => 0,
                    'return_to_invoice' => 0,
                    'lopb' => 0,
                ],
                'other' => [],
            ];
            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "roadSideAssistance");
            break;
        case 'TELEMATICS_PREMIUM':
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => $zero_dep_amount,
                    'engine_protector' => $engine_protector,
                    'road_side_assistance' => $rsa,
                    'key_replace' => $key_replacement,
                    'lopb' => $lossbaggage,
                ],
                'additional' => [
                    'zero_depreciation' => 0,
                    'road_side_assistance' => 0,
                    'engine_protector' => 0,
                    'ncb_protection' => 0,
                    'key_replace' => 0,
                    'consumables' => 0,
                    'tyre_secure' => 0,
                    'return_to_invoice' => 0,
                    'lopb' => 0,
                ],
                'other' => [
                    'Accident_shield' => $accident_shield,
                ],
            ];
            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb", "AccidentShield");
            break;
        case 'TELEMATICS_PRESTIGE':
            $add_ons_data = [
                'in_built' => [
                    'zero_depreciation' => $zero_dep_amount,
                    'engine_protector' => $engine_protector,
                    'road_side_assistance' => $rsa,
                    'key_replace' => $key_replacement,
                    'lopb' => $lossbaggage,
                    'consumables' => $consExps,
                ],
                'additional' => [
                    'zero_depreciation' => 0,
                    'road_side_assistance' => 0,
                    'engine_protector' => 0,
                    'ncb_protection' => 0,
                    'key_replace' => 0,
                    'consumables' => 0,
                    'tyre_secure' => 0,
                    'return_to_invoice' => 0,
                    'lopb' => 0,
                ],
                'other' => [
                    'Accident_shield' => $accident_shield,
                    'Conveyance_Benefit' => $conynBenef,
                ],
            ];
            array_push($applicable_addons, "zeroDepreciation", "engineProtector", "keyReplace", "roadSideAssistance", "lopb", "consumables", "AccidentShield", "ConveyanceBenefit");
            break;
        case 'TELEMATICS_CLASSIC':
            $add_ons_data = [
                'in_built' => [
                    'road_side_assistance' => $rsa,
                    'key_replace' => $key_replacement,
                    'lopb' => $lossbaggage,
                ],
                'additional' => [
                    'zero_depreciation' => 0,
                    'road_side_assistance' => 0,
                    'engine_protector' => 0,
                    'ncb_protection' => 0,
                    'key_replace' => 0,
                    'consumables' => 0,
                    'tyre_secure' => 0,
                    'return_to_invoice' => 0,
                    'lopb' => 0,
                ],
                'other' => [
                    'Accident_shield' => $accident_shield,
                ],
            ];
            array_push($applicable_addons, "keyReplace", "roadSideAssistance", "lopb", "AccidentShield");
            break;

        case 'Prime':
            $add_ons_data = [
                'in_built' => [
                    'road_side_assistance' => $rsa,
                    'key_replace' => $key_replacement,
                ],
                'additional' => [
                    'zero_depreciation' => 0,
                    'road_side_assistance' => 0,
                    'engine_protector' => 0,
                    'ncb_protection' => 0,
                    'key_replace' => 0,
                    'consumables' => 0,
                    'tyre_secure' => 0,
                    'return_to_invoice' => 0,
                    'lopb' => 0,
                ],
                'other' => [],
            ];
            if ($car_age < 9) {
                array_push($applicable_addons, "keyReplace", "roadSideAssistance");
            }
            break;
        default:
            $add_ons_data['in_built'] = $add_ons_data['additional'] = [];

    }
    $add_ons_data['in_built_premium'] = !empty($add_ons_data['in_built']) ? array_sum($add_ons_data['in_built']) : 0;
    $add_ons_data['additional_premium'] = !empty($add_ons_data['in_built']) ? array_sum($add_ons_data['additional']) : 0;
    $add_ons_data['other_premium'] = !empty($add_ons_data['in_built']) ? array_sum($add_ons_data['other']) : 0;
    $ExtraPremiumForRejectedRTO = is_array($service_response['pDetariffObj_inout']['typ:extCol22']) ? 0 : $service_response['pDetariffObj_inout']['typ:extCol22'];

    $tp_amount += $tppd;
    $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;// + $ExtraPremiumForRejectedRTO; //A
    $totalTP = $tp_amount + $pa_unnamed + $ll_paid_driver + $lpg_cng_tp; //B
    $totalDiscount = $ncb_discount + $other_discount + $voluntary_deductible + $tppd; //C
    $totalBasePremium = $final_od_premium + $totalTP - $totalDiscount; //A + B - C
    $final_gst_amount = $totalBasePremium * 0.18;
    $final_payable_amount = $totalBasePremium * (1.18);
    $data_response = [
        'status' => true,
        'msg' => 'Found',
        'webservice_id'=> $get_response['webservice_id'],
        'table'=> $get_response['table'],
        'Data' => [
            'idv' => $idv,
            'min_idv' => $idv_min,
            'max_idv' => $idv_max,
            'vehicle_idv' => $idv,
            'qdata' => null,
            'pp_enddate' => $requestData->previous_policy_expiry_date,
            'addonCover' => null,
            'addon_cover_data_get' => '',
            'rto_decline' => null,
            'rto_decline_number' => null,
            'mmv_decline' => null,
            'mmv_decline_name' => null,
            'policy_type' => $premium_type == 'third_party' ? 'Third Party' : (($premium_type == "own_damage") ? 'Own Damage' : 'Comprehensive'), //$tp_only == 'true' ? 'Third Party' : 'Comprehensive',
            'cover_type' => '1YC',
            'underwriting_loading_amount'=> $premium_type == 'third_party' ? 0 : $ExtraPremiumForRejectedRTO,
            'tppd_discount' => $tppd,
            'hypothecation' => '',
            'hypothecation_name' => '',
            'vehicle_registration_no' => $rto_code,
            'rto_no' => $rto_code,
            'version_id' => $requestData->version_id,
            'selected_addon' => [],
            'showroom_price' => 0,
            'fuel_type' => $requestData->fuel_type,
            'ncb_discount' => $requestData->applicable_ncb,
            'company_name' => $productData->company_name,
            'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
            'product_name' => $productData->product_sub_type_name . ' - ' . ucwords(str_replace('_', ' ', strtolower($masterProduct->product_identifier))),
            'mmv_detail' => $mmv_data,
            'master_policy_id' => [
                'policy_id' => $productData->policy_id,
                'policy_no' => $productData->policy_no,
                'policy_start_date' => $policy_start_date,
                'policy_end_date' => $policy_end_date,
                'sum_insured' => $productData->sum_insured,
                'corp_client_id' => $productData->corp_client_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'insurance_company_id' => $productData->company_id,
                'status' => $productData->status,
                'corp_name' => '',
                'company_name' => $productData->company_name,
                'logo' => url(config('constants.motorConstant.logos') . $productData->logo),
                'product_sub_type_name' => $productData->product_sub_type_name,
                'flat_discount' => $productData->default_discount,
                'predefine_series' => '',
                'is_premium_online' => $productData->is_premium_online,
                'is_proposal_online' => $productData->is_proposal_online,
                'is_payment_online' => $productData->is_payment_online,
            ],
            'motor_manf_date' => $requestData->vehicle_register_date,
            'vehicle_register_date' => $requestData->vehicle_register_date,
            'vehicleDiscountValues' => [
                'master_policy_id' => $productData->policy_id,
                'product_sub_type_id' => $productData->product_sub_type_id,
                'segment_id' => 0,
                'rto_cluster_id' => 0,
                'car_age' => $car_age,
                'aai_discount' => 0,
                'ic_vehicle_discount' => round(abs($other_discount)),
            ],
            'ic_vehicle_discount' => round(abs($other_discount)),
            'basic_premium' => round($basic_od),
            'motor_electric_accessories_value' => round($electrical_accessories),
            'motor_non_electric_accessories_value' => round($non_electrical_accessories),
            'motor_lpg_cng_kit_value' => round($lpg_cng),
            'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
            'total_own_damage' => round($basic_od),
            'tppd_premium_amount' => round($tp_amount),
            'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
            'cover_unnamed_passenger_value' => $pa_unnamed,
            'default_paid_driver' => $ll_paid_driver,
            'll_paid_driver_premium' => $ll_paid_driver,
            'll_paid_conductor_premium' => 0,
            'll_paid_cleaner_premium' => 0,
            'motor_additional_paid_driver' => '', //round($pa_paid_driver),
            'cng_lpg_tp' => round($lpg_cng_tp),
            'seating_capacity' => $mmv_data->carryingcapacity,
            'deduction_of_ncb' => round(abs($ncb_discount)),
            'antitheft_discount' => '', //round(abs($anti_theft)),
            'aai_discount' => '', //$automobile_association,
            'voluntary_excess' => $voluntary_deductible,
            'other_discount' => round(abs($other_discount)),
            'total_liability_premium' => round($totalTP),
            'net_premium' => round($totalBasePremium),
            'service_tax_amount' => round($final_gst_amount),
            'service_tax' => 18,
            'total_discount_od' => 0,
            'add_on_premium_total' => 0,
            'addon_premium' => 0,
            'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
            'quotation_no' => '',
            'premium_amount' => round($final_payable_amount),
            'service_data_responseerr_msg' => 'success',
            'user_id' => $requestData->user_id,
            'product_sub_type_id' => $productData->product_sub_type_id,
            'user_product_journey_id' => $requestData->user_product_journey_id,
            'business_type' => $requestData->business_type == 'newbusiness' ? 'New Business' : $requestData->business_type,
            'service_err_code' => null,
            'service_err_msg' => null,
            'policyStartDate' => date('d-m-Y', strtotime($policy_start_date)),
            'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
            'ic_of' => $productData->company_id,
            'vehicle_in_90_days' => $vehicle_in_90_days,
            'get_policy_expiry_date' => null,
            'get_changed_discount_quoteid' => 0,
            'vehicle_discount_detail' => [
                'discount_id' => null,
                'discount_rate' => null,
            ],
            'is_premium_online' => $productData->is_premium_online,
            'is_proposal_online' => $productData->is_proposal_online,
            'is_payment_online' => $productData->is_payment_online,
            'policy_id' => $productData->policy_id,
            'insurane_company_id' => $productData->company_id,
            'max_addons_selection' => null,
            'add_ons_data' => $add_ons_data,
            /* 'add_ons_data' => [
            'in_built' => [],
            'additional' => [
            'zero_depreciation' => round($zero_dep_amount),
            'road_side_assistance' => round($rsapremium),
            'imt23' => round($imt_23),
            ],
            'in_built_premium' => 0,
            'additional_premium' => array_sum([$zero_dep_amount, $rsapremium, $imt_23]),
            'other_premium' => 0,
            ], */
            'applicable_addons' => $applicable_addons,
            'final_od_premium' => round($final_od_premium),
            'final_tp_premium' => round($totalTP),
            'final_total_discount' => round(abs($totalDiscount)),
            'final_net_premium' => round($totalBasePremium),
            'final_gst_amount' => round($final_gst_amount),
            'final_payable_amount' => round($final_payable_amount),
            'mmv_detail' => [
                'manf_name' => $mmv_data->vehiclemake,
                'model_name' => $mmv_data->vehiclemodel,
                'version_name' => $mmv_data->vehiclesubtype,
                'fuel_type' => $mmv_data->fuel,
                'seating_capacity' => $mmv_data->carryingcapacity,
                'carrying_capacity' => $mmv_data->carryingcapacity,
                'cubic_capacity' => $mmv_data->cubiccapacity,
                'gross_vehicle_weight' => '',
                'vehicle_type' => '',//$mmv_data->vehicletype,
            ],
            'product_identifier' => $masterProduct->product_identifier,
            'transaction_id' => $transaction_id
        ],
    ];
    return camelCase($data_response);
}

function getQuoteJson($enquiryId, $requestData, $productData)
{
    $is_gcv = policyProductType($productData->policy_id)->parent_id == 4;

    $mmv = get_mmv_details($productData, $requestData->version_id, 'bajaj_allianz');

    if ($mmv['status'] == 1)
    {
        $mmv = $mmv['data'];
    }
    else
    {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => $mmv['message'],
            'request' => [
                'mmv' => $mmv,
            ],
        ];
    }

    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

    $user_product_journey = UserProductJourney::find($enquiryId);
    // dd($user_product_journey->addons);
    $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;
    $addons = $user_product_journey->addons;
    $agent_details = $user_product_journey->agent_details;

    $premium_type = MasterPremiumType::where('id', $productData->premium_type_id)->pluck('premium_type_code')->first();

    if (in_array($corporate_vehicles_quote_request->business_type, ['newbusiness', 'breakin']))
    {
        $policy_start_date = date('d-M-Y', time());
    }
    else
    {
        $policy_start_date = date('d-M-Y', strtotime('+1 day', strtotime($corporate_vehicles_quote_request->previous_policy_expiry_date)));
    }

    $policy_end_date = date('d-M-Y', strtotime('+1 year -1 day', strtotime($policy_start_date)));

    $rto_data = MasterRto::where('rto_code', $corporate_vehicles_quote_request->rto_code)
        ->first();

    $registration_number = '';

    if ( ! is_null($corporate_vehicles_quote_request->vehicle_registration_no) && ($corporate_vehicles_quote_request->vehicle_registration_no != 'NEW') )
    {
        $registration_number = str_replace('-', '', $corporate_vehicles_quote_request->vehicle_registration_no);
    }
    else
    {
        $reg_no_3 = chr(rand(65,90));
        $registration_number = str_replace('-', '', $corporate_vehicles_quote_request->rto_code) . $reg_no_3 . $reg_no_3 . '1234';
    }

    $vehicleDate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
    $date1 = new DateTime($vehicleDate);
    $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
    $interval = $date1->diff($date2);
    $age = (($interval->y * 12) + $interval->m) + 1;
    $vehicle_age = floor($age / 12);

    $cover_list = [];

    $electrical_accessories_sa = 0;
    $non_electrical_accessories_sa = 0;
    $lpg_cng_kit_sa = 0;
    $additional_paid_driver_sa = 0;
    $pa_unnamed_passenger_sa = 0;
    $no_of_unnamed_passenger = 0;
    $voluntary_deductible_si = 0;
    $ll_paid_driver = 0;
    $geo_extension = 0;

    if ($addons)
    {
        if ( ! is_null($addons[0]['accessories']))
        {
            foreach ($addons[0]['accessories'] as $accessory)
            {
                if ($accessory['name'] == 'Electrical Accessories')
                {
                    $electrical_accessories_sa = $accessory['sumInsured'];

                    $cover_list[] = [
                        'paramdesc' => 'ELECACC',
                        'paramref' => 'ELECACC'
                    ];
                }
                elseif ($accessory['name'] == 'Non-Electrical Accessories')
                {
                    $non_electrical_accessories_sa = $accessory['sumInsured'];

                    $cover_list[] = [
                        'paramdesc' => 'NELECACC',
                        'paramref' => 'NELECACC'
                    ];
                }
                elseif ($accessory['name'] == 'External Bi-Fuel Kit CNG/LPG')
                {
                    $lpg_cng_kit_sa = $accessory['sumInsured'];

                    $cover_list[] = [
                        'paramdesc' => 'CNG',
                        'paramref' => 'CNG'
                    ];
                }
            }
        }

        if ( ! is_null($addons[0]['additional_covers']))
        {
            foreach ($addons[0]['additional_covers'] as $additional_cover)
            {
                if ($additional_cover['name'] == 'Unnamed Passenger PA Cover')
                {
                    $pa_unnamed_passenger_sa = $additional_cover['sumInsured'];
                    $no_of_unnamed_passenger = (int) $mmv_data->carryingcapacity;

                    $cover_list[] = [
                        'paramdesc' => 'PA',
                        'paramref' => 'PA'
                    ];
                }
                elseif ($additional_cover['name'] == 'LL paid driver')
                {
                    $ll_paid_driver = 1;

                    $cover_list[] = [
                        'paramdesc' => 'LLO',
                        'paramref' => 'LLO'
                    ];
                }
                elseif ($additional_cover['name'] == 'LL paid driver/conductor/cleaner')
                {
                    $ll_paid_driver = $additional_cover['LLNumberDriver'] + $additional_cover['LLNumberConductor'] + $additional_cover['LLNumberCleaner'];

                    $cover_list[] = [
                        'paramdesc' => 'LLO',
                        'paramref' => 'LLO'
                    ];
                } elseif ($additional_cover['name'] == 'Geographical Extension') {
                    $geo_extension = 1;

                    $cover_list[] = [
                        'paramdesc' => 'GEOG',
                        'paramref' => 'GEOG'
                    ];
                }
            }
        }

        if ( ! is_null($addons[0]['discounts']))
        {
            foreach ($addons[0]['discounts'] as $discount)
            {
                if ($discount['name'] == 'PA cover for additional paid driver')
                {
                    $voluntary_deductible_si = $discount['sumInsured'];
                }
                elseif ($discount['name'] == 'TPPD Cover')
                {
                    $cover_list[] = [
                        'paramdesc' => 'TPPD_RES',
                        'paramref' => 'TPPD_RES'
                    ];
                }
            }
        }
    }

    $rto_details = MasterRto::where('rto_code', $requestData->rto_code)->first();

    $zone_A = ['AHMEDABAD', 'BANGALORE', 'CHENNAI', 'HYDERABAD', 'KOLKATA', 'MUMBAI', 'NEW DELHI', 'PUNE', 'DELHI'];

    $zone = in_array(strtoupper($rto_details->rto_name), $zone_A) ? 'A' : 'B';

    $extCol40 = '';

    $userid = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_USER_ID');
    $password = config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PASSWORD');

    $is_pos = config('constants.motorConstant.IS_POS_ENABLED');
    $is_pos_policy = FALSE;

    if (($is_pos == 'Y' && $agent_details && isset($agent_details[0]->seller_type) && $agent_details[0]->seller_type == 'P') || config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y')
    {
        $is_pos_policy = TRUE;

        $extCol40 = config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y' ? 'AAAAA1234A' : $agent_details[0]->pan_no;
        $userid = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_USERNAME");
        $password = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_POS_PASSWORD");
    }

    $premium_request = [
        'userid' => $userid,
        'password' => $password,
        'vehiclecode' => $mmv_data->vehiclecode,
        'city' => $rto_data->rto_name,
        'weomotpolicyin' => [
            'contractid' => 0,
            'poltype' => $corporate_vehicles_quote_request->business_type == 'newbusiness' ? 1 : 3,
            'product4digitcode' => in_array($premium_type, ['third_party', 'third_party_breakin']) ? 1831 : 1803,
            'deptcode' => 18,
            'branchcode' => config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_BRANCH_CODE'),
            'termstartdate' => $policy_start_date,
            'termenddate' => $policy_end_date,
            'vehicletypecode' => $mmv_data->vehicletypecode,
            'vehicletype' => 'Commercial Vehicle',
            'miscvehtype' => 0,
            'vehiclemakecode' => $mmv_data->vehiclemakecode,
            'vehiclemake' => $mmv_data->vehiclemake,
            'vehiclemodelcode' => $mmv_data->vehiclemodelcode,
            'vehiclemodel' => $mmv_data->vehiclemodel,
            'vehiclesubtypecode' => $mmv_data->vehiclesubtypecode,
            'vehiclesubtype' => $mmv_data->vehiclesubtype,
            'fuel' => $mmv_data->fuel,
            'zone' => $zone,
            'registrationno' => $registration_number,
            'registrationdate' => date('d-M-Y', strtotime($corporate_vehicles_quote_request->vehicle_register_date)),
            'registrationlocation' => $rto_data->rto_name,
            'regilocother' => $rto_data->rto_name,
            'carryingcapacity' => $mmv_data->carryingcapacity,
            'cubiccapacity' => ! empty($mmv_data->cubiccapacity) ? $mmv_data->cubiccapacity : ($is_gcv && ! empty($mmv_data->vehiclegvw) ? $mmv_data->vehiclegvw : 0),
            'yearmanf' => explode('-', $requestData->manufacture_year)[1],
            'vehicleidv' => '0',
            'ncb' => $corporate_vehicles_quote_request->applicable_ncb,
            'addloading' => '0', //
            'addloadingon' => '0', //
            'elecacctotal' => $electrical_accessories_sa,
            'nonelecacctotal' => $non_electrical_accessories_sa,
            'prvpolicyref' => '123123123',
            'prvexpirydate' => date('d-M-Y', strtotime($corporate_vehicles_quote_request->previous_policy_expiry_date)),
            'prvinscompany' => '1',
            'prvncb' => $corporate_vehicles_quote_request->previous_ncb,
            'prvclaimstatus' => $corporate_vehicles_quote_request->is_claim == 'Y' ? '1' : '0',
            'partnertype' => $corporate_vehicles_quote_request->vehicle_owner_type == 'I' ? 'P' : 'I'
        ],
        'accessorieslist' => [
            [
                'contractid' => '0',
                'acccategorycode' => '0',
                'acctypecode' => '0',
                'accmake' => '0',
                'accmodel' => '0',
                'acciev' => '0',
                'acccount' => '0'
            ]
        ],
        'paddoncoverlist' => $cover_list,
        'motextracover' => [
            'geogextn' => $geo_extension,
            'noofpersonspa' => $no_of_unnamed_passenger,
            'suminsuredpa' => $pa_unnamed_passenger_sa,
            'suminsuredtotalnamedpa' => 0,
            'cngvalue' => $lpg_cng_kit_sa,
            'noofemployeeslle' => '0',
            'noofpersonsllo' => $ll_paid_driver,
            'fibreglassvalue' => '0',
            'sidecarvalue' => '0',
            'nooftrailers' => '0',
            'totaltrailervalue' => '0',
            'voluntaryexcess' => '0',
            'covernoteno' => '',
            'covernotedate' => '',
            'subimdcode' => '',
            'extrafield1' => '',
            'extrafield2' => '',
            'extrafield3' => ''
        ],
        'questlist' => [
            [
                'questionref' => '',
                'contractid' => '',
                'questionval' => ''
            ]
        ],
        'detariffobj' => [
            'vehpurchasetype' => '',
            'vehpurchasedate' => '',
            'monthofmfg' => '',
            'registrationauth' => '',
            'bodytype' => '',
            'goodstranstype' => '',
            'natureofgoods' => '',
            'othergoodsfrequency' => '',
            'permittype' => '',
            'roadtype' => '',
            'vehdrivenby' => '',
            'driverexperience' => '',
            'clmhistcode' => '',
            'incurredclmexpcode' => '',
            'driverqualificationcode' => '',
            'tacmakecode' => '',
            'extcol1' => '',
            'extcol2' => '',
            'extcol3' => '',
            'extcol4' => '',
            'extcol5' => '',
            'extcol6' => '',
            'extcol7' => config('constants.motor.bajaj_allianz.SUB_IMD_CODE_BAJAJ_ALLIANZ_MOTOR'),
            'extcol8' => '',
            'extcol9' => '',
            'extcol10' => $productData->product_identifier,
            'extcol11' => '',
            'extcol12' => '',
            'extcol13' => '',
            'extcol14' => '',
            'extcol15' => '',
            'extcol16' => '',
            'extcol17' => '',
            'extcol18' => '',
            'extcol19' => '',
            'extcol20' => '',
            'extcol21' => 'Y',
            'extcol22' => '',
            'extcol23' => '',
            'extcol24' => '',
            'extcol25' =>  '',
            'extcol26' =>  '',
            'extcol27' =>  '',
            'extcol28' =>  '',
            'extcol29' =>  '',
            'extcol30' =>  '',
            'extcol31' =>  '',
            'extcol32' =>  '',
            'extcol33' =>  '',
            'extcol34' =>  '',
            'extcol35' =>  '',
            'extcol36' =>  '',
            'extcol37' =>  '',
            'extcol38' =>  '',
            'extcol39' =>  '',
            'extcol40' =>  $extCol40
        ],
        'transactionid' => "0",
        'transactiontype' => "MOTOR_WEBSERVICE",
        'contactno' => "9999912123"
    ];

    if ($corporate_vehicles_quote_request->business_type == 'newbusiness')
    {
        unset($premium_request['weomotpolicyin']['prvncb']);
    }

    if ($is_gcv)
    {
        $premium_request['paddoncoverlist'][] = [
            'paramdesc' => 'IMT23',
            'paramref' => 'IMT23'
        ];
    }

    $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PREMIUM_CALCULATION_URL'), $premium_request, 'bajaj_allianz', [
        'enquiryId' => $enquiryId,
        'requestMethod' =>'post',
        'productName'  => $productData->product_name,
        'section' => $productData->product_sub_type_code,
        'method' =>'Premium Calculation',
        'transaction_type' => 'quote',
        'contentType' => 'json'
    ]);

    $premium_response = $get_response['response'];
    if ($premium_response)
    {
        $premium_response = json_decode($premium_response, TRUE);

        $recalculate_premium = FALSE;

        $min_idv = $default_idv = $vehicle_idv = $max_idv = 0;

        if (isset($premium_response['errorcode']) && $premium_response['errorcode'] == 0 && ! is_null($premium_response['premiumdetails']))
        {
            // $premium_request['transactionid'] = $premium_response['transactionid'];


            if ( ! in_array($premium_type, ['third_party', 'third_party_breakin']))
            {
                $recalculate_premium = TRUE;

                $vehicle_idv = $premium_response['premiumdetails']['totaliev'];
                $default_idv = $vehicle_idv;
                $min_idv = ceil(0.9 * $vehicle_idv);
                $max_idv = floor(1.10 * $vehicle_idv);

                $idv = $requestData->edit_idv;

                if ($requestData->is_idv_changed == 'Y')
                {
                    if ($requestData->edit_idv <= $min_idv)
                    {
                        $idv = $min_idv;
                    }
                    elseif ($requestData->edit_idv >= $max_idv)
                    {
                        $idv = $max_idv;
                    }
                }
                else
                {
                    //$idv = $min_idv;
                    $recalculate_premium = FALSE;
                }

                $premium_request['weomotpolicyin']['vehicleidv'] = $idv;
                $premium_request['weomotpolicyin']['elecacctotal'] = $electrical_accessories_sa;
                $premium_request['weomotpolicyin']['nonelecacctotal'] = $non_electrical_accessories_sa;

                if ($idv > 5000000)
                {
                    if (($is_pos == 'Y' && $agent_details && isset($agent_details[0]->seller_type) && $agent_details[0]->seller_type == 'P') || config('constants.motor.bajaj_allianz.IS_POS_TESTING_MODE_ENABLE_BAJAJ') == 'Y')
                    {
                        $is_pos_policy = FALSE;
            
                        $premium_request['detariffobj']['extcol40'] = '';
                        $premium_request['userid'] = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_USER_ID");
                        $premium_request['password'] = config("constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PASSWORD");
                    }
                }
            }

            if ($recalculate_premium)
            {
                $get_response = getWsData(config('constants.motor.bajaj_allianz.BAJAJ_ALLIANZ_CV_JSON_PREMIUM_CALCULATION_URL'), $premium_request, 'bajaj_allianz', [
                    'enquiryId' => $enquiryId,
                    'requestMethod' =>'post',
                    'productName'  => $productData->product_name,
                    'section' => $productData->product_sub_type_code,
                    'method' =>'Premium Re-calculation',
                    'transaction_type' => 'quote',
                    'contentType' => 'json'
                ]);

                $premium_response = $get_response['response'];
                if ($premium_response)
                {
                    $premium_response = json_decode($premium_response, TRUE);

                    if (isset($premium_response['errorcode']) && $premium_response['errorcode'] != 0 || is_null($premium_response['premiumdetails']))
                    {
                        return camelCase([
                            'status' => FALSE,
                            'premium_amount' => 0,
                            'webservice_id'=>$get_response['webservice_id'],
                            'table'=>$get_response['table'],
                            'message' => isset($premium_response['errorlist'][0]['errtext']) ? $premium_response['errorlist'][0]['errtext'] : 'Insurer Not Reachable'
                        ]);
                    }
                    else
                    {
                        $vehicle_idv = $default_idv = $premium_response['premiumdetails']['totaliev'];
                    }
                }
                else
                {
                    return camelCase([
                        'status' => FALSE,
                        'premium_amount' => 0,
                        'webservice_id'=>$get_response['webservice_id'],
                        'table'=>$get_response['table'],
                        'message' => 'Insurer Not Reachable'
                    ]);
                }
            }

            $basic_od = 0;
            $basic_tp = 0;
            $ncb_discount = 0;
            $cpa = 0;
            $pa_unnamed_passenger = 0;
            $ll_paid_driver = 0;
            $other_discount = 0;
            $non_electrical_accessories = 0;
            $electrical_accessories = 0;
            $lpg_cng_kit_od = 0;
            $lpg_cng_kit_tp = 0;
            $tppd_discount = 0;
            $imt_23 = 0;
            $geo_extension_od_premium = 0;
            $geo_extension_tp_premium = 0;

            if (isset($premium_response['premiumsummerylist']) && ! empty($premium_response['premiumsummerylist']))
            {
                foreach ($premium_response['premiumsummerylist'] as $premium)
                {
                    switch ($premium['paramref'])
                    {
                        case 'OD': // Basic OD
                            $basic_od = $premium['od'];
                            break;

                        case 'ACT': // Basic TP
                            $basic_tp = $premium['act'];
                            break;

                        case 'PA_DFT': // CPA
                            $cpa = $premium['act'];
                            break;

                        case 'NELECACC': // Non-electrical Accessories
                            $non_electrical_accessories = $premium['od'];
                            break;

                        case 'ELECACC': // Electrical Accesssories
                            $electrical_accessories = $premium['od'];
                            break;

                        case 'PA': // PA for Unnamed Passenger
                            $pa_unnamed_passenger = $premium['act'];
                            break;

                        case 'CNG': // External LPG/CNG Kit
                            $lpg_cng_kit_od = $premium['od'];
                            $lpg_cng_kit_tp = $premium['act'];
                            break;

                        case 'LLO': // LL Paid Driver
                            $ll_paid_driver = abs($premium['act']);
                            break;

                        case 'IMT23': // IMT-23
                            $imt_23 = abs($premium['od']);
                            break;

                        case 'TPPD_RES': // TPPD Discount
                            $tppd_discount = abs($premium['act']);
                            break;

                        case 'COMMDISC': // Other Discount
                            $other_discount = abs($premium['od']);
                            break;

                        case 'GEOG': // Geographical Extention
                            $geo_extension_od_premium = abs($premium['od']);
                            $geo_extension_tp_premium = abs($premium['act']);
                            break;

                        default:
                            break;
                    }
                }
            }
            else
            {
                return camelCase([
                    'status' => FALSE,
                    'premium_amount' => 0,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'message' => isset($premium_response[0]['errtext']) ? $premium_response[0]['errtext'] : 'Insurer Not Reachable'
                ]);
            }

            $final_od_premium = $basic_od + $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od + $geo_extension_od_premium;
            $final_tp_premium = $basic_tp + $pa_unnamed_passenger + $lpg_cng_kit_tp + $ll_paid_driver + $tppd_discount + $geo_extension_tp_premium; // TPPD Discount added as we are getting basic tp value with TPPD discount deduction in response
            $ncb_discount = $final_od_premium * $requestData->applicable_ncb / 100;

            if ($is_gcv && $other_discount > 0)
            {
                $other_discount = $other_discount * 100 / 115; // Discount without IMT-23
            }

            $final_total_discount = $ncb_discount + $other_discount + $tppd_discount; // TPPD Discount added as we are getting other discounts value with TPPD discount deduction in response

            $final_net_premium = $final_od_premium + $final_tp_premium - $final_total_discount;

            if ($is_gcv)
            {
                $final_gst_amount = ($final_net_premium - $basic_tp) * 0.18 + $basic_tp * 0.12;
            }
            else
            {
                $final_gst_amount = $final_net_premium * 0.18;
            }

            $final_payable_amount = $final_net_premium + $final_gst_amount;

            $business_types = [
                'rollover' => 'Rollover',
                'newbusiness' => 'New Business',
                'breakin' => 'Break-in'
            ];
            // dd($mmv_data);
            $fuel_types = [
                'P' => 'Petrol',
                'D' => 'Diesel',
                'C' => 'CNG',
                'B' => 'Electric'
            ];

            $data_response = [
                'status' => true,
                'msg' => 'Found',
                'Data' => [
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
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $premium_type == 'third_party' || $premium_type == 'third_party_breakin' ? 'Third Party' : (in_array($premium_type, ['short_term_3', 'short_term_6', 'short_term_3_breakin', 'short_term_6_breakin']) ? 'Short Term' : 'Comprehensive'),
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'voluntary_excess' => '0',
                    'version_id' => $mmv_data->ic_version_code,
                    'selected_addon' => [],
                    'showroom_price' => $vehicle_idv,
                    'fuel_type' => $fuel_types[$mmv_data->fuel],
                    'ncb_discount' => (int)$ncb_discount > 0 ? $requestData->applicable_ncb : 0,
                    'tppd_discount' => $tppd_discount,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name' => $productData->product_sub_type_name,
                    'mmv_detail' => [
                        'manf_name' => $mmv_data->vehiclemake,
                        'model_name' => $mmv_data->vehiclemodel,
                        'version_name' => $mmv_data->vehiclesubtype,
                        'version_id' => $mmv_data->ic_version_code,
                        'seating_capacity' => (int) $mmv_data->carryingcapacity + 1,
                        'cubic_capacity' => $mmv_data->cubiccapacity,
                        'fuel_type' => $fuel_types[$mmv_data->fuel]
                    ],
                    'vehicle_register_date' => $requestData->vehicle_register_date,
                    'master_policy_id' => [
                        'policy_id' => $productData->policy_id,
                        'policy_no' => $productData->policy_no,
                        'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                        'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
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
                    'motor_manf_date' => date('d-m-Y', strtotime('01-' . $requestData->manufacture_year)),
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => '0',
                        'rto_cluster_id' => '0',
                        'car_age' => $vehicle_age,
                        'ic_vehicle_discount' => $other_discount,
                    ],
                    'basic_premium' => (float) $basic_od,
                    'deduction_of_ncb' => (float) $ncb_discount,
                    'tppd_premium_amount' => (float) $basic_tp + (float) $tppd_discount,
                    'motor_electric_accessories_value' => $electrical_accessories,
                    'motor_non_electric_accessories_value' => $non_electrical_accessories,
                    'motor_lpg_cng_kit_value' => $lpg_cng_kit_od,
                    'cover_unnamed_passenger_value' => isset($pa_unnamed_passenger) ? (float) $pa_unnamed_passenger : 0,
                    'seating_capacity' => (int) $mmv_data->carryingcapacity + 1,
                    'default_paid_driver' => $ll_paid_driver,//$llpaiddriver_premium + $llcleaner_premium,
                    'll_paid_driver_premium' => $ll_paid_driver,
                    'll_paid_conductor_premium' => 0,
                    'll_paid_cleaner_premium' => 0,
                    'GeogExtension_ODPremium'                     => $geo_extension_od_premium,
                    'GeogExtension_TPPremium'                     => $geo_extension_tp_premium,
                    'ic_vehicle_discount' => $other_discount,
                    'compulsory_pa_own_driver' => (float) $cpa,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage' => (float) $final_od_premium,
                    'cng_lpg_tp' => $lpg_cng_kit_tp,
                    'total_liability_premium' => (float) $final_tp_premium,
                    'net_premium' => (float) $final_net_premium,
                    'service_tax_amount' => (float) $final_gst_amount,
                    'service_tax' => 18,
                    'total_discount_od' => 0,
                    'add_on_premium_total' => 0,
                    'addon_premium' => 0,
                    'vehicle_lpg_cng_kit_value' => $requestData->bifuel_kit_value,
                    'quotation_no' => '',
                    'premium_amount'  => (float) $final_payable_amount,
                    'final_od_premium' => (float) $final_od_premium,
                    'final_tp_premium' => (float) $final_tp_premium,
                    'final_total_discount' => $final_total_discount,
                    'final_net_premium' => (float) $final_net_premium,
                    'final_gst_amount' => (float) $final_gst_amount,
                    'final_payable_amount' => (float) $final_payable_amount,
                    'service_data_responseerr_msg' => 'success',
                    'user_id' => $requestData->user_id,
                    'product_sub_type_id' => $productData->product_sub_type_id,
                    'user_product_journey_id' => $requestData->user_product_journey_id,
                    'business_type' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? 'Break-in' : $business_types[$requestData->business_type],
                    'service_err_code' => NULL,
                    'service_err_msg' => NULL,
                    'policyStartDate' => ($requestData->previous_policy_type == 'Third-party' && $premium_type != 'third_party') || ($requestData->previous_policy_type == 'Not sure' && $requestData->business_type != 'newbusiness') ? '' : date('d-m-Y', strtotime($policy_start_date)),
                    'policyEndDate' => date('d-m-Y', strtotime($policy_end_date)),
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
                    'add_ons_data' =>   [
                        'in_built'   => [],
                        'additional' => [
                            'zero_depreciation' => '0',
                            'road_side_assistance' => '0',
                            'imt23' => $imt_23
                        ],
                        'in_built_premium' => 0,
                        'additional_premium' => $imt_23,
                        'other_premium' => 0
                    ],
                    'applicable_addons' => [],
                    'is_cv_json_kit' => TRUE,
                    'transaction_id' => $premium_response['transactionid']
                ],
            ];

            if ($electrical_accessories == 0)
            {
                unset($data_response['Data']['motor_electric_accessories_value']);
            }

            if ($non_electrical_accessories == 0)
            {
                unset($data_response['Data']['motor_non_electric_accessories_value']);
            }

            if ($lpg_cng_kit_od == 0)
            {
                unset($data_response['Data']['motor_lpg_cng_kit_value']);
            }

            if ($lpg_cng_kit_tp == 0)
            {
                unset($data_response['Data']['cng_lpg_tp']);
            }

            if ($imt_23 > 0)
            {
                $data_response['Data']['applicable_addons'] = ['imt23'];
            }

            return camelCase($data_response);
        }
        else
        {
            return camelCase([
                'status' => FALSE,
                'premium_amount' => 0,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => isset($premium_response['errorlist'][0]['errtext']) ? $premium_response['errorlist'][0]['errtext'] : 'Insurer Not Reachable'
            ]);
        }
    }
    else
    {
        return camelCase([
            'status' => FALSE,
            'premium_amount' => 0,
            'webservice_id'=> $get_response['webservice_id'],
            'table'=> $get_response['table'],
            'message' => 'Insurer Not Reachable'
        ]);
    }
}