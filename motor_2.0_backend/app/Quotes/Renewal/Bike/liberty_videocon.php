<?php
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;

include_once app_path().'/Helpers/BikeWebServiceHelper.php';

function getRenewalQuote($enquiryId, $requestData, $productData)
{
    if(config("ENABLE_LIBERTY_RENEWAL_API") === 'Y')
    {
        if(($requestData->ownership_changed ?? '' ) == 'Y')
        {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Quotes not allowed for ownership changed vehicle',
                'request' => [
                    'message' => 'Quotes not allowed for ownership changed vehicle',
                    'requestData' => $requestData
                ]
            ];
        }
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

            if (empty($requestData->rto_code)) {
                return [
                    'status' => false,
                    'premium' => '0',
                    'message' => 'RTO not available',
                    'request'=> [
                        'rto_code' =>$requestData->rto_code,
                        'request_data' =>$requestData
                    ]
                ];
            }

            $mmv = get_mmv_details($productData, $requestData->version_id, 'liberty_videocon');

            if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
            } else {
                return  [   
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $mmv['message'],
                    'request'=>[
                        'mmv'=> $mmv,
                        'version_id'=>$requestData->version_id
                    ]
                ];          
            }

            $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

            $date1 = new DateTime($requestData->vehicle_register_date);
            $date2 = new DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
            $interval = $date1->diff($date2);
            $age = (($interval->y * 12) + $interval->m) + 1;
            $vehicle_age = floor($age / 12);

            // if ($vehicle_age > 5 && $productData->product_identifier == 'consumable') {
            //     return [
            //         'status' => false,
            //         'premium_amount' => 0,
            //         'message' => 'Consumable Package is not allowed for vehicle age greater than 5 years',
            //         'request'=> [
            //             'vehicle_age'=>$vehicle_age,
            //             'product_data'=>$productData->product_identifier
            //         ]
            //     ];
            // } elseif ($vehicle_age > 5 && $productData->product_identifier == 'engprotect') {
            //     return [
            //         'status' => false,
            //         'premium_amount' => 0,
            //         'message'  => 'Engine Protector Package is not allowed for vehicle age greater than 5 years',
            //         'request'=> [
            //             'vehicle_age'=>$vehicle_age,
            //             'product_data'=>$productData->product_identifier
            //         ]
            //     ];
            // } elseif ($vehicle_age > 5 && $productData->product_identifier == 'zerodep') {
            //     return [
            //         'status' => false,
            //         'premium_amount' => 0,
            //         'message'  => 'Zero Dep. Package is not allowed for vehicle age greater than 5 years',
            //         'request'=> [
            //             'vehicle_age'=>$vehicle_age,
            //             'product_data'=>$productData->product_identifier
            //         ]
            //     ];
            // }

            $vehicle_registration_no = explode('-', $requestData->vehicle_registration_no ?? $requestData->rto_code.'-'.substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 2).'-'.substr(str_shuffle('1234567890'), 1, 4));

            if (isset($requestData->vehicle_registration_no) && $requestData->vehicle_registration_no && !in_array(strtoupper($requestData->vehicle_registration_no), ['NEW', 'NONE'])) {
            
                $vehicle_registration_no = explode('-', $requestData->vehicle_registration_no);
                if ($vehicle_registration_no[0] == 'DL') {
                    $registration_no = RtoCodeWithOrWithoutZero($vehicle_registration_no[0].$vehicle_registration_no[1],true); 
                    $vehicle_registration_no = $registration_no.'-'.$vehicle_registration_no[2].'-'.$vehicle_registration_no[3];
                    $vehicle_registration_no = explode('-', $vehicle_registration_no);
                } else {
                    $vehicle_registration_no = $requestData->vehicle_registration_no;
                    $vehicle_registration_no = explode('-', $vehicle_registration_no);
                }
            } else {
                $vehicle_registration_no = array_merge(explode('-', RtoCodeWithOrWithoutZero($requestData->rto_code,true)), ['MG', rand(1111, 9999)]);
            }
            $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        
            $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
            
            $electrical_accessories = 'No';
            $electrical_accessories_details = '';
            $non_electrical_accessories = 'No';
            $non_electrical_accessories_details = '';
            $external_fuel_kit = 'No';
            $fuel_type = $mmv_data->fuel_type;
            $external_fuel_kit_amount = '';

            if (!empty($additional['accessories'])) {
                foreach ($additional['accessories'] as $key => $data) {
                    if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                        if ($data['sumInsured'] < 15000 || $data['sumInsured'] > 50000) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Vehicle non-electric accessories value should be between 15000 to 50000',
                                'request'=> [
                                    'accessories'=>'External Bi-Fuel Kit CNG/LPG',
                                    'amount'=>$data['sumInsured']
                                ]
                            ];
                        } else {
                            $external_fuel_kit = 'Yes';
                            $fuel_type = 'CNG';
                            $external_fuel_kit_amount = $data['sumInsured'];
                        }
                    }

                    if ($data['name'] == 'Non-Electrical Accessories') {
                        if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Vehicle non-electric accessories value should be between 10000 to 25000',
                                'request'=> [
                                    'accessories'=>'Non-Electrical Accessories',
                                    'amount'=>$data['sumInsured']
                                ]
                            ];
                        } else {
                            $non_electrical_accessories = 'Yes';
                            $non_electrical_accessories_details = [
                                [
                                    'Description'     => 'Other',
                                    'Make'            => 'Other',
                                    'Model'           => 'Other',
                                    'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                                    'SerialNo'        => '1001',
                                    'SumInsured'      => $data['sumInsured'],
                                ]
                            ];
                        }
                    }

                    if ($data['name'] == 'Electrical Accessories') {
                        if ($data['sumInsured'] < 10000 || $data['sumInsured'] > 25000) {
                            return [
                                'premium_amount' => 0,
                                'status' => false,
                                'message' => 'Vehicle electric accessories value should be between 10000 to 25000',
                                'request'=> [
                                    'accessories'=>'Electrical Accessories',
                                    'amount'=>$data['sumInsured']
                                ]
                            ];
                        } else {
                            $electrical_accessories = 'Yes';
                            $electrical_accessories_details = [
                                [
                                    'Description'     => 'Other',
                                    'Make'            => 'Other',
                                    'Model'           => 'Other',
                                    'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                                    'SerialNo'        => '1001',
                                    'SumInsured'      => $data['sumInsured'],
                                ]
                            ];
                        }
                    }
                }
            }
            $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type', 'P')
            ->first();
            $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
            $pos_name = $pos_type = $pos_code   = $pos_aadhar = $pos_pan = $pos_mobile = $imd_number = $tp_source_name = '' ;
            $imd_number = config('constants.IcConstants.liberty_videocon.IMD_NUMBER_LIBERTY_VIDEOCON_MOTOR');
            $tp_source_name = config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR');
            if ($is_pos_enabled == 'Y' && !empty($pos_data) && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') 
            {
                $pos_name   = $pos_data->agent_name;
                $pos_type   = 'POSP';
                $pos_code   = $pos_data->pan_no;
                $pos_aadhar = $pos_data->aadhar_no;
                $pos_pan    = $pos_data->pan_no;
                $pos_mobile = $pos_data->agent_mobile;
                $imd_number = config('constants.IcConstants.liberty_videocon.pos.IMD_NUMBER_LIBERTY_VIDEOCON_BIKE_POS');
                $tp_source_name = config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR_POS');
            }
            $fetch_url = config('constants.IcConstants.liberty.MOTOR_FETCH_RENEWAL_URL');
            $renewal_fetch_array =
            [
                "QuotationNumber"=> config('constants.IcConstants.liberty_videocon.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
                "RegNo1"=> "",
                "RegNo2"=> "",
                "RegNo3"=> "",
                "RegNo4"=> "",
                "EngineNumber"=> "",
                "ChassisNumber"=> "",
                "IMDNumber"=> "",
                "PreviousPolicyNumber"=> $user_proposal->previous_policy_number,
                "TPSourceName"=> $tp_source_name
            ];
            $get_response = getWsData($fetch_url,$renewal_fetch_array, 'liberty_videocon',
            [
                'enquiryId'         => $enquiryId,
                'requestMethod'     => 'post',
                'productName'       => $productData->product_name,
                'company'           => 'liberty_videocon',
                'section'           => $productData->product_sub_type_code,
                'method'            => 'Fetch Policy Details',
                'transaction_type'  => 'quote',
            ]);
           
            $is_anti_theft_device_certified_by_arai = 'false';
            $is_voluntary_access = 'No';
            $cover_pa_paid_driver_amt = $voluntary_excess_amt = 0;  
        $data = $get_response['response'];
        $response_data = json_decode($data);
        if (!empty($response_data)) 
        {
            $premium_request = [
            'QuickQuoteNumber' => config('constants.IcConstants.liberty_videocon.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
            'IMDNumber' => $response_data->IMDNumber, //$imd_number,
            'AgentCode' => '',
            'TPSourceName' => $tp_source_name,
            'ProductCode' => $response_data->ProductCode,
            'IsFullQuote' => 'false',// ($is_breakin ? 'false' : 'true'),
            'BusinessType' => $response_data->BusinessType,
            'MakeCode' => $response_data->MakeCode,
            'ModelCode' => $response_data->ModelCode,
            'ManfMonth' => $response_data->ManfMonth,
            'ManfYear' => $response_data->ManfYear,
            'RtoCode' => $response_data->RtoCode, //$requestData->rto_code,
            'RegNo1' =>$response_data->RegNo1,
            'RegNo2' => $response_data->RegNo2,
            'RegNo3' => $response_data->RegNo3,
            'RegNo4' => $response_data->RegNo4,
            'DeliveryDate' => $response_data->DeliveryDate,
            'RegistrationDate' => $response_data->RegistrationDate,
            'VehicleIDV' => $response_data->VehicleIDV,
            'PolicyStartDate' => $response_data->PolicyStartDate,
            'PolicyEndDate' => $response_data->PolicyEndDate,
            'PolicyTenure' => $response_data->PolicyTenure,
            'GeographicalExtn' => 'No',
            'GeographicalExtnList' => '',
            'DrivingTuition' => '',
            'VintageCar' => '',
            'LegalLiabilityToPaidDriver' => $response_data->LegalLiabilityToPaidDriver ,
            'NoOfPassengerForLLToPaidDriver' => $response_data->NoOfPassengerForLLToPaidDriver ,
            'LegalliabilityToEmployee' => $response_data->LegalliabilityToEmployee ,
            'NoOfPassengerForLLToEmployee' => $response_data->NoOfPassengerForLLToEmployee ,
            'PAUnnnamed' => $response_data->PAUnnnamed,
            'NoOfPerunnamed' => $response_data->NoOfPerunnamed,
            'UnnamedPASI' => $response_data->UnnamedPASI,
            'PAOwnerDriver' => $response_data->PAOwnerDriver,
            'PAOwnerDriverTenure' => $response_data->PAOwnerDriverTenure,
            'LimtedtoOwnPremise' => '',
            'ElectricalAccessories' => $response_data->ElectricalAccessories,
            'lstAccessories' => $electrical_accessories_details,
            'NonElectricalAccessories' => $response_data->NonElectricalAccessories,
            'lstNonElecAccessories' => $non_electrical_accessories_details,
            'ExternalFuelKit' => $external_fuel_kit,
            'FuelType' => $fuel_type,
            'FuelSI' => $external_fuel_kit_amount,
            'PANamed' => 'No',
            'NoOfPernamed' => '0',
            'NamedPASI' => '0',
            'PAToPaidDriver'          => $response_data->PAToPaidDriver ,
            'NoOfPaidDriverPassenger' => $response_data->NoOfPaidDriverPassenger,
            'PAToPaidDriverSI' => $cover_pa_paid_driver_amt,
            'AAIMembship' => 'No',
            'AAIMembshipNumber' => $response_data->AAIMembshipNumber,
            'AAIAssociationCode' => $response_data->AAIAssociationCode,
            'AAIAssociationName' => $response_data->AAIAssociationName,
            'AAIMembshipExpiryDate' => $response_data->AAIMembshipExpiryDate,
            'AntiTheftDevice'           => $response_data->AntiTheftDevice ,
            'IsAntiTheftDeviceCertifiedByARAI' => $is_anti_theft_device_certified_by_arai,
            'TPPDDiscount' => $response_data->TPPDDiscount,
            'ForeignEmbassy' => '',
            'VoluntaryExcess' => $is_voluntary_access,
            'VoluntaryExcessAmt' => $voluntary_excess_amt,
            'NoNomineeDetails' => $requestData->vehicle_owner_type == 'I' ? 'false' : 'true',
            'NomineeFirstName' => 'john',
            'NomineelastName' => 'doe',
            'NomineeRelationship' => 'brother',
            'OtherRelation' => '',
            'IsMinor' => 'false',
            'RepFirstName' => '',
            'RepLastName' => '',
            'RepRelationWithMinor' => '',
            'RepOtherRelation' => '',
            'NoPreviousPolicyHistory'   => $response_data->NoPreviousPolicyHistory,
            'IsNilDepOptedInPrevPolicy' => $response_data->IsNilDepOptedInPrevPolicy,
            'PreviousPolicyInsurerName' => $response_data->PreviousPolicyInsurerName,
            'PreviousPolicyType'        => ($response_data->PreviousPolicyType),#LIABILITYPOLICY
            'PreviousPolicyStartDate'   => ($response_data->PreviousPolicyStartDate),
            'PreviousPolicyEndDate'     => ($response_data->PreviousPolicyEndDate),
            'PreviousPolicyNumber'      => ($response_data->PreviousPolicyNumber),
            'PreviousYearNCBPercentage' => ($response_data->PreviousYearNCBPercentage),
            'ClaimAmount'               => $response_data->ClaimAmount,
            'NoOfClaims'                => $response_data->NoOfClaims,
            'PreviousPolicyTenure'      => $response_data->PreviousPolicyTenure,
            'IsInspectionDone'          => $response_data->IsInspectionDone,
            'InspectionDoneByWhom'      => $response_data->InspectionDoneByWhom,
            'ReportDate'                => $response_data->ReportDate,
            'InspectionDate'            => $response_data->InspectionDate,
            'ConsumableCover'           => $response_data->ConsumableCover,
            'DepreciationCover'         => $response_data->DepreciationCover,
            'RoadSideAsstCover'         => $response_data->RoadSideAsstCover,
            'GAPCover'                  => $response_data->GAPCover,
            'GAPCoverSI'                => '0',
            'EngineSafeCover'           => $response_data->EngineSafeCover,
            'KeyLossCover'              => $response_data->KeyLossCover,  
            'KeyLossCoverSI'            => $response_data->KeyLossCoverSI,
            'PassengerAsstCover'        => $response_data->PassengerAsstCover,
            'EngineNo'                  => $response_data->EngineNo,
            'ChassisNo'                 => $response_data->ChassisNo,
            'BuyerState'                => $response_data->BuyerState,
            'POSPName'                  => $pos_name,
            'POSPType'                  => $pos_type,
            'POSPCode'                  => $pos_code,
            'POSPAadhar'                => $pos_aadhar,
            'POSPPAN'                   => $pos_pan,
            'POSPMobileNumber'          => $pos_mobile,
            
            ];

            // if($noPreviousData)
            // {
            //     $premium_request['NoPreviousPolicyHistory']   = 'true';
            //     $premium_request['IsNilDepOptedInPrevPolicy'] = '';
            //     $premium_request['PreviousPolicyInsurerName'] = '';
            //     $premium_request['PreviousPolicyType']        = '';
            //     $premium_request['PreviousPolicyStartDate']   = '';
            //     $premium_request['PreviousPolicyEndDate']     = '';
            //     $premium_request['PreviousYearNCBPercentage'] = '';
            // }

            // if (!$is_new && !$is_liability) {#for tp not required
            //     $premium_request['lstPreviousAddonDetails'] = [
            //         [
            //             'IsConsumableOptedInPrevPolicy' => $consumables_cover == 'Yes' ? 'true' : 'false',
            //             'IsEngineSafeOptedInPrevPolicy' => $engine_protection_cover == 'Yes' ? 'true' : 'false',
            //             'IsGAPCoverOptedInPrevPolicy' => $return_to_invoice_cover == 'Yes' ? 'true' : 'false',
            //             'IsKeyLossOptedInPrevPolicy' => $key_and_lock_protection_cover == 'Yes' ? 'true' : 'false',
            //             'IsNilDepreicationOptedInPrevPolicy' => $zero_depreciation_cover == 'Yes' ? 'true' : 'false',
            //             'IsPassengerAsstOptedInPrevPolicy' => 'false',
            //             'IsRoadSideAsstOptedInPrevPolicy' => $road_side_assistance_cover == 'Yes' ? 'true' : 'false'
            //         ]
            //     ];
            // }


            $data = getWsData(config('constants.IcConstants.liberty_videocon.END_POINT_URL_LIBERTY_VIDEOCON_PREMIUM_CALCULATION'), $premium_request, 'liberty_videocon', [
                'enquiryId' => $enquiryId,
                'requestMethod' =>'post',
                'productName'  => $productData->product_name,
                'company'  => 'liberty_videocon',
                'section' => $productData->product_sub_type_code,
                'method' =>'Premium Calculation',
                'transaction_type' => 'quote',
                'headers' => [
                    'Content-Type' => 'Application/json'
                ]
            ]);

            if ($data['response']) {
            if (str_contains($data['response'], '503 Service Temporarily Unavailable'))
            {
                return [
                    'webservice_id' => $data['webservice_id'],
                    'table' => $data['table'],
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => '503 Service Temporarily Unavailable'
                ];
            }
            $premium_response = json_decode($data['response'], TRUE);
            if ($premium_response !== null && empty($premium_response['ErrorText'])) {
                $vehicle_idv = round($premium_response['CurrentIDV']);
                $min_idv = ceil($premium_response['MinIDV']);
                $max_idv = floor($premium_response['MaxIDV']);
                $default_idv = round($premium_response['CurrentIDV']);

                if ($requestData->is_idv_changed == 'Y') {
                    if ($requestData->edit_idv >= $max_idv) {
                        $premium_request['VehicleIDV'] = $max_idv;
                    } elseif ($requestData->edit_idv <= $min_idv) {
                        $premium_request['VehicleIDV'] = $min_idv;
                    } else {
                        $premium_request['VehicleIDV'] = $requestData->edit_idv;
                    }
                } else {
                    //$premium_request['VehicleIDV'] = $min_idv;
                    $getIdvSetting = getCommonConfig('idv_settings');
                    switch ($getIdvSetting) {
                        case 'default':
                            $premium_request['VehicleIDV'] = $vehicle_idv;
                            break;
                        case 'min_idv':
                            $premium_request['VehicleIDV'] = $min_idv;
                            break;
                        case 'max_idv':
                            $premium_request['VehicleIDV'] = $max_idv;
                            break;
                        default:
                            $premium_request['VehicleIDV'] = $min_idv;
                            break;
                    }
                }
            
                if (!empty($data['response'])) {
                    $premium_response = json_decode($data['response'], TRUE);

                    if (trim($premium_response['ErrorText']) == "") {
                        $vehicle_idv = round($premium_response['CurrentIDV']);
                        $default_idv = round($premium_response['CurrentIDV']);
                    } else {
                        return [
                            'webservice_id' => $data['webservice_id'],
                            'table' => $data['table'],
                            'status' => false,
                            'premium_amount' => 0,
                            'message' => preg_replace('/\d+.\z/', '', $premium_response['ErrorText'])
                        ];
                    }
                } else {
                    return [
                        'webservice_id' => $data['webservice_id'],
                        'table' => $data['table'],
                        'premium_amount' => 0,
                        'status' => false,
                        'message' => 'Insurer not reachable'
                    ];
                }

                $geog_Extension_OD_Premium = 0;
                $geog_Extension_TP_Premium = 0;
                $llpaiddriver_premium = round($premium_response['LegalliabilityToPaidDriverValue'] ?? 0);
                $cover_pa_owner_driver_premium = round($premium_response['PAToOwnerDrivervalue'] ?? 0);
                $cover_pa_paid_driver_premium = round($premium_response['PatoPaidDrivervalue'] ?? 0);
                $cover_pa_unnamed_passenger_premium = round($premium_response['PAToUnnmaedPassengerValue'] ?? 0);
                $voluntary_excess = round($premium_response['VoluntaryExcessValue'] ?? 0);
                $anti_theft = $premium_response['AntiTheftDiscountValue'] ?? 0;
                $ic_vehicle_discount = (round($premium_response['Loading'] ?? 0) + round($premium_response['Discount'] ?? 0)) ;
                $ncb_discount = round($premium_response['DisplayNCBDiscountvalue'] ?? 0);
                $od = round($premium_response['BasicODPremium'] ?? 0);
                $tppd = round($premium_response['BasicTPPremium'] ?? 0);
                $cng_lpg = round($premium_response['FuelKitValueODpremium'] ?? 0);
                $cng_lpg_tp = round($premium_response['FuelKitValueTPpremium'] ?? 0);
                $electrical_accessories_amt = round($premium_response['ElectricalAccessoriesValue'] ?? 0);
                $non_electrical_accessories_amt = round($premium_response['NonElectricalAccessoriesValue'] ?? 0);
    
                $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
                $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium;
                $final_total_discount = $ncb_discount + $voluntary_excess + $anti_theft + $ic_vehicle_discount;
                $final_net_premium = round($final_od_premium + $final_tp_premium - $final_total_discount);
                $final_gst_amount = round($final_net_premium * 0.18);
                $final_payable_amount  = $final_net_premium + $final_gst_amount;
                $zeroDepreciation = ($premium_response['NilDepValue']) ?? 0;
                $engineProtect = ($premium_response['EngineCoverValue']) ?? 0;
                $consumables = ($premium_response['ConsumableCoverValue']) ?? 0;
                $returnToInvoice = ($premium_response['GAPCoverValue']) ?? 0;
                $roadSideAssistance = ($premium_response['RoadAssistCoverValue']) ?? 0;
                $addons_data = [];
                $addons_data['other'] = [];
                if (round($premium_response['PassengerAssistCoverValue']) > 0) {
                    $addons_data['other'] = [
                        'passenger_assist_cover' => round($premium_response['PassengerAssistCoverValue'])
                    ];
                }
                $addons_list = [
                    'zeroDepreciation'     => round($zeroDepreciation),
                    'engineProtector'      => round($engineProtect),
                    'returnToInvoice'     => round($returnToInvoice),
                    'consumables'           => round($consumables),
                    'roadSideAssistance'  => round($roadSideAssistance)
                ];
                $in_bult = [];
                $other = '';
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
                  foreach ($addons_data['other'] as $key1 => $value1) 
                  {
                    if($value1 > 0)
                    {
                      $other[$key1] =  $value1;
                    }
                  }

                $addons_data = [
                    'in_built'   => $in_bult,
                    'additional' => [
                        'zero_depreciation' => round($premium_response['NilDepValue']),
                        'road_side_assistance' => round($premium_response['RoadAssistCoverValue']),
                    ]
                ];
                $addons_data['other'] = $other;
                if (round($premium_response['PassengerAssistCoverValue']) > 0) {
                    $addons_data['other'] = [
                        'passenger_assist_cover' => round($premium_response['PassengerAssistCoverValue'])
                    ];
                }

                $addons_data['in_built_premium'] = array_sum($addons_data['in_built']);
                $addons_data['additional_premium'] = array_sum($addons_data['additional']);
                $addons_data['other_premium'] = $addons_data['other']['passenger_assist_cover'] ?? 0;

                $applicable_addons = [
                    'zeroDepreciation',
                    'roadSideAssistance',
                    // 'engineProtector',
                    // 'consumables',
                    // 'returnToInvoice'
                ];

                if (!round($premium_response['RoadAssistCoverValue']) > 0)
                {
                    array_splice($applicable_addons, array_search('roadSideAssistance', $applicable_addons), 1);
                }
                if (!round($premium_response['NilDepValue']) > 0)
                {
                    array_splice($applicable_addons, array_search('zeroDepreciation', $applicable_addons), 1);
                }
                if (($requestData->business_type != 'newbusiness') && (strtolower($requestData->previous_policy_expiry_date) != 'new'))
                {
                    $date_difference = get_date_diff('day', $requestData->previous_policy_expiry_date);
                    if($date_difference > 90){
                        $requestData->applicable_ncb = 0;
                    }
                }
                $previous_policy_start_Date=$response_data->PreviousPolicyStartDate;
                $previous_policy_end_Date=$response_data->PreviousPolicyEndDate;
                $Previous_policy_Number=$response_data->PreviousPolicyNumber;
                $Claim_Amount=$response_data->ClaimAmount;
                $noofCalims=$response_data->NoOfClaims;
                $motor_manf_date='01-'.$response_data->ManfMonth.'-'.$response_data->ManfYear;

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
                $fm_policystartdate = DateTime::createFromFormat('d/m/Y H:i:s', $response_data->PolicyStartDate);
                $fm_policyenddate = DateTime::createFromFormat('d/m/Y H:i:s', $response_data->PolicyEndDate);
                $policy_start_date = $fm_policystartdate->format('d-m-Y');
                $policy_end_date = $fm_policyenddate->format('d-m-Y');
                $idv=$response_data->VehicleIDV ?? 0;
                $voluntaryDiscount=0;
                $data = [
                'status' => true,
                'msg' => 'Found',
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'Data' => 
                [
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
                    'voluntary_excess'          => (int) $voluntaryDiscount ?? 0,
                    'version_id'                => $mmv_data->ic_version_code,                
                    'fuel_type'                 => $mmv_data->fuel_type,
                    'ncb_discount'              => $requestData->applicable_ncb,
                    'company_name'              => $productData->company_name,
                    'company_logo'              => url(config('constants.motorConstant.logos').$productData->logo),
                    'product_name'              => $productData->product_sub_type_name,
                    'mmv_detail' => [
                        'manf_name'             => $mmv_data->manufacturer,
                        'model_name'            => $mmv_data->vehicle_model,
                        'version_name'          => $mmv_data->variant,
                        'fuel_type'             => $mmv_data->fuel_type,
                        'seating_capacity'      => $mmv_data->seating_capacity,
                        'carrying_capacity'     => $mmv_data->carrying_capacity,
                        'cubic_capacity'        => $mmv_data->cubic_capacity,
                        'gross_vehicle_weight'  => $mmv_data->gross_weight ?? 1,
                        'vehicle_type'          => $mmv_data->vehicle_class_desc,
                    ],
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
                    'vehicleDiscountValues' => [
                        'master_policy_id' => $productData->policy_id,
                        'product_sub_type_id' => $productData->product_sub_type_id,
                        'segment_id' => 0,
                        'rto_cluster_id' => 0,
                        'car_age' => $vehicle_age,
                        'ic_vehicle_discount' => $ic_vehicle_discount,
                    ],
                    'motor_manf_date'           => $motor_manf_date,
                    'ic_vehicle_discount' => $ic_vehicle_discount,
                    'basic_premium'             => (int) $od,
                    'deduction_of_ncb'          => (int) $ncb_discount,
                    'tppd_premium_amount'       => (int) $tppd,
                    'tppd_discount'             => (int) 0,
                    'total_loading_amount'      => 0,
                    'motor_electric_accessories_value' => (int) $electrical_accessories_amt,
                    'motor_non_electric_accessories_value' => (int) $non_electrical_accessories_amt,
                    'motor_lpg_cng_kit_value'   => (int)$cng_lpg,
                    'cover_unnamed_passenger_value' => (int) $cover_pa_unnamed_passenger_premium,
                    'seating_capacity'          => $mmv_data->seating_capacity,
                    'default_paid_driver'       => (int) $llpaiddriver_premium,
                    'motor_additional_paid_driver' => 0,
                    'compulsory_pa_own_driver'  => (int) $cover_pa_owner_driver_premium,
                    'cpa_allowed'               => (int) $cover_pa_owner_driver_premium > 0 ? true : false,
                    'total_accessories_amount(net_od_premium)' => 0,
                    'total_own_damage'          => (int) $final_od_premium,
                    'cng_lpg_tp'                => (int) $cng_lpg_tp,
                    'total_liability_premium'   => (int) $final_tp_premium,
                    'net_premium'               => (int) $final_net_premium,
                    'service_tax_amount'        => (int) $final_gst_amount,
                    'service_tax'               => 18,
                    'total_discount_od'         => 0,
                    'add_on_premium_total'      => 0,
                    'addon_premium'             => 0,
                    'vehicle_lpg_cng_kit_value' => (int)$requestData->bifuel_kit_value,
                    'premium_amount'            => (int) $final_payable_amount,
                    'antitheft_discount'        => (int) $anti_theft,
                    'final_od_premium'          => (int) $final_od_premium,
                    'final_tp_premium'          => (int) $final_tp_premium,
                    'final_total_discount'      => round($final_total_discount),
                    'final_net_premium'         => (int) $final_net_premium,
                    'final_gst_amount'          => (int) $final_gst_amount,
                    'final_payable_amount'      => (int) $final_payable_amount,
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

                    return camelCase($data); 
            }
            else
            {
                return [
                    'status' => false,
                    'premium' => '0',
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'message' => 'error in premium calculation'
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
                    'message' => !empty($response_data->ErrorText ?? null) ? $response_data->ErrorText : 'Renewal Fetch APi Hit failed.'
                ];
            }


        }

    }
    else
    {
        return [
            'status' => false,
            'premium' => '0',
            'message' => 'No quotes for renewal liberty 2w'
        ];
    }
}