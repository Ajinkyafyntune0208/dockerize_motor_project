<?php

namespace App\Http\Controllers\Finsall;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Controller;

use App\Http\Controllers\Proposal\Services\relianceSubmitProposal;

use App\Models\User;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use App\Models\PolicyDetails;
use App\Models\PaymentResponse;

use Exception;
use Carbon\Carbon;
use Spatie\ArrayToXml\ArrayToXml;
use Mtownsend\XmlToArray\XmlToArray;

include_once app_path() . '/Helpers/CvWebServiceHelper.php';

class RelianceFinsallController extends Controller
{
    public static function reliancePaymentCheck($request, $proposal)
    {
        try {
            $paymentRequest = [
                'proposalNumber' => $proposal->proposal_no,
                'customerEmail' => $proposal->email,
                'customerMobile' => $proposal->mobile_number,
                'integratorTransNumber' => $request->txnRefNo,
                'paymentGatewayName' => 'Reliance',
                'userID' => config('constants.IcConstants.cv.reliance.USERID_RELIANCE'),
                'authorityToken' => config('constants.IcConstants.cv.reliance.AUTH_TOKEN_RELIANCE'),
                'getwayOredrID' => $request->txnRefNo,
                'paymentStatus' => 'Success',
                'paymentResponse' => '',
                'bundleProposalNumber' => '',
                'proposalAmount' => $proposal->final_payable_amount,
                'bundleTranNumber' => '',
            ];

            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $paymentRequest['CKYC'] = $proposal->ckyc_number;
                $paymentRequest['IsDocumentUpload'] = 'false';
                $paymentRequest['PanNo'] = $proposal->pan_number;
                $paymentRequest['IsForm60'] = 'false';
            }

            $get_response = getWsData(
                config('constants.IcConstants.finsall.END_POINT_URL_RELIANCE_FINSALL_PAYMENT_CHECK'),
                $paymentRequest,
                'reliance',
                [
                    'root_tag' => 'PoilicyMakeLiveExternal',
                    'section' => 'Cv',
                    'method' => 'Payment Status - Reliance Finsall',// 'Payment Status - Reliance'
                    'requestMethod' => 'post',
                    'enquiryId' => $proposal->user_product_journey_id,
                    'productName' => '',
                    'transaction_type' => 'proposal',
                    'headers' => [
                        'Content-type' => 'text/xml'
                    ]
                ]
            );
            $paymentResponseData = $get_response['response'];

            if(empty($paymentResponseData))
            {
                return [
                    'status' => true,
                    'message' => 'no response from payment check service'
                ];
            }
            $paymentResponse = XmlToArray::convert($paymentResponseData);

            if(empty($paymentResponse))
            {
                return [
                    'status' => true,
                    'message' => 'no response from payment check service'
                ];
            }
            if(empty($paymentResponse['PolicyNo']))
            {
                if(!empty($paymentResponse['ErrorMessages'] ?? ''))
                {
                    return [
                        'status' => true,
                        'message' => 'PolicyMakeLiveExternal service -'.$paymentResponse['ErrorMessages']
                    ];
                }
                return [
                    'status' => true,
                    'message' => 'no response from payment check service'
                ];
            }

            $data['user_product_journey_id'] = $proposal->user_product_journey_id;
            $data['ic_id'] = $proposal->ic_id;
            $data['stage'] = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
    
            updateJourneyStage($data);
    
            PolicyDetails::updateOrCreate(
                ['proposal_id' => $proposal->user_proposal_id],
                [
                    'policy_number' => $paymentResponse['PolicyNo'],
                ]
            );

            UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)->update([
                'policy_no' => $paymentResponse['PolicyNo']
            ]);

            $proposal->policy_no;

            $data = self::relianceGeneratePDF($proposal);
            
            return [
                'status' => true,
                'policy_no' => $paymentResponse['PolicyNo']
            ];

        } catch (\Exception $e) {
            return [
                'status' => true,
                'message' => $e->getMessage(),
                'error_trace' => $e->getTrace()
            ];
        }
    }

    public static function relianceGeneratePDF($proposal) {
        $PolicyDetails = PolicyDetails::where('proposal_id',$proposal->user_proposal_id) 
                                        ->get()
                                        ->toArray();
        $status = false;
        $message = STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'];
        $policy_number = '';
        if(isset($PolicyDetails[0]))
        {
           $policy_number = $PolicyDetails[0]['policy_number'];
        }
        
        if($policy_number == '')
        {
            $status = false;
            $message = 'Policy Number is NULL';            
        }
        else
        {
            $pdf_url = config('constants.IcConstants.reliance.POLICY_DWLD_LINK_RELIANCE').'?PolicyNo='.$policy_number.'&ProductCode='.$proposal->product_code;
            $pdf_name = config('constants.motorConstant.CV_PROPOSAL_PDF_URL').'reliance/' .  md5($proposal->user_proposal_id). '.pdf';
            PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id ], [
                'ic_pdf_url' => $pdf_url,
            ]);
            Storage::put($pdf_name, file_get_contents($pdf_url));
            PolicyDetails::updateOrCreate(['proposal_id' => $proposal->user_proposal_id ], [
                'pdf_url' => $pdf_name,
            ]);
            $status = true;
            $message = STAGE_NAMES['POLICY_ISSUED'];            
        }

        return [
            'status' => $status,
            'msg' => $message,
            'data' => [
                'policy_number' => $policy_number,
                'pdf_link'      => $policy_number != '' ? file_url($pdf_name) : ''
            ]
        ];
    }

    public static function submitProposal($user_proposal, $masterCompany, $enquiryId)
    {
        $icId = $masterCompany->company_id;

        $quote_log_data = QuoteLog::where('user_product_journey_id', $enquiryId)
                ->first();

        $productData = getProductDataByIc($quote_log_data->master_policy_id);
        $requestData = getQuotation($enquiryId);
        $parent_id = get_parent_code($productData->product_sub_type_id);

        if (get_parent_code($productData->product_sub_type_id) == 'MISCELLANEOUS-CLASS') {
            $mmv = [
                'Model_ID_PK' => '34350',
                'Make_id_pk' => '140',
                'Make_Name' => 'MAHINDRA',
                'Model_Name' => 'GENIO',
                'Variance' => 'DC VX BS 4',
                'Veh_Type_Name' => 'Misc-D',
                'Veh_Sub_Type_Name' => 'BREAKDOWN VEHICLES',
                'Wheels' => '4',
                'Manufacturing_Year' => '1000',
                'Operated_By' => 'DIESEL',
                'CC' => '2489',
                'Unit_Name' => 'CC',
                'Gross_Weight' => '1680',
                'Seating_Capacity' => '5',
                'Carrying_Capacity' => '4',
                'ModelStatus' => 'Active',
                'Body_Price' => '0',
                'ex_showroom_price' => '749134',
                'chassis_price' => '0',
                'Mfg_BuildIn' => 'Yes',
                'ic_version_code' => '34350'
            ];
        } else {
            $mmv = get_mmv_details($productData, $requestData->version_id, 'reliance', $parent_id == 'GCV' ? $requestData->gcv_carrier_type : NULL);

            if ($mmv['status'] == 1) {
                $mmv = $mmv['data'];
            } else {
                return  [
                    'premium_amount' => '0',
                    'status' => false,
                    'message' => $mmv['message']
                ];
            }
        }

        $mmv_data = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        // $rto_code = $requestData->rto_code;
        // $rto_data = DB::table('reliance_rto_master as rm')
        //     ->where('rm.region_code',$rto_code)
        //     ->select('rm.*')
        //     ->first();

        $rto_code = $requestData->rto_code;
        $registration_number = $user_proposal->vehicale_registration_number;

        $rcDetails = \App\Helpers\IcHelpers\RelianceHelper::getRtoAndRcDetail(
            $registration_number,
            $rto_code,
            $requestData->business_type == 'newbusiness'
        );

        if (!$rcDetails['status']) {
            return $rcDetails;
        }

        $rto_data = $rcDetails['rtoData'];

        $breakinDetails = DB::table('cv_breakin_status')
            ->where('cv_breakin_status.user_proposal_id', '=', $user_proposal->user_proposal_id)
            ->first();

        $premium_req_data = relianceSubmitProposal::getPremiumRequestData([
            'enquiryId' => $enquiryId,
            'requestData' => $requestData,
            'productData' => $productData,
            'quote_log_data' => $quote_log_data,
            'mmv_data' => $mmv_data,
            'rto_data' => $rto_data,
            'proposal' => $user_proposal,
            'breakin_details' => $breakinDetails
        ]);

        extract($premium_req_data);

        if (isset($status) && ! $status)
        {
            return [
                'status' => false,
                'message' => $message
            ];
        }

        if ($requestData->vehicle_owner_type == "I") {
            if ($user_proposal->gender == "M") {
                $Salutation = 'Mr.';
            } else {
                if ($user_proposal->gender == "F" && $user_proposal->marital_status == "Single") {
                    $Salutation = 'Ms.';
                } else {
                    $Salutation = 'Ms.';
                }
            }
        } else {
            $Salutation = 'M/S';
        }

        $corres_address_data = DB::table('reliance_pincode_state_city_master')
            ->where('pincode',$user_proposal->pincode)
            ->select('*')
            ->first();
        
        $address_data = [
                    'address' => $user_proposal->address_line1,
                    'address_1_limit'   => 250,
                    'address_2_limit'   => 250            
                ];
        $getAddress = getAddress($address_data);

        $ClientDetails = [
            'ClientType' => $ClientType,
            'Salutation' => $Salutation,
            'ForeName' => $ForeName,
            'LastName' => $LastName,
            'CorporateName' => $CorporateName,
            'MidName' => '',
            'OccupationID' => $OccupationID,
            'DOB' => $DOB,
            'Gender' => $Gender,
            'PhoneNo' => '',
            'MobileNo' => $user_proposal->mobile_number,
            'RegisteredUnderGST' => trim($user_proposal->gst_number) == '' ? '0' : '1',
            'RelatedParty' => '0',
            'GSTIN' => $user_proposal->gst_number,
            'GroupCorpID' => '',
            'ClientAddress' => [
                'CommunicationAddress' => [
                    'AddressType' => '0',
                    'Address1'        => trim($getAddress['address_1']),
                    'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                    'Address3'        => trim($getAddress['address_3']),
                    'CityID' => $corres_address_data->city_or_village_id_pk,
                    'DistrictID' => $corres_address_data->district_id_pk,
                    'StateID' => $corres_address_data->state_id_pk,
                    'Pincode' => $user_proposal->pincode,
                    'Country' => '1',
                    'NearestLandmark' => '',
                ],
                'RegistrationAddress'  => [
                    'AddressType' => '0',
                    'Address1'        => trim($getAddress['address_1']),
                    'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                    'Address3'        => trim($getAddress['address_3']),
                    'CityID' => $corres_address_data->city_or_village_id_pk,
                    'DistrictID' => $corres_address_data->district_id_pk,
                    'StateID' => $corres_address_data->state_id_pk,
                    'Pincode' => $user_proposal->pincode,
                    'Country' => '1',
                    'NearestLandmark' => '',
                ],
                'PermanentAddress' => [
                    'AddressType' => '0',
                    'Address1'        => trim($getAddress['address_1']),
                    'Address2'        => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                    'Address3'        => trim($getAddress['address_3']),
                    'CityID' => $corres_address_data->city_or_village_id_pk,
                    'DistrictID' => $corres_address_data->district_id_pk,
                    'StateID' => $corres_address_data->state_id_pk,
                    'Pincode' => $user_proposal->pincode,
                    'Country' => '1',
                    'NearestLandmark' => '',
                ],
            ],
            'EmailID' => $user_proposal->email,
            'MaritalStatus' => $MaritalStatus,
            'Nationality' => '1949'
        ];

        if (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin']) && $user_proposal->is_inspection_done == 'Y') {
            $premium_req_array['Risk']['IsInspectionAddressSameasCommAddress'] = 'true';

            $ClientDetails['ClientAddress']['InspectionAddress'] = [
                'AddressType' => '0',
                'Address1' => trim($getAddress['address_1']),
                'Address2' => trim($getAddress['address_2']) == '' ? '.' : trim($getAddress['address_2']),
                'Address3' => trim($getAddress['address_3']),
                'CityID' => $corres_address_data->city_or_village_id_pk,
                'DistrictID' => $corres_address_data->district_id_pk,
                'StateID' => $corres_address_data->state_id_pk,
                'Pincode' => $user_proposal->pincode,
                'Country' => '1',
                'NearestLandmark' => ''
            ];
        }

        if ($user_proposal->is_car_registration_address_same == 0) {
            $reg_address_data = DB::table('reliance_pincode_state_city_master')
                ->where('pincode',$user_proposal->car_registration_pincode)
                ->select('*')
                ->first();

            $ClientDetails['ClientAddress']['RegistrationAddress'] = [
                'AddressType' => '0',
                'Address1' => $user_proposal->car_registration_address1,
                'Address2' => $user_proposal->car_registration_address2,
                'Address3' => $user_proposal->car_registration_address3,
                'CityID' => $reg_address_data->city_or_village_id_pk,
                'DistrictID' => $reg_address_data->district_id_pk,
                'StateID' => $reg_address_data->state_id_pk,
                'Pincode' => $user_proposal->car_registration_pincode,
                'Country' => '1',
                'NearestLandmark' => '',
            ];
        }

        unset($premium_req_array['ClientDetails']);

        $client['ClientDetails'] = $ClientDetails;

        $premium_req_array = array_merge($client,$premium_req_array);

        if ($requestData->business_type == 'breakin')
        {
            $policy_start_date = $tp_only == 'true' ? date('Y-m-d', strtotime('tomorrow')) : date('Y-m-d', time());

            if (in_array($premium_type, ['short_term_3', 'short_term_3_breakin']))
            {
                $policy_end_date = date('Y-m-d', strtotime('+3 month -1 day', strtotime($policy_start_date)));
            }
            elseif (in_array($premium_type, ['short_term_6', 'short_term_6_breakin']))
            {
                $policy_end_date = date('Y-m-d', strtotime('+6 month -1 day', strtotime($policy_start_date)));
            }
            else
            {
                $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
            }

            $premium_req_array['Policy']['Cover_From'] = $policy_start_date;
            $premium_req_array['Policy']['Cover_To'] = $policy_end_date;

            $premium_req_array['Vehicle']['InspectionNo'] = $breakinDetails->breakin_number ?? NULL;
        }

        $proposal_submit_url = config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PROPOSAL');

        if (in_array($premium_type, ['breakin', 'short_term_3_breakin', 'short_term_6_breakin']) && $user_proposal->is_inspection_done == 'Y') {
            $proposal_submit_url = config('constants.IcConstants.reliance.END_POINT_URL_RELIANCE_MOTOR_PROPOSAL_POST_INSPECTION');
        }

        $get_response = getWsData(
            $proposal_submit_url,
            $premium_req_array,
            'reliance',
            [
                'root_tag' => 'PolicyDetails',
                'section' => $productData->product_sub_type_code,
                'method' => 'Proposal Creation - Reliance Finsall',
                'requestMethod' => 'post',
                'enquiryId' => $enquiryId,
                'productName' => $productData->product_name,
                'transaction_type' => 'proposal',
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => config('constants.IcConstants.reliance.OCP_APIM_SUBSCRIPTION_KEY'),
                    'Content-type' => 'text/xml'
                ]
            ]
        );
        $proposal_res_data = $get_response['response'];

        if(empty($proposal_res_data))
        {
            return [
                'status' => false,
                'message' => 'Insurer not reachable'
            ];
        }

        $proposal_resp = json_decode($proposal_res_data)->MotorPolicy;

        if ($proposal_resp->status == '1') {
            $updateProposal = UserProposal::where('user_product_journey_id', $enquiryId)
                ->where('user_proposal_id', $user_proposal->user_proposal_id)
                ->update([
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'proposal_no' => $proposal_resp->ProposalNo,
                ]);

            $params = [
                'ProposalNo'     => $proposal_resp->ProposalNo,
                'userID'         => config('constants.IcConstants.cv.reliance.USERID_RELIANCE'),
                'ProposalAmount' => $user_proposal->final_payable_amount,
                'PaymentType'    => '1',
                'Responseurl'    => route('cv.payment-confirm', ['reliance'])
            ];

            if (config('constants.IS_CKYC_ENABLED') == 'Y') {
                $kyc_param = [
                    'CKYC' => $user_proposal->ckyc_number,
                    'IsDocumentUpload' => 'false',
                    'PanNo' => $user_proposal->pan_number,
                    'IsForm60' => 'false'
                ];
                $params = array_merge($params, $kyc_param);
            }
    
            $payment_url = config('constants.IcConstants.reliance.PAYMENT_GATEWAY_LINK_RELIANCE'). '?' . http_build_query($params);
    
            // DB::table('payment_request_response')
            //       ->where('user_product_journey_id', $enquiryId)
            //       ->where('ic_id', $icId)
            //       ->where('user_proposal_id', $user_proposal->user_proposal_id)
            //       ->update(['active' => 0]);
    
            // DB::table('payment_request_response')->insert([
            //     'quote_id' => $quote_log_data->quote_id,
            //     'user_product_journey_id' => $enquiryId,
            //     'user_proposal_id' => $user_proposal->user_proposal_id,
            //     'ic_id' => $icId,
            //     'order_id' => $proposal_resp->ProposalNo,
            //     'proposal_no' => $proposal_resp->ProposalNo,
            //     'amount' => $user_proposal->final_payable_amount,
            //     'payment_url' => $payment_url,
            //     'return_url' => route('cv.payment-confirm', ['reliance']),
            //     'status' => STAGE_NAMES['PAYMENT_INITIATED'],
            //     'active' => 1
            // ]);

            // updateJourneyStage([
            //     'user_product_journey_id' => $user_proposal->user_product_journey_id,
            //     'stage' => STAGE_NAMES['PAYMENT_INITIATED']
            // ]);

            return [
                'status' => true,
                'data' => [
                    'payment_type' => 1,
                    'proposal_no' => $proposal_resp->ProposalNo,
                    'paymentUrl' => trim($payment_url)
                ]
            ];
        } elseif (trim($proposal_resp->ErrorMessages) != '') {
            return [
                'status' => false,
                'msg' => trim($proposal_resp->ErrorMessages)
            ];
        } else {
            return [
                'status' => false,
                'msg' => 'Error in proposal submit service'
            ];
        }
    }


}