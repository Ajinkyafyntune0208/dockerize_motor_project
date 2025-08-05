<?php

namespace App\Quotes\FetchRenewalData\Car;

use DateTime;
use App\Models\PreviousInsurerList;
use Illuminate\Support\Facades\Storage;
use App\Models\UserProposal;
use Carbon\Carbon;

class tata_aig
{
    public static function getRenewalData($enquiryId, &$value)
    {

        include_once app_path() . '/Helpers/CarWebServiceHelper.php';

        if (in_array($value["prev_policy_type"], ['OD'])) {
            $policyType = "own_damage";
        } else if (in_array($value["prev_policy_type"], ['COMPREHENSIVE'])) {
            $policyType = "comprehensive";
        } else if ($value["prev_policy_type"] == "TP") {
            $policyType = "third_party";
        } else {
            $policyType = "comprehensive";
        }

        $tpOnly = $policyType == 'third_party';

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'headers'           => [],
            'requestMethod'     => 'post',
            'requestType'       => 'json',
            'section'           => "CAR",
            'method'            => 'Token Generation',
            'transaction_type'  => 'quote',
            'product_name'       => "Renewal Data Migration",
            'type'              => 'token'
        ];

        $tokenRequest = [
            'grant_type'    => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_GRANT_TYPE_RENEWAL'),
            'scope'         => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_SCOPE_RENEWAL'),
            'client_id'     => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_ID_RENEWAL'),
            'client_secret' => config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_CLIENT_SECRET_RENEWAL'),
        ];


        $get_response = getWsData(config('constants.IcConstants.tata_aig_v2.TATA_AIG_V2_END_POINT_URL_TOKEN'), $tokenRequest, 'tata_aig_v2', $additional_data);
        $tokenResponse = $get_response['response'];

        if ($tokenResponse && $tokenResponse != '' && $tokenResponse != null) {

            $tokenResponse = json_decode($tokenResponse, true);

            $tokenResponse = $tokenResponse['access_token'];
        }

        $requestdata = [
            "PolicyNumber" => $value['previous_policy_number'],
            "lobname" => "PrivateCarPolicyInsurance",
            "RegNumber" => ""
        ];

        $fetch_url = config('IC.TATA_AIG.V2.CAR.END_POINT_URL_RENEWAL'); //https://uatapigw.tataaig.com/pc-motor-renewal/v1/modify

        $additional_data = [
            'enquiryId'         => $enquiryId,
            'requestMethod'     => 'post',
            'product_name'       =>  " Renewal Data Migration",
            'company'           => 'tata_aig',
            'section'           => "CAR",
            'method'            => 'Renewal Fetch',
            'transaction_type'  => 'quote',
            'type'              => 'renewal',
            'headers' => [
                'Content-Type'   => "application/json",
                'Connection'     => "Keep-Alive",
                'Authorization'  =>  'Bearer ' . $tokenResponse,
                'Accept'         => "application/json",
                'x-api-key'      =>   config("IC.TATA_AIG.V2.CAR.FETCH_KEY_ID_RENEWAL"),


            ]
        ];

        $get_response = getWsData($fetch_url, $requestdata, 'tata_aig', $additional_data);
        $data = $get_response['response'];
        $response = $data;

        if ($response) {
            $response = json_decode($response);
            if ($response->message_txt == "Success"  && $response->status == '200') {

                $value['vehicle_registration_number'] = $response->data->reg_no == "NEW" ? $response->data->reg_no : getRegisterNumberWithHyphen($response->data->reg_no);
                $value['registration_date'] = date('d-m-Y', strtotime(str_replace('/', '-', $response->data->first_registration)));
                $value['vehicle_manufacture_year'] = date('m-Y', strtotime(str_replace('/', '-', $response->data->first_registration)));
                $value['vehicle_registration_state'] = $response->data->stateperrto;
                $value['vehicle_registration_city'] = $response->data->rto_loc;
                $value['is_financed'] =  'No';
                $value['financier_agreement_type'] = $response->data->agreement_type;
                $value['financier_name'] = "";
                $value['hypothecation_city'] = "";
                $value['engine_no'] = strtoupper($response->data->engine_no);
                $value['chassis_no'] = strtoupper($response->data->chais_no);
                $value['previous_policy_number'] = $response->data->policy_no;
                $value['previous_policy_end_date'] = date('d-m-Y', strtotime(str_replace('/', '-', $response->data->dat_previouspolicyenddate)));
                $value['previous_policy_start_date'] = date('d-m-Y', strtotime(str_replace('/', '-', $response->data->dat_previouspolicystartdate)));
                $value['previous_claim_status'] = "";
                $value['idv'] = round($response->data->total_idv_year1);
                $value['previous_ncb'] = $response->data->expiring_ncb;
                $value['proposal_no'] = $response->data->proposal_no;

                if (!empty($response->data->previousPolicyNumberTP ?? null)) {
                    $value["previous_tp_policy_number"] = $response->data->previousPolicyNumberTP;
                }

                $value['full_name'] = $response->data->First_Name . ' ' . $response->data->Middle_Name . ' ' . $response->data->Last_Name;
                $value['email_address'] = $response->data->email_id;
                $value['mobile_no'] = null;
                $value['owner_type'] = strtolower($response->data->client_type) == 'Organization' ? 'company' : 'individual';
                $value['gender'] = $response->data->gender == "MALE" ? 'M' :  'F';
                $value['dob'] = str_replace('/', '-',  $response->data->dob);
                $value['pan_card'] = $response->data->panNo;
                $value['occupation'] = $response->data->customerOccupation;
                $value['marital_status'] = $response->data->maritalStatus;
                $value['communication_address'] =  $response->data->mail_addr_line_1;
                $value['communication_pincode'] = $response->data->mail_pincode;
                $value['communication_state'] =  $response->data->mail_state;
                $value['communication_city'] =  $response->data->mail_addr_line_1;
                $value['relationship_with_nominee'] = $value['nominee_name'] = $value['nominee_age'] = $value['nominee_dob'] = null;
                $value['ll_paid_driver'] = !empty((float)$response->data->ll_to_paid_driver) ? $response->data->ll_to_paid_driver : 0;


                if (!empty($response->data->ele_accessories_opt)) {

                    $value['electrical'] =  true;
                    $value['electrical_si_amount'] =  "1000";
                }

                if (!empty($response->data->nonelectricalaccessorieopt)) {
                    $value['non-electrical'] = true;
                    $value['non-electrical_si_amount'] =  "1000";
                }

                $value['cpa_amount'] =  !empty((float)$response->data->cparate ?? null) ? $response->data->cparate : null;
                if (!empty($response->data->no_of_unnamed_passengers)) {
                    $value['unnammed_passenger_pa_cover'] = true;
                    $value['unnammed_passenger_pa_cover_si_amount'] = $response->data->si_for_unnamed_passengers;
                }
                if (!empty($response->data->pa_to_paid_driver)) {
                    $value['pa_cover_for_additional_paid_driver'] = $response->data->pa_to_paid_driver;
                    $value['pa_cover_for_additional_paid_driver_si_amount'] =  "100000";
                }
                if (!empty($response->data->anti_thef_disc_opt)) {
                    $value['anti_theft'] = $response->data->current_plan->anti_theft_devi_disc;
                }
                if (!empty($response->data->voluntary_deduct)) {
                    $value['voluntary_excess'] = $response->data->voluntary_deductible_disc;
                    $value['voluntary_excess_si'] =  null;
                }
                $value['geogarphical_extension'] = !empty($response->data->geographical_extension_tp) ?? !empty($response->data->current_plan->geographical_extension_od) ?? null;
                $value['tpp_discount'] = $response->data->tppd_liability;
                ///addons
                $value['zero_dep'] = !empty($response->data->dep_no_claims ?? null) ? $response->data->dep_no_claims : ($value['zero_dep'] ?? null);
                $value['engine_protector'] = !empty($response->data->engine_secu_opt_pip ?? null) ? $response->data->engine_secu_opt_pip : ($value['engine_protector'] ?? null);
                $value['key_replacement'] = !empty($response->data->txt_keyreplacementoptprvyr ?? null) ? $response->data->txt_keyreplacementoptprvyr : ($value['key_replacement'] ?? null);
                $value['loss_of_personal_belonging'] = !empty($response->data->txt_lossofperbelonoptprvyr ?? null) ? $response->data->txt_lossofperbelonoptprvyr : ($value['loss_of_personal_belonging'] ?? null);
                $value['ncb_protection'] = !empty($response->data->ncb_protection_opt_lst_yr ?? null) ? $response->data->ncb_protection_opt_lst_yr : ($value['ncb_protection'] ?? null);
                $value['return_to_invoice'] = !empty($response->data->txt_returntoinvoiceoptprvyr ?? null) ? $response->data->txt_returntoinvoiceoptprvyr : ($value['return_to_invoice'] ?? null);
                $value['rsa'] = !empty($response->data->txt_roadsideassistanceoptprvyr ?? null) ? $response->data->txt_roadsideassistanceoptprvyr : ($value['rsa'] ?? null);
                $value['tyre_secure'] = !empty($response->data->tyresecureinpip ?? null) ? $response->data->tyresecureinpip : ($value['tyre_secure'] ?? null);
              if(!empty( $response->data->current_plan->emergency_transport_si))
              {
                $value['emergency_medical_expenses'] =  $response->data->current_plan->emergency_trans_hotel_exp;
              }

                self::getFyntuneVersionId($value, $response->data->variant_code);
                //this is hardcoded for now due to mmv not mapped git id #29687
                // self::getFyntuneVersionId($value, "100039"); 
                return true;
            }
        }

        return false;
    }

    public static function getFyntuneVersionId(&$value, $code)
    {

        $env = config('app.env');
        if ($env == 'local') {
            $envFolder = 'uat';
        } elseif ($env == 'test') {
            $envFolder = 'production';
        } elseif ($env == 'live') {
            $envFolder = 'production';
        }
        $product = "motor";
        $path = 'mmv_masters/' . $envFolder . '/';
        $path  = $path . $product . "_model_version.json";
        $mmvData = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($path), true);

        foreach ($mmvData as $mmv) {
            if (($mmv['mmv_tata_aig_v2'] ?? '') == $code) {
                $value['version_id'] = $mmv['version_id'];

                break;
            }
        }
    }
}
