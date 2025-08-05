<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Models\UserProductJourney;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\RenewalNotificationTemplates;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class AbiblDropoutJourneyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $enquiryId = null;
    protected $dropout = null;
    public function __construct($enquiryId,$dropout)
    {
        $this->enquiryId = $enquiryId;
        $this->dropout = $dropout;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('ABIBL_DROPOUT_JOURNEY_JOB') != "Y") {
            return;
        }

        if (empty($this->enquiryId)) {
            return ;
        }
        if ($this->enquiryId) {
            $user_product_journey = UserProductJourney::where('user_product_journey_id', customDecrypt($this->enquiryId))
                ->whereHas('journey_stage', function ($query) {
                    $query->whereNotIn('stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']]);
                })->get(['user_product_journey_id', 'created_on']);
        }
        $templates = RenewalNotificationTemplates::where('status','Active')
                                                    ->where('notification_type','dropout')
                                                    ->get()->toArray();
        
        foreach ($user_product_journey as $key => $value) {
            //$droput = \App\Models\AbiblDropoutJourney::where(['user_product_journey_id' => $value->user_product_journey_id, 'status' => 'dropout'])->first();
//            if ($droput) {
//                continue;
//            }            
            //$url_report = 'https://motor_api_uat.adityabirlainsurancebrokers.com/api/proposalReports';
            $url_report = url('api/proposalReports');
            $proposal_report = httpRequestNormal($url_report, 'POST', ['enquiry_id' => $value->journey_id])['response']['data'][0];
            $proposer_mobile = $proposal_report['proposer_mobile'];
            unset($proposal_report['proposer_mobile']);
            $proposal_report = array_merge($proposal_report, [
                "source" => "ABIBL",
                "sub_source" => "IVR Bot",
                "campaign_id" => "40004034" #"50005000"
            ]);
            $url = ($proposal_report['proposal_url'] ?? $proposal_report['quote_url']) . '&dropout=true';
            $payload_id = time() . rand();
            $data = [
                "payloadEncrypted" => "false",
                "payloadId" => 'abibltestrequest' . rand(),
                "payload" => "{'context':'" . json_encode($proposal_report) . "','msisdn':'" . $proposer_mobile . "','campaign':'" . $proposal_report["campaign_id"] . "','customerInfo':'','priority':'Urgent'}"
            ];
            $Dropout_mobile = explode(',',config("DROPOUT_ALLOWED_MOBILE"));   
            $name = $proposal_report['first_name'].' '.$proposal_report['last_name'];
            $url = shortUrl($url)['response']['short_url'];

                foreach($templates as $t_value)
                {
                    $type = strtoupper($t_value['type']);
                    $store_logs = false;
                    $data = '';
                    switch ($type) 
                    {
                        case 'WHATSAPP':

                                        $data = [
                                            "send_to"   => $proposer_mobile,
                                            "msg_type"  => $t_value['media_type'],
                                            "method"    => $t_value['method'],
                                            "isTemplate" => "true",
                                            "msg" => $t_value['template'],
                                            "media_url" => $t_value['media_path'] ?? '',
                                            "cta_button_url" => $url,
                                            "footer" => "Aditya Birla Insurance Brokers Limited",
                      
                                        ];

                                        if(!empty($t_value['footer']))
                                        {
                                            unset($data['footer']);
                                            $data['footer'] = $t_value['footer']; 
                                        }

                                        if(!empty($t_value['variables_in_template']))
                                        {
                                            $data['msg'] = self::response_build();
                                        }

                                        if (empty($t_value['template'])) return;
                                        $response = httpRequest('whats_app_two_way', $data);
                                        $store_logs = true;
                            break;
                        
                        case 'SMS':
                            // sms code to implemented
                            $data = [
                                "To" => $proposer_mobile,
                                "message" => "Hi,Thank you for placing your insurance inquiry. Here are the quotes for your motor insurance {$url} -Aditya Birla Insurance Brokers Ltd"
                            ];

                            $response = httpRequest('sms', $data);
                            $store_logs = true;

                            break;

                        case 'EMAIL':
                            //email code to implemented

                            break;

                    }

                    if($store_logs == true)
                    {
                        $status = ($response['status'] == '200') ? true : false;
                        $response = $response['response'] ?? 'NO RESPONSE';
                        $req =  json_encode($data);
                        $save = StoreCommunicationLogs($type, $value->user_product_journey_id,$req,json_encode($response),$status,'OTHER',1,$value->user_product_journey_id);
     
                    }
                    
                }
            //httpRequest('dropout', $data);
            \App\Models\AbiblDropoutJourney::Create( ['user_product_journey_id' => $value->user_product_journey_id, 'status' => $this->dropout]);
        }
    }

    public function response_build()
    {
        // this function needs to be implmented for variables
    }
}
