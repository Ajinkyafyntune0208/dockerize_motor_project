<?php

namespace App\Http\Controllers;

use App\Models\CvAgentMapping;
use App\Models\QuoteServiceRequestResponse;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AgentMappingController extends Controller
{
    protected $enquiry_id;
    public $log_data = [];
    public function __construct($enquiry_id)
    {
        $this->enquiry_id = $enquiry_id;
        $this->log_data = [
            'enquiry_id' => $this->enquiry_id,
            'company' => 'Agent Mapping', //This should be same for all brokers
            'method_name' => 'Agent Mapping', //This should be same for all brokers
            'product' => '',
            'method' => '', //
            'request' => '',
            'response' => '',
            'endpoint_url' => '',
            'ip_address' => request()->ip(),
            'created_at' => date('Y-m-d H:i:s'),
            'transaction_type' => 'quote', //This should be same for all brokers
        ];
    }

    public function mapAgent(Request $request)
    {
        //Write condition as per the data received and paas data to respective function.
        $broker = config('constants.motorConstant.AGENT_MAPPING_BROKER');
        if (empty($broker)) {
            return false;
        }
        $functionName = $broker . 'AgentMapping';
        if (method_exists(__CLASS__, $functionName)) {
            return $this->{$functionName}($request);
        }
        Log::error('Method not found for Agent Mapping : ' . $functionName);
    }

    /*
     * RenewBuy uses SSO login. Data received will consist of username, token, pos_code etc.
     */
    public function renewbuyAgentMapping(Request $request)
    {
        if (!isset($request->tokenResp) || empty($request->tokenResp)) {
            return false;
        }
        $is_cse_agent = CvAgentMapping::where(["seller_type" => "P", "user_product_journey_id" => $this->enquiry_id])->whereIn("source", ["cse", "app"])->exists();
        if ($is_cse_agent) {
            return false;
        }
        $data = $request->tokenResp;
        // Don't save logs in third_party_api_request_responses table,
        // as we are already storing in the Quote Web service table. - 16-01-2023 @Amit
        $startTime = new DateTime(date('Y-m-d H:i:s'));
        $response = httpRequest('get-RenewBuy-POSDetails', ['user_id' => $data['username']], [], [], [], false);
        $endTime = new DateTime(date('Y-m-d H:i:s'));

        $this->log_data['method'] = 'POST';
        $this->log_data['request'] = json_encode($response['request']);
        $this->log_data['response'] = json_encode($response['response']);
        $this->log_data['endpoint_url'] = $response['url'];
        $this->log_data['start_time'] = $startTime->format('Y-m-d H:i:s');
        $this->log_data['end_time'] = $endTime->format('Y-m-d H:i:s');
        $this->log_data['response_time'] = $endTime->getTimestamp() - $startTime->getTimestamp();
        $this->log_data['headers'] = json_encode($response['request_headers']);

        QuoteServiceRequestResponse::insert($this->log_data);
        if ($response['status'] != 200) {
            Log::error('Agent Mapping failed!!! Getting incorrect data from POS details API. Error : ' . json_encode($response['response']));
            throw new \Exception('Agent Mapping failed!!! Getting incorrect data from POS details API.');
        }
        $seller_type = 'P';
        $unCertifiedPosAgents = config('constants.motorConstant.unCertifiedPosAgents');
        if (in_array($response['response']['pos_code'], explode(',', $unCertifiedPosAgents))) {
            $seller_type = 'Partner';
        }
        if (isset($response['response']['is_Gi_certified']) && $response['response']['is_Gi_certified'] === false && $seller_type == 'P') {
            throw new \Exception('Dear Partner Please Complete Your POSP Certification In Order To Issue Policy');
        }

        $cvAgentMapping = CvAgentMapping::select('seller_type')->where('user_product_journey_id', $this->enquiry_id)->first();

        if (!empty($cvAgentMapping) && $seller_type != $cvAgentMapping->seller_type) {
            throw new \Exception('Something went wrong. Overwriting agent detail is not allowed. Please do a fresh journey.');
        }
        CvAgentMapping::updateOrCreate(["seller_type" => $seller_type, "user_product_journey_id" => $this->enquiry_id], [
            "user_product_journey_id" => $this->enquiry_id,
            "stage" => "quote",
            "seller_type" => $seller_type,
            "agent_id" => $response['response']['pos_code'],
            "user_name" => $response['response']['pos_code'],
            "agent_name" => $response['response']['pos_name'],
            "category" => (strpos($response['response']['pos_code'], 'AEV') === 0) ? 'EV' : null,
            //"agent_mobile" => $request->tokenResp['phone'] ?? null,
            "agent_mobile" => $response['response']['toll_free_no'],
            "agent_email" => $response['response']['pos_email'],
            "aadhar_no" => $response['response']['pos_aadhar_no'],
            "pan_no" => $response['response']['pos_pan_no'],
            "token" => $request->tokenResp['token'],
            "source" => $request->tokenResp['source'] ?? null,
            "region_name" => $response['response']['vc_location'] ?? null,
        ]);
    }
}
