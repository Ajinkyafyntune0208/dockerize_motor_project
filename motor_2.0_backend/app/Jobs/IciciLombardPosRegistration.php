<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Agents;
use App\Models\IciciLombardPosMapping;
use Illuminate\Http\Request;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

require_once app_path() . '/Helpers/CarWebServiceHelper.php';

class IciciLombardPosRegistration implements ShouldQueue
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
    public function handle(Request $request)
    {
        $return_data = [];
        $type = $request->input('type', '');

        $all_pos_data = Agents::whereNotIn('agent_id', IciciLombardPosMapping::pluck('agent_id'))
            ->select('agents.*')
            ->where('usertype', 'P')
            ->where('status', 'Active')
            ->whereNotNull('pan_no')
            ->whereNotNull('gender')
            ->whereNotNull('unique_number')
            ->where('pan_no', '<>', '')
            ->where('gender', '<>', '')
            ->where('unique_number', '<>', '')
            ->orderBy('ag_id', 'DESC')
            ->limit(20)
            ->get();
        if (!empty($all_pos_data)) {
            foreach ($all_pos_data as $key => $pos) {
                sleep(1);
                if ($pos) {
                    $location_data = DB::table('icici_illocation_master')->where('city_name', $pos->city)->first();
                    $additionData = [
                        'requestMethod'     => 'post',
                        'type'              => 'tokenGeneration',
                        'productName'       => 'ICICI POS REGISTRATION',
                        'section'           => 'ICICI POS REGISTRATION',
                        'enquiryId'         => $pos->agent_id,
                        'transaction_type'  => 'POS REGISTRATION'
                    ];

                    $tokenParam = [
                        'grant_type'    => 'password',
                        'username'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_USERNAME_MOTOR'),
                        'password'      => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_PASSWORD_MOTOR'),
                        'client_id'     => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_ID_MOTOR'),
                        'client_secret' => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_CLIENT_SECRET_MOTOR'),
                        'scope'         => 'esbgeneric',
                    ];

                    $get_response = getWsData(
                        config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_TOKEN_GENERATION_URL'),
                        http_build_query($tokenParam),
                        'icici_lombard',
                        $additionData
                    );
                    $token = $get_response['response'];
                    $token = json_decode($token, true);

                    if (isset($token['access_token'])) {
                        $uid = getUUID();
                        $submit_pos_certificate = [
                            "IRDALicNo"             => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR'),
                            "CertificateNo"         => $pos->unique_number,
                            "StartDate"             => date('d-m-Y'),
                            "EndDate"               => "31-12-2030",
                            "PanNumber"             => $pos->pan_no,
                            "CertificateUserName"   => $pos->agent_name,
                            "Gender"                => $pos->gender,
                            "AadhaarNo"             => $pos->aadhar_no,
                            "correlationID"         => $uid
                        ];

                        $additionalData = [
                            'requestMethod'     => 'post',
                            'type'              => 'Submit POS Certificate',
                            'section'           => 'ICICI POS REGISTRATION',
                            'productName'       => 'ICICI POS REGISTRATION',
                            'token'             => $token['access_token'],
                            'enquiryId'         => $pos->agent_id,
                            'transaction_type'  => 'POS REGISTRATION'
                        ];
                        $get_response = getWsData(
                            config('SUBMIT_POS_CERTIFICATE_ICICI_LOMBARD'),
                            $submit_pos_certificate,
                            'icici_lombard',
                            $additionalData
                        );
                        $data_response = $get_response['response'];
                        $data_response = json_decode($data_response, true);
                        if (isset($data_response["status"])) {
                            // $name = explode(' ',$pos->agent_name);
                            $create_im_broker = [
                                "correlationID"         => $uid,
                                "PanNumber"             => $pos->pan_no,
                                "LicenseNo"             => config('constants.IcConstants.icici_lombard.ICICI_LOMBARD_IRDA_LICENCE_NUMBER_MOTOR'),
                                "IlLocation"            => config('IlLocation') == '' ? $location_data->il_location_name :  config('IlLocation'), //"MUMBAI - ANDHERI TELI GALI",  
                                "CertificateNo"         => $pos->unique_number,
                                "FirstName"             => $pos->first_name ?? '',
                                "MiddleName"            => $pos->middle_name ?? '',
                                "LastName"              => empty($pos->last_name) ? '.' : $pos->last_name,
                                "FatherHusbandName"     => (($pos->father_name == '') ? ' ' : $pos->father_name),
                                "DateOfBirth"           => date("d/m/Y", strtotime($pos->date_of_birth)),
                                "Gender"                => $pos->gender == 'M' ? 'MALE' : 'FEMALE',
                                "Mobile"                => $pos->mobile,
                                "EmailId"               => $pos->email,
                                "ContactPersonMobile"   => $pos->mobile,
                                "ContactPersonEmailId"  => $pos->email,
                                "Address"               => $pos->address == '' ? $pos->city : $pos->address,
                                "State"                 => $pos->state,
                                "City"                  => $pos->city,
                                "Country"               => "India",
                                "PostalCode"            => $pos->pincode,
                            ];
                            unset($additionalData);
                            $additionalData = [
                                'requestMethod'     => 'post',
                                'type'              => 'Create IM Broker Child',
                                'section'           => 'ICICI POS REGISTRATION',
                                'productName'       => 'ICICI POS REGISTRATION',
                                'token'             => $token['access_token'],
                                'enquiryId'         => $pos->agent_id,
                                'transaction_type'  => 'POS REGISTRATION'
                            ];

                            $get_response = getWsData(
                                config('CREATE_IMBROKER_POSCHILD_ICICI_LOMBARD'),
                                $create_im_broker,
                                'icici_lombard',
                                $additionalData
                            );
                            $data_response_im = $get_response['response'];
                            $data_response_im =  json_decode($data_response_im, true);
                            if (isset($data_response_im[0])) {
                                $data_response_im = $data_response_im[0];
                            }
                            if (isset($data_response_im["status"]) && strtolower($data_response_im["status"]) == "success") {
                                IciciLombardPosMapping::updateorCreate(
                                    ['agent_id' => $pos->agent_id],
                                    [
                                        'im_id'         => $data_response_im["imid"] ?? NULL,
                                        'request'       => json_encode($create_im_broker),
                                        'response'      => json_encode($data_response_im["statusDesc"]),
                                        'status'        => $data_response_im["status"],
                                        'updated_at'    => date("Y-m-d H:i:s")
                                    ]
                                );

                                $return_data[] = [
                                    'status' => true,
                                    'message' => "Agent registered successfully . " . $pos->agent_id
                                ];
                            } else {
                                IciciLombardPosMapping::updateorCreate(
                                    ['agent_id' => $pos->agent_id],
                                    [
                                        'im_id'         => $data_response_im["imid"] ?? NULL,
                                        'request'       => json_encode($create_im_broker),
                                        'response'      => json_encode($data_response_im["statusDesc"] ?? $data_response_im),
                                        'status'        => 'success', //((strpos($data_response['statusDesc'], 'IM Already exist for PAN') !== false) ? "success" :( (strpos($data_response['statusDesc'], 'Certifiacte Already Exists!') !== false) ? "success" :  $data_response_im["status"]) ),
                                        'updated_at'    => date("Y-m-d H:i:s")
                                    ]
                                );

                                $return_data[] =  [
                                    'status' => true,
                                    'message' => "Agent was already registered . " . $pos->agent_id
                                ];
                            }
                        } else {
                            $return_data[] =  [
                                'status' => false,
                                'message' => "Error in Submit pos certificate service"
                            ];
                        }
                    } else {
                        $return_data[] =  [
                            'status' => false,
                            'message' => "Issue in Token Generation Service " . $pos->agent_id
                        ];
                    }
                } else {
                    $return_data[] =
                        [
                            'status' => false,
                            'message' => "Data not complete " . $pos->agent_id
                        ];
                }
            }
        } else {
            $return_data[] =
                [
                    'status' => false,
                    'message' => "No Agents to process"
                ];
        }
        return $return_data;
    }
}
