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
    //print_r($mmv_data);
    //die;
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
//            print_r($reponse_data);
//            die;
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
                        'manf_name'     => $mmv_data->veh_model,
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
    }
}  
