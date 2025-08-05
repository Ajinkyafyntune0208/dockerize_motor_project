<?php

namespace App\Jobs;

use App\Models\KotakPosMapping;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

require_once app_path() . '/Helpers/CarWebServiceHelper.php';

class PosRegistrationProcessSingle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $pos_detail, $company_alias;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($pos_detail, $company_alias)
    {
        $this->pos_detail = $pos_detail;
        $this->company_alias = $company_alias;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (method_exists(self::class, $this->company_alias)) {
            call_user_func_array([self::class, $this->company_alias], [$this->pos_detail]);
        } else {
            info('Pos Registration for '.$this->company_alias. ' not found');
        }
    }

    public static function kotak($pos)
    {
        $pos_request = [
            'objParaUser' => [
                'vUserID' => config('constants.IcConstants.kotak.KOTAK_MOTOR_POS_USERID'),
                'vPassword' => config('constants.IcConstants.kotak.KOTAK_MOTOR_POS_PASSWORD'),
                'vFirstName' => $pos->agent_name,
                'vFieldUserType' => 'PS',
                'vTypeofUser' => 'Point of Sale',
                'vOrganisationName' => '',
                'vMiddleName' => '',
                'vLastName' => '',
                'vIntermediaryCode' => config('constants.IcConstants.kotak.KOTAK_MOTOR_INTERMEDIARY_CODE'),
                'vVertical' => '',
                'vOfficelocationcode' => config('constants.IcConstants.kotak.KOTAK_MOTOR_OFFICE_CODE'),
                'vlicensenos' => $pos->user_name,
                'vLicense_start_date' => Carbon::parse($pos->licence_start_date)->format('d/m/Y'),
                'vLicense_end_date' =>  Carbon::parse($pos->licence_end_date)->format('d/m/Y'),
                'vUid' => '',
                'vPancard' => $pos->pan_no,
            ],
        ];

        $url = config('constants.IcConstants.kotak.KOTAK_POS_REGISTRATION_END_POINT_URL');

        $get_response = getWsData($url, $pos_request, 'kotak', [
            'enquiryId' => $pos->agent_id,
            'requestMethod' => 'post',
            'productName'  => 'KOTAK POS REGISTRATION',
            'company'  => 'kotak',
            'section' => 'car',
            'method' => 'KOTAK POS REGISTRATION',
            'transaction_type' => 'proposal',
            'request_method' => 'post'
        ]);

        $data = $get_response['response'];
        if ($data) {
            $pos_response = json_decode($data, true);
            if (($pos_response['Fn_Add_Field_UserResult']['vErrorMsg'] ?? '') == 'SUCCESS') {
                KotakPosMapping::updateorCreate(
                    ['agent_id' => $pos->agent_id],
                    [
                        'request'       => json_encode($pos_request),
                        'response'      => json_encode($pos_response),
                        'status'        => 'Success',
                        'updated_at'    => date("Y-m-d H:i:s")
                    ]
                );
            } else {
                KotakPosMapping::updateorCreate(
                    ['agent_id' => $pos->agent_id],
                    [
                        'request'       => json_encode($pos_request),
                        'response'      => json_encode($pos_response),
                        'status'        => 'failed',
                        'updated_at'    => date("Y-m-d H:i:s")
                    ]
                );
            }
        } else {
            KotakPosMapping::updateorCreate(
                ['agent_id' => $pos->agent_id],
                [
                    'request'       => json_encode($pos_request),
                    'response'      => json_encode($data),
                    'status'        => 'failed',
                    'updated_at'    => date("Y-m-d H:i:s")
                ]
            );
        }
    }
}
