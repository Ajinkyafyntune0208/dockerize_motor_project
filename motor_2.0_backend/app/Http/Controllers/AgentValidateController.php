<?php

namespace App\Http\Controllers;

use App\Models\UserProductJourney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\CvAgentMapping;
use App\Models\QuoteLog;
use App\Models\JourneyStage;

class AgentValidateController extends Controller
{
    protected $enquiryId;

    public function __construct($enquiryId)
    {
        $this->enquiryId = $enquiryId;
    }

    public function agentValidation(Request $request)
    {
        $broker = config('constants.motorConstant.AGENT_VALIDATE_BROKER');
        if (empty($broker)) {
            return ['status' => true];
        }

        $functionName = $broker . 'AgentValidation';
        if (method_exists(__CLASS__, $functionName)) {
            return $this->{$functionName}($request);
        }
        return ['status' => true];
    }


    public function renewbuyAgentValidation(Request $request)
    {
        $userData = UserProductJourney::find($this->enquiryId);

        if (
            $request->vehicleValidation == 'Y' &&
            !in_array($userData->corporate_vehicles_quote_request->business_type, ['rollover'])
        ) {
            return ['status' => true];
        }
        
        $agentDetails = $userData->agent_details;
        if (!isset($agentDetails[0])) {
            return ['status' => true];
        }


        $agentDetails = $agentDetails[0];

        if (!in_array(strtoupper($request->section), explode(',', config('AGENT_VALIDATE_PRODUCT_TYPES')))) {
            return ['status' => true];
        }
        if (!in_array(strtoupper($agentDetails->seller_type), ['P', 'PARTNER'])) {
            return ['status' =>true];
        }

        $extras = [
            'transactionType' => $request->vehicleValidation == 'Y' ? 'proposal' : 'quote',
            'enquiryId' =>$this->enquiryId,
            'method' => '',
            'product' => '',
            'section' => $request->section ?? '',
            'methodName' => 'Agent Validation',
            'company' => 'renewbuy',
            'method' => 'get',
            'headers' => []
        ];
        $registrationNo = strtoupper(str_replace('-','',$request->registration_no));
        $url = config('constants.motorConstant.AGENT_VALIDATE_URL').$registrationNo;
        $response = httpRequestNormal($url, 'GET', [], [], [], [], true, false, false, false, $extras)['response'];
        if ($response) {
            
            if (isset($response['error_message'])) {
                $response['error'] = $response['error_message'];
            }

            if ((isset($response['error']) && in_array(
                strtoupper($response['error']),
                [
                    "TRANSACTION OLDER THAN 400 DAYS",
                    "UNABLE TO FETCH DATA FOR FOLLOWING REASONS 'NONETYPE' OBJECT HAS NO ATTRIBUTE 'PREMIUM'",
                    "UNABLE TO FETCH DATA FOR FOLLOWING REASONS 'NONETYPE' OBJECT HAS NO ATTRIBUTE 'CODE'",
                    "PAYMENT DOES NOT EXISTS IN SYSTEM FOR THIS REGISTRATION NO ".strtoupper($registrationNo),
                ]
            )) || (isset($response['executive_code']) && $response['executive_code'] == $agentDetails->agent_id)) {
                return ['status' => true];
            } else {
                return ['status' => false,'message' => 'This case belongs to another RenewBuy agent'];
            }
        }
        return ['status' => true];
    }

    public function isEvPOS()
    {
        $EV_Category = config('POS_CATEGORY_IDENTIFIER');
        if($EV_Category !== NULL)
        {
            return CvAgentMapping::where('user_product_journey_id', $this->enquiryId)
                ->where('seller_type', 'P')
                ->where('category', $EV_Category)
                ->exists();
        }
        return false;
    }

    public function isEvProductSubType(): bool
    {
        $ev_sub_product_type = config("EV_SUB_PRODUCT_TYPE") ?? NULL;
        if($ev_sub_product_type == NULL) return false;

        $sub_type_id = QuoteLog::where('user_product_journey_id', $this->enquiryId)
        ->value('product_sub_type_id');

        $ev_sub_product_type_array = explode(",", $ev_sub_product_type);
        return in_array($sub_type_id, $ev_sub_product_type_array);
    }

    public function isEvFuelType(): bool
    {
        $quote_data = QuoteLog::where('user_product_journey_id', $this->enquiryId)->value('quote_data');
        $quote_data = json_decode($quote_data, true);

        return (isset($quote_data['fuel_type']) && $quote_data['fuel_type'] === "ELECTRIC");
    }

    public function commonAgentValidation(Request $request)
    {
        $agentTypes = explode(',', config('JOURNEY_AGENT_VALIDATION_SELLER_TYPES'));
        $productTypes = explode(',', config('AGENT_VALIDATE_PRODUCT_TYPES'));

        $userData = UserProductJourney::find($this->enquiryId);

        $curentProductType = strtoupper(get_parent_code($userData->product_sub_type_id));

        if (empty($productTypes) || !in_array($curentProductType, $productTypes)) {
            return ['status' => true, 'message' => 'Current product type is not set for agent validation'];
        }

        $registrationNo = $userData->user_proposal->vehicale_registration_number;

        if(empty($registrationNo) || $registrationNo == 'NEW' || in_array($userData->corporate_vehicles_quote_request->business_type, ['newbusiness'])) {
            return ['status' => true, 'message' => 'Validation is not Applicable to New Business'];
        }

        $currentJourneyAgentsDetails = CvAgentMapping::where('user_product_journey_id', $userData->user_product_journey_id)->whereIn('seller_type', ($agentTypes ?? []))->first();
        if(empty($agentTypes) || empty($currentJourneyAgentsDetails)) {
            return ['status' => true, 'message' => 'no Agent Details to match for current journey'];
        }
        $currentAgentId= $currentJourneyAgentsDetails?->agent_id;
        if(empty($currentJourneyAgentsDetails?->agent_id)) {
            return ['status' => true, 'message' => 'no Agent id to match for current journey'];
        }


        $oldJourney =  JourneyStage::join('user_proposal as up', 'up.user_product_journey_id', 'cv_journey_stages.user_product_journey_id')
            ->join('cv_agent_mappings as am', 'am.user_product_journey_id', 'up.user_product_journey_id')
            ->where('up.vehicale_registration_number', $registrationNo)
            ->whereIn('cv_journey_stages.stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['PAYMENT_RECEIVED']])
            ->whereNotNull('am.agent_id')
            ->where('am.seller_type', $currentJourneyAgentsDetails->seller_type)
            ->select('am.agent_id', 'am.seller_type', 'am.user_name', 'up.vehicale_registration_number', 'up.user_product_journey_id')
            ->get()->first();
        if (empty($oldJourney)) {
            return [
                'status' => true,
                'message' => 'No Previous Policy History'
            ];
        }
        if(($currentAgentId != $oldJourney->agent_id)) {
            return [
                'status' => false,
                'message' => 'This journey belongs to some another agent',
                'overrideMsg' => 'This journey belongs to some another agent',
                'show_error' => true,
                'oldAgentId' => $oldJourney->agent_id,
                'currentAgentId' => $currentAgentId
            ];
        } else {
            return [
                'status' => true,
                'message' => 'Agent is same as previous policy'
            ];
        }
    }
}
