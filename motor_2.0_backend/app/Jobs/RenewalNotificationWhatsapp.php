<?php

namespace App\Jobs;

use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\MasterProductSubType;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\LeadController;
use Illuminate\Queue\InteractsWithQueue;
use App\Http\Controllers\CommonController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class RenewalNotificationWhatsapp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('RENEWAL_NOTIFICATION_ACE') != 'Y') {
            return false;
        }
        $renewal_days = config('RENEWAL_NOTIFICATION_DAYS');
        $renewal_days = explode(',', $renewal_days);
        foreach ($renewal_days as $key => $days) {
            $renewal_records = UserProductJourney::with(['user_proposal' => function ($query) {
                $query->select(['user_product_journey_id', 'policy_end_date']);
            }])->whereHas('user_proposal', function ($query) use ($days) {
                $query->where("policy_end_date", Carbon::now()->addDays($days)->format('d-m-Y'));
                // $query->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') = CURDATE() + INTERVAL {$days} DAY");
            })->whereHas('journey_stage', function ($query) {
                $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
            })->whereNotIn('user_product_journey_id', function ($query) use ($days) {
                $query->select('user_product_journey_id')
                    ->from('communication_logs_5')
                    ->whereDate('created_at', Carbon::now())
                    //->where('communication_module', 'RENEWAL')
                    ->where('days', $days);
            })->limit(5)->get(['user_product_journey_id', 'created_on']);
            foreach ($renewal_records as $key => $value) {
                $proposal_report_url = url('api/proposalReports');
                $proposal_report = httpRequestNormal($proposal_report_url, 'POST', ['enquiry_id' => $value->journey_id])['response']['data'][0];
                //getvehicle details payload start
                $product_sub_type = MasterProductSubType::where('product_sub_type_code', strtoupper($proposal_report['sub_product_type']))->select('product_sub_type_id')->first();
                $payload = [
                    'enquiryId'         => $value->journey_id,
                    'registration_no'   => 'NULL',
                    'productSubType'    => $product_sub_type['product_sub_type_id'],
                    'section'           => $proposal_report['product_type'],
                    'is_renewal'        => 'Y',
                    'is_premium_call'   => 'true'
                ];

                if (strtoupper($proposal_report['vehicle_registration_number']) == 'NEW') {
                    $proposal_report['vehicle_registration_number'] = '';
                }

                if (!empty($proposal_report['vehicle_registration_number'])) {
                    $payload['registration_no'] = getRegisterNumberWithHyphen(str_replace("-", "", $proposal_report['vehicle_registration_number']));
                    $payload['vendor_rc']       = $proposal_report['vehicle_registration_number'];
                } else {
                    $payload['isPolicyNumber'] = 'Y';
                    $payload['policyNumber'] = $proposal_report['policy_no'];
                }
                //need to check whther renewal applicable for that ic or not
                $parent_code = strtolower(get_parent_code($product_sub_type['product_sub_type_id']));
                if (!in_array($proposal_report['company_alias'], explode(',', config(strtoupper($parent_code) . '_RENEWAL_ALLOWED_IC')))) {
                    $rra = [];
                    if (config('IS_ROLLOVER_RENEWAL_ALLOWED') != 'Y') {
                        $rra = [
                            'status' => false,
                            'msg' => 'Rollover Renewal is disabled.'
                        ];
                    }
                    StoreCommunicationLogs( 
                        'WHATSAPP', 
                        customDecrypt($value->journey_id), 
                        'NULL', 
                        json_encode($rra), 
                        'N', 
                        'RENEWAL', 
                        $days, 
                        customDecrypt($value->journey_id), 
                        date('Y-m-d',strtotime($proposal_report['policy_end_date'])),
                        '5'
                    );
                    continue;
                }
                //get lead payload
                $lead_payload = [
                    'reg_no'        => $proposal_report['vehicle_registration_number'],
                    'policy_no'     => $proposal_report['policy_no'],
                    'source'        => 'RENEWAL_WHATSAPP_JOURNEY',
                    'segment'       => 'CV',
                    'redirection'   => 'N' // for getting link of renewal
                ];
                $lead_payload['skip'] = 'Y';
                $LeadController = new LeadController();
                $get_lead_link = $LeadController->getleads(request()->replace($lead_payload));
                if (!isset($get_lead_link['new_user_product_journey_id'])) {
                    StoreCommunicationLogs( 
                        'WHATSAPP', 
                        customDecrypt($value->journey_id), 
                        'NULL', 
                        json_encode(['response'=>$get_lead_link,'error' => 'Error in getting journey id']), 
                        'N', 
                        'RENEWAL', 
                        $days, 
                        customDecrypt($value->journey_id), 
                        date('Y-m-d',strtotime($proposal_report['policy_end_date'])),
                        '5'
                    );
                    continue;
                }
                $new_enquiry_id = $get_lead_link['new_user_product_journey_id'];
                $payload['enquiryId'] = customEncrypt($new_enquiry_id);
                $payload['skip'] = 'Y';
                $url = $get_lead_link['redirection_url']; // redirection link recived

                $common = new CommonController;
                $getVehicleDetails = $common->getVehicleDetails(request()->replace($payload));
                //vehicle detail recived
                $vehicle_details = $getVehicleDetails->getOriginalContent();
                //if redirection url not found return false
                if (isset($vehicle_details['data']['redirection_data']['is_redirection']) && !$vehicle_details['data']['redirection_data']['is_redirection']) {
                    StoreCommunicationLogs( 
                        'WHATSAPP', 
                        customDecrypt($value->journey_id), 
                        'NULL', 
                        json_encode($vehicle_details), 
                        'N', 
                        'RENEWAL', 
                        $days, 
                        customDecrypt($value->journey_id), 
                        date('Y-m-d',strtotime($proposal_report['policy_end_date'])),
                        '5'
                    );
                    continue;
                }
                if(!isset($vehicle_details['data']))
                {
                    StoreCommunicationLogs( 
                        'WHATSAPP', 
                        customDecrypt($value->journey_id), 
                        'NULL', 
                        json_encode($vehicle_details), 
                        'N', 
                        'RENEWAL', 
                        $days, 
                        customDecrypt($value->journey_id), 
                        date('Y-m-d',strtotime($proposal_report['policy_end_date'])),
                        '5'
                    );
                    continue;
                }
                //final premium amount
                $getPremiumAmount = QuoteLog::where('user_product_journey_id', $new_enquiry_id)->select('final_premium_amount')->first();
                $mobile_number = $proposal_report['proposer_mobile'];
                $product_code = self::getProductCode($new_enquiry_id);
                $proposal = self::proposalData($new_enquiry_id);
                unset($proposal_report['proposer_mobile']);
                $name = $proposal_report['first_name'] . ' ' . $proposal_report['last_name'];
                //$proposer_mobile = 9819898104;
                //$url = shortUrl($url)['response']['short_url'];
                $final_payable_amount = $getPremiumAmount['final_premium_amount'];
                $expiryTime = today()->endOfDay()->format('d/m/Y H:i');
                $enquiryId = customEncrypt($new_enquiry_id);
                $old_user_product_journey_id = customDecrypt($vehicle_details['data']['additional_details']['oldenquiryId']);
                $data = "Dear {$name}, Please click {$url} to pay the premium for your {$product_code} Vehicle policy, Proposal No. {$enquiryId}. Your Total Payable Amount is INR {$final_payable_amount}. Important: This link will expire at {$expiryTime}.\nACE Insurance Brokers Private Limited, Registered Office- B-17 Ashadeep Building, 9 Hailey Road, New Delhi 110001, IRDAI License No. 246, Period 19.02.22 to 18.02.25, Category: Composite";
                $req = [
                    'method' => 'SendMessage',
                    'msg_type' => 'HSM',
                    'isHSM' => 'true',
                    'isTemplate' => 'false',
                    "linktracking" => 'false',
                    'send_to' => $mobile_number,
                    'msg' => $data
                ];
                $response = httpRequest('whatsapp_post', $req);
                $status = ($response['status'] == '200') ? true : false;
                $response = $response['response'] ?? 'NO RESPONSE';
                $req =  json_encode($req ,true);
                $trace_id = $get_lead_link['new_user_product_journey_id'] ?? '';
                $save = StoreCommunicationLogs( 'WHATSAPP', $trace_id, $req, json_encode($response['response'],true), $status, 'RENEWAL', $days, $old_user_product_journey_id, date('Y-m-d',strtotime($proposal_report['policy_end_date'])),'5');
                
            }
        }
    }
    public static function getProductCode($enquiry_id): string
    {
        return ucfirst(strtolower(get_parent_code(CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiry_id)
            ->first()->product_id)));
    }
    public static function proposalData($enquiry_id)
    {
        return UserProposal::where('user_product_journey_id', $enquiry_id)->first();
    }
}
