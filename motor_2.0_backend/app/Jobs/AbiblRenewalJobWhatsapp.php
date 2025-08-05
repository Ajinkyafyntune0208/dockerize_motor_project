<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use App\Models\AbiblDailerApiLog;
use App\Models\UserProductJourney;
use App\Models\CommunicationLogs;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Http\Controllers\ProposalReportController;
use Illuminate\Http\Request;
use App\Http\Controllers\LeadController;
use App\Models\PolicyDetails;
use App\Models\RenewalNotificationTemplates;
use Illuminate\Support\Facades\DB;

class AbiblRenewalJobWhatsapp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $user_product_journey_id;
    protected $queue_number;
    public function __construct($trace_id, $queue_no)
    {
        //
        $this->user_product_journey_id = $trace_id;
        $this->queue_number = $queue_no;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $anyCampgain = [ 'ABIBL_MG_DATA', 'HYUNDAI','',NULL ];
        $renewal_days = config('RENEWAL_NOTIFICATION_DAYS');
        $renewal_days = explode(',', $renewal_days);
        foreach ($renewal_days as $key => $days) {
            $user_product_journeys = UserProductJourney::whereIn('lead_source', $anyCampgain )->with(['user_proposal' => function ($query) {
                $query->select(['user_proposal_id', 'user_product_journey_id', 'policy_end_date', 'vehicale_registration_number', 'first_name', 'last_name', 'mobile_number']);
            }])->whereHas('user_proposal', function ($query) use ($days) {
                $query->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') = CURDATE() + INTERVAL {$days} DAY");
            })->whereHas('journey_stage', function ($query) {
                $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
            })->whereHas('corporate_vehicles_quote_request', function ($query) {
                $query->whereNotNull('version_id')->whereRaw('version_id != ""');
            })->where('user_product_journey_id', $this->user_product_journey_id)
                ->get(['user_product_journey_id', 'created_on', 'lead_source']);


            foreach ($user_product_journeys as $key => $value) {
                $policy_no = PolicyDetails::where('proposal_id', $value->user_proposal->user_proposal_id)
                    ->get(['policy_number'])->pluck('policy_number')[0];

                $policy_end_date = $value->user_proposal->policy_end_date ?? null;
                $old_user_product_journey_id = $value->user_product_journey_id;

                $vehicale_registration_number = $value->user_proposal->vehicale_registration_number ?? null;


                $payload = [
                    'reg_no'        => $vehicale_registration_number,
                    'policy_no'     => $policy_no,
                    'source'        => $value->lead_source,
                    'segment'       => 'CAR',
                    'redirection'   => 'N' // for getting link of renewal
                ];
                $LeadController = new LeadController();
                $get_lead_link = $LeadController->getleads(request()->replace($payload));
                if ( !is_array( $get_lead_link ) ) 
                {
                    $get_lead_link = json_decode( $get_lead_link, true );
                }
                if (!isset($get_lead_link['new_user_product_journey_id'])) {
                    continue;
                }

                $url = $get_lead_link['redirection_url'];
                $proposer_mobile = $value->user_proposal->mobile_number;
                // $proposer_mobile = "9527849688";
                // unset($proposal_report['proposer_mobile']);

                $name = $value->user_proposal->first_name . ' ' . $value->user_proposal->last_name;
                //$proposer_mobile = 9819898104;

                $templates = RenewalNotificationTemplates::where('days', $days)
                    ->where('status', 'Active')
                    ->where('notification_type', 'renewal')
                    ->get()->toArray();
                foreach ($templates as $t_value) {
                    $type = strtoupper($t_value['type']);
                    $store_logs = false;
                    $data = '';
                    switch ($type) {
                        case 'WHATSAPP':

                            $data = [
                                "send_to"   => $proposer_mobile,
                                "msg_type"  => $t_value['media_type'],
                                "method"    =>  $t_value['method'],
                                "isTemplate" => "true",
                                "caption" => str_replace("\r\n", "\n", $t_value['template']),
                                "media_url" => $t_value['media_path'] ?? '',
                                "cta_button_url" => $url,
                                "footer" => "Aditya Birla Insurance Brokers Limited",

                            ];

                            if (!empty($t_value['footer'])) {
                                unset($data['footer']);
                                $data['footer'] = $t_value['footer'];
                            }

                            if (!empty($t_value['variables_in_template'])) {
                                $data['msg'] = self::response_build();
                            }

                            if (empty($t_value['template'])) return;
                            $response = httpRequest('whats_app_two_way', $data);
                            $store_logs = true;
                            break;

                        case 'SMS':
                            $url = shortUrl($url)['response']['short_url'];

                            // sms code to implemented
                            $data = [
                                "To" => $value->user_proposal->mobile_number,
                                "message" => "Hi,Thank you for placing your insurance inquiry. Here are the quotes for your motor insurance {$url} -Aditya Birla Insurance Brokers Ltd"
                            ];

                            $response = httpRequest('sms', $data);
                            $store_logs = true;

                            break;

                        case 'EMAIL':
                            //email code to implemented

                            break;
                    }

                    if ($store_logs == true) {
                        $status = ($response['status'] == '200') ? true : false;
                        $response = $response['response'] ?? 'NO RESPONSE';
                        $req =  json_encode($data);
                        $trace_id = $get_lead_link['new_user_product_journey_id'] ?? '';
                        $save = StoreCommunicationLogs($type, $get_lead_link['new_user_product_journey_id'], $req, json_encode($response), $status, 'RENEWAL', (string) $t_value['days'], $old_user_product_journey_id, $policy_end_date, $this->queue_number);
                    }
                }
            }

        }
    }

    public function response_build()
    {
        // this function needs to be implmented for variables
    }
}
