<?php

namespace App\Jobs;

use App\Models\Agents;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Illuminate\Queue\SerializesModels;
use App\Models\FutureGeneraliPosMapping;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

require_once app_path() . '/Helpers/CarWebServiceHelper.php';
class FutureGeneraliPosRegistration implements ShouldQueue
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
        $all_pos_data = Agents::whereNotIn('agent_id', FutureGeneraliPosMapping::pluck('agent_id'))
            ->where('usertype', 'P')
            ->where('status', 'Active')
            ->whereNotNull('pan_no')
            ->whereNotNull('state')
            ->where([
                ['pan_no', '!=', ''],
                ['state', '!=', ''],
            ])
            ->orderBy('ag_id', 'DESC')
            // ->where('agent_id', 2046)
            ->limit(20)
            ->get();
        $error = [];
        foreach ($all_pos_data as $key => $pos) {
            if (true) {
                if (!empty(config('constants.IcConstants.future_generali.BRANCH_CODE_FUTURE_GENERALI'))) {
                    $branchcode = trim(config('constants.IcConstants.future_generali.BRANCH_CODE_FUTURE_GENERALI'));
                } else {
                    $branchcode = DB::table("fg_cron_branch_master")
                        ->where("branch_name", $pos->state)
                        ->orWhere("region", $pos->state)
                        ->select("branch_code")
                        ->first();
                    $branchcode = $branchcode->branch_code ?? '';
                }

                $root = [
                    "VendorCode" => config('constants.IcConstants.future_generali.POS_REGISTRATION_VENDOR_CODE_FUTURE_GENERALI'),
                    "MajorClass" => "MOT",
                    "ContractType" => "P13",
                    "Method" => "CRT",
                    "Type" => "P",
                    "PanNo" => $pos->pan_no,
                    "AgentCode" => config('constants.IcConstants.future_generali.AGENT_CODE_FUTURE_GENERALI'),
                    "BranchCode" => $branchcode,
                    "FullName" => $pos->agent_name,
                    "City" => $pos->city,
                    "State" => $pos->state,
                    "LicenceNo" => $pos->user_name,
                    "Email" => $pos->email,
                    "Mobile" => $pos->mobile,
                    "AlternateRefNo" => "",
                    "ExpiryDate" => "",
                    "TerminationDate" => ""
                ];

                $additionalData = [
                    'type'          => 'Pos Service',
                    'requestMethod' => 'post',
                    'enquiryId' => $pos->agent_id,
                    'soap_action' => 'Pos_MispMaster',
                    'container'   => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/"><soapenv:Header/><soapenv:Body><tem:Pos_MispMaster><tem:xml><![CDATA[#replace]]></tem:xml></tem:Pos_MispMaster></soapenv:Body></soapenv:Envelope>',
                    'method' => 'FUTURE POS REGISTRATION',
                    'section' => 'car',
                    'transaction_type' => 'POS REGISTRATION',
                    'productName'  => 'FUTURE POS REGISTRATION'
                ];
                $get_response = getWsData(
                    config('constants.IcConstants.future_generali.END_POINT_URL_FUTURE_GENERALI'),
                    $root,
                    'future_generali',
                    $additionalData
                );
                $data_response = $get_response['response'];
                if ($data_response) {
                    $response = html_entity_decode($data_response);
                    $data = XmlToArray::convert($response);
                    if (isset($data['s:Body']['Pos_MispMasterResponse']['Pos_MispMasterResult']['Root'])) {
                        $response_data = $data['s:Body']['Pos_MispMasterResponse']['Pos_MispMasterResult']['Root'];

                        if (isset($response_data["Status"]) && ($response_data["Status"] == "Successful")) {
                            FutureGeneraliPosMapping::updateorCreate(
                                ['agent_id' => $pos->agent_id],
                                [
                                    'request'       => json_encode($root),
                                    'response'      => json_encode($data),
                                    'status'        => $response_data["Status"],
                                    'updated_at'    => date("Y-m-d H:i:s")
                                ]
                            );
                        } else {
                            FutureGeneraliPosMapping::updateorCreate(
                                ['agent_id' => $pos->agent_id],
                                [
                                    'request'       => json_encode($root),
                                    'response'      => json_encode($data),
                                    'status'        => 'failed',
                                    'updated_at'    => date("Y-m-d H:i:s")
                                ]
                            );
                        }
                    } else {
                        FutureGeneraliPosMapping::updateorCreate(
                            ['agent_id' => $pos->agent_id],
                            [
                                'request'       => json_encode($root),
                                'response'      => json_encode($data),
                                'status'        => 'failed',
                                'updated_at'    => date("Y-m-d H:i:s")
                            ]
                        );
                    }
                }/* else
                {
                    return [
                        'status' => false,
                        'message' => "Issue in Pos Registration Service"
                    ];
                }   */
            }
        }

        info('Future Generali Pos Registration', $error);
    }
}
