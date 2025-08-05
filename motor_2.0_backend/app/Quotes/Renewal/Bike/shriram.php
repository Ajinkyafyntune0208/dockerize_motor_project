<?php

use App\Models\SelectedAddons;
use Illuminate\Support\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\UserProposal;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';
function getRenewalQuote($enquiryId, $requestData, $productData)
{
    $mmv = get_mmv_details($productData,$requestData->version_id,'shriram');
     
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
            'request'=>[
                'mmv'=> $mmv,
                'version_id'=>$requestData->version_id
             ]
        ];
    }
    $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);
    if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle Not Mapped',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    } else if ($mmv_data->ic_version_code == 'DNE') {
        return [
            'premium_amount' => 0,
            'status' => false,
            'message' => 'Vehicle code does not exist with Insurance company',
            'request'=>[
                'mmv'=> $mmv_data,
                'version_id'=>$requestData->version_id
             ]
        ];
    } 
    else 
    {
        $rto_code = $requestData->rto_code;
        // Re-arrange for Delhi RTO code - start 
        $rto_code = explode('-', $rto_code);
        if ((int)$rto_code[1] < 10) {
            $rto_code[1] = '0'.(int)$rto_code[1];
        }
        $rto_code = implode('-', $rto_code);
        $user_proposal = UserProposal::where('user_product_journey_id', $enquiryId)->first();
        $inputArray = [
                'EmailId'       =>   $user_proposal['email'],
                "MobileNo"      =>   $user_proposal['mobile_number'],
                "PolicyNumber"  =>   $user_proposal['previous_policy_number'],
                "VehicleRegno"  =>   $user_proposal['vehicale_registration_number']
        ];
        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                'Username' => config('constants.IcConstants.shriram.SHRIRAM_USERNAME'),
                'Password' => config('constants.IcConstants.shriram.SHRIRAM_PASSWORD'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'requestMethod' => 'post',
            'requestType' => 'json',
            'section' => 'Taxi',
            'method' => 'Proposal Submit',
            'transaction_type' => 'proposal',
        ];
        $url = 'https://novauat.shriramgi.com/UATWebAggrNAPI/PolicyGeneration.svc/RestService/GetRenewalDetailsResult';
        //$response = getWsData(config('constants.IcConstants.shriram.SHRIRAM_PROPOSAL_SUBMIT_URL'), $inputArray, $request['companyAlias'], $additional_data);
        $get_response = getWsData($url, $inputArray, 'shriram', $additional_data);
        $response = $get_response['response'];
//        $response = '{
//            "MessageResult": null,
//            "ERROR_CODE": "0",
//            "ERROR_DESC": "Successful Completion",
//            "RenewalDtlList": [
//                {
//                    "AddSI": "",
//                    "AddSIeleacc": "",
//                    "AddSInoneleacc": "",
//                    "Address1": "SADHRANA POST GARHI HARSARU",
//                    "Address2": "GARHI HARSALU",
//                    "Address3": "",
//                    "Bangladesh": "0",
//                    "Bhutan": "0",
//                    "BrokerExecutive": "",
//                    "CategoryCode": "",
//                    "CategoryDesc": "",
//                    "ChassisNum": "ME4JF50CBJ7080405",
//                    "CityCode": "GURGAON",
//                    "CityName": "GURGAON",
//                    "ClmSts": "",
//                    "Collies": "",
//                    "CoverNoteDate": "",
//                    "CoverNoteNo": "",
//                    "CreditNo": "",
//                    "CustCode": "AG0000000080",
//                    "CustName": "",
//                    "DiscountValue": "50",
//                    "District": "",
//                    "DocketSubmissionDate": "3/4/2022 5:51:37 PM",
//                    "EmailId": "gopi.p@novactech.in",
//                    "EngineNum": "JF50E79080419",
//                    "Executive": "",
//                    "FinCategory": "",
//                    "FinName": "",
//                    "Fitness_validupto": "",
//                    "Fitnessno": "",
//                    "GetRenewCoverDtl": [
//                        {
//                            "CoverDesc": "Basic OD Premium",
//                            "Premium": "545.89"
//                        },
//                        {
//                            "CoverDesc": "De-Tariff Discount",
//                            "Premium": "-272.95"
//                        },
//                        {
//                            "CoverDesc": "NCB Discount ",
//                            "Premium": "-122.82"
//                        },
//                        {
//                            "CoverDesc": "OD Total",
//                            "Premium": "150.12"
//                        },
//                        {
//                            "CoverDesc": "Basic TP Premium",
//                            "Premium": "752.00"
//                        },
//                        {
//                            "CoverDesc": "GR36A-PA FOR OWNER DRIVER",
//                            "Premium": "315.00"
//                        },
//                        {
//                            "CoverDesc": "Legal Liability To Employees",
//                            "Premium": "50.00"
//                        },
//                        {
//                            "CoverDesc": "TP Total",
//                            "Premium": "1117.00"
//                        },
//                        {
//                            "CoverDesc": "Total Premium",
//                            "Premium": "1267.00"
//                        },
//                        {
//                            "CoverDesc": "IGST(0.00%)",
//                            "Premium": "0.00"
//                        },
//                        {
//                            "CoverDesc": "SGST/UTGST(9.00%)",
//                            "Premium": "114.00"
//                        },
//                        {
//                            "CoverDesc": "CGST(9.00%)",
//                            "Premium": "114.00"
//                        },
//                        {
//                            "CoverDesc": "Total Amount",
//                            "Premium": "1495.00"
//                        }
//                    ],
//                    "Govtveh": "NO",
//                    "Gvw": "0",
//                    "Imt23": "",
//                    "Imt44": "",
//                    "InsuredName": "SHYAM SUNDER",
//                    "InsuredPrefix": "Mr.",
//                    "Itdtppd": "",
//                    "Maldives": "0",
//                    "ManufYear": "2018",
//                    "MobileNum": "9962113331",
//                    "ModeOfPayment": "",
//                    "NCBYear": "",
//                    "NcbPercentage": "",
//                    "Nepal": "0",
//                    "NewProposalNo": "102015/31/22/P/0018420",
//                    "Nfpp": "",
//                    "Nfppemp": "",
//                    "NilDep": "",
//                    "NomineeAge": "27",
//                    "NomineeName": "Parmila",
//                    "NomineeRelship": "Spouse",
//                    "Noofllemp": "1",
//                    "Nooftrailor": "",
//                    "OutbuiltValue": "",
//                    "PaCover": "",
//                    "PaCoverDesc": "",
//                    "Pakistan": "0",
//                    "PccVehDesc": "0",
//                    "PccvVehType": "0",
//                    "Permit_type": "",
//                    "Permit_validupto": "",
//                    "Permitno": "",
//                    "PinCode": "123505",
//                    "PolEndDate": "3/9/2023 11:59:00 PM",
//                    "PolFromDate": "3/10/2022 12:00:00 AM",
//                    "PolType": "MOT-PLT-001",
//                    "PolTypeName": "MOT-PLT-001",
//                    "PolicyTenure": "1",
//                    "ProdDesc": "",
//                    "ProposalSysId": "9292479",
//                    "ProposalType": "RENEWAL OF SGI",
//                    "RegDate": "4/3/2018 12:00:00 AM",
//                    "RegNo": "HR 26 DM 3005",
//                    "RenewalPremiumAmount": "1495",
//                    "RtoCode": "HR-26",
//                    "RtoName": "RTO Gurgaon",
//                    "RtoZone": "B",
//                    "Rtocity": "",
//                    "SplDiscount": "0",
//                    "Srilanka": "0",
//                    "StateCode": "HR",
//                    "StateDesc": "HR",
//                    "Towingvehtype": "",
//                    "TowingvehtypeDesc": "",
//                    "TransferOwner": "0",
//                    "VehBodyDesc": "SCOOTY",
//                    "VehCC": "110",
//                    "VehCode": "M_15223",
//                    "VehFuelDesc": "PETROL",
//                    "VehIdv": "32571",
//                    "VehManuDesc": "HONDA",
//                    "VehModelDesc": "ACTIVA 4G BS4",
//                    "VehModelId": "",
//                    "VehSC": "1",
//                    "VhManuId": "HONDA",
//                    "VoluntaryExcess": "",
//                    "ll_conductor": "",
//                    "llcleaner": "",
//                    "lldriver": ""
//                }
//            ]
//        }';
        $response = json_decode($response, true);
        if(empty($response)){
            return response()->json([
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'msg' => 'Insurer Not Reachable.'
            ]);
        }
        if($response['ERROR_CODE'] == 0 && $response['ERROR_DESC'] == 'Successful Completion')
        {            
            $reponse_data = $response['RenewalDtlList'][0];
            $premium_lists = $response['RenewalDtlList'][0]['GetRenewCoverDtl'];
            
            $igst                   = $anti_theft = $other_discount = 
            $rsapremium             = $pa_paid_driver = $zero_dep_amount = 
            $ncb_discount           = $tppd = $final_tp_premium = 
            $final_od_premium       = $final_net_premium =
            $final_payable_amount   = $basic_od = $electrical_accessories = 
            $lpg_cng_tp             = $lpg_cng = $non_electrical_accessories = $tppd_discount=
            $pa_owner               = $voluntary_excess = $pa_unnamed =  
            $ll_paid_driver         = $engine_protection = $consumables_cover = $return_to_invoice= 0;      
            $final_gst_amount       = $vehicle_in_90_days = 0;
            
            foreach ($premium_lists as $key => $premium_list) {
                if($premium_list['CoverDesc'] == 'Basic OD Premium')
                {
                  $basic_od = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'De-Tariff Discount')
                {
                  $other_discount = abs($premium_list['Premium']);
                }
                else if($premium_list['CoverDesc'] == 'NCB Discount')
                {
                  $ncb_discount = abs($premium_list['Premium']);
                }
                else if($premium_list['CoverDesc'] == 'OD Total')
                {
                  $OD_Total = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'Basic TP Premium')
                {
                  $Basic_TP_Premium = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'GR36A-PA FOR OWNER DRIVER')
                {
                  $pa_owner = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'Legal Liability To Employees')
                {
                  $Legal_Liability_To_Employees = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'TP Total')
                {
                  $final_tp_premium = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'Total Premium')
                {
                  $final_net_premium = $premium_list['Premium'];
                }
                else if($premium_list['CoverDesc'] == 'Total Amount')
                {
                  $final_payable_amount = $premium_list['Premium'];
                }
            }
            //$policy_start_date = $reponse_data['PolFromDate'];
            //die;
            $policy_start_date = Carbon::parse($reponse_data['PolFromDate'])->format('d-m-Y');
            $policy_end_date = Carbon::parse($reponse_data['PolEndDate'])->format('d-m-Y');
            $previous_end_date =  Carbon::parse($reponse_data['PolFromDate'])->subDay(1)->format('d-m-Y');
            
            $idv = $reponse_data['VehIdv']; 

            //$final_tp_premium = $final_tp_premium - ($pa_owner) - $lpg_cng_tp - $pa_paid_driver - $ll_paid_driver;
            $final_tp_premium = $final_tp_premium;
            

            $final_total_discount = $ncb_discount + $other_discount + $voluntary_excess + $tppd_discount;

            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;
            $final_gst_amount = $final_payable_amount - $final_net_premium;
            $add_ons = [
                    'in_built' => [],
                    'additional' => [
                        'zero_depreciation'     => 0,
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => 0,
                        'consumables'           => 0,
                        'return_to_invoice'     => round($return_to_invoice),
                    ],
                    'other' => []
                ];
                //array_push($applicable_addons, "zeroDepreciation");
            $applicable_addons = [];
            $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
            $add_ons['additional_premium'] = array_sum($add_ons['additional']);
            $add_ons['other_premium'] = array_sum($add_ons['other']);
            
            $data_response = [
                'status' => true,
                'msg' => 'Found',
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'Data' => [
                    'idv' => (int) $idv,
                    'min_idv' => (int) $idv,
                    'max_idv' => (int) $idv,
                    'vehicle_idv' => (int) $idv,
                    //'qdata' => null,
                    'is_renewal' => 'Y',
                    'pp_enddate' => $previous_end_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => 'Comprehensive',
                    'business_type' => 'rollover',
                    'cover_type' => '1YC',
                    //'hypothecation' => '',
                    //'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'rto_no' => $rto_code,

                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'showroom_price' => 0,
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                    'product_name' => $productData->product_sub_type_name,
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
                        'segment_id' => '0',
                        'rto_cluster_id' => '0',
                        'bike_age' => 2, //$bike_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => round(abs($other_discount)),
                    ],
                    'ic_vehicle_discount' => round(abs($other_discount)),
                    'basic_premium' => round($basic_od),
                    'motor_electric_accessories_value' => round($electrical_accessories),
                    'motor_non_electric_accessories_value' => round($non_electrical_accessories),
                    'motor_lpg_cng_kit_value' => round($lpg_cng),
                    'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                    'total_own_damage' => round($final_od_premium),
                    'tppd_premium_amount' => ($requestData->business_type== 'newbusiness') ? round($tppd * 5): round($tppd),
                    'tppd_discount' => $tppd_discount,
                    'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                    'cover_unnamed_passenger_value' => $pa_unnamed,
                    'default_paid_driver' => $ll_paid_driver,
                    'motor_additional_paid_driver' => round($pa_paid_driver),
                    'cng_lpg_tp' => round($lpg_cng_tp),
                    'seating_capacity' => $mmv_data->veh_seat_cap,
                    'deduction_of_ncb' => round(abs($ncb_discount)),
                    'antitheft_discount' => '',
                    'aai_discount' => '', //$automobile_association,
                    'voluntary_excess' => $voluntary_excess,
                    'other_discount' => round(abs($other_discount)),
                    'total_liability_premium' => round($final_tp_premium),
                    'net_premium' => round($final_net_premium),
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

                    'add_ons_data'      => $add_ons,
                    'applicable_addons' => $applicable_addons,
                    'final_od_premium'  => round($final_od_premium),
                    'final_tp_premium'  => round($final_tp_premium),
                    'final_total_discount' => round(abs($final_total_discount)),
                    'final_net_premium' => round($final_net_premium),
                    'final_gst_amount'  => round($final_gst_amount),
                    'final_payable_amount' => round($final_payable_amount),
                    'mmv_detail'    => [
                        'manf_name'     => $mmv_data->manf,
                        'model_name'    => $mmv_data->model_desc,
                        'version_name'  => '',
                        'fuel_type'     => $mmv_data->fuel,
                        'seating_capacity' => $mmv_data->veh_seat_cap,
                        'carrying_capacity' => $mmv_data->veh_seat_cap,
                        'cubic_capacity' => $mmv_data->veh_cc,
                        'gross_vehicle_weight' => '',
                        'vehicle_type'  => '',
                    ],
                ],
            ];
            return camelCase($data_response);
        }
        else
        {
            return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'message' => $response['ERROR_DESC'] != NULL ? $response['ERROR_DESC'] : 'No Data Found'
            ];           
        }
        
       
        // zero depriciation validation
        if ($bike_age > 4 && $productData->zero_dep == '0') {
            return [
                'premium_amount' => 0,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'status' => true,
                'message' => 'Zero dep is not allowed for vehicle age greater than 4 years',
                'request'=>[
                    'bike_age'=>$bike_age,
                    'productData'=>$productData->zero_dep
                ]
            ];
        }

        $rto_code = $requestData->rto_code;
        // Re-arrange for Delhi RTO code - start 
        $rto_code = explode('-', $rto_code);
        if ((int)$rto_code[1] < 10) {
            $rto_code[1] = '0'.(int)$rto_code[1];
        }
        $rto_code = implode('-', $rto_code);
        $previous_not_sure = strtolower($requestData->previous_policy_expiry_date) == 'new';
        $vehicle_in_90_days = 'N';
        if ($requestData->business_type== 'newbusiness') {
            $BusinessType = '1';
            $ISNewVehicle = 'true';
            $Registration_Number = 'NEW';
            $proposalType = 'FRESH';
            $NCBEligibilityCriteria = '1';
            $policy_start_date = today();
            $policy_end_date = Carbon::parse($policy_start_date)->addYear(1)->subDay(1)->format('d/m/Y');

            $PreviousPolicyFromDt = $PreviousPolicyToDt =  $previous_ncb = '';
            $break_in = 'NO';
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);
            $soapAction = "GenerateLTTwoWheelerProposal";
        } elseif($requestData->business_type == 'rollover' || $requestData->business_type == 'breakin') {
            if($requestData->business_type == 'breakin'){
                // If previous policy is not sure then consider it as a break in case with more than 90days case.
                if ($previous_not_sure) { 
                    $policy_start_date = today()->addDay(2);
                }else{
                    $policy_start_date = (Carbon::parse($requestData->previous_policy_expiry_date) >= now()) ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(2) : today()->addDay(2);
                }
            }else{
                $policy_start_date = (Carbon::parse($requestData->previous_policy_expiry_date) >= now()) ? Carbon::parse($requestData->previous_policy_expiry_date)->addDay(1) : today()->addDay(1);
            }
            $policy_end_date = date('d/m/Y', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, ['/' => '-']))));          
            $vehicale_registration_number = explode('-', $requestData->vehicle_registration_no);

            $BusinessType = '2';
            $ISNewVehicle = 'false';
            $proposalType = "RENEWAL";
            
            $PreviousPolicyToDt = $previous_not_sure ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->format('d/m/Y');
            $PreviousPolicyFromDt = $previous_not_sure ? '' : Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');

            $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : '2';
            $previous_ncb = $NCBEligibilityCriteria == '1' ? "" : $requestData->previous_ncb;

            if ($requestData->vehicle_registration_no != '') {
                $Registration_Number = $requestData->vehicle_registration_no;
            } else {
                $Registration_Number = $rto_code . '-AB-1234';
            }

            $break_in = "No";
            $soapAction = "GenerateProposal";
            //$nilDepreciationCover = ($bike_age > 5) ? 0 : 1; 
        }
        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();
        
        $state_details = DB::table('shriram_rto_location')
            ->where('rtocode', $requestData->rto_code)
            ->first();
            
            if ($state_details->rtoname != '')
            {   
                $findstring = explode(' ', $state_details->rtoname); 
                $rto_details = DB::table('shriram_pin_city_state')->where(function ($q) use ($findstring) {
                    foreach ($findstring as $value) {
                        $q->orWhere('pin_desc', 'like', "%{$value}");
                        break;
                    }
                })->select('*')->first();
                if(!$rto_details){
                    return [
                        'premium_amount' => 0,
                        'webservice_id'=> $get_response['webservice_id'],
                        'table'=> $get_response['table'],
                        'status' => true,
                        'request' => $requestData,
                        'state_details' => $state_details,
                        'message' => 'RTO city pincode not available in master.',
                    ];
                }
            }else
            {
                return [
                'premium_amount' => 0,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'], 
                'status' => true, 
                'message' => 'RTO city not available',
                ];
            }

        $tp_only = ($premium_type == 'third_party') ? true : false;
        switch ($premium_type) 
               {
                   case 'comprehensive':
                       $ProdCode = $BusinessType == '1' ? "MOT-PRD-021" : "MOT-PRD-002";
                       $policy_type =  $BusinessType == '1' ? "MOT-PLT-014" : "MOT-PLT-001";
                       $policy__type = 'Comprehensive';
                       $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($BusinessType == '1' ? '' :'MOT-PLT-001') : "MOT-PRD-002";
                       $URL = $BusinessType == '1' ? config('constants.motor.shriram.NBQUOTE_URL') : config('constants.motor.shriram.QUOTE_URL');
                       break;
                    case 'breakin':
                        $ProdCode = $BusinessType == '1' ? "MOT-PRD-021" : "MOT-PRD-002";
                        $policy_type = 'MOT-PLT-001';
                        $policy__type = 'Comprehensive';
                        $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($BusinessType == '1' ? '' :'MOT-PLT-001') : "MOT-PRD-002";
                        $URL = config('constants.motor.shriram.QUOTE_URL');
                        break;
                   case 'third_party':
                       $ProdCode =  $BusinessType == '1' ? "MOT-PRD-017" : "MOT-PRD-002";
                       $policy_type = 'MOT-PLT-002';
                       $policy__type = 'Third Party';
                       $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($BusinessType == '1' ? '' :'MOT-PLT-002') : 'MOT-PLT-002';
                       $URL = $BusinessType == '1' ? config('constants.motor.shriram.NBQUOTE_URL') : config('constants.motor.shriram.QUOTE_URL');
                       break;
                    case 'third_party_breakin':
                        $ProdCode = "MOT-PRD-002";
                        $policy_type = 'MOT-PLT-002';
                        $policy__type = 'Third Party';
                        $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? ($BusinessType == '1' ? '' :'MOT-PLT-002') : 'MOT-PLT-002';
                        $URL = config('constants.motor.shriram.QUOTE_URL');
                        break;
                    case 'own_damage':
                       $ProdCode = "MOT-PRD-021";
                       $policy_type = 'MOT-PLT-013';
                       $policy__type = 'Own Damage';
                       $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? 'MOT-PLT-013' : 'MOT-PLT-002';
                       $soapAction = "GenerateLTTwoWheelerProposal";
                       $tp_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');
                       $tp_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($tp_start_date, '/', '-'))))));
                       $URL = config('constants.motor.shriram.ODQUOTE_URL');
                       break;
                    case 'own_damage_breakin':
                        $ProdCode = "MOT-PRD-021";
                        $policy_type = 'MOT-PLT-013';
                        $policy__type = 'Own Damage';
                        $previous_policy_type = ($requestData->previous_policy_type != 'Third-party') ? 'MOT-PLT-013' : 'MOT-PLT-002';
                        $soapAction = "GenerateLTTwoWheelerProposal";
                        $tp_start_date = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');
                        $tp_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+5 year -1 day', strtotime(strtr($tp_start_date, '/', '-'))))));
                        $URL = config('constants.motor.shriram.ODQUOTE_URL');
                        break;
                   
               }
        $zero_dep = false;
        if ($productData->zero_dep == '0') {
            $NilDepreciationCoverYN = '1';
            $DepDeductWaiverYN = "N";
            $pckg_name = "ADDON_03";
            $zero_dep = true;
            $consumable = '1';
            $Eng_Protector = '1';
        } else {
            $NilDepreciationCoverYN = '0';
            $DepDeductWaiverYN = "Y";
            $consumable = '0';
            $Eng_Protector = '0';
            $pckg_name = ($bike_age > 5) ? "ADDON_01" : "ADDON_03";
        }
        // Addons And Accessories
        $selected_addons = SelectedAddons::where('user_product_journey_id', $enquiryId)->first();
        $ElectricalaccessSI = $PAforUnnamedPassengerSI = $antitheft = $Electricalaccess = $NonElectricalaccess = $NonElectricalaccessSI = $PAforUnnamedPassenger = $PAPaidDriverConductorCleaner = $PAPaidDriverConductorCleanerSI = $externalCNGKIT = $externalCNGKITSI = 0;
         //$addons = ($selected_addons->addons == null ? [] : $selected_addons->addons);
        $accessories = ($selected_addons->accessories == null ? [] : $selected_addons->accessories);
        $discounts = ($selected_addons->discounts == null ? [] : $selected_addons->discounts);
        $additional_covers = ($selected_addons->additional_covers == null ? [] : $selected_addons->additional_covers);

        $voluntary_insurer_discounts = 'TWVE1'; // Discount of 0
        $voluntary_discounts = [
            '0'     => 'TWVE1', // Discount of 0
            '500'  => 'TWVE2', // Discount of 750
            '750'  => 'TWVE3', // Discount of 1500
            '1000'  => 'TWVE4', // Discount of 2000
            '1500' => 'TWVE5', 
            '3000' => 'TWVE6',// Discount of 2500
        ];
        $LimitedTPPDYN = 0;
        foreach ($discounts as $key => $value) {
            if (in_array('voluntary_insurer_discounts', $value)) {
                if(isset( $value['sumInsured'] ) && array_key_exists($value['sumInsured'], $voluntary_discounts)){
                    $voluntary_insurer_discounts = $voluntary_discounts[$value['sumInsured']];
                }
            }
            if (in_array('TPPD Cover', $value)) {
                $LimitedTPPDYN = 1;
            }
        }
        $LLtoPaidDriverYN = '0';
        foreach($additional_covers as $key => $value) {
            if (in_array('LL paid driver', $value)) {
                $LLtoPaidDriverYN = '1';
            }

            if (in_array('PA cover for additional paid driver', $value)) {
                $PAPaidDriverConductorCleaner = 1;
                $PAPaidDriverConductorCleanerSI = $value['sumInsured'];
            }

            if (in_array('Unnamed Passenger PA Cover', $value)) {
                $PAforUnnamedPassenger = 1;
                $PAforUnnamedPassengerSI = $value['sumInsured'];
            }
        }
        foreach ($accessories as $key => $value) {
            if (in_array('Electrical Accessories', $value)) {
                $Electricalaccess = 1;
                $ElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('Non-Electrical Accessories', $value)) {
                $NonElectricalaccess = 1;
                $NonElectricalaccessSI = $value['sumInsured'];
            }

            if (in_array('External Bi-Fuel Kit CNG/LPG', $value)) {
                $externalCNGKIT = 1;
                $externalCNGKITSI = $value['sumInsured'];
            }
        }

        /*$PAOwnerDriverExclusion = "1";
        $PAOwnerDriverExReason = "PA_TYPE2";
        if (isset($selected_addons->compulsory_personal_accident['0']['name'])) {
            $PAOwnerDriverExclusion = "0";
            $PAOwnerDriverExReason = "";
        }*/
        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $posp_name = '';
        $posp_pan_number = '';
    
        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();
    
        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $posp_name = $pos_data->agent_name;
                $posp_pan_number = $pos_data->pan_no;
            }
        }

        $data = [
            "soap:Header" => [
                "AuthHeader" => [
                    "Username" => config('constants.motor.shriram.AUTH_NAME_SHRIRAM_MOTOR'),
                    "Password" => config('constants.motor.shriram.AUTH_PASS_SHRIRAM_MOTOR'),
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/"
                    ],
                ]
            ],
            "soap:Body" => [
                $soapAction => [
                    "objPolicyEntryETT" => [
                        "ReferenceNo" => '',
                        "ProdCode" => $ProdCode,
                        "PolicyFromDt" => $policy_start_date->format('d/m/Y'), //"19/08/2021",
                        "PolicyToDt" => $policy_end_date, //"18/08/2022",
                        "PolicyIssueDt" => today()->format('d/m/y'),
                        "InsuredPrefix" => ($requestData->vehicle_owner_type == 'I') ? "1" : "3",
                        "InsuredName" => ($requestData->user_fname ?? "Test") . ' ' . ($requestData->user_lname ?? "Test"), //"Gopi",
                        "Gender" => ($requestData->vehicle_owner_type == 'I') ? "M" : "", //"M",
                        "Address1" => "Address1",
                        "Address2" => "Address2",
                        "Address3" => "Address3",
                        "State" => $rto_details->state, 
                        "City" => $rto_details->city, 
                        "PinCode" => $rto_details->pin_code,
                        "PanNo" => "",
                        'GSTNo' => '',
                        'TelephoneNo' => '',
                        "FaxNo" => "",
                        "EMailID" => $requestData->user_email ?? 'ABC@testmail.com', //"Gopi@testmail.com",
                        "PolicyType" => $policy_type, //"MOT-PLT-001",
                        "ProposalType" => $proposalType, //"Renewal",
                        "MobileNo" => $requestData->user_mobile ?? "9876543211", //"9626616284",
                        "DateOfBirth" => ($requestData->vehicle_owner_type == 'I') ? "05/06/1993" : "",
                        "POSAgentName" => $posp_name, //"Gopi",
                        "POSAgentPanNo" => $posp_pan_number, //"12344",
                        "CoverNoteNo" => '',
                        "CoverNoteDt" => '',
                        //"VehicleCategory" => "CLASS_4A1",
                        "VehicleCode" => $mmv_data->veh_code, //"M_10075",
                        "EngineNo" => "6584D218",//Str::upper(Str::random(8)),
                        "FirstRegDt" => date('d/m/Y', strtotime($requestData->vehicle_register_date)), //"10/07/2021", //,
                        "VehicleType" => $BusinessType == "1" ? "W" : "U",
                        "ChassisNo" => "6589311F4SDSA3FFH",//Str::upper(Str::random(12)),
                        "RegNo1" => explode('-', $rto_code)[0],
                        "RegNo2" => explode('-', $rto_code)[1],
                        "RegNo3" => !empty($vehicale_registration_number[2]) ? substr($vehicale_registration_number[2], 0, 3) : 'TT', // "OK",
                        "RegNo4" => $vehicale_registration_number[3] ?? '4521', // "4521",
                        "RTOCode" => $rto_code, // "MH-01",
                        "IDV_of_Vehicle" => "",
                        "Colour" => "",
                        "NoEmpCoverLL" => "",
                        "VehiclePurposeYN" => "",
                        "DriverAgeYN" => "",
                        "LimitOwnPremiseYN" => '0',
                        "CNGKitYN" => $externalCNGKIT,
                        "CNGKitSI" => $externalCNGKITSI,
                        // "LimitedTPPDYN" => $LimitedTPPDYN,//https://github.com/Fyntune/motor_2.0_backend/issues/29067#issuecomment-2538123782
                        "InBuiltCNGKitYN" => $requestData->fuel_type == 'CNG' ? "1" : "0",
                        "VoluntaryExcess" => $voluntary_insurer_discounts,//$BusinessType == "2" ? "PCVE1" : "PCVE2", //"MOT-DED-002", $voluntary_insurer_discounts,
                        "Bangladesh"    => "0",
                        "Bhutan"        => "0",
                        "Srilanka"      => "0",
                        "Pakistan"      => "0",
                        "Nepal"         => "0",
                        "Maldives"      => "0",
                        "DeTariff"      => 0,//$productData->default_discount,
                        "PreInspectionReportYN" => "",
                        "PreInspection" => '', // new added
                        "BreakIn"       => "NO",
                        "AddonPackage"  => $pckg_name,
                        "NilDepreciationCoverYN" => $NilDepreciationCoverYN,//$nilDepreciationCover,
                        "PAforUnnamedPassengerYN" => $PAforUnnamedPassenger,
                        "PAforUnnamedPassengerSI" => $PAforUnnamedPassengerSI,
                        "ElectricalaccessYN" => $Electricalaccess,
                        "ElectricalaccessSI" => $ElectricalaccessSI,
                        "ElectricalaccessRemarks" => "electric",
                        "NonElectricalaccessYN" => $NonElectricalaccess,
                        "NonElectricalaccessSI" => $NonElectricalaccessSI,
                        "NonElectricalaccessRemarks" => "non electric",
                        "PAPaidDriverConductorCleanerYN" => $PAPaidDriverConductorCleaner,
                        "PAPaidDriverConductorCleanerSI" => $PAPaidDriverConductorCleanerSI,
                        "PAPaidDriverCount"     => "0",
                        "PAPaidConductorCount"  => "0",
                        "PAPaidCleanerCount"    => "0",
                        "NomineeNameforPAOwnerDriver" => ($requestData->vehicle_owner_type == 'I') ? "Test Nominee" : "",
                        "NomineeAgeforPAOwnerDriver" => ($requestData->vehicle_owner_type == 'I') ? "30" : "",
                        "NomineeRelationforPAOwnerDriver" => ($requestData->vehicle_owner_type == 'I') ? "Brother" : "",
                        "AppointeeNameforPAOwnerDriver" => "",
                        "AppointeeRelationforPAOwnerDriver" => "",
                        "LLtoPaidDriverYN"      => $LLtoPaidDriverYN,
                        "AntiTheftYN"           => $antitheft,
                        "PreviousPolicyNo"      => ($BusinessType == "1" || $previous_not_sure) ? "" : "POL1234",
                        "PreviousInsurer"       => ($BusinessType == "1" || $previous_not_sure) ? "" : "HDFC CHUBB General Insurance Company",
                        "PreviousPolicyFromDt"  => $PreviousPolicyFromDt, //"19/08/2020",
                        "PreviousPolicyToDt"    => $PreviousPolicyToDt, // "18/08/2021", 
                        "PreviousPolicyUWYear"  => "", //$PreviousPolicyToDt, 
                        "PreviousPolicySI"      => "",
                        "PreviousPolicyClaimYN" => $requestData->is_claim == 'Y' ? '1' : '0', 
                        "PreviousPolicyNCBPerc" => $requestData->is_claim == 'Y' ? '' : ($tp_only ? '': $previous_ncb),
                        "PreviousPolicyType"    => $previous_policy_type,
                        "PreviousNilDepreciation" => "1",
                        "HypothecationType"     => "",
                        "HypothecationBankName" => "",
                        "HypothecationAddress1" => "",
                        "HypothecationAddress2" => "",
                        "HypothecationAddress3" => "",
                        "HypothecationAgreementNo" => "",
                        "HypothecationCountry"  => "",
                        "HypothecationState"    => "",
                        "HypothecationCity"     => "",
                        "HypothecationPinCode"  => "",
                        "SpecifiedPersonField"  => "",
                        "PAOwnerDriverExclusion" => ($requestData->vehicle_owner_type == 'I') ? '' : '1',//$PAOwnerDriverExclusion,
                        "PAOwnerDriverExReason" => ($requestData->vehicle_owner_type == 'I') ? "" : 'PA_TYPE1',//$PAOwnerDriverExReason,
                        "CPAPolicyNo"       => "",
                        "CPASumInsured"     => "",
                        "CPAInsComp"        => "",
                        "CPAPolicyFmDt"     => "",
                        "CPAPolicyToDt"     => "",
                        "DepDeductWaiverYN"     => $DepDeductWaiverYN,
                        "MultiCarBenefitYN"     => "N",
                        "RSACover"              => $tp_only ? '0' : '1',
                        "InvReturnYN"           => "N",
                        "Eng_Protector"         => ($requestData->business_type== 'newbusiness' || $policytype = 'Own Damage') ? '0':$Eng_Protector,
                        "Consumables"           => ($requestData->business_type == 'newbusiness' || $policytype = 'Own Damage') ? '0':$consumable,
                        /*"AadharNo"              => "",
                        "AadharEnrollNo"        => "",
                        "Form16"                => "",
                        "VehicleManufactureYear" => "",
                        "PuccNo"                => "",
                        "validfrom"         => "",
                        "validTo"           => "",
                        "PuccState"         => "",
                        "PuccYN"            => "",*/
                        'TpFmDt'               => ($premium_type == 'own_damage') ? $tp_start_date : '',
                        'TpToDt'               =>  ($premium_type == 'own_damage') ? $tp_end_date : '',
                        'TpPolNo'              => ($premium_type == 'own_damage') ?'1234abcde':'',
                        'TpCompName'           => ($premium_type == 'own_damage') ? 'Test' : '',
                        'TpAddress'            => ($premium_type == 'own_damage') ? 'Chennai-Thiruvanmiyuar' : '',
                    ],
                    '_attributes' => [
                        "xmlns" => "http://tempuri.org/"
                    ],
                ]
            ],
        ];

        $additional_data = [
            'enquiryId' => $enquiryId,
            'headers' => [
                //'SOAPAction' => 'http://tempuri.org/GenerateGCCVProposal',
                'Content-Type' => 'text/xml; charset="utf-8"',
            ],
            'requestMethod' => 'post',
            'requestType' => 'xml',
            'section' => 'Bike',
            'method' => 'Premium Calculation',
            'product' => 'Bike Insurance',
            'transaction_type' => 'quote',
            'productName' => $productData->product_name . ($zero_dep ? ' zero_dep' : '')
        ];
        $root = [
            'rootElementName' => 'soap:Envelope',
            '_attributes' => [
                "xmlns:soap" => "http://schemas.xmlsoap.org/soap/envelope/",
                //"xmlns:xsi" => "http://www.w3.org/2001/XMLSchema-instance",
                //"xmlns:xsd" => "http://www.w3.org/2001/XMLSchema",
            ]
        ];
        $input_array = ArrayToXml::convert($data, $root, false, 'utf-8');
       $get_response = getWsData($URL, $input_array, 'shriram', $additional_data);
       $response = $get_response['response'];
        
       $response = XmlToArray::convert($response);
        if($proposalType == "RENEWAL" && $premium_type != 'own_damage'){
            $quote_response = $response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'];
        }elseif($proposalType == 'RENEWAL' && $premium_type == 'own_damage')
        {
            $quote_response = $response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult'];
        }else{
            $quote_response = $response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult'];
        }
        
        if ($quote_response['ERROR_CODE'] == 0) {

            $idv = $quote_response['VehicleIDV'];
            $idv_min = $tp_only ? '0' : (string) ceil(0.85 * $quote_response['VehicleIDV']);
            $idv_max = $tp_only ? '0' : (string) floor(1.20 * $quote_response['VehicleIDV']);
            if ($requestData->is_idv_changed == 'Y') 
            {
                if ($requestData->edit_idv >= floor($idv_max)) 
                {
                    $data['soap:Body'][$soapAction]['objPolicyEntryETT']['IDV_of_Vehicle'] = floor($idv_max);
                } 
                elseif ($requestData->edit_idv <= ceil($idv_min)) 
                {
                    $data['soap:Body'][$soapAction]['objPolicyEntryETT']['IDV_of_Vehicle'] = ceil($idv_min);
                } 
                else 
                {
                    $data['soap:Body'][$soapAction]['objPolicyEntryETT']['IDV_of_Vehicle'] = $requestData->edit_idv;
                }
            }
            else
            {
               $data['soap:Body'][$soapAction]['objPolicyEntryETT']['IDV_of_Vehicle'] =  $idv_min; 
            }
            $additional_data['method'] = 'Premium Re Calculation';
            $input_array = ArrayToXml::convert($data, $root, false, 'utf-8');
          $get_response = getWsData($URL, $input_array, 'shriram', $additional_data);
          $response = $get_response['response'];
            $response = XmlToArray::convert($response);
            if($proposalType == "RENEWAL" && $premium_type != 'own_damage'){
            $quote_response = $response['soap:Body']['GenerateProposalResponse']['GenerateProposalResult'];
            }elseif($proposalType == 'RENEWAL' && $premium_type == 'own_damage')
            {
                $quote_response = $response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult'];
            }else{
                $quote_response = $response['soap:Body']['GenerateLTTwoWheelerProposalResponse']['GenerateLTTwoWheelerProposalResult'];
            }
        
            if ($quote_response['ERROR_CODE'] != 0) {
                return [
                    'premium_amount' => 0,
                    'webservice_id'=> $get_response['webservice_id'],
                    'table'=> $get_response['table'],
                    'status' => false,
                    'msg' => $quote_response['ERROR_DESC'],
                ];
            }
            $idv = $quote_response['VehicleIDV'];
            $igst           = $anti_theft = $other_discount = 
            $rsapremium     = $pa_paid_driver = $zero_dep_amount = 
            $ncb_discount   = $tppd = $final_tp_premium = 
            $final_od_premium = $final_net_premium =
            $final_payable_amount = $basic_od = $electrical_accessories = 
            $lpg_cng_tp     = $lpg_cng = $non_electrical_accessories = $tppd_discount=
            $pa_owner       = $voluntary_excess = $pa_unnamed =  
            $ll_paid_driver =$engine_protection = $consumables_cover = $return_to_invoice= 0;

            foreach ($quote_response['CoverDtlList']['CoverDtl'] as $key => $value) {
                /*if ($value['CoverDesc'] == 'Road Side Assistance') {
                    $rsapremium = $value['Premium'];
                }*/
                if ( in_array($value['CoverDesc'], array('BASIC OD COVER', 'BASIC OD COVER - 1 YEAR')) ) {
                    $basic_od = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'VOLUNTARY EXCESS DISCOUNT-IMT-22A') {
                    $voluntary_excess = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('OD TOTAL', 'OD TOTAL - 1 YEAR')) ) {
                    $final_od_premium = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('BASIC TP COVER', 'BASIC TP COVER - 1 YEAR')) ) {
                    $tppd = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'TOTAL PREMIUM') {
                    $final_net_premium = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'IGST') {
                    $igst = $igst + $value['Premium'];
                }

                if ($value['CoverDesc'] == 'SGST') {
                    $sgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'CGST') {
                    $cgst = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'TOTAL AMOUNT') {
                    $final_payable_amount = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'NO CLAIM BONUS-GR27') {
                    $ncb_discount = $value['Premium'];
                }

                if ( in_array($value['CoverDesc'], array('Nil Depreciation', 'Nil Depreciation Cover','Nil Depreciation - 1 YEAR')) ) {
                    $zero_dep_amount = $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('INVOICE RETURN', 'INVOICE RETURN - 1 YEAR')) ) {
                    $return_to_invoice = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Consumables Cover', 'Consumables Loading'])) {
                    $consumables_cover += $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['Engine Protector Cover', 'Engine Protector Loading'])) {
                    $engine_protection += $value['Premium'];
                }

                if ($value['CoverDesc'] == 'GR41--COVER FOR ELECTRICAL AND ELECTRONIC ACCESSORIES') {
                    $electrical_accessories = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG-KIT-COVER-GR42', 'INBUILT CNG/LPG KIT'))) {
                    $lpg_cng = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], array('CNG/LPG KIT - TP  COVER-GR-42', 'IN-BUILT CNG/LPG KIT - TP  COVER'))) {
                    $lpg_cng_tp = $value['Premium'];
                }

                /*if ($value['CoverDesc'] == 'Cover For Non Electrical Accessories') {
                    $non_electrical_accessories = $value['Premium'];
                }*/

                if ($value['CoverDesc'] == 'PA-UN-NAMED-GR36B2') {
                    $pa_unnamed = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'PA-PAID DRIVER, CONDUCTOR,CLEANER-GR36B3') {
                    $pa_paid_driver = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], ['GR36A-PA FOR OWNER DRIVER', 'GR36A-PA FOR OWNER DRIVER - 1 YEAR'])) {
                    $pa_owner = $value['Premium'];
                }
                if ($value['CoverDesc'] == 'LL-PAID DRIVER, CONDUCTOR,CLEANER-IMT-28') {
                    $ll_paid_driver = $value['Premium'];
                }

                if ($value['CoverDesc'] == 'DETARIFF DISCOUNT ON BASIC OD') {
                    $other_discount = $value['Premium'];
                }

                if (in_array($value['CoverDesc'], ['GR30-Anti Theft Discount Cover', 'ANTI-THEFT DISCOUNT-GR-30'])) {
                    $anti_theft = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('ROAD SIDE ASSISTANCE', 'ROAD SIDE ASSISTANCE - 1 YEAR')) ) {
                    $rsapremium = $value['Premium'];
                }
                if (in_array($value['CoverDesc'], array('GR39A-TPPD COVER', 'GR39A-TPPD COVER - 1 YEAR')) ) {
                    $tppd_discount = $value['Premium'];
                    $tppd_discount = ($requestData->business_type== 'newbusiness') ? ($value['Premium'] * 5): $value['Premium'];
                }
                if ( in_array($value['CoverDesc'], array('TP TOTAL', 'TP TOTAL - 1 YEAR')) ) {
                    $final_tp_premium = $value['Premium'];
                    $final_tp_premium = ($requestData->business_type== 'newbusiness') ? (($tppd * 5)+ $pa_owner )- $tppd_discount: $final_tp_premium;
                }
            }
            if ((int) $NonElectricalaccessSI > 0) {
                $non_electrical_accessories = round(($NonElectricalaccessSI * 3.283 ) / 100);
                $basic_od = ($basic_od - $non_electrical_accessories);
            }

            $final_gst_amount = isset($igst) ? $igst : 0;

            //$final_tp_premium = $final_tp_premium - ($pa_owner) - $lpg_cng_tp - $pa_paid_driver - $ll_paid_driver;
            $final_tp_premium = $final_tp_premium - ($pa_owner) + $tppd_discount;

            $final_total_discount = $ncb_discount + $other_discount + $voluntary_excess + $tppd_discount;

            $final_od_premium = $basic_od + $non_electrical_accessories + $electrical_accessories + $lpg_cng;

            $add_ons = [];
            $applicable_addons = [ "roadSideAssistance" ];
            if ($bike_age > 4) {
                $add_ons = [
                    'in_built' => [],
                    'additional' => [
                        'zero_depreciation'     => 0,
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => 0,
                        'consumables'           => 0,
                        'return_to_invoice'     => 0,
                    ],
                    'other' => []
                ];
            }elseif($zero_dep){
                $add_ons = [
                    'in_built' => [	
					],
                    'additional' => [   
                        'zero_depreciation'     => round($zero_dep_amount),                     
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => round($engine_protection),
                        'consumables'           => round($consumables_cover),
                        'return_to_invoice'     => round($return_to_invoice),
                    ],
                    'other' => []
                ];
                array_push($applicable_addons, "zeroDepreciation");
            }else{
                $add_ons = [
                    'in_built' => [],
                    'additional' => [
                        'zero_depreciation'     => 0,
                        'road_side_assistance'  => round($rsapremium),
                        'engine_protector'      => 0,
                        'consumables'           => 0,
                        'return_to_invoice'     => round($return_to_invoice),
                    ],
                    'other' => []
                ];
                array_push($applicable_addons, "zeroDepreciation");
            }
            $add_ons['in_built_premium'] = array_sum($add_ons['in_built']);
            $add_ons['additional_premium'] = array_sum($add_ons['additional']);
            $add_ons['other_premium'] = array_sum($add_ons['other']);
            $data_response = [
                'status' => true,
                'msg' => 'Found',
                'Data' => [
                    'idv' => (int) $idv,
                    'min_idv' => (int) $idv_min,
                    'max_idv' => (int) $idv_max,
                    'vehicle_idv' => $idv,
                    'qdata' => null,
                    'pp_enddate' => $requestData->previous_policy_expiry_date,
                    'addonCover' => null,
                    'addon_cover_data_get' => '',
                    'rto_decline' => null,
                    'rto_decline_number' => null,
                    'mmv_decline' => null,
                    'mmv_decline_name' => null,
                    'policy_type' => $policy__type,
                    'business_type' => ($requestData->business_type == 'newbusiness') ? 'New Business' : $requestData->business_type,
                    'cover_type' => '1YC',
                    'hypothecation' => '',
                    'hypothecation_name' => '',
                    'vehicle_registration_no' => $requestData->rto_code,
                    'rto_no' => $rto_code,
                    'version_id' => $requestData->version_id,
                    'selected_addon' => [],
                    'showroom_price' => 0,
                    'fuel_type' => $requestData->fuel_type,
                    'ncb_discount' => $requestData->applicable_ncb,
                    'company_name' => $productData->company_name,
                    'company_logo' => url(config('constants.motorConstant.logos')) . '/' . $productData->logo,
                    'product_name' => $productData->product_sub_type_name,
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
                        'bike_age' => 2, //$bike_age,
                        'aai_discount' => 0,
                        'ic_vehicle_discount' => round(abs($other_discount)),
                    ],
                    'ic_vehicle_discount' => round(abs($other_discount)),
                    'basic_premium' => round($basic_od),
                    'motor_electric_accessories_value' => round($electrical_accessories),
                    'motor_non_electric_accessories_value' => round($non_electrical_accessories),
                    'motor_lpg_cng_kit_value' => round($lpg_cng),
                    'total_accessories_amount(net_od_premium)' => round($electrical_accessories + $non_electrical_accessories + $lpg_cng),
                    'total_own_damage' => round($final_od_premium),
                    'tppd_premium_amount' => ($requestData->business_type== 'newbusiness') ? round($tppd * 5): round($tppd),
                    'tppd_discount' => $tppd_discount,
                    'compulsory_pa_own_driver' => round($pa_owner), // Not added in Total TP Premium
                    'cover_unnamed_passenger_value' => $pa_unnamed,
                    'default_paid_driver' => $ll_paid_driver,
                    'motor_additional_paid_driver' => round($pa_paid_driver),
                    'cng_lpg_tp' => round($lpg_cng_tp),
                    'seating_capacity' => $mmv_data->veh_seat_cap,
                    'deduction_of_ncb' => round(abs($ncb_discount)),
                    'antitheft_discount' => '',
                    'aai_discount' => '', //$automobile_association,
                    'voluntary_excess' => $voluntary_excess,
                    'other_discount' => round(abs($other_discount)),
                    'total_liability_premium' => round($final_tp_premium),
                    'net_premium' => round($final_net_premium),
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

                    'add_ons_data'      => $add_ons,
                    'applicable_addons' => $applicable_addons,
                    'final_od_premium'  => round($final_od_premium),
                    'final_tp_premium'  => round($final_tp_premium),
                    'final_total_discount' => round(abs($final_total_discount)),
                    'final_net_premium' => round($final_net_premium),
                    'final_gst_amount'  => round($final_gst_amount),
                    'final_payable_amount' => round($final_payable_amount),
                    'mmv_detail'    => [
                        'manf_name'     => $mmv_data->manf,
                        'model_name'    => $mmv_data->model_desc,
                        'version_name'  => '',
                        'fuel_type'     => $mmv_data->fuel,
                        'seating_capacity' => $mmv_data->veh_seat_cap,
                        'carrying_capacity' => $mmv_data->veh_seat_cap,
                        'cubic_capacity' => $mmv_data->veh_cc,
                        'gross_vehicle_weight' => '',
                        'vehicle_type'  => 'Bike',
                    ],
                ],
            ];
            return camelCase($data_response);
        } else {
            return [
                'status' => false,
                'webservice_id'=> $get_response['webservice_id'],
                'table'=> $get_response['table'],
                'msg' => $quote_response['ERROR_DESC'],
            ];
        }
    }
}  
