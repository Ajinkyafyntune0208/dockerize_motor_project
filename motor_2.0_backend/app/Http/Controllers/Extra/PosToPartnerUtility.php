<?php

namespace App\Http\Controllers\Extra;

use App\Http\Controllers\Controller;
use App\Models\CvAgentMapping;
use App\Models\ProposalExtraFields;
use App\Models\UserProductJourney;
use Ixudra\Curl\Facades\Curl;

class PosToPartnerUtility extends Controller
{
     public static function posToPartnerFiftyLakhIdv($request, $enquiryId , $offline)
    {
        $dasboard_get_seller_details_api = config('DASHBOARD.BASE_URL') . config('DASHBOARD.GET_SELLER_DETAILS');
        $pos_request_data = [
            'seller_type' => $request->seller_type,
            'seller_username' => $request->user_name,
        ];
        $response = httpRequestNormal($dasboard_get_seller_details_api, 'POST', $pos_request_data , save:false);
        $partnerResponse = $response['response'];

        if (!empty($partnerResponse) && ($partnerResponse['status'] ?? null) == "true") {
            $partnerResponse = $partnerResponse['data'];
            if(empty($partnerResponse['seller_type']) || empty($partnerResponse['user_name']) || empty($partnerResponse['seller_id'])){
                return [
                    'status' => false,
                    'msg' => 'pos data not found'
                ];
            }
            $original_pos_details = [
                "seller_type" => $partnerResponse['seller_type'],
                "user_name" => $partnerResponse['user_name'],
                "agent_id" => $partnerResponse['seller_id']
            ];
    
            ProposalExtraFields::updateOrCreate(["enquiry_id" => $enquiryId], [
                "enquiry_id" => $enquiryId,
                "original_agent_details" => json_encode($original_pos_details)
            ]);
            
            if (empty($partnerResponse['h_seller_user_name']) || empty($partnerResponse['h_seller_id']) || empty($partnerResponse['h_seller_type'])) {
                return [
                    'status' => false,
                    'msg' => 'Seller Details Not Found'
                ];
            }
            if ($offline) {

                return [
                    'status' => true,
                    'msg' => 'offline renewal data upload',
                    'data' => [
                        'user_name' => $partnerResponse['h_seller_user_name'],
                        'agent_id' => $partnerResponse['h_seller_id'],
                        'seller_type' => $partnerResponse['h_seller_type'],
                        'reference_code' => !empty($partnerResponse['reference_code']) ? $partnerResponse['reference_code'] : null 
                    ],
                ];
            } else {
                CvAgentMapping::updateOrCreate(["user_product_journey_id" => $enquiryId], [
                    "user_name" => $partnerResponse['h_seller_user_name'],
                    "agent_id" => $partnerResponse['h_seller_id'],
                    "seller_type" => $partnerResponse['h_seller_type'],
                ]);
            }        

            if (config('POS_TO_PARTNER_CONVERSION_REFFERNCE_CODE_ENABLE') == 'Y' && !empty($partnerResponse['reference_code'])) {
                ProposalExtraFields::updateOrCreate(["enquiry_id" => $enquiryId], [
                    "enquiry_id" => $enquiryId,
                    "reference_code" => $partnerResponse['reference_code']
                ]);
            }
            return [
                'status' => true,
                'msg' => 'Partner data Updated.'
            ];
        }
        return [
            'status' => false,
            'msg' => 'No Agent Details Found from Dashboard'
        ];
    }

    //convert partner to pos
    public static function parentToPosConversion($request, $enquiryId)
    {
        if (!empty($request->original_agent_details)) {
            $response = json_decode($request->original_agent_details, true);
            CvAgentMapping::updateOrCreate(["user_product_journey_id" => $enquiryId], [
                "user_name" => $response['user_name'],
                "agent_id" => $response['agent_id'],
                "seller_type" => $response['seller_type'],
            ]);
            return true;
        }
        return false;
    }

    public static function PartnerFiftyLakhIdv($request)
    {

        $cUrl = config('DASHBOARD_VALIDATE_USER');
        $userName = $request->user_name;

        $pos_request_data = [
            'partner' => [
                'seller_code' => [$userName],
            ]
        ];
        $response = Curl::to($cUrl)
            ->withData(json_encode($pos_request_data))
            ->post();

        $partnerResponse = json_decode($response , true); 

        if(isset($partnerResponse['Partner'][$userName]['status']) && $partnerResponse['Partner'][$userName]['status'] == true && !empty($partnerResponse['Partner'][$userName]['data']['reference_code']))
        {
            $reference_code = $partnerResponse['Partner'][$userName]['data']['reference_code'];
            return [
                'status' => true,
                'data' => [
                    'reference_code' => $reference_code
                ]
            ];
        }
    }
}