<?php

namespace App\Http\Controllers\Proposal\Services\V1;

use Config;
use Illuminate\Support\Facades\DB;
use App\Models\UserProposal;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;
use App\Http\Controllers\Proposal\Services\RelianceMiscdSubmitProposal;
use App\Http\Controllers\SyncPremiumDetail\Services\ReliancePremiumDetailController;
use App\Models\CvBreakinStatus;

include_once app_path().'/Helpers/CvWebServiceHelper.php';

class RelianceSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */
    public static function submit($proposal, $request)
    {
        $enquiryId   = customDecrypt($request['enquiryId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);
        $quote_log_data = DB::table('quote_log')
            ->where('user_product_journey_id',$enquiryId)
            ->select('idv','premium_json')
            ->first();
        $idv = $quote_log_data->idv;
        $quote_log_data->bodyIDV = (json_decode($quote_log_data->premium_json)->bodyIDV);
        $quote_log_data->chassisIDV = (json_decode($quote_log_data->premium_json)->chassisIDV);

        $parent_id = get_parent_code($productData->product_sub_type_id);
        if (get_parent_code($productData->product_sub_type_id) == 'MISC') {
            return RelianceMiscdSubmitProposal::submit($proposal, $request);
    
        } else {
            $mmv = get_mmv_details($productData,$requestData->version_id,'reliance', $parent_id == 'GCV' ? $requestData->gcv_carrier_type : NULL);

            if ($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return  [
                    'premium_amount' => 0,
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }
        }

        $mmv_data = (object) array_change_key_case((array) $mmv,CASE_LOWER);

        // $rto_code = $requestData->rto_code;
        // $rto_code = RtoCodeWithOrWithoutZero($rto_code,true);
        

        // $rto_data = DB::table('reliance_rto_master as rm')
        //     ->where('rm.region_code',$rto_code)
        //     ->select('rm.*')
        //     ->first();
            
        if ($requestData->ownership_changed == 'Y') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Ownership change not allowed',
                'request' => [
                    'message' => 'Ownership change not allowed',
                ]
            ];
        }

        $rto_code = $requestData->rto_code;
        $registration_number = $proposal->vehicale_registration_number;

        $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
            $registration_number,
            $rto_code,
            $requestData->business_type == 'newbusiness'
        );

        if (!$rcDetails['status']) {
            return $rcDetails;
        }

        $registration_number = $rcDetails['rcNumber'];
        $rto_data = $rcDetails['rtoData'];

        // $registration_number = $proposal->vehicale_registration_number; 
        // $registration_number = explode('-', $registration_number); 
        
        // if ($registration_number[0] == 'DL') { 
        //     $registration_no = RtoCodeWithOrWithoutZero($registration_number[0].$registration_number[1],true);  
        //     $registration_number = $registration_no.'-'.$registration_number[2].'-'.$registration_number[3]; 
        // } else { 
        //     $registration_number = $proposal->vehicale_registration_number; 
        // } 

        $premium_req_data = self::getPremiumRequestData([
            'enquiryId' => $enquiryId,
            'requestData' => $requestData,
            'productData' => $productData,
            'quote_log_data' => $quote_log_data,
            'mmv_data' => $mmv_data,
            'rto_data' => $rto_data,
            'proposal' => $proposal
        ]);

        extract($premium_req_data);

        if (isset($status) && ! $status)
        {
            return [
                'status' => false,
                'message' => $message
            ];
        }

        if($tp_only)
        {
            $premium_req_array['Cover']['IsSecurePremium'] = '';
        }

        $get_response = getWsData(
            config('IC.RELIANCE.V1.CV.END_POINT_URL_PREMIUM'),
            $premium_req_array,
            'reliance',
            [
                'root_tag'      => 'PolicyDetails',
                'section'       => $productData->product_sub_type_code,
                'method'        => 'Premium Calculation',
                'requestMethod' => 'post',
                'enquiryId'     => $enquiryId,
                'productName'   => $productData->product_name,
                'transaction_type'    => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('IC.RELIANCE.V1.CV.OCP_APIM_SUBSCRIPTION_KEY'),
                    'Content-type' => 'text/xml'
                ]
            ]
        );
        $premium_res_data = $get_response['response'];

        $response = json_decode($premium_res_data)->MotorPolicy;
        unset($premium_res_data);
        
        $address_data = [
                    'address' => $proposal->address_line1,
                    'address_1_limit'   => 250,
                    'address_2_limit'   => 250            
                ];
        $getAddress = getAddress($address_data);

        if (trim($response->ErrorMessages) == '') {

            ReliancePremiumDetailController::savePremiumDetails($get_response['webservice_id']);
            $is_breakin = '';
            $inspection_id = '';

            if ((in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin']) || ($requestData->previous_policy_type == 'Third-party' && ! in_array($premium_type, ['third_party', 'third_party_breakin']))) && ($proposal->is_inspection_done == 'N' || $proposal->is_inspection_done == NULL))
            {
                $is_breakin = 'Y';

                $corres_address_data = DB::table('reliance_pincode_state_city_master')
                    ->where('pincode', $proposal->pincode)
                    ->select('*')
                    ->first();

                $vehicle_manf = explode('-',$proposal->vehicle_manf_year);

                $lead_array = [
                    'LEADDESCRIPTION' => [
                        'CUSTOMERFNAME' => $requestData->vehicle_owner_type == 'C' ? $CorporateName : $ForeName,
                        'CUSTOMERMNAME' => '',
                        'CUSTOMERLNAME' => $LastName,
                        'CUSTOMERADDRESS1' => trim($getAddress['address_1']),
                        'CUSTOMERADDRESS2' => trim($getAddress['address_2']),
                        'CUSTOMERADDRESS3' => trim($getAddress['address_3']),
                        'CUSTOMERCONTACTNO' => $proposal->mobile_number,
                        'CUSTOMERTELEPHONENO' => '',
                        'CUSTOMEREMAILID' => $proposal->email,
                        'VENDORCODE' => config('IC.RELIANCE.V1.CV.LEAD_VENDORCODE'),
                        'VENDORCODEVALUE' => config('IC.RELIANCE.V1.CV.LEAD_VENDORCODEVALUE'),
                        'BASCODE' => config('IC.RELIANCE.V1.CV.LEAD_BASCODE'),
                        'BASCODEVALUE' => config('IC.RELIANCE.V1.CV.LEAD_BASCODEVALUE'),
                        'BASMOBILE' => config('IC.RELIANCE.V1.CV.LEAD_BASMOBILE'),
                        'SMCODE' => config('IC.RELIANCE.V1.CV.LEAD_SMCODE'),
                        'SMCODEVALUE' => config('IC.RELIANCE.V1.CV.LEAD_SMCODEVALUE'),
                        'SMMOBILENO' => config('IC.RELIANCE.V1.CV.LEAD_SMMOBILENO'),
                        'VEHICLETYPE' => (strtoupper($mmv_data->veh_type_name) === 'PCV' ) ? '7' : '2',//'6',
                        'VEHICLETYPEVALUE' => strtoupper($mmv_data->veh_type_name),//'Pvt. Car',
                        'VEHICLEREGNO' => $registration_number, 
                        'MAKE' => $mmv_data->make_id_pk,
                        'MAKEVALUE' => $mmv_data->make_name,
                        'MODEL' => $mmv_data->model_id_pk,
                        'MODELVALUE' => $mmv_data->variance,
                        'ENGINENO' => removeSpecialCharactersFromString($proposal->engine_number),
                        'CHASSISNO' => removeSpecialCharactersFromString($proposal->chassis_number),
                        'STATE' => $corres_address_data->state_id_pk,
                        'STATEVALUE' => $corres_address_data->state_name,
                        'DISTRICT' => $corres_address_data->district_id_pk,
                        'DISTRICTVALUE' => $corres_address_data->district_name,
                        'CITY' => $corres_address_data->city_or_village_id_pk,
                        'CITYVALUE' => $corres_address_data->city_or_village_name,
                        'PINCODE' => $proposal->pincode,
                        'RGICL_OFFICE' => config('IC.RELIANCE.V1.CV.LEAD_RGICL_OFFICE'),
                        'RGICL_OFFICEVALUE' => config('IC.RELIANCE.V1.CV.LEAD_RGICL_OFFICEVALUE'),
                        'VECH_INSP_ADDRESS' => trim($getAddress['address_1']),
                        'VECH_INSP_ADDRESS2' => trim($getAddress['address_2']),
                        'VECH_INSP_ADDRESS3' => trim($getAddress['address_3']),
                        'REMARK' => 'GZB',
                        'LEADTYPE' => 'F',
                        'STATE_VEH' => $corres_address_data->state_id_pk,
                        'STATEVALUE_VEH' => $corres_address_data->state_name,
                        'DISTRICT_VEH' => $corres_address_data->district_id_pk,
                        'DISTRICTVALUE_VEH' => $corres_address_data->district_name,
                        'CITY_VEH' => $corres_address_data->city_or_village_id_pk,
                        'CITYVALUE_VEH' => $corres_address_data->city_or_village_name,
                        'PINCODE_VEH' => $proposal->pincode,
                        'OBJECTIVEOFINSPECTION' => '8',
                        'OBJECTIVEOFINSPECTIONVALUE' => 'Policy Booking',
                        'INSPECTIONTOBEDONE' => '12',
                        'INSPECTIONTOBEDONEVALUE' => 'Inspection Agency',
                        'LEADCREATEDBY' => 'reliance',
                        'LEADCREATEDBYSYSTEM' => config('IC.RELIANCE.V1.CV.LEAD_LEADCREATEDBYSYSTEM'),
                        'INTIMATORNAME' => config('IC.RELIANCE.V1.CV.LEAD_INTIMATORNAME'),
                        'INTIMATORMOBILENO' => config('IC.RELIANCE.V1.CV.LEAD_INTIMATORMOBILENO'),
                        'PREVIOUS_POLICYNUMBER' => '',
                        'BUSINESSTYPE_CODE' => '4',
                        'FITNESS_CERTIFICATE' => '',
                        'FITNESS_VALID_UPTO' => '',
                        'PERMIT_NO' => '',
                        'PERMIT_VALID_UPTO' =>'',
                        'PERMIT_TYPE' => 'All India',
                        'MANUFACTURER_MONTH' => $vehicle_manf[0],
                        'MANUFACTURER_YEAR' => $vehicle_manf[1],
                    ]
                ];

                $lead_array = ArrayToXML::convert($lead_array, 'LEAD');
                $lead_array = preg_replace("/<\\?xml .*\\?>/i", '', $lead_array);

                $root = [
                    'rootElementName' => 'soapenv:Envelope',
                    '_attributes' => [
                        'xmlns:soapenv' => 'http://schemas.xmlsoap.org/soap/envelope/',
                        'xmlns:tem' => 'http://tempuri.org/'
                    ]
                ];

                $request_array = [
                    'soapenv:Header' => [
                        'tem:UserCredential' => [
                            'tem:UserID' => config('IC.RELIANCE.V1.CV.LEAD_USERID'),
                            'tem:UserPassword' => config('IC.RELIANCE.V1.CV.LEAD_USERPASSWORD')
                        ]
                    ],
                    'soapenv:Body' => [
                        'tem:InsertLead' => [
                            'tem:xmlstring' => [
                                '_cdata' => $lead_array
                            ]
                        ]
                    ]
                ];

                $lead_array = ArrayToXml::convert($request_array, $root, false);

                $headers = ((config('constants.IcConstants.reliance.IS_WIMWISURE_RELIANCE_ENABLED') == 'Y') && config('constants.IcConstants.reliance.IS_WIMWISURE_RELIANCE_ENABLED_SHORT_TERM') != 'Y') ? [
                    'Content-type' => 'text/xml'
                ] : [
                    'Content-type' => 'text/xml',
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.RELIANCE_CV_OCP_APIM_SUBSCRIPTION_KEY_FOR_INSERT_LEAD'),
                    'SOAPAction' => config('constants.IcConstants.reliance.RELIANCE_CV_SOAP_ACTION_FOR_INSERT_LEAD')
                ];

                $headers['Ocp-Apim-Subscription-Key'] = config('IC.RELIANCE.V1.CV.OCP_APIM_SUBSCRIPTION_KEY');

                $get_response = getWsData(config('IC.RELIANCE.V1.CV.END_POINT_URL_LEAD'),
                    $lead_array,
                    'reliance',
                    [
                        'section' => $productData->product_sub_type_code,
                        'method' => 'Lead Creation',
                        'requestMethod' => 'post',
                        'enquiryId' => $enquiryId,
                        'productName' => $productData->product_name,
                        'transaction_type' => 'proposal',
                        'headers' => $headers
                    ]
                );

                $lead_res_data = $get_response['response'];
                if ($lead_res_data) {
                    $lead_response = XmlToArray::convert($lead_res_data);
                    
                    if (isset($lead_response['soap:Body']['InsertLeadResponse']['InsertLeadResult']) && is_numeric($lead_response['soap:Body']['InsertLeadResponse']['InsertLeadResult'])) {
                        $inspection_id = $lead_response['soap:Body']['InsertLeadResponse']['InsertLeadResult'];

                        UserProposal::where('user_proposal_id', $proposal->user_proposal_id)
                            ->update([
                                'customer_id' => $inspection_id
                            ]);
                    }
                    else
                    {
                        if (isset($proposal->customer_id) && $proposal->customer_id != NULL)
                        {
                            $inspection_id = $proposal->customer_id;
                        }
                        else
                        {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => $lead_response['soap:Body']['InsertLeadResponse']['InsertLeadResult'] ?? 'Error in lead creation service'
                            ];
                        }
                    }
                }
                else
                {
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Error in lead creation service'
                    ];
                }

                $wimwisure_case_number = NULL;

                $breakin__data = [
                    'user_proposal_id' => $proposal->user_proposal_id,
                    'ic_id' => $productData->company_id,
                    'breakin_number' => $inspection_id,
                    'breakin_status' => STAGE_NAMES['PENDING_FROM_IC'],
                    'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                    'breakin_check_url' => config('constants.motorConstant.BREAKIN_CHECK_URL'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if (config('constants.IcConstants.reliance.IS_WIMWISURE_RELIANCE_ENABLED') == 'Y')
                {
                    $wimwisure = new WimwisureBreakinController();

                    $payload = [
                        'user_name' => $requestData->vehicle_owner_type == 'C' ? $CorporateName : $ForeName . ' ' . $LastName,
                        'user_email' => $proposal->email,
                        'reg_number' => $registration_number, 
                        'mobile_number' => $proposal->mobile_number,
                        'fuel_type' => strtolower($mmv_data->operated_by),
                        'enquiry_id' => $enquiryId,
                        'inspection_number' => $inspection_id,
                        'section' => 'cv',
                        'chassis_number' => removeSpecialCharactersFromString($proposal->chassis_number),
                        'engine_number' => removeSpecialCharactersFromString($proposal->engine_number),
                        'api_key' => config('constants.wimwisure.API_KEY_RELIANCE')
                    ];

                    $breakin_data = $wimwisure->WimwisureBreakinIdGen($payload);

                    if ($breakin_data)
                    {
                        if ($breakin_data->original['status'] == true && ! is_null($breakin_data->original['data']))
                        {
                            $breakin_data_json = json_decode($breakin_data->original['data']['content']);
                            $wimwisure_case_number = $breakin_data_json->ID;
                
                            $breakin__data['wimwisure_case_number'] = $wimwisure_case_number;
                        }
                        else
                        {
                            return [
                                'status' => false,
                                'webservice_id' => $get_response['webservice_id'],
                                'table' => $get_response['table'],
                                'message' => $breakin_data->original['data']['message'] ?? 'An error occurred while sending data to wimwisure'
                            ];
                        }
                    }
                    else
                    {
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Error in wimwisure breakin service'
                        ];
                    }
                }

                $proposal->is_breakin_case = 'Y';
                $proposal->save();

                CvBreakinStatus::create($breakin__data);
            }

            $od = 0;
            $tp_liability = 0;
            $ll_paid_driver = 0;
            $ll_paid_cleaner = 0;
            $electrical_accessories = 0;
            $non_electrical_accessories = 0;
            $external_lpg_cng = 0;
            $external_lpg_cng_tp = 0;
            $pa_to_paid_driver = 0;
            $cpa_premium = 0;
            $ncb_discount = 0;
            $automobile_association = 0;
            $anti_theft = 0;
            $tppd_discount_amt = 0;
            $ic_vehicle_discount = 0;
            $voluntary_excess = 0;
            $other_discount = 0;
            $imt_23 = 0;
            $nil_depreciation = 0;
            $GeogExtension_od = 0;
            $GeogExtension_tp = 0;
            $total_od_addon = 0;

            if (is_array($response->lstPricingResponse)) {
                foreach ($response->lstPricingResponse as $single) {
                    if (isset($single->CoverageName) && $single->CoverageName == 'PA to Owner Driver') {
                        $cpa_premium = $single->Premium;
                    } elseif (isset($single->CoverageName) && $single->CoverageName == 'NCB') {
                        $ncb_discount = round(abs($single->Premium));
                    } elseif ($single->CoverageName == 'Automobile Association Membership') {
                        $automobile_association = round(abs($single->Premium));
                    } elseif ($single->CoverageName == 'Anti-Theft Device') {
                        $anti_theft = round(abs($single->Premium));
                    } elseif ($single->CoverageName == 'TPPD') {
                        $tppd_discount_amt = round(abs($single->Premium));
                    }
                    elseif ($single->CoverageName == 'Basic OD')
                    {
                        $od = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Basic Liability')
                    {
                        $tp_liability = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Liability to Paid Driver')
                    {
                        $ll_paid_driver = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Liability to Cleaner')
                    {
                        $ll_paid_cleaner = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Electrical Accessories')
                    {
                        $electrical_accessories = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Non Electrical Accessories')
                    {
                        $non_electrical_accessories = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Bifuel Kit')
                    {
                        $external_lpg_cng = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Bifuel Kit TP')
                    {
                        $external_lpg_cng_tp = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'PA to Paid Driver')
                    {
                        $pa_to_paid_driver = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'IMT 23(Lamp/ tyre tube/ Headlight etc )')
                    {
                        $imt_23 = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Nil Depreciation')
                    {
                        $nil_depreciation = round($single->Premium);
                    }
                    elseif (($single->CoverageName == 'Geographical Extension' || $single->CoverageName == 'Geo Extension')&& $single->CoverID == '5')
                    {
                        $GeogExtension_od = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Geographical Extension' && $single->CoverID == '6')
                    {
                        $GeogExtension_tp = round($single->Premium);
                    }
                    elseif ($single->CoverageName == 'Total OD and Addon')
                    {
                        $total_od_addon = abs( (int) $single->Premium);
                    }
                }
            } else {
                $tp_liability = $response->lstPricingResponse->Premium;
            }

            $NetPremium = $response->NetPremium;
            $final_total_discount = $ncb_discount + $anti_theft + $automobile_association + $voluntary_excess + $ic_vehicle_discount + $other_discount + $tppd_discount_amt;
            $total_od_amount = ($od + $GeogExtension_od) - $final_total_discount + $tppd_discount_amt;
            $total_tp_amount = $tp_liability + $ll_paid_driver + $ll_paid_cleaner + $external_lpg_cng_tp + $pa_to_paid_driver + $cpa_premium - $tppd_discount_amt + $GeogExtension_tp;
            $total_addon_amount = $electrical_accessories + $non_electrical_accessories + $external_lpg_cng + $imt_23 + $nil_depreciation;
            $final_payable_amount = $response->FinalPremium;

            $ic_vehicle_details = [
                'manufacture_name' => $mmv_data->make_name,
                'model_name' => $mmv_data->model_name,
                'version' => $mmv_data->variance,
                'fuel_type' => $mmv_data->operated_by,
                'seating_capacity' => $mmv_data->seating_capacity,
                'carrying_capacity' => $mmv_data->carrying_capacity,
                'cubic_capacity' => $mmv_data->cc,
                'gross_vehicle_weight' => $mmv_data->gross_weight,
                'vehicle_type' => $mmv_data->veh_type_name,
            ];

            $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $proposal->user_proposal_id)
                ->update([
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'proposal_no' => time(),//$response->ProposalNo, // we are not using proposal_no from this service as our final proposal_no(we are submiting proposal request at the time of payment so proposal no get changed if we submit proposal here)
                    'od_premium' => $total_od_amount,
                    'tp_premium' => round($total_tp_amount),
                    'ncb_discount' => round($ncb_discount), 
                    'addon_premium' => round($total_addon_amount),
                    'cpa_premium' => round($cpa_premium),
                    'total_discount' => round($final_total_discount),
                    'total_premium' => round($NetPremium),
                    'service_tax_amount' => round($final_payable_amount - $NetPremium),
                    'final_payable_amount' => round($final_payable_amount),
                    'product_code' => $productCode,
                    'ic_vehicle_details' => json_encode($ic_vehicle_details),
                    'additional_details_data' => json_encode($response),
                    'tp_start_date' =>  date('d-m-Y', strtotime($tp_start_date)),
                    'tp_end_date' =>  date('d-m-Y', strtotime($tp_end_date)),
                ]);

            updateJourneyStage([
                'user_product_journey_id' => $enquiryId,
                'ic_id' => $productData->company_id,
                'stage' => $is_breakin == 'Y' ? STAGE_NAMES['INSPECTION_PENDING'] : STAGE_NAMES['PROPOSAL_ACCEPTED'],
                'proposal_id' => $proposal->user_proposal_id
            ]);

            $user_proposal_data = UserProposal::where('user_product_journey_id',$enquiryId)
                ->where('user_proposal_id',$proposal->user_proposal_id)
                ->select('*')
                ->first();

            $proposal_data = $user_proposal_data;

            $finsall = new \App\Http\Controllers\Finsall\FinsallController();
            $finsall->checkFinsallAvailability('reliance', 'cv', $premium_type, $proposal);
            
            return response()->json([
                'status' => true,
                'msg' => "Proposal Submitted Successfully!",
                'webservice_id' => $get_response['webservice_id'],
                 'table' => $get_response['table'],
                'data' => [
                    'proposalId' => $proposal_data->user_proposal_id,
                    'userProductJourneyId' => $proposal_data->user_product_journey_id,
                    'proposalNo' => $proposal_data->proposal_no,
                    'finalPayableAmount' => $proposal_data->final_payable_amount,
                    'is_breakin' => $is_breakin,
                    'inspection_number' => $inspection_id
                ]
            ]);
        }
        else
        {
            return [
                'premium_amount' => 0,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'status'         => false,
                'message'        => $response->ErrorMessages
            ];
        }
    }

    public static function getPremiumRequestData($data)
    {
        extract($data);

        $idv = $quote_log_data->idv;

        $parent_id = get_parent_code($productData->product_sub_type_id);

        $TypeOfFuel = [
            'petrol' => '1',
            'diesel' => '2',
            'cng' => '3',
            'lpg' => '4',
            'bifuel' => '5',
            'battery operated' => '6',
            'none' => '0',
            'na' => '7',
        ];

        $NCB_ID = [
            '0' => '0',
            '20' => '1',
            '25' => '2',
            '35' => '3',
            '45' => '4',
            '50' => '5'
        ];

        
        $date_difference = $requestData->previous_policy_expiry_date == 'New' ? 0 : get_date_diff('day', $requestData->previous_policy_expiry_date);

        if ($requestData->is_claim == 'Y') {
            $PreviousNCB = $NCB_ID[$requestData->previous_ncb];
            $IsNCBApplicable = 'false';
            $IsClaimedLastYear = 'true';
            $NCBEligibilityCriteria = '1';
        } else {
            $PreviousNCB = $NCB_ID[$requestData->previous_ncb];
            $IsNCBApplicable = 'true';
            $IsClaimedLastYear = 'false';
            $NCBEligibilityCriteria = $requestData->previous_policy_type == 'Third-party' || ($requestData->business_type == 'breakin' && $date_difference > 90) || $requestData->previous_policy_expiry_date == 'New' ? '1' : '2';
        }

        $premium_type = DB::table('master_premium_type')
            ->where('id',$productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $tp_only = ($premium_type == 'third_party' || $premium_type == 'third_party_breakin') ? 'true' : 'false';

        if ($requestData->business_type == 'newbusiness') {
            $PrevYearPolicyStartDate = '';
            $PrevYearPolicyEndDate = '';
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date)));
            $Registration_Number = 'NEW';
            $BusinessType = '1';
            $ISNewVehicle = 'true';
            $PrevYearPolicyType = '0';
            $PreviousNCB = 0;
            $previous_insurance_details = [];
        } else {
            // $BusinessType = $requestData->previous_policy_type == 'Not sure' ? '6' : '5';//6 means ownership change
            $BusinessType = '5';
            $ISNewVehicle = 'false';
            $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));

            if(strtoupper($requestData->previous_policy_type) == 'COMPREHENSIVE' && $requestData->prev_short_term == '1')
            {
                $additional_details = json_decode($proposal->additional_details);
                $PrevYearPolicyStartDate = date('Y-m-d', strtotime($additional_details->prepolicy->previousPolicyStartDate));
            }
            else
            {
                $PrevYearPolicyStartDate = date('Y-m-d', strtotime('-1 year +1 day', strtotime($requestData->previous_policy_expiry_date)));
            }

            $PrevYearPolicyEndDate = date('Y-m-d', strtotime($requestData->previous_policy_expiry_date));
            $previousInsurerCode = DB::table('previous_insurer_lists')
            ->where('name', $proposal->insurance_company_name)
            ->where('company_alias', "reliance")
            ->pluck('code')
            ->first();

            $previous_insurance_details = [
                'PrevInsuranceID' => '',
                'IsVehicleOfPreviousPolicySold' => 'false',
                'IsNCBApplicable' => $IsNCBApplicable,
                'PrevYearInsurer' => $previousInsurerCode,
                'PrevYearPolicyNo' => $proposal->previous_policy_number,// CHAGING PrevYearPolicyNo to PolicyNo AS PR IC GIT ID 12461
                // 'PolicyNo' => $proposal->previous_policy_number,// CHAGING PrevYearPolicyNo to PolicyNo AS PR IC GIT ID 12461
                'PrevYearInsurerAddress' => $proposal->previous_insurer_address.' '.$proposal->previous_insurer_pin,
                'DocumentProof' => '',
                'PrevPolicyPeriod' => '1',
                'PrevYearPolicyType' => $requestData->previous_policy_type == 'Third-party' ? '2' : '1',
                'PrevYearPolicyStartDate' => $PrevYearPolicyStartDate,
                'MTAReason' => '',
                'IsPreviousPolicyDetailsAvailable' => ($requestData->previous_policy_type == 'Not sure' ? 'false' : 'true'),
                'PrevYearPolicyEndDate' => $PrevYearPolicyEndDate,
                'PrevYearNCB' => $requestData->previous_ncb,
                'IsInspectionDone' => $proposal->is_inspection_done == 'Y' ? 'true' : 'false',
                'InspectionDate' => $breakin_details->inspection_date ?? '',
                'Inspectionby' => '',
                'InspectorName' => '',
                'IsNCBEarnedAbroad' => 'false',
                'ODLoading' => '',
                'IsClaimedLastYear' => $IsClaimedLastYear,
                'ODLoadingReason' => '',
                'PreRateCharged' => '',
                'PreSpecialTermsAndConditions' => '',
                'IsTrailerNCB' => 'false',
                'InspectionID' => $breakin_details->breakin_number ?? '',
            ];

            if (strtoupper($requestData->previous_policy_type) == 'NOT SURE') {
                $previous_insurance_details['PrevYearInsurer'] = '';
                $previous_insurance_details['PrevYearPolicyNo'] = '';
                $previous_insurance_details['PrevYearInsurerAddress'] = '';
                $previous_insurance_details['PrevYearPolicyType'] = '';
                $previous_insurance_details['PrevYearPolicyStartDate'] = '';
                $previous_insurance_details['PrevYearPolicyEndDate'] = '';
            }

            $PrevYearPolicyType = '';
        }

        if ($requestData->business_type == 'rollover') {
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date)));
        } else {
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d');
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date)));
        }

        if ( in_array($premium_type, ['short_term_3', 'short_term_3_breakin']))
        {   
            $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : $NCBEligibilityCriteria;
            $policy_end_date = date('Y-m-d', strtotime('-1 days + 3 months', strtotime($policy_start_date)));
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date = date('d-m-Y', strtotime($policy_end_date));
        }
        elseif ( in_array($premium_type, ['short_term_6', 'short_term_6_breakin']))
        {   
            $NCBEligibilityCriteria = ($requestData->is_claim == 'Y') ? '1' : $NCBEligibilityCriteria;
            $policy_end_date = date('Y-m-d', strtotime('-1 days + 6 months', strtotime($policy_start_date)));
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date = date('d-m-Y', strtotime($policy_end_date));
        }
        else
        {
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            $tp_start_date = date('d-m-Y', strtotime($policy_start_date)) ;
            $tp_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($tp_start_date)));
        }

        $IsVehicleHypothicated = ($proposal->is_vehicle_finance == '1') ? 'true' : 'false';

        $selected_addons = DB::table('selected_addons')
            ->where('user_product_journey_id', $enquiryId)
            ->first();

         //PA for un named passenger
        $IsPAToUnnamedPassengerCovered = 'false';
        $PAToUnNamedPassenger_IsChecked = '';
        $PAToUnNamedPassenger_NoOfItems = '';
        $PAToUnNamedPassengerSI = 0;

        //additional Paid Driver
        $IsPAToDriverCovered = 'false';
        $PAToPaidDriver_IsChecked = 'false';
        $PAToPaidDriver_NoOfItems = '1';
        $PAToPaidDriver_SumInsured = '0';

        $IsLiabilityToPaidDriverCovered = 'false';
        $LiabilityToPaidDriver_IsChecked = 'false';
        $LiabilityToPaidDriver_NoOfItems = '0';
        $IsLiabilityToPaidCleanerCovered = 'false';
        $LiabilityToPaidCleaner_IsChecked = 'false';
        $LiabilityToPaidCleaner_NoOfItems = '0';
        $is_geo_ext = false;

        if ($selected_addons && $selected_addons->additional_covers != NULL && $selected_addons->additional_covers != '') {
            $additional_covers = json_decode($selected_addons->additional_covers);

            foreach ($additional_covers as $value) {
                if ($value->name == 'PA cover for additional paid driver' || $value->name == 'PA paid driver/conductor/cleaner') {
                    $IsPAToDriverCovered = 'true';
                    $PAToPaidDriver_IsChecked = 'true';
                    $PAToPaidDriver_NoOfItems = '1';
                    $PAToPaidDriver_SumInsured = $value->sumInsured;
                }

                if($value->name == 'Unnamed Passenger PA Cover') {
                    $IsPAToUnnamedPassengerCovered = 'true';
                    $PAToUnNamedPassenger_IsChecked = 'true';
                    $PAToUnNamedPassenger_NoOfItems = '1';
                    $PAToUnNamedPassengerSI = $value->sumInsured;
                }

                if ($value->name == 'LL paid driver') {
                    $IsLiabilityToPaidDriverCovered = 'true';
                    $LiabilityToPaidDriver_IsChecked = 'true';
                    $LiabilityToPaidDriver_NoOfItems = 1;
                }

                if ($value->name == 'LL paid driver/conductor/cleaner') {
                    $IsLiabilityToPaidDriverCovered = in_array('DriverLL', $value->selectedLLpaidItmes) ? 'true' : 'false';
                    $LiabilityToPaidDriver_IsChecked = in_array('DriverLL', $value->selectedLLpaidItmes) ? 'true' : 'false';
                    $LiabilityToPaidDriver_NoOfItems = $value->LLNumberDriver ?? 0;
                    $IsLiabilityToPaidCleanerCovered = in_array('CleanerLL', $value->selectedLLpaidItmes) ? 'true' : 'false';
                    $LiabilityToPaidCleaner_IsChecked = in_array('CleanerLL', $value->selectedLLpaidItmes) ? 'true' : 'false';
                    $LiabilityToPaidCleaner_NoOfItems = $value->LLNumberCleaner ?? 0;
                }
                if ($value->name == 'Geographical Extension') {
                    $country = $value->countries;
                    $is_geo_ext = true;
                }
            }
        }

        $isIMT23selected = 'false';

        if ($selected_addons && $selected_addons->applicable_addons != NULL && $selected_addons->applicable_addons != '') {
            $applicable_addons = json_decode($selected_addons->applicable_addons);

            foreach ($applicable_addons as $value) {
                if ($value->name == 'IMT - 23') {
                    $isIMT23selected = 'true';
                }
            }
        }

        $is_tppd = 'false';
        $is_anti_theft_device = 'false';

        if ($selected_addons && $selected_addons->discounts != NULL && $selected_addons->discounts != '') {
            $discounts = json_decode($selected_addons->discounts);

            foreach ($discounts as $value) {
                if ($value->name == 'TPPD Cover') {
                    $is_tppd = 'true';
                } elseif ($value->name == 'anti-theft device') {
                    $is_anti_theft_device = 'true';
                }
            }
        }

        $IsElectricalItemFitted = 'false';
        $ElectricalItemsTotalSI = 0;

        $IsNonElectricalItemFitted = 'false';
        $NonElectricalItemsTotalSI = 0;

        $is_bifuel_kit = 'true';

        if (in_array(strtolower($mmv_data->operated_by), ['petrol+cng', 'petrol+lpg'])) {
            $type_of_fuel = '5';
            $bifuel = 'true';
            $Fueltype = 'CNG';
        } else {
            $type_of_fuel = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? '5' : $TypeOfFuel[strtolower($mmv_data->operated_by)];
            $bifuel = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? 'true' : 'false';
            $Fueltype = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? $mmv_data->operated_by : '';
            $is_bifuel_kit = in_array(strtolower($mmv_data->operated_by), ['cng', 'lpg']) ? 'true' : 'false';
        }

        $BiFuelKitSi = 0;

        if ($selected_addons && $selected_addons->accessories != NULL && $selected_addons->accessories != '') {
            $accessories = json_decode($selected_addons->accessories);
            foreach ($accessories as $value) {
                if ($value->name == 'Electrical Accessories') {
                    $IsElectricalItemFitted = 'true';
                    $ElectricalItemsTotalSI = $value->sumInsured;
                } elseif ($value->name == 'Non-Electrical Accessories') {
                    $IsNonElectricalItemFitted = 'true';
                    $NonElectricalItemsTotalSI = $value->sumInsured;
                } elseif ($value->name == 'External Bi-Fuel Kit CNG/LPG') {
                    $type_of_fuel = '5';
                    $Fueltype = 'CNG';
                    $BiFuelKitSi = $value->sumInsured;
                    $is_bifuel_kit = 'true';
                }
            }
        }

        $cpa_selected = 'false';

        if ($selected_addons && $selected_addons->compulsory_personal_accident != NULL && $selected_addons->compulsory_personal_accident != '') {
            $addons = json_decode($selected_addons->compulsory_personal_accident);

            foreach ($addons as $value) {
                if(isset($value->name) && ($value->name == 'Compulsory Personal Accident'))
                {
                    $cpa_selected = 'true';
                }
            }
        }
        
        $address_data = [
                    'address' => $proposal->address_line1,
                    'address_1_limit'   => 250,
                    'address_2_limit'   => 250            
                ];
        $getAddress = getAddress($address_data);

        if ($requestData->vehicle_owner_type == 'I') {
            $ClientType = '0';
            $IsPAToOwnerDriverCoverd = $cpa_selected;
            $NomineeName = $proposal->nominee_name;
            $NomineeRelationship = $proposal->nominee_relationship;
            $NomineeAddress = trim($getAddress['address_1'].' '.$getAddress['address_2'].' '.$getAddress['address_3']);
            $NomineeDOB = date('Y-m-d', strtotime($proposal->nominee_dob));
            $Salutation = ($proposal->title == '1') ? 'Mr.' : 'Ms.';
            $ForeName = $proposal->first_name;
            $LastName =  ! empty($proposal->last_name) ? $proposal->last_name : '.';
            $CorporateName = '';
            $OccupationID = $proposal->occupation;
            $DOB = date('Y-m-d', strtotime($proposal->dob));
            $Gender = $proposal->gender;
            $MaritalStatus = $proposal->marital_status == 'Single' ? '1952' : '1951';

            $IsChecked = 'true';
            $NoOfItems = '1';
            $CPAcovertenure = '1';

            $IsHavingValidDrivingLicense = '';
        } else {
            $ClientType = '1';
            $IsPAToOwnerDriverCoverd = 'false';
            $IsHavingValidDrivingLicense = '';
            $IsOptedStandaloneCPAPolicy = '';
            $NomineeName = '';
            $NomineeDOB = '';
            $NomineeRelationship = '';
            $NomineeAddress = '';
            $OtherRelation = '';
            $Salutation = 'M/S';
            $ForeName = '';
            $LastName = '';
            $CorporateName = $proposal->first_name;
            $OccupationID = '';
            $Occupation = '';
            $Gender = '';
            $DOB = '';
            $MaritalStatus = '';
            $IsChecked = 'false';
            $NoOfItems = '';
            $CPAcovertenure = '';
        }

        if ($requestData->vehicle_owner_type == 'I' && $selected_addons->compulsory_personal_accident) {
            $cpa = json_decode($selected_addons->compulsory_personal_accident);

            if (isset($cpa[0]->reason) && $cpa[0]->reason != '') {
                $IsChecked = 'false';
                $NoOfItems = '';
                $CPAcovertenure = '';
                $NomineeName = '';
                $NomineeDOB = '';
                $NomineeRelationship = '';
                $NomineeAddress = '';
            }
        }

        $IsVoluntaryDeductableOpted = 'false';
        $VoluntaryDeductible = '';

        if($productData->product_sub_type_code == 'TAXI')
        {
            if($mmv_data->carrying_capacity <= 6)
            {
                $productCode = $tp_only == 'true' ? '2353' : '2338';
            }
            else
            {
                $productCode = $tp_only == 'true' ? '2355' : '2340';
            }
        } elseif ($productData->product_sub_type_code == 'AUTO-RICKSHAW' || $productData->product_sub_type_code == 'ELECTRIC-RICKSHAW') {
            $productCode = $tp_only == 'true' ? '2354' : '2339';
        }
        elseif ($productData->product_sub_type_code == 'MISCELLANEOUS-CLASS')
        {
            $productCode = $tp_only == 'true' ? '2358' : '2343';
        }
        else {
            if ($requestData->gcv_carrier_type == 'PUBLIC') {
                if ($mmv_data->wheels > 3) {
                    $productCode = $tp_only == 'true' ? '2349' : '2334';
                } elseif ($mmv_data->wheels == 3) {
                    $productCode = $tp_only == 'true' ? '2351' : '2336';
                }
            } else {
                if ($mmv_data->wheels > 3) {
                    $productCode = $tp_only == 'true' ? '2350' : '2335';
                } elseif ($mmv_data->wheels == 3) {
                    $productCode = $tp_only == 'true' ? '2352' : '2337';
                }
            }
        }

        $vehicledate = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $DateOfPurchase = date('d-m-Y', strtotime($vehicledate));
        $vehicle_register_date = date('d-m-Y', strtotime($requestData->vehicle_register_date));
        $vehicle_manf = explode('-', $proposal->vehicle_manf_year);
        $user_proposal_data = UserProposal::where('user_product_journey_id', $data['enquiryId'])
        ->where('user_proposal_id', $data['proposal']['user_proposal_id'])
        ->select('*')
        ->first();

       

        $is_pos_enabled = config('constants.motorConstant.IS_POS_ENABLED');
        $pos_testing_mode = config('constants.motor.reliance.IS_POS_TESTING_MODE_ENABLE_RELIANCE');
        $posp_type = '';
        $posp_pan_number = '';
        $posp_aadhar_number = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos_enabled == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if ($pos_data) {
                $posp_type = '2';
                $posp_pan_number = $pos_data->pan_no;
                $posp_aadhar_number = $pos_data->aadhar_no;
            }

            if ($pos_testing_mode === 'Y')
            {
                $posp_type = '2';
                $posp_pan_number = 'ASDFC4242K';
                $posp_aadhar_number = '339066355663';
            }
        }
        elseif ($pos_testing_mode === 'Y')
        {
            $posp_type = '2';
            $posp_pan_number = 'ASDFC4242K';
            $posp_aadhar_number = '339066355663';
        }

        $gcv_vehicle_sub_classes = [
            'TRUCK' => 1,
            'DUMPER/TIPPER' => 2,
            'TANKER/BULKER' => 4,
            'PICK UP/DELIVERY/REFRIGERATED VAN' => 5,
            'TRACTOR' => 9
        ];
       
        $misc_vehicles_sub_classes = [
            'AGRICULTURAL TRACTORS' => 48,
            'AGRICULTURE' => 47,
            'AMBULANCES' => 9,
            'ANGLE DOZERS' => 52,
            'ANTI MALARIAL VANS' => 53,
            'BREAKDOWN VEHICLES' => 54,
            'BULLDOZERS, BULLGRADERS' => 55,
            'CASH VAN' => 86,
            'CEMENT BULKER' => 41,
            'CINEMA FILM RECORDING AND PUBLICITY VANS' => 36,
            'CINEMA FILM RECORDING AND PUBLICITY VANS' => 18,
            'COMPRESSORS' => 49,
            'CPM' => 89,
            'CRANES' => 25,
            'CRANES' => 15,
            'DELIVERY TRUCKS PEDESTRAIN CONTROLLED' => 56,
            'DISPENSARIES' => 57,
            'DRAGLINE EXCAVATORS' => 58,
            'DRILLING RIGS' => 34,
            'DUMPERS' => 1,
            'DUST CARTS WATER CARTS ROAD SWEEPER AND TOWER WAGONS' => 81,
            'ELECTRIC DRIVEN GOODS VEHICLES' => 59,
            'ELECTRIC TROLLEYS OR TRACTORS' => 26,
            'EXCAVATORS' => 21,
            'FIRE BRIGADE AND SALVAGE CORPS VEHICLE' => 60,
            'FIRE FIGHTER' => 40,
            'FOOTPATH ROLLERS' => 61,
            'FORK LIFT TRUCKS' => 22,
            'GARBAGE VAN' => 37,
            'GRABS' => 62,
            'GRITTING MACHINES' => 63,
            'HARVESTER' => 32,
            'HEARSES' => 20,
            'HORSE BOXES' => 64,
            'LADDER CARRIER CARTS' => 65,
            'LAWN MOWERS' => 66,
            'LETOURNA DOZERS' => 67,
            'LEVELLERS' => 68,
            'MECHANICAL NAVVIES, SHOVELS, GRABS AND EXCAVATORS' => 69,
            'MILITARY TEA VANS' => 70,
            'MILK VANS (INSULATED)' => 16,
            'MOBILE CONCRETE MIXER' => 50,
            'MOBILE PLANT' => 31,
            'MOBILE SHOPS AND CANTEENS' => 71,
            'MOBILE SURGERIES AND DISPENSARIES' => 51,
            'OIL FILTERATION MACHINE' => 46,
            'OTHERS' => 90,
            'PLANE LOADERS AND OTHER VEHICLES' => 28,
            'PLANE LOADERS AND OTHER VEHICLES' => 44,
            'POWER TILLER' => 43,
            'PRE-MIX LAYING EQUIPMENT' => 23,
            'PRISON VANS' => 72,
            'RECOVERY VAN' => 42,
            'REFRIGERATION/PRE-COOLING UNIT' => 35,
            'RIPPERS' => 82,
            'ROAD ROLLERS' => 27,
            'ROAD SCRAPPING, SURFACING AND PRE-MIX LAYING EQUIPMENT' => 83,
            'ROAD SPRINKLERS USED ALSO AS FIRE FIGHTING VEHICLES' => 84,
            'ROAD SWEEPERS' => 24,
            'SCIENTIFIC VANS' => 73,
            'SCRAPERS' => 74,
            'SELF PROPELLED COMBINED HARVESTORS' => 29,
            'SHEEP FOOT TAMPING ROLLER' => 75,
            'SHOVELS' => 76,
            'SITE CLEARING AND LEVELLING PLANT' => 77,
            'SPRAYING PLANT' => 78,
            'TANKER' => 2,
            'TAR SPRAYERS (SELF PROPELLED)' => 79,
            'TIPPERS' => 3,
            'TOWER WAGONS' => 85,
            'TRACTORS' => 19,
            'TRAILER' => 4,
            'TRANSIT MIXER' => 45,
            'TRIAL BUILDERS, TREE DOZERS' => 80,
            'TROLLEYS AND GOODS CARRYING TRACTORS' => 17,
            'TYRE HANDLER' => 39,
            'VIBRATORY SOIL COMPACTOR' => 38,
            'WATER CARTS' => 30,
            'WATER SPRINKLER' => 33 
        ];

        $rto_code = $requestData->rto_code;
        $registration_number = $proposal->vehicale_registration_number;

        $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
            $registration_number,
            $rto_code,
            $requestData->business_type == 'newbusiness'
        );

        if (!$rcDetails['status']) {
            return $rcDetails;
        }

        $registration_number = $rcDetails['rcNumber'];
        $rto_data = $rcDetails['rtoData'];

        // $registration_number = $proposal->vehicale_registration_number; 
        // $registration_number = explode('-', $registration_number); 
        // if($requestData->business_type == 'newbusiness')
        // {
        //     $registration_number = 'NEW';
        // }
        // else if ($registration_number[0] == 'DL') { 
        //     $registration_no = RtoCodeWithOrWithoutZero($registration_number[0].$registration_number[1],true);  
        //     $registration_number = $registration_no.'-'.(count($registration_number) == 3 ? "" : $registration_number[2]).'-'.(count($registration_number) == 3 ? $registration_number[2] : $registration_number[3]); 
        // } else { 
        //     $registration_number = getRegisterNumberWithHyphen($proposal->vehicale_registration_number); 
        // } 
        
        $premium_req_array = [
            'ClientDetails' => [
                'ClientType' => $ClientType,
            ],
            'Policy' => [
                'BusinessType' => $BusinessType,
                'AgentCode' => 'Direct',
                'AgentName' => 'Direct',
                'Branch_Name' => 'Direct',
                'Cover_From' => $policy_start_date,
                'Cover_To' => $policy_end_date,
                'Branch_Code' => config('IC.RELIANCE.V1.CV.BRANCH_CODE'),//'9202',
                'productcode' => $productCode,
                'OtherSystemName' => '1',
                'isMotorQuote' => ($tp_only == 'true') ? 'false' : 'true',
                'isMotorQuoteFlow' => ($tp_only == 'true') ? 'false' : 'true',
                'POSType' => $posp_type,
                'POSAadhaarNumber' => $posp_aadhar_number,
                'POSPANNumber' => $posp_pan_number                
            ],
            'Risk' => [
                'VehicleMakeID' => $mmv_data->make_id_pk,
                'VehicleModelID' => $mmv_data->model_id_pk,
                'StateOfRegistrationID' => $rto_data->state_id_fk,
                'RTOLocationID' => $rto_data->model_region_id_pk,
                'Rto_RegionCode' => $rto_data->region_code,
                'ExShowroomPrice' => isset($mmv_data->mfg_buildin) && $mmv_data->mfg_buildin == 'No' ? (int)$mmv_data->body_price + (int)$mmv_data->chassis_price : ($mmv_data->ex_showroom_price ?? 0),
                'IDV' => ($tp_only == 'true') ? 0 : (isset($mmv_data->mfg_buildin) && $mmv_data->mfg_buildin == 'No' ? 0 : $idv),
                'DateOfPurchase' => $DateOfPurchase,
                'ManufactureMonth' => $vehicle_manf[0],
                'ManufactureYear' => $vehicle_manf[1] ?? '',
                'EngineNo' => removeSpecialCharactersFromString($proposal->engine_number),
                'Chassis' => removeSpecialCharactersFromString($proposal->chassis_number),
                'IsRegAddressSameasCommAddress' => 'true',
                'IsRegAddressSameasPermanentAddress' => 'true',
                'IsPermanentAddressSameasCommAddress' => 'true',
                'VehicleVariant' => $mmv_data->variance,
                'IsVehicleHypothicated' => $IsVehicleHypothicated,
                'FinanceType' => ($proposal->is_vehicle_finance == '1') ? $proposal->financer_agreement_type : '', //FinanceType value changes to numeric as per kit , old value true/false
                'FinancierName' => ($proposal->is_vehicle_finance == '1') ?  $proposal->name_of_financer: '',
                'FinancierAddress' => ($proposal->is_vehicle_finance == '1') ?  $proposal->hypothecation_city: '',
		        'IsHavingValidDrivingLicense' => ($IsPAToOwnerDriverCoverd == 'false') ? $IsHavingValidDrivingLicense : '',
                'IsOptedStandaloneCPAPolicy' => ($IsPAToOwnerDriverCoverd == 'false') ? (($IsHavingValidDrivingLicense == 'true') ? $IsOptedStandaloneCPAPolicy : '') : '',
            ],
            'Vehicle' => [
                'TypeOfFuel' => $type_of_fuel,
                'ISNewVehicle' => $ISNewVehicle,
                'Registration_Number' => $registration_number, 
                'Registration_date' => $vehicle_register_date,
                'MiscTypeOfVehicleID' => '',
                'PCVVehicleCategory' => $proposal->vehicle_category,
                'PCVVehicleUsageType' => $proposal->vehicle_usage_type
                // 'PCVVehicleSubUsageType' => '6'
            ],
            'Cover' => [
                'IsPAToUnnamedPassengerCovered' => $IsPAToUnnamedPassengerCovered,
                'IsVoluntaryDeductableOpted' => $IsVoluntaryDeductableOpted,
                'IsElectricalItemFitted' => $IsElectricalItemFitted,
                'ElectricalItemsTotalSI' => $ElectricalItemsTotalSI,
                'IsPAToOwnerDriverCoverd' => $IsPAToOwnerDriverCoverd,
		        'IsLiabilityToPaidDriverCovered' => $IsLiabilityToPaidDriverCovered,
                'IsTPPDCover' => $is_tppd,
                'IsBasicODCoverage' => ($tp_only == 'true') ? 'false' : 'true',
                'IsBasicLiability' => 'true',
                'IsNonElectricalItemFitted' => $IsNonElectricalItemFitted,
                'NonElectricalItemsTotalSI' => $NonElectricalItemsTotalSI,
                'IsPAToDriverCovered' => $IsPAToDriverCovered,
                'IsBiFuelKit' => $is_bifuel_kit,
                'BiFuelKitSi' => $BiFuelKitSi,
                'IsBifuelTypeChecked' => $bifuel,
                'IsInsurancePremium' => 'true',
                'VoluntaryDeductible' => [
                    'VoluntaryDeductible' => ['SumInsured' => $VoluntaryDeductible],
                ],
                'IsAntiTheftDeviceFitted' => $is_anti_theft_device,
                'IsAutomobileAssociationMember' => 'false',
                'AutomobileAssociationName' => '',
                'AutomobileAssociationNo' => '',
                'AutomobileAssociationExpiryDate' => '',
		        'PACoverToOwnerDriver' => '1',
                'PACoverToOwner' => [
                    'PACoverToOwner' => [
                        'IsChecked' => $IsChecked,
                        'NoOfItems' => $NoOfItems,
                        'CPAcovertenure' => $CPAcovertenure,
                        'PackageName' => '',
                        'NomineeName' => $NomineeName,
                        'NomineeDOB' => $NomineeDOB,
                        'NomineeRelationship' => $NomineeRelationship,
                        'NomineeAddress' => $NomineeAddress,
                        'AppointeeName' => '',
                        'OtherRelation' => '',
                    ],
                ],
                'PAToUnNamedPassenger' => [
                    'PAToUnNamedPassenger' => [
                        'IsChecked' => $PAToUnNamedPassenger_IsChecked,
                        'NoOfItems' => $PAToUnNamedPassenger_NoOfItems,
                        'SumInsured' => $PAToUnNamedPassengerSI,
                    ],
                ],
                'PAToPaidDriver' => [
                    'PAToPaidDriver' => [
                        'IsChecked' => $PAToPaidDriver_IsChecked,
                        'NoOfItems' => $PAToPaidDriver_NoOfItems,
                        'SumInsured' => $PAToPaidDriver_SumInsured,
                    ],
                ],
                'LiabilityToPaidDriver' => [
                    'LiabilityToPaidDriver' => [
                        'IsMandatory' => 'true',
                        'IsChecked' => $LiabilityToPaidDriver_IsChecked,
                        'NoOfItems' => $LiabilityToPaidDriver_NoOfItems,
                        'PackageName' => '',
                        'PolicyCoverID' => '',
                    ]
                ],
                'BifuelKit' => [
                    'BifuelKit' => [
                        'IsChecked' => 'false',
                        'IsMandatory' => 'false',
                        'PolicyCoverDetailsID' => '',
                        'Fueltype' => $Fueltype,
                        'ISLpgCng' => $bifuel,
                        'PolicyCoverID' => '',
                        'SumInsured' => $BiFuelKitSi,
                        'NoOfItems' => '',
                        'PackageName' => '',
                    ],
                ],
            ],
            'PreviousInsuranceDetails' => $previous_insurance_details,
            'NCBEligibility' => [
                'NCBEligibilityCriteria' => $NCBEligibilityCriteria,
                'NCBReservingLetter' => '',
                'PreviousNCB' => $PreviousNCB,
            ],
            'ProductCode' => $productCode,
            'UserID' => config('IC.RELIANCE.V1.CV.USERID'),
            'SourceSystemID' => config('IC.RELIANCE.V1.CV.SOURCE_SYSTEM_ID'),
            'AuthToken' => config('IC.RELIANCE.V1.CV.AUTH_TOKEN'),
            'IsQuickquote' => ($tp_only == 'true') ? 'false' : 'true',
        ];
        $thirdParty =  in_array($premium_type, ['third_party', 'third_party_breakin']) ? true : false; 
        // $idvCalculation = (in_array($mmv_data->fyntune_version['vehicle_built_up'], ['PARTIALLY BUILT', 'Partial Built', 'Partial Built Vehicle'])) && (!empty($proposal->additional_details_data)); 
        if(in_array($productCode, ['2350','2349']) && $tp_only == 'true')
        {
            if ($mmv_data->wheels == 3) {
                $premium_req_array['UserID'] = config('IC.RELIANCE.V1.CV.3W_TP_USERID');
                $premium_req_array['SourceSystemID'] = config('IC.RELIANCE.V1.CV.3W_TP_SOURCE_SYSTEM_ID');
                $premium_req_array['AuthToken'] = config('IC.RELIANCE.V1.CV.3W_TP_AUTH_TOKEN');
            }
        }
        if ($NomineeName == '') {
            unset($premium_req_array['Cover']['PACoverToOwner']['PACoverToOwner']['NomineeName']);
        }

        if ($requestData->business_type == 'newbusiness') {
            unset($premium_req_array['PreviousInsuranceDetails']);
        }

        $premium_req_array['Cover']['IsImt23LampOrTyreTubeOrHeadlightCover'] = $isIMT23selected;

        if ($parent_id == 'GCV' || $parent_id == 'MISCELLANEOUS-CLASS') {
            $premium_req_array['Cover']['IsLiabilitytoCleaner'] = $IsLiabilityToPaidCleanerCovered;

            $premium_req_array['Cover']['LegalLiabilitytoCleaner']['LegalLiabilitytoCleaner'] = [
                'isChecked' => $LiabilityToPaidCleaner_IsChecked,
                'NoOfItems' => $LiabilityToPaidCleaner_NoOfItems
            ];

            if ($parent_id == 'GCV')
            {
                $premium_req_array['Vehicle']['GCVGoodTypeOfVehicleID'] = 2;//2 for non-hazardeous
                $premium_req_array['Vehicle']['GCVSubTypeOfVehicleID'] = $gcv_vehicle_sub_classes[$productData->product_sub_type_code];
                $premium_req_array['Vehicle']['GrossVehicleWeight'] = $requestData->selected_gvw;
            }
            else
            {
                $premium_req_array['Vehicle']['MiscTypeOfVehicleID'] = $misc_vehicles_sub_classes[strtoupper($mmv_data->veh_sub_type_name)] ?? 90;
            }
        }

        if ($parent_id != 'PCV')
        {
            unset($premium_req_array['Vehicle']['PCVVehicleCategory']);
            unset($premium_req_array['Vehicle']['PCVVehicleUsageType']);
        }

        if ($is_tppd == 'true') {
            $premium_req_array['Cover']['TPPDCover']['TPPDCover']['SumInsured'] = '6000';
        }
        if ($thirdParty) {
            $premium_req_array['Vehicle']['BodyIDV'] = 0;
            $premium_req_array['Vehicle']['ChassisIDV'] = 0;
            $premium_req_array['Risk']['IDV'] =  0;
        }
        if (isset($mmv_data->mfg_buildin) && $mmv_data->mfg_buildin == 'No') {
            $premium_req_array['Vehicle']['ISmanufacturerfullybuild'] = 'false';
            $premium_req_array['Vehicle']['BodyPrice'] = $mmv_data->body_price ?? 0;
            $premium_req_array['Vehicle']['ChassisPrice'] = $mmv_data->chassis_price ?? 0;
            // dd(json_decode($proposal->additional_details));
            if (!$thirdParty) {
                $proposal_data = $user_proposal_data;
                $additional_data = json_decode($proposal_data->additional_details);
                $additional_body_idv = $additional_data->vehicle->bodyIdv;
                $additional_chassis_idv = $additional_data->vehicle->chassisIdv;
                $premium_response_proposal = json_decode($proposal->additional_details_data);
                
                $premium_req_array['Vehicle']['BodyIDV'] = ((!empty($additional_body_idv)) ? $additional_body_idv :  ($premium_response_proposal->BodyIDV ?? 0));
                $premium_req_array['Vehicle']['ChassisIDV'] = ((!empty($additional_body_idv)) ? $additional_chassis_idv :  ($premium_response_proposal->ChassisIDV) ?? 0);
                $premium_req_array['Risk']['IDV'] = ($premium_response_proposal->IDV) ?? $idv ?? 0;
            }
            $premium_req_array['Vehicle']['BodyIDV'] = !empty($premium_req_array['Vehicle']['BodyIDV']) ? $premium_req_array['Vehicle']['BodyIDV'] :  $quote_log_data->bodyIDV;
            $premium_req_array['Vehicle']['ChassisIDV'] = !empty($premium_req_array['Vehicle']['ChassisIDV']) ? $premium_req_array['Vehicle']['ChassisIDV'] :  $quote_log_data->chassisIDV;
            // } else {
            //     $premium_response_proposal = json_decode($proposal->additional_details_data);
            //     $premium_req_array['Vehicle']['BodyIDV'] = $premium_response_proposal->BodyIDV;
            //     $premium_req_array['Vehicle']['ChassisIDV'] = $premium_response_proposal->ChassisIDV;
            //     $premium_req_array['Risk']['IDV'] = $premium_response_proposal->IDV;
            // }

        }
        if($is_geo_ext)
        {
            $premium_req_array['Cover']['IsGeographicalAreaExtended'] = 'true';
            $premium_req_array['Cover']['GeographicalExtension']['GeographicalExtension']['IsChecked'] = 'false';
            $premium_req_array['Cover']['GeographicalExtension']['GeographicalExtension']['Countries'] = 1949;#hardcoded
        }
        
        $get_response = getWsData(
            config('IC.RELIANCE.V1.CV.END_POINT_URL_COVERAGE'),
            $premium_req_array,
            'reliance',
            [
                'root_tag'      => 'PolicyDetails',
                'section'       => $productData->product_sub_type_code,
                'method'        => 'Coverage Calculation',
                'requestMethod' => 'post',
                'enquiryId'     => $enquiryId,
                'productName'   => $productData->product_name,
                'transaction_type'    => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('IC.RELIANCE.V1.CV.OCP_APIM_SUBSCRIPTION_KEY'),
                    'Content-type' => 'text/xml'
                ]
            ]
        );
        $coverage_res_data = $get_response['response'];

        if($coverage_res_data)
        {
            $coverage_res_data = json_decode($coverage_res_data);

            if(!isset($coverage_res_data->ErrorMessages))
            {
                $nil_dep_rate = 0;

                foreach ($coverage_res_data->LstAddonCovers as $k => $v)
                {
                    if($v->CoverageName == 'Nil Depreciation')
                    {
                        $nil_dep_rate = $v->rate;
                    }
                }

                if ($productData->zero_dep == 0 && $nil_dep_rate > 0)
                {
                    $premium_req_array['Cover']['IsNilDepreciation'] = 'true';
                    $premium_req_array['Cover']['NilDepreciationCoverage']['NilDepreciationCoverage']['ApplicableRate'] = $nil_dep_rate;
                }
            }
            else
            {
                return [
                    'status'         => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message'        => $coverage_res_data->ErrorMessages ?? 'Insurer Not Reachable'
                ];
            }
        }
        else
        {
            return [
                'status'         => false,
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'        => 'Insurer Not Reachable'
            ];
        }

        return [
            'premium_req_array' => trim_array($premium_req_array),
            'premium_type' => $premium_type,
            'tp_only' => $tp_only,
            'ClientType' => $ClientType,
            'ForeName' => $ForeName,
            'LastName' => $LastName,
            'CorporateName' => $CorporateName,
            'OccupationID' => $OccupationID,
            'DOB' => $DOB,
            'Gender' => $Gender,
            'MaritalStatus' => $MaritalStatus,
            'policy_start_date' => $policy_start_date,
            'policy_end_date' => $policy_end_date,
            'productCode' => $productCode
        ];
    }
}
