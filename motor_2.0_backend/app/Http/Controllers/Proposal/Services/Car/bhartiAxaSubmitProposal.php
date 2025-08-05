<?php

namespace App\Http\Controllers\Proposal\Services\Car;

use App\Models\QuoteLog;
use App\Models\UserProposal;
use DateTime;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path() . '/Helpers/CarWebServiceHelper.php';

class bhartiAxaSubmitProposal
{
    /**
     * @param $proposal
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     * */

    public static function submit($proposal, $request)
    {

        $enquiryId   = customDecrypt($request['userProductJourneyId']);
        $requestData = getQuotation($enquiryId);
        $productData = getProductDataByIc($request['policyId']);

        // if(($requestData->business_type != "newbusiness") && ($productData->zero_dep == '0') && ($requestData->zero_dep_in_last_policy != 'Y'))
        // {
        //     return  response()->json([
        //         'status' => false,
        //         'message' => 'Zero dep is not available because zero dep is not part of your previous policy'
        //     ]);
        // }
        $quoteData   = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
        $car_age     = car_age($requestData->vehicle_register_date, $requestData->previous_policy_expiry_date);
        $rto_details = DB::table('bharti_axa_rto_location')->where('rta_code', str_replace('-', '', $requestData->rto_code))->first();


        $mmv = get_mmv_details($productData, $requestData->version_id, 'bharti_axa');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        // dd($requestData->version_id, $productData->company_id, $mmv_data);

        if (empty($mmv_data->ic_version_code) || $mmv_data->ic_version_code == '') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
            ];
        } else if ($mmv_data->ic_version_code == 'DNE') {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
            ];
        }


        $today_date = date('Y-m-d');
        if (new DateTime($requestData->previous_policy_expiry_date) > new DateTime($today_date)) {
            $policy_start_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)))));
        } else if (new DateTime($requestData->previous_policy_expiry_date) < new DateTime($today_date)) {
            $policy_start_date = date('d/m/Y', strtotime("+1 day"));
        } else {
            $policy_start_date = date('d/m/Y', strtotime("+1 day"));
        }

        $policystartdatetime = DateTime::createFromFormat('d/m/Y', $policy_start_date);
        $policy_start_date = $policystartdatetime->format('Y-m-d');

        $policy_end_date = date('d/m/Y', strtotime(date('Y-m-d', strtotime('+1 year -1 day', strtotime(strtr($policy_start_date, '/', '-'))))));

        $policyenddatetime = DateTime::createFromFormat('d/m/Y', $policy_end_date);
        $policy_end_date = $policyenddatetime->format('Y-m-d');


        date_default_timezone_set('GMT');
        $InitTime = date('D, d M Y H:i:s e', time());

        if ($requestData->fuel_type == 'PETROL') {
            $fuel_type = 'P';
        } elseif ($requestData->fuel_type == 'DIESEL') {
            $fuel_type = 'D';
        }


        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $salutation = 'MR';
            } else {
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $salutation = 'MRS';
                } else {
                    $salutation = 'MS';
                }
            }
        } else {
            $salutation = 'M/S';
        }

        $quote_req_array = [
            'SessionData' => [
                '@attributes' => [
                    'xmlns' => 'http://schemas.cordys.com/bagi/b2c/emotor/bpm/1.0',
                ],
                'Index'             => '1',
                'InitTime'          => $InitTime,
                'UserName'          => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_USERNAME'),
                'Password'          => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_PASSWORD'),
                'OrderNo'           => 'NA',
                'QuoteNo'           => 'NA',
                'Route'             => 'INT',
                'Contract'          => 'MTR',
                'Channel'           => config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_CHANNEL'),
                'TransactionType'   => 'Quote',
                'TransactionStatus' => 'Fresh',
                'ID'                => '',
                'UserAgentID'       => '',
                'Source'            => '',
            ],
            'Vehicle' => [
                '@attributes' => [
                    'xmlns' => 'http://schemas.cordys.com/bagi/b2c/emotor/2.0',
                ],
                'TypeOfBusiness' => 'TR', // TR AND NB
                'AccessoryInsured' => 'N',
                'AccessoryValue' => 0,
                'NonElecAccessoryInsured' => 'N',
                'NonElecAccessoryValue' => 0,
                'ARAIMember' => 'N',
                'BiFuelKit' => [
                    'IsBiFuelKit' => 'N',
                    'ExternallyFitted' => 'N',
                    'BiFuelKitValue' => '0',
                ],
                'DateOfRegistration' => date('Y-m-d', strtotime($requestData->vehicle_register_date)) . 'T00:00:00.000',
                'RiskType' => $mmv_data->product_type,
                'Make' => $mmv_data->manufacture,
                'Model' => $mmv_data->model,
                'FuelType' => $fuel_type,
                'Variant' => $mmv_data->variant,
                'IDV' => $quoteData->idv,
                'EngineNo' => $proposal->engine_number,
                'ChasisNo' => $proposal->chassis_number,
                'VehicleAge' => $car_age,
                'CC' => $mmv_data->cc,
                'PlaceOfRegistration' => $rto_details->city,
                'SeatingCapacity' => $mmv_data->seating_capacity,
                'VehicleExtraTag01' => '',
                'RegistrationNo' => str_replace('-', '', $requestData->vehicle_registration_no),
                'ExShowroomPrice' => $mmv_data->ex_showroom_price,
                'PAOwnerDriverTenure' => (($requestData->policy_type == 'breakin') ? 1 : 3),
                'DateOfManufacture' => date('Y-m-d', strtotime('01-' . $requestData->manufacture_year)) . 'T00:00:00.000',
                'AntiTheftDevice' => '', //($requestData->policy_type == 'breakin' ? 'N' : $anti_theft),
                'RateType' => (($requestData->policy_type == 'breakin') ? 'G' : ''),
                'PUC' => 'Y'
            ],
            'Quote' => [
                '@attributes' => [
                    'xmlns' => 'http://schemas.cordys.com/bagi/b2c/emotor/2.0',
                ],
                'ExistingPolicy' => [
                    'Claims' => $requestData->is_claim == 'Y' ? 1 : 0,
                    'PolicyType' => $requestData->previous_policy_type,
                    'EndDate' => date('Y-m-d', strtotime($requestData->previous_policy_expiry_date)) . 'T00:00:00.000',
                    'NCB' => $requestData->previous_ncb,
                ],
                'PolicyStartDate' => date('Y-m-d', strtotime($policy_start_date)) . 'T00:00:00.000',
                'Deductible' => '0',
                'PAFamilySI' => 0,
                'AgentNumber' => '',
                'DealerId' => '',
                'Premium' => [
                    'Discount' => ($requestData->policy_type == 'breakin') ? '0' : '',
                ],
                'SelectedCovers' => [
                    'CarDamageSelected' => 'True',
                    'TPLiabilitySelected' => 'False',
                    'PADriverSelected' => 'False',
                    'RoadsideAssistanceSelected' => 'False',
                    'KeyReplacementSelected' => 'False',
                    'NoClaimBonusSameSlabSelected' => 'False',
                    'PAFamilyPremiumSelected' => 'False',
                    'ZeroDepriciationSelected' => 'False',
                    'AmbulanceChargesSelected' => 'False',
                    'CosumableCoverSelected' => 'False',
                    'EngineGearBoxProtectionSelected' => 'False',
                    'HospitalCashSelected' => 'False',
                    'HydrostaticLockSelected' => 'False',
                    'InvoicePriceSelected' => 'False',
                    'MedicalExpensesSelected' => 'False',
                ],
                'PolicyEndDate' => date('Y-m-d', strtotime($policy_end_date)) . 'T00:00:00.000',
                'BreakIn' => (($requestData->policy_type == 'breakin') ? 'True' : ''),
                'Stage' => (($requestData->policy_type == 'breakin') ? '1' : ''),
                'PolicyTenure' => (($requestData->policy_type == 'rollover') ? '3' : ''),
                'IsExistingPA' => '',
                'PADeclaration' => '',
            ],
            'Client' => [
                '@attributes' => [
                    'xmlns' => 'http://schemas.cordys.com/bagi/b2c/emotor/2.0',
                ],
                'ClientType' => $requestData->vehicle_owner_type == 'I' ? 'Individual' : 'Corporate',
                'CltDOB' => str_replace('-', '', $proposal->dob),
                'FinancierDetails' => [
                    'IsFinanced' => '0',
                ],
                'Salut' => $salutation,
                'GivName' => $proposal->first_name,
                'SurName' => $proposal->last_name,
                'ClientExtraTag01' => $rto_details->state,
                'CityOfResidence' => $rto_details->city,
                'EmailID' => $proposal->email,
                'MobileNo' => $proposal->mobile_number,
                'RegistrationZone' => $rto_details->zone,
            ],
        ];


        $additionalData = [
            'root_tag'          => 'Session',
            'container'         => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><serve xmlns="http://schemas.cordys.com/gateway/Provider"><SessionDoc> #replace </SessionDoc></serve></Body></Envelope>',
            'section'           => $productData->product_sub_type_code,
            'method'            => 'Premium Calculation',
            'requestMethod'     => 'post',
            'enquiryId'         => $enquiryId,
            'productName'       => $productData->product_sub_type_name,
            'transaction_type'  => 'proposal'
        ];

        $get_response = getWsData(config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_END_POINT_URL'), $quote_req_array, 'bharti_axa', $additionalData);
        $data = $get_response['response'];
        if ($data) {
            $quote_resp_array = XmlToArray::convert($data);
            $filter_response = array_search_key('response', $quote_resp_array);

            if (isset($filter_response['StatusMsg']) &&  $filter_response['StatusMsg'] != 'Success')
            {
                return [
                    'status' => false,
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $filter_response['StatusMsg'],
                ];
            }

            if (isset($filter_response['QuoteNo']))
            {
                $order_no = $filter_response['OrderNo'];
                $quote_no = $filter_response['QuoteNo'];
                $service_tax = $filter_response['PremiumSet']['ServiceTax'];
                $total_premium = ((($service_tax * 100) / config('constants.IcConstants.bharti_axa.BHARTI_AXA_SERVICE_TAX')) + $service_tax);
                $covers = $filter_response['PremiumSet']['Cover'];
                $tp_premium = 0;
                $acc_cover_unnamed_passenger = 0; // keep it in mind
                $net_premium = ($total_premium - $service_tax);

                foreach ($covers as $key => $cover)
                {
                    if ($cover['Name'] === 'ThirdPartyLiability') {
                        $tp_premium += ($cover['Premium']);
                    } elseif ($cover['Name'] === 'PAOwnerDriver') {
                        $tp_premium += ($cover['Premium']);
                    } elseif (($acc_cover_unnamed_passenger !== '') && ($cover['Name'] === 'PAFamily')) {
                        $tp_premium += ($cover['Premium']);
                    }
                }

                $od_premium = ($net_premium - $tp_premium);
                $marital_status = '';
                if ($proposal->marital_status == 'Single') {
                    $marital_status = 'S';
                }elseif($marital_status = 'Married') {
                    $marital_status = 'M';
                }

                $quote_req_array['SessionData']['index']                    = '2';
                $quote_req_array['SessionData']['InitTime']                 = date('D, d M Y H:i:s e', time());
                $quote_req_array['SessionData']['OrderNo']                  = $order_no;
                $quote_req_array['SessionData']['QuoteNo']                  = $quote_no;
                $quote_req_array['Client']['CltSex']                        = $proposal->gender;
                $quote_req_array['Client']['Marryd']                        = $marital_status;
                $quote_req_array['Client']['Occupation']                    = $proposal->occupation;
                $quote_req_array['Client']['CltAddr01']                     = $proposal->address_line1;
                $quote_req_array['Client']['CltAddr02']                     = $proposal->address_line2;
                $quote_req_array['Client']['CltAddr03']                     = $proposal->address_line3;
                $quote_req_array['Client']['PinCode']                       = $proposal->pincode;
                $quote_req_array['Client']['City']                          = $proposal->city;
                $quote_req_array['Client']['State']                         = $proposal->state;
                $quote_req_array['Client']['GSTIN']                         = $proposal->gst_number;
                $quote_req_array['Client']['Nominee']['Name']               = $proposal->nominee_name;
                $quote_req_array['Client']['Nominee']['Age']                = $proposal->nominee_age;
                $quote_req_array['Client']['Nominee']['Relationship']       = $proposal->nominee_relationship;
                $quote_req_array['Client']['Nominee']['Appointee']          = '';
                $quote_req_array['Client']['Nominee']['AppointeeRelation']  = '';

                $additionalData = [
                    'root_tag'          => 'Session',
                    'container'         => '<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"><Body><serve xmlns="http://schemas.cordys.com/gateway/Provider"><SessionDoc> #replace </SessionDoc></serve></Body></Envelope>',
                    'section'           => $productData->product_sub_type_code,
                    'method'            => 'Proposal Generation',
                    'requestMethod'     => 'post',
                    'enquiryId'         => $enquiryId,
                    'productName'       => $productData->product_sub_type_name,
                    'transaction_type'  => 'proposal'
                ];

                $get_response = getWsData(config('constants.IcConstants.bharti_axa.BHARTI_AXA_CAR_END_POINT_URL'), $quote_req_array, 'bharti_axa', $additionalData);
                $data = $get_response['response'];

                if ($data) {

                    $quote_resp_array = XmlToArray::convert($data);
                    $filter_response = array_search_key('response', $quote_resp_array);

                    if ($filter_response['StatusCode'] != '200') {
                        $error = isset($filter_response['StatusMsg']) ? 'Error coming from service : ' . $filter_response['StatusMsg'] : 'Something went wrong. Please re-verify details which you have provided.';
                        return [
                            'status' => false,
                            'webservice_id' => $get_response['webservice_id'],
                            'table' => $get_response['table'],
                            'message' => 'Error coming from service : ' . $error,
                        ];
                    }

                    $covers = $filter_response['PremiumSet']['Cover'];
                    $tppd = 0;
                    $cover_amount = 0;
                    $rsa = 0;
                    $paowner_amount = 0;
                    $pafamily = 0;
                    $key_repl = 0;
                    $ncb_prot = 0;
                    $NCB = 0;
                    $key_repl = 0;
                    $eng_grbx_prot = 0;
                    $consumable = 0;
                    $Ambulance_Charges = 0;
                    $Hospital_Cash = 0;
                    $return_to_invoice = 0;
                    $Medical_Expenses = 0;
                    $AntiTheft = 0;
                    $voluntary_deductible = 0;
                    $only_od_premium = 0;
                    $Zero_Depriciation_Selected = 'False'; //
                    $is_cpa_cover = 'False'; //
                    $total_od_premium = 0;

                    foreach ($covers as $key => $cover) {
                        if (($Zero_Depriciation_Selected == 'True') && ($cover['Name'] === 'DEPC')) {
                            $cover_amount = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'CarDamage')
                        {
                            $od_premium = ($cover['ExtraDetails']['BreakUp']['BasicOD']);
                            $only_od_premium = $od_premium;
                            $NCB = $cover['ExtraDetails']['BreakUp']['NCB'];
                            $electrical = $cover['ExtraDetails']['BreakUp']['Accessory'];
                            $bifuel = $cover['ExtraDetails']['BreakUp']['BiFuel'];
                            $NonElecAccessory = $cover['ExtraDetails']['BreakUp']['NonElecAccessory'];
                            $AntiTheft = $cover['ExtraDetails']['BreakUp']['AntiTheft'];
                            $voluntary_deductible = $cover['ExtraDetails']['BreakUp']['ODDeductible'];
                            $total_od_premium = $od_premium + $electrical + $bifuel + $NonElecAccessory;
                        }
                        elseif ($cover['Name'] === 'ThirdPartyLiability') {
                            $tp_premium = $cover['ExtraDetails']['BreakUp']['TP'];
                            $LL = $cover['ExtraDetails']['BreakUp']['LLDriver'];
                            $TPbifuel = $cover['ExtraDetails']['BreakUp']['TPBiFuel'];
                            $tppd += $tp_premium + $LL + $TPbifuel + $cover['ExtraDetails']['BreakUp']['TPPD'];
                        } elseif (($is_cpa_cover == 'Yes') && ($cover['Name'] === 'PAOwnerDriver')) {
                            $paowner_amount = ($cover['Premium']);
                            $tppd += $paowner_amount;
                        } elseif (($acc_cover_unnamed_passenger != '') && ($cover['Name'] === 'PAFamily')) {
                            $pafamily = ($cover['Premium']);
                            $tppd += $pafamily;
                        } elseif (($cover['Name'] === 'KEYC')) {
                            $key_repl = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'NCBS') {
                            $ncb_prot = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'RSAP') {
                            $rsa = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'EGBP') {
                            $eng_grbx_prot = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'CONC') {
                            $consumable = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'AMBC') {
                            $Ambulance_Charges = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'HOSP') {
                            $Hospital_Cash = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'INPC') {
                            $return_to_invoice = ($cover['Premium']);
                        } elseif ($cover['Name'] === 'MEDI') {
                            $Medical_Expenses = ($cover['Premium']);
                        }
                    }

                    $order_no = $filter_response['OrderNo'];
                    $quote_no = $filter_response['QuoteNo'];

                    $service_tax = $filter_response['PremiumSet']['PremiumDetails']['ServiceTax'];
                    $total_premium = ((($service_tax * 100) / config('constants.IcConstants.bharti_axa.BHARTI_AXA_SERVICE_TAX')) + $service_tax);

                    UserProposal::where('user_product_journey_id', $enquiryId)
                                    ->where('user_proposal_id', $proposal->user_proposal_id)
                                    ->update([
                                        'proposal_no' => $order_no,
                                        'unique_proposal_id' => $quote_no,
                                        'policy_start_date' =>  str_replace('00:00:00', '', $policy_start_date),
                                        'policy_end_date' =>  str_replace('00:00:00', '', $policy_end_date),
                                        'od_premium' => ($total_od_premium),
                                        'tp_premium' => ($tppd),
                                        'addon_premium' => '',
                                        'cpa_premium' => $paowner_amount,
                                        'service_tax_amount' => '',
                                        'total_discount' => '',
                                        'final_payable_amount' => ($total_premium),
                                        'ic_vehicle_details' => '',
                                        'discount_percent' => ''
                                    ]);

                    $proposal_data = UserProposal::find($proposal->user_proposal_id);
                    return response()->json([
                        'status' => true,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => "Proposal Submitted Successfully!",
                        'data' => [
                            'proposalId' =>  $proposal->user_proposal_id,
                            'userProductJourneyId' => $proposal_data->user_product_journey_id,
                        ]
                    ]);

                }else{
                    return [
                        'status' => false,
                        'webservice_id' => $get_response['webservice_id'],
                        'table' => $get_response['table'],
                        'message' => 'Insurer not reachable'
                    ];
                }

            }

        }
    }
}
