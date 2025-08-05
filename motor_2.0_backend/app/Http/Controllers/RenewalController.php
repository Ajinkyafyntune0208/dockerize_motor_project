<?php

namespace App\Http\Controllers;
use App\Models\MasterRto;
use App\Models\MasterPolicy;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterCompany;
use App\Models\RenewalDataApi;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\PreviousInsurerList;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\CommonController;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\NomineeRelationship;
use App\Models\UserTokenRequestResponse;
use App\Models\CvAgentMapping;
use Carbon\Carbon;

//include_once app_path() . '/Helpers/BikeWebServiceHelper.php';

class RenewalController extends Controller
{
    public static function kotakConfirmRenewalData($productData, $renewalData)
    {
        $tokenData = getKotakTokendetails('bike');

        $token_req_array = [
            'vLoginEmailId' => $tokenData['vLoginEmailId'],
            'vPassword' => $tokenData['vPassword'],
        ];
        $data = getWsData(config('constants.IcConstants.kotak.END_POINT_URL_TOKEN_KOTAK_BIKE'), $token_req_array, 'kotak', [
            'Key' => $tokenData['vRanKey'],
            'enquiryId' => $renewalData['enquiryId'],
            'requestMethod' => 'post',
            'productName' => $productData->product_name,
            'company' => 'kotak',
            'section' => $productData->product_sub_type_code,
            'method' => 'Token Generation',
            'transaction_type' => 'quote',
        ]);
        return $data['response'];
    }

    public static function fetchRenewalData($request)
    { 
        //$request->registration_no = 'DL-01-RT-4563';
        if ($request->isPolicyNumber == 'Y') {
            $policy_number = $request->policyNumber;
            $registration_number = null;
        } else {
            $policy_number = null;
            $registration_number = str_replace("-", "", $request->vendor_rc ?? $request->registration_no);
        }

        $renewal_fetch_data = [
            'registration_number'   => $registration_number,
            'policy_number'         => $policy_number,
            'access_key'            => config('RENEWBUY_FETCH_RENEWAL_DATA_API_ACCESS_KEY')
        ];

//        $renewal_fetch_data = [
//            'registration_number' => 'HR26TG4563',
//            'policy_number' => null,
//        ];
        $url = config('RENEWBUY_FETCH_RENEWAL_DATA_API');
        //$tokken = config('RENEWBUY_FETCH_RENEWAL_DATA_API_TOKKEN');
        //$url = '14202.diff.rbstaging.in/api/v1/motor/renewal_data/';UAT
        //$url = '14824.diff.rbstaging.in/api/v1/motor/renewal_data/';
        //$url = '14864.diff.rbstaging.in/api/v1/motor/fyntune_data/renewal_data/';
        //UAT
        //$url = 'https://www.renewbuy.com/api/v1/motor/renewal_data/';
        //Prod
        //$url = config('RENEWBUY_FETCH_RENEWAL_DATA_API');
       // $tokken = '872644aa6e3ae1715cf9083f8be4cea447aec1c8';//config('RENEWBUY_FETCH_RENEWAL_DATA_API_TOKKEN');
        //$tokken = config('RENEWBUY_FETCH_RENEWAL_DATA_API_TOKKEN');
        //$renewal_response = Http::withoutVerifying()->post($url,$renewal_fetch_data)->json();
        //$renewal_response = httpRequestNormal($url, 'POST', $renewal_fetch_data, [], ['Authorization' => 'Token '.$tokken ], [], false)['response'];
        $renewal_response = httpRequestNormal($url, 'POST', $renewal_fetch_data, [], [], [], false)['response'];
//        print_r($renewal_response);
//        die;
        RenewalDataApi::updateOrCreate(                    
                ['user_product_journey_id' => customDecrypt($request->enquiryId)],
                [
                'registration_no'   => $request->registration_no,
                'policy_number'     => $policy_number,
                'api_request'       => is_array($renewal_fetch_data) ? json_encode($renewal_fetch_data) : $renewal_fetch_data,
                'api_response'      => json_encode($renewal_response),
                //'required_data'     => json_encode($generateUserJourneyData),
                'url'               => $url,
                'created_at'        => now(),
                'updated_at'        => now()
            ]);
        if(isset($renewal_response['error']))
        {
            return (object) [
                'status' => false,
                'msg' => $renewal_response['error']
            ];
        }
        if (isset($renewal_response["rto_city_id"])) 
        {
            if($renewal_response['vehicle_class'] == 'TWO_WHEELER')
            {
               $product_sub_type_id = 2;
               $ft_product_code = 'bike';
            }
            else if($renewal_response['vehicle_class'] == 'FOUR_WHEELER')
            {
               $product_sub_type_id = 1;
               $ft_product_code = 'car';
            }
            $version_id = NULL;
            if($ft_product_code == 'car' || $ft_product_code == 'bike')
            {
                $env = config('app.env');
                if ($env == 'local') 
                {
                    $env_folder = 'uat';
                } 
                else if ($env == 'test' ||  $env == 'live') 
                {
                    $env_folder = 'production';
                }
                $path = 'mmv_masters/' . $env_folder . '/';
                $file_name  = $path .'fyntune_rb_'.$ft_product_code.'_relation.json';
                $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
                $version_id = $data[$renewal_response['vehicle_variant_id']]['fyntune_version_id'] ?? NULL;
                if (empty($version_id) && !empty($renewal_response['vehicle_variant_id'] ?? null)) {
                    $file_name  = $path . ($ft_product_code == 'car' ? 'motor' : $ft_product_code) . '_model_version.json';
                    $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
                    $key = array_search($renewal_response['vehicle_variant_id'], array_column($data, 'mmv_renewbuy'));
                    if ($key !== false) {
                        $version_id = array_keys($data)[$key] ?? null;
                    }
                }
            }
            //$fyntune_mmv_code = get_fyntune_mmv_code($product_sub_type_id,$renewal_response['vehicle_variant_id'],'renewbuy');
            if($version_id != NULL)
            {
               //$version_id = $fyntune_mmv_code['data']['version_id'];
               RenewalDataApi::updateOrCreate(                    
                ['user_product_journey_id' => customDecrypt($request->enquiryId)],
                [
                    'mmv_source'   => 'RENEWAL API'
                ]);
            }
            else
            {
                //$request->registration_no = "BR-01-EE-1985";
                //This api is only for validating , Not Updating any data in DB
                $ongrid_data_response = \App\Http\Controllers\ProposalVehicleValidation::onGridValidateVehicle($request)->getData();
                $ongrid_data_response = json_decode(json_encode($ongrid_data_response), true);
//                $common = new CommonController;
//                //this api is updating the data in DB
//                $ongrid_data_response = $common->vehicleDetails($request);
//                $ongrid_data_response = json_decode($ongrid_data_response,TRUE);
                if($ongrid_data_response['status'] == true)
                {
                    if(isset($ongrid_data_response['data']['data']['rc_data']['vehicle_data']['custom_data']['version_id']))
                    {
                        $version_id = $ongrid_data_response['data']['data']['rc_data']['vehicle_data']['custom_data']['version_id'];
                        
                        RenewalDataApi::updateOrCreate(                    
                            ['user_product_journey_id' => customDecrypt($request->enquiryId)],
                            [
                                'mmv_source'   => 'ONGRID'
                            ]);
                    }
                }
            }
            if(!isset($version_id)) {
                return (object) [
                    'status' => false,
                    'msg' => 'Unable to Find Vehicle Version Mapping. '.$ft_product_code.' => '.$renewal_response['vehicle_variant_id']
                ];
            }
            $fyntune_mmv_details = get_fyntune_mmv_details($product_sub_type_id,$version_id,'renewbuy');
            if($fyntune_mmv_details['status'] == false)
            {
		return (object) [
                    'status' => false,
                    'msg' => ($fyntune_mmv_details['message'] ?? 'Vehicle Not Found for Vehicle Code'). ' : ' . $version_id
                ];
            }
            $business_type = 'rollover';
            $previous_policy_type = 'Comprehensive';
            if($renewal_response['coverage_type'] == "COMPREHENSIVE")
            {
                $previous_policy_type = 'Comprehensive';
                $applicable_premium_type_id = '1';
                
                $policy_start_date = date('d-m-Y', strtotime($renewal_response['previous_policy_startdate']));
                $policy_end_date = date('d-m-Y', strtotime($renewal_response['previous_policy_enddate']));
                $policy_number = $renewal_response['previous_policy_number'];
                
                $policy_start_date_tp = $renewal_response['previous_policy_startdate_tp'] != '' ? date('d-m-Y', strtotime($renewal_response['previous_policy_startdate_tp'])) : '';
                $policy_end_date_tp = $renewal_response['previous_policy_enddate_tp'] != '' ? date('d-m-Y', strtotime($renewal_response['previous_policy_enddate_tp'])) : '';
                $tp_policy_number = $renewal_response['previous_policy_number_tp'];
                
            }
            else if($renewal_response['coverage_type'] == "OWN_DAMAGE")
            {
                $previous_policy_type = 'Own Damage';
                $applicable_premium_type_id = 3;
                
                $policy_start_date = date('d-m-Y', strtotime($renewal_response['previous_policy_startdate']));
                $policy_end_date = date('d-m-Y', strtotime($renewal_response['previous_policy_enddate']));
                $policy_number = $renewal_response['previous_policy_number'];
                
                $policy_start_date_tp = date('d-m-Y', strtotime($renewal_response['previous_policy_startdate_tp']));
                $policy_end_date_tp = date('d-m-Y', strtotime($renewal_response['previous_policy_enddate_tp']));
                $tp_policy_number = $renewal_response['previous_policy_number_tp'];
            }
            else if($renewal_response['coverage_type'] == "THIRD_PARTY")
            {
                $previous_policy_type = 'Third Party';
                $applicable_premium_type_id = 2;
                
                $policy_start_date = date('d-m-Y', strtotime($renewal_response['previous_policy_startdate_tp']));
                $policy_end_date = date('d-m-Y', strtotime($renewal_response['previous_policy_enddate_tp']));
                $policy_number = $renewal_response['previous_policy_number'] ?? $renewal_response['previous_policy_number_tp'];
                
                $policy_start_date_tp = date('d-m-Y', strtotime($renewal_response['previous_policy_startdate_tp']));
                $policy_end_date_tp = date('d-m-Y', strtotime($renewal_response['previous_policy_enddate_tp']));
                $tp_policy_number = $renewal_response['previous_policy_number_tp'];
            }
            
            $policy_type = $previous_policy_type == 'Own Damage' ? 'own_damage' : 'comprehensive';

            // if (
            //     $previous_policy_type == 'Own Damage' && !empty($renewal_response['registration_date']) &&
            //     in_array($product_sub_type_id, [1, 2])
            // ) {
            //     $registratiom_date = Carbon::parse($renewal_response['registration_date']);
            //     $od_compare_years = $product_sub_type_id == 1 ? 3 : 5;
            //     $od_compare_date = now()->subYear($od_compare_years - 1)->subDay(180);

            //     $policy_type = 'comprehensive';
            //     $applicable_premium_type_id = 1;
            //     if ($registratiom_date > $od_compare_date) {
            //         $policy_type = 'own_damage';
            //         $applicable_premium_type_id = 3;
            //     }
            //     // dd($policy_type, $registratiom_date > $od_compare_date);
            // }
            
            $ncb_slab = [
                '0'     => 20,
                '20'    => 25,
                '25'    => 35,
                '35'    => 45,
                '45'    => 50,
                '50'    => 50
            ];
            $previous_ncb = (int) $renewal_response['proposal_ncb'];
            if (!isset($ncb_slab[$previous_ncb])) {
                return (object) [
                    'status' => false,
                    'msg' => 'Invalid previous NCB'
                ];
            }
            $applicable_ncb = $ncb_slab[$previous_ncb];
            
            $custom_data = [
                'product_sub_type_id'           => $product_sub_type_id,
                'business_type'                 => $business_type,
                'policy_type'                   => $policy_type,
                'previous_policy_type'          => $previous_policy_type,
                'previous_ncb'                  => $previous_ncb,
                'applicable_ncb'                => $applicable_ncb,
                'applicable_premium_type_id'    => $applicable_premium_type_id,
                'policy_start_date'             => $policy_start_date,
                'policy_end_date'               => $policy_end_date,
                'policy_number'                 => $policy_number,
                'policy_start_date_tp'          => $policy_start_date_tp ?? '',
                'policy_end_date_tp'            => $policy_end_date_tp ?? '',
                'tp_policy_number'              => $tp_policy_number ?? ''                
            ];
            
            $renewal_response['previous_IC'] = getPreviousIcMapping($renewal_response['insurer_name']);
            $find_company = explode(' ',$renewal_response['insurer_name']);
            $company_details = DB::table('master_company as mc')
                                ->when(!empty($renewal_response['previous_IC']->company_alias ?? ''), function($q1) use ($renewal_response){
                                    $q1->where('company_alias', '=', $renewal_response['previous_IC']->company_alias);
                                }, function($q2) use ($find_company) {
                                    $q2->where('company_name', 'like', '%' . trim($find_company[0]) . '%');
                                })
                                ->select('company_id','company_name','company_alias')
                                //->get()
                                ->first();
            $custom_data['company_id'] = $company_details->company_id;
            $custom_data['company_name'] = $company_details->company_name;
            $custom_data['company_alias'] = $company_details->company_alias;
            //$renewal_response['addons']['is_zero_dep'] = true;
            if($renewal_response['previous_insurer_name_tp'] != NULL)
            {
               $tp_previous_IC = json_decode(getPreviousIcMapping($renewal_response['previous_insurer_name_tp']),TRUE); 
            }
            $custom_data['tp_company_id'] = $tp_previous_IC['master_company']['company_id'] ?? '';
            $custom_data['tp_company_name'] = $tp_previous_IC['master_company']['company_name'] ?? '';
            $custom_data['tp_company_alias'] = $tp_previous_IC['master_company']['company_alias'] ?? '';
            $ZD = $renewal_response['addons']['is_zero_dep'] == true ? '0' : '1';
            $MasterPolicy = MasterPolicy::where('insurance_company_id',$custom_data['company_id'])
                            ->where('product_sub_type_id',$custom_data['product_sub_type_id'])
                            ->where('premium_type_id',$custom_data['applicable_premium_type_id'])
                            ->where('zero_dep',$ZD)
//                            ->get
                            ->first();
            
            $custom_data["previous_master_policy_id"] = $MasterPolicy->policy_id;
            
            $custom_data["version_id"]      = $fyntune_mmv_details['data']['version']['version_id'];            
            $custom_data["version_name"]    = $fyntune_mmv_details['data']['version']['version_name'];
            $custom_data["cubic_capacity"]  = $fyntune_mmv_details['data']['version']['cubic_capacity'];
            $custom_data["fuel_type"]       = $fyntune_mmv_details['data']['version']['fuel_type'];
            $custom_data["seating_capacity"] = $fyntune_mmv_details['data']['version']['seating_capacity'];
            
            $custom_data["model_id"]        = $fyntune_mmv_details['data']['version']['model_id'];
            $custom_data["model_name"]      = $fyntune_mmv_details['data']['model']['model_name'];
            $custom_data["manf_id"]         = $fyntune_mmv_details['data']['model']['manf_id'];
            $custom_data["manf_name"]       = $fyntune_mmv_details['data']['manufacturer']['manf_name'];
            if($renewal_response['registration_number'] == NULL || strtoupper($renewal_response['registration_number']) == 'NEW')
            {
              $renewal_response['registration_number'] = NULL;  
            }
            else
            {
               $renewal_response['registration_number'] = ($request->isPolicyNumber == 'Y') ? getRegisterNumberWithHyphen($renewal_response['registration_number']) : $request->registration_no; 
            }
            
            $generateUserJourneyData = (new self)->generateUserJourneyData($renewal_response,$custom_data);
            $generateCorporateData = (new self)->generateCorporateData($renewal_response,$custom_data);
            $generateAddonData = (new self)->generateAddonData($renewal_response['addons'],$custom_data);
            $generateQuoteLogData = (new self)->generateQuoteLogData($renewal_response,$custom_data);
            $generateProposalData = (new self)->generateProposalData($renewal_response,$custom_data); 
            $generateUserJourneyData->user_proposal = $generateProposalData;
            $generateUserJourneyData->policy_number = $renewal_response['policy_number'];
            $generateUserJourneyData->quote_log = $generateQuoteLogData;
            $generateUserJourneyData->quote_log['quote_details'] = $generateQuoteLogData;
            $generateUserJourneyData->corporate_vehicles_quote_request = $generateCorporateData;
            $generateUserJourneyData->agent_details = [];
            $generateUserJourneyData->pos_details = [];
            $generateUserJourneyData->journey_stage = [];
//            $generateUserJourneyData->sub_product = [
//                "product_sub_type_id" => $product_sub_type_id,
//                "product_id"          => 1,
//                "parent_id"           => 0,
//                "parent_product_sub_type_id" => 2,
//                "product_sub_type_code" => "BIKE",
//                "product_sub_type_name" => "Two Wheeler Insurance",
//                "short_name" => "TWI",
//                "logo" => "",
//                "status" => "Active",
//            ];
            $generateUserJourneyData->addons = [
                $generateAddonData,
            ];
            if(!empty($renewal_response['pos_code']))
            {
                $generateUserJourneyData->pos_details['pos_code'] = $renewal_response['pos_code'];
            }
            RenewalDataApi::updateOrCreate(                    
                ['user_product_journey_id' => customDecrypt($request->enquiryId)],
                [
                'registration_no'   => $request->registration_no,
                'policy_number'     => $policy_number,
                'api_request'       => is_array($renewal_fetch_data) ? json_encode($renewal_fetch_data) : $renewal_fetch_data,
                // 'api_response'      => json_encode(array_diff_key($renewal_response, array('previous_IC' => ''))),
                'required_data'     => json_encode($generateUserJourneyData),
                'url'               => $url,
                //'created_at'        => now(),
                'updated_at'        => now()
            ]);
           return $generateUserJourneyData;
        } 
        else 
        {
            return (object) [
                'status' => false,
                'msg' => 'No data found'
            ];

        }
    }

    public function generateUserJourneyData($payload,$custom_data)
    {
        list($firstname, $lastname) = getFirstAndLastName($payload['customer_fullname']);
        return
        (object) [
            "product_sub_type_id"   => $custom_data['product_sub_type_id'],
            "user_fname"            => $firstname,
            "user_id"               => null,
            "user_mname"            => $firstname,
            "user_lname"            => $lastname,
            "user_email"            => $payload['customer_email'],
            "user_mobile"           => $payload['customer_mobileno'],
            "status"                => "yes",
            "lead_stage_id"         => null,
            "lead_source"           => "API",
            "api_token"             => null,
        ];
    }

    public function generateQuoteLogData($payload,$custom_data)
    {
        return [
            "full_name"                         => $payload['customer_fullname'],
            "user_email"                        => $payload['customer_email'],
            "user_mobile"                       => $payload['customer_mobileno'],
            "product_sub_type_id"               => $custom_data['product_sub_type_id'],
            "manfacture_id"                     => $custom_data['manf_id'],
            "manfacture_name"                   => $custom_data['manf_name'],
            "model"                             => $custom_data['model_id'],
            "model_name"                        => $custom_data['model_name'],
            "version_name"                      => $custom_data['version_name'],
            "version_id"                        => $custom_data['version_id'],
            "vehicle_usage"                     => 2,
            "policy_type"                       => $custom_data['policy_type'],
            "business_type"                     => $custom_data['business_type'],
            "vehicle_registration_no"           => $payload['registration_number'],
            "vehicle_register_date"             => !empty($payload['registration_date']) ? date('d-m-Y', strtotime($payload['registration_date'])) : null,
            "previous_policy_expiry_date"       => date('d-m-Y', strtotime($payload['previous_policy_enddate'])),
            "previous_policy_type"              => $custom_data['previous_policy_type'],
            "fuel_type"                         => $payload['vehicle_fuel_type'],
            "manufacture_year"                  => '01-'.$payload['manufacturing_year'],
            //"rto_code"                          => implode('-', str_split($payload['rto_code'], 2)),
            "rto_code"                          => $this->getRtoCodeWithHyphen($payload['rto_code']),
            "vehicle_owner_type"                => $payload['customer_type'] == 'Individual' ? 'I' : 'C',
            "is_claim"                          => "N",
            "previous_ncb"                      => $custom_data['previous_ncb'],
            "applicable_ncb"                    => $custom_data['applicable_ncb'],
            "is_ncb_verified"                   => "N",
            "ic_id"                             => $custom_data['company_id'],
            "ic_alias"                          => $custom_data['company_name'],
            "master_policy_id"                  => $custom_data['previous_master_policy_id'],
        ];
    }

    public function generateAddonData($addons_data,$custom_data)
    {
        $all_addons = [
            'is_zero_dep' => [
                'name' => 'Zero Depreciation',
                'prevZeroDep' => 'Yes',
            ],
            //no consu
            'is_rsa' => [
                'name' => 'Road Side Assistance',
            ],            
            'is_key_replacement' => [
                'name' => 'Key Replacement',
            ],
            'is_engine_protector' => [
                'name' => 'Engine Protector',
            ],
            //Consumable and NCB Protection not available in API response
            'is_tyre_secure' => [
                'name' => 'Tyre Secure',
            ],
            'is_rti' => [
                'name' => 'Return To Invoice',
            ],
            'is_personal_belonging_covered' => [
                'name' => 'Loss of Personal Belongings',
            ],
        ];
        $all_accessories = [
            'electrical_accessories' => [
                'name' => 'Electrical Accessories',
                'sumInsured' => 0,
            ],
            'nonelectrical_accessories' => [
                'name' => 'Non-Electrical Accessories',
                'sumInsured' => 0,
            ],
            //Bi-fuel tag available in API response
            'lpg_cng' => [
                'name' => 'External Bi-Fuel Kit CNG/LPG',
                'sumInsured' => 0
            ],
        ];
        $cpa_tag = [
            'is_pa_owner_driver' => [
            'true' => [ "name" => "Compulsory Personal Accident" ],
            'false' => [ "reason" => "I have another motor policy with PA owner driver cover in my name" ]
                //"tenure" => 1,
            ],
        ];
        $all_additional_cover = [
            'is_pa_unnamed_passenger' => [
                "name" => "Unnamed Passenger PA Cover",
                "sumInsured" => 0,
            ],
            'is_ll_paid_driver' => [
                "name" => "LL paid driver",
                "sumInsured" => 0,
            ],
        ];
        $all_discounts = [
            //Anti theft, Vehicle Limited to Own Premises, TPPD Premium, Voluntary Discount not available in API response
        ];

        $selected_addons = [
            'id' => null,
            'user_product_journey_id' => null,
            'addons' => [],
            'applicable_addons' => [],
            'accessories' => [],
            'additional_covers' => [],
            'voluntary_insurer_discounts' => [],
            'discounts' => [],
            'compulsory_personal_accident' => [],
            'selected_addons' => [],
        ];
        foreach ($addons_data as $key => $value) {
//            if ((int) $value == 0) {
//                continue;
//            }
            // Preparing Addons data
//            
            if (in_array($key, array_keys($all_addons))) {
                if($value == true)
                {
                  array_push($selected_addons['addons'], $all_addons[$key]);
                }                
            }
            //Preparing CPA
            if (in_array($key, array_keys($cpa_tag))) {
                $value_tag = $value ? 'true' : 'false';
                array_push($selected_addons['compulsory_personal_accident'], $cpa_tag[$key][$value_tag]);
            }
            if((int) $value == 0)
            {
                continue;
            }
            // Preparing Accessories Data
            if (in_array($key, array_keys($all_accessories))) {
                $all_accessories[$key]['sumInsured'] = (int) $value;
                array_push($selected_addons['accessories'], $all_accessories[$key]);
            }
            //Preparing Additional Covers
            if (in_array($key, array_keys($all_additional_cover))) {
                if($key == 'is_pa_unnamed_passenger' && $addons_data['is_pa_unnamed_passenger'] === true) {
                    $all_additional_cover[$key]['sumInsured'] = 100000;
                }
                array_push($selected_addons['additional_covers'], $all_additional_cover[$key]);
            }
            //Preparing Discounts
            if (in_array($key, array_keys($all_discounts))) {
                array_push($selected_addons['discounts'], $all_discounts[$key]);
            }
        }
        return $selected_addons;
    }

    public function generateProposalData($payload,$custom_data)
    {
        $nominee_relationship_name = $payload['nominee_relation'];
        if($payload['nominee_relation'] != NULL)
        {
            $nominee_relationship_name = NomineeRelationship::where('relation', $payload['nominee_relation'])
                    ->where('company_alias', $custom_data['company_alias'])
                    ->pluck('relation')
                    ->first();
        }
        list($firstname, $lastname) = getFirstAndLastName($payload['customer_fullname']);
        return [
            "user_proposal_id"                  => null,
            "user_product_journey_id"           => null,
            "title"                             => null,
            "first_name"                        => $firstname,
            "last_name"                         => $lastname,
            "email"                             => $payload['customer_email'],
            "office_email"                      => $payload['customer_email'],
            "marital_status"                    => '',
            "mobile_number"                     => $payload['customer_mobileno'],
            "dob"                               => str_replace("/", "-",$payload['customer_dob']),
            "occupation"                        => $payload['occupation'],
            "occupation_name"                   => $payload['occupation'],
            "gender"                            => $payload['customer_gender'],
            "gender_name"                       => $payload['customer_gender'],
            "pan_number"                        => '',
            "gst_number"                        => '',
            "address_line1"                     => $payload['complete_communication_address'],
            "address_line2"                     => '',
            "address_line3"                     => '',
            "pincode"                           => is_numeric($payload['customer_pincode']) ? $payload['customer_pincode'] : $payload['pincode'],
            "state"                             => '',
            "city"                              => '',
            "street"                            => '',
            "rto_location"                      => $payload['rto_city_name'],
            "vehicle_color"                     => '',
            "is_valid_puc"                      => 'Y',
            "financer_agreement_type"           => '',
            "financer_location"                 => '',
            "is_car_registration_address_same"  => $payload['complete_communication_address'] == $payload['complete_registration_address'] ? 1 : 0,
            "car_registration_address1"         => $payload['complete_registration_address'],
            "car_registration_address2"         => '',
            "car_registration_address3"         => '',
            "car_registration_pincode"          => '',
            "car_registration_state"            => '',
            "car_registration_city"             => '',
            "vehicale_registration_number"      => $payload['registration_number'],
            "vehicle_manf_year"                 => '01-'.$payload['manufacturing_year'],
            "engine_number"                     => removeSpecialCharactersFromString($payload['vehicle_engine_no']),
            "chassis_number"                    => removeSpecialCharactersFromString($payload['chassis_no']),
            "is_vehicle_finance"                => empty(trim($payload['financier'])) ? 0 : 1,
            "name_of_financer"                  => $payload['financier'],
            "hypothecation_city"                => '',
            "previous_insurance_company"        => $custom_data['company_name'],
            "previous_policy_number"            => $custom_data['policy_number'],
            "previous_insurer_pin"              => '',
            "previous_insurer_address"          => '',
            "nominee_name"                      => $payload['nominee_name'],
            "nominee_age"                       => $payload['nominee_age'],
            "nominee_relationship"              => $nominee_relationship_name,
            "vehicle_registration_no"           => $payload['registration_number'],
            "engine_no"                         => removeSpecialCharactersFromString($payload['vehicle_engine_no']),
            "chassis_no"                        => removeSpecialCharactersFromString($payload['chassis_no']),
            "policy_start_date"                 => $custom_data['policy_start_date'],
            "policy_end_date"                   => $custom_data['policy_end_date'],
            "tp_start_date"                     => $custom_data['policy_start_date_tp'],
            "tp_end_date"                       => $custom_data['policy_end_date_tp'],
            "tp_insurance_company"              => $custom_data['tp_company_name'],
            "tp_insurance_company_name"         => $custom_data['tp_company_name'],            
            "tp_insurance_number"               => $custom_data['tp_policy_number'],
            "prev_policy_expiry_date"           => $custom_data['policy_end_date'],
//            "is_breakin_case"                   => '',
//            "is_inspection_done"                => '',
            "policy_type"                       => $custom_data['policy_type'],
            "product_code"                      => '',            
            "additional_details"                => '',
            "owner_type"                        => $payload['customer_type'] == 'Individual' ? 'I' : 'C',
            "state_id"                          => '',
            "city_id"                           => '',
            "nominee_dob"                       => '',
            "car_registration_state_id"         => '',
            "car_registration_city_id"          => '',
            "insurance_company_name"            => '',
//            "pdf_url" => '',
//            "ic_pdf_url" => '',
//            "cpa_premium" => '',
            "ic_vehicle_details"                => $custom_data['previous_policy_type'],
            "business_type"                     => $custom_data['business_type'],
            "product_type"                      => $custom_data['business_type'],
            "ic_name"                           => $custom_data['company_name'],
            "ic_id"                             => $custom_data['company_id'],
            "idv"                               => $payload['addons']['vehicle_idv'],
            "cpa_ins_comp"                      => '',
            "cpa_policy_fm_dt"                  => '',
            "cpa_policy_no"                     => '',
            "cpa_policy_to_dt"                  => '',
            "cpa_sum_insured"                   => '',
            "car_ownership"                     => '',
            "policy_owner"                      => $payload['customer_type'] == 'Individual' ? 'I' : 'C',
            "additional_details_data"           => '',
            "previous_policy_type"              => $custom_data['previous_policy_type'],
            "previous_ncb"                      => $custom_data['previous_ncb'],
            "applicable_ncb"                    => $custom_data['applicable_ncb'],
            "is_claim"                          => '',
            "cpa_tenure"                        => '',
            "tp_tenure"                         => '',
            "od_tenure"                         => '',
            "full_name_finance"                 => $payload['financier'],
        ];
    }

    public function generateCorporateData($payload,$custom_data)
    {
        $rto_code = $this->getRtoCodeWithHyphen($payload['rto_code']);

        $returnData = [
            //"quotes_request_id"             => null,
            "version_id"                    => $custom_data['version_id'],
            "user_product_journey_id"       => null,
            "policy_type"                   => $custom_data['policy_type'],
            "business_type"                 => $custom_data['business_type'],
            "vehicle_register_date"         => date('d-m-Y', strtotime($payload['registration_date'])),
            "vehicle_registration_no"       => $payload['registration_number'],
            "product_id"                    => $custom_data['product_sub_type_id'],
            "previous_policy_expiry_date"   => date('d-m-Y', strtotime($payload['previous_policy_enddate'])),
            "previous_policy_type"          => $custom_data['previous_policy_type'],//'Comprehensive',
            "previous_insurer"              => $custom_data['company_name'],
            "previous_insurer_code"         => $custom_data['company_alias'],
            "insurance_company_id"          => $custom_data['company_id'],
            "fuel_type"                     => $custom_data['fuel_type'],
            "manufacture_year"              => '01-'.$payload['manufacturing_year'],
            "rto_code"                      => $rto_code,
            "rto_city"                      => MasterRto::where('rto_code', $rto_code)->select('rto_name')->get()->first()->rto_name ?? null,
            "ex_showroom_price_idv"         => $payload['addons']['vehicle_idv'],
            "is_idv_changed"                => 'N',
            "edit_idv"                      => $payload['addons']['vehicle_idv'] ?? 0,
            "vehicle_owner_type"            => $payload['customer_type'] == 'Individual' ? 'I' : 'C',
            "previous_ncb"                  => $payload['previous_policy_ncb'],
            "applicable_ncb"                => $payload['previous_policy_ncb'],            
            //"previous_policy_type_id"       => null,
            //"is_prev_zero_dept"             => null,
            //"ownership_changed"             => 'N',
            //"idv_changed_type"              => null,
            "version_name"                  => $custom_data['version_name'],
            //"gcv_carrier_type"              => null,
            "is_fastlane"                   => 'N',
            "is_popup_shown"                => 'N',
            "is_ncb_verified"               => "N",
            //"journey_type"                  => "",
            "is_renewal"                    => 'Y',
            "zero_dep_in_last_policy"       => 'Y',
            //"whatsapp_consent"              => null,
            //"site_identifier"               => null,
            //"prev_short_term"               => null,
            //"selected_policy_type"          => null,
           // "is_claim_verified"             => null,
            //"is_idv_selected"               => null,
            //"is_toast_shown"                => null,
            "is_redirection_done"           => 'N',
            "is_renewal_redirection"        => 'N',
            //"prefill_policy_number"         => null,
            "is_default_cover_changed"      => "N",
            "applicable_premium_type_id"    => $custom_data['applicable_premium_type_id'],
            "previous_master_policy_id"     => $custom_data['previous_master_policy_id'],
            "previous_product_identifier"   => null,
            "is_claim" => 'N',
        ];

        if(config('PREVIOUS_POLICY_ID_CHANGES') == "Y") {
            $was_tw = ($custom_data['product_sub_type_id'] == 2);
            $was_fw = ($custom_data['product_sub_type_id'] == 1);
            $was_cv = (!($was_tw || $was_fw));
        
            $was_new = ($custom_data['business_type'] == 'newbusiness');
        
            $previousPolicyTypeIdThirdParty = ($was_new ? ($was_tw ? '05' : ($was_fw ? '03' : '01')) : '01');
            $previousPolicyTypeIdComprehensive = ($was_new ? ($was_tw ? '15' : ($was_fw ? '13' : '11')) : '11');

            $previousPolicyTypeIdCodeArray = [
                'comprehensive' => $previousPolicyTypeIdComprehensive,
                'third_party' => $previousPolicyTypeIdThirdParty,
                'own_damage' => 10,
            ];

            $returnData["previous_policy_type_identifier"] = (($was_new && ($was_tw || $was_fw)) ? 'N' : 'Y');
            $returnData["previous_policy_type_identifier_code"] = ($previousPolicyTypeIdCodeArray[$custom_data['previous_policy_type']] ?? '11');
        }

        return $returnData;
    }
    
    public function renewalGenerateLead(Request $request)
    {
        if (!in_array($request->product_type, ['car', 'bike', 'cv'])) {
            abort(404);
        }
        $UserProductJourney = UserProductJourney::create([
            'user_fname' => null,
            'user_lname' => null,
            'user_email' => null,
            'user_mobile' => null
        ]);
        $enc_id = customEncrypt($UserProductJourney->user_product_journey_id);

        $query_parameters = ['enquiry_id' => $enc_id];

        $redirect_page = '/registration?';

        $params = ['xutm','token','reg_no', 'policy_no', 'is_renewal', 'is_partner', 'redirection'];
        foreach ($params as $v) {
            if (isset($request->{$v})) {
                $query_parameters[$v] = $request->{$v};
            }
        }
        if(!empty($query_parameters['reg_no']) && strtoupper($query_parameters['reg_no']) == 'NEW') {
            $query_parameters['reg_no'] = '';
        }
        if (isset($query_parameters['is_renewal']) && $query_parameters['is_renewal'] == 'Y') {
            $redirect_page = '/renewal?';
        }
        
        $frontend_url = config('constants.motorConstant.' . strtoupper($request->product_type) . '_FRONTEND_URL');

        if ( ! empty($query_parameters['redirection']) && $query_parameters['redirection'] == 'YjJi' && ! empty(strtoupper($request->product_type) . '_B2B_FRONTEND_URL')) { // Only for B2B url
            $frontend_url = config(strtoupper($request->product_type) . '_B2B_FRONTEND_URL');
        }
        
        updateJourneyStage([
            'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
            'stage' => STAGE_NAMES['LEAD_GENERATION'],
            'proposal_url' => $frontend_url . "/proposal-page?enquiry_id=" . $enc_id,
            'quote_url' => $frontend_url . "/quotes?enquiry_id=" . $enc_id,
        ]);

        if ($request->has('agent_id')) {
            try {
                $agentMapping = new \App\Http\Controllers\AgentMappingController($UserProductJourney->user_product_journey_id);
                if(!empty($query_parameters['xutm']))
                {
                    $token = $query_parameters['xutm'] = (string) \Illuminate\Support\Str::uuid();
                }
                else if(!empty($query_parameters['token']))
                {
                    $token = $query_parameters['token'] = (string) \Illuminate\Support\Str::uuid();					
                }
                $request->tokenResp = [
                    'token' => $token,
                    'username' => $request->agent_id,
                    //'source' => 'cse'
                ];
                $agentMapping->mapAgent($request);
            }catch(\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }
        }
        if ($request->has('token') || $request->has('xutm'))
        {
            if($request->has('xutm'))
            {
                $data = httpRequestNormal(config('constants.motorConstant.TOKEN_VALIDATE_URL'), 'POST', ['token' => $request->xutm])['response'];
            }
            else
            {
                $data = httpRequestNormal(config('constants.motorConstant.TOKEN_VALIDATE_URL'), 'POST', ['token' => $request->token])['response'];				
            }
            //$data = httpRequestNormal(config('constants.motorConstant.TOKEN_VALIDATE_URL'), 'POST', ['token' => $request->token])['response'];

            UserTokenRequestResponse::create([
                'user_type' => $data['data']['usertype'] ?? NULL,
                'request' => json_encode($request->all()),
                'response' => json_encode($data),
            ]);

            if(isset($data['status']) && $data['status'] =='true')
            {
                $token_resp = $data['data'];
                CvAgentMapping::updateOrCreate(
                    [
                        'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                    ],
                    [
                        'seller_type'   => $token_resp['seller_type'] ?? null,
                        'agent_id'      => $token_resp['seller_id'] ?? null,
                        'user_name'     => isset($token_resp['user_name']) ? $token_resp['user_name'] : null,
                        'agent_name'    => $token_resp['seller_name'] ?? null,
                        'agent_mobile'  => $token_resp['mobile'] ?? null,
                        'agent_email'   => $token_resp['email'] ?? null,
                        'unique_number' => $token_resp['unique_number'] ?? null,
                        'aadhar_no'     => $token_resp['aadhar_no'] ?? null,
                        'pan_no'        => $token_resp['pan_no'] ?? null,
                        'stage'         => "quote",
                        "category"      => $token_resp['category'] ?? null,
                        "relation_sbi" => $token_resp['relation_sbi'] ?? null,
                        "relation_tata_aig" => (isset($token_resp['relation_tata_aig']) ? ($token_resp['relation_tata_aig'] ?? null) : ''),
                        'token'=> $request->token,
                        'branch_code'=> (isset($token_resp['branch_code']) ? ($token_resp['branch_code'] ?? null) : ''),
                        'user_id'=> (isset($token_resp['user_id']) ? ($token_resp['user_id'] ?? null) : ''),
                        'pos_key_account_manager' => $token_resp['pos_key_account_manager'] ?? null,
                        "agent_business_type" => $token_resp['business_type'] ?? null,
                        "agent_business_code" => $token_resp['business_code'] ?? null,
                    ]
                );

                /* In Seller Type U Agent Id is User Id */

                if (isset($token_resp['seller_type']) && $token_resp['seller_type'] === "U") {
                    CvAgentMapping::updateOrCreate(
                        [
                            'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                        ],
                        [
                            'user_id' => $token_resp['agent_id'] ?? $token_resp['user_id'],
                        ]
                    );
                }

                /* Quote Log Entry for Renewal #17161 */
                \App\Models\QuoteLog::updateOrCreate(['user_product_journey_id' => $UserProductJourney->user_product_journey_id ], []);

                /* Save Registration Number in Corporate Request Renewal Case #16790 */

                if (!empty($token_resp['vehicle_registration_number'])) {
                    CorporateVehiclesQuotesRequest::updateOrCreate(
                        [
                            'user_product_journey_id' => $UserProductJourney->user_product_journey_id
                        ],
                        [
                            'vehicle_registration_no' => $token_resp['vehicle_registration_number'] ?? ''
                        ]
                    );
                }
                
                if(!empty($token_resp['policy_no']))
                {
                    UserProposal::updateOrCreate(
                        [ 'user_product_journey_id' => $UserProductJourney->user_product_journey_id ],
                        [ 'previous_policy_number'  => $token_resp['policy_no'] ]
                    );
                }

                if(isset($token_resp['seller_type']) && $token_resp['seller_type'] == 'U')
                {                    
                    UserProductJourney::where('user_product_journey_id',$UserProductJourney->user_product_journey_id)
                    ->update(
                        [
                            'user_fname' => $token_resp['seller_name'] ?? null,
                            'user_lname' => null,
                            'user_email' => $token_resp['email'] ?? null,
                            'user_mobile' => $token_resp['mobile'] ?? null,
                        ]
                    );                    
                }
            }else
            {
                $return_data = [
                    'status' => false,
                    'msg' => $data['message'],
                    'token' => $request->token,
                    'enquiry_id' => $enc_id,
                    'redirect_url' =>  '',
                ];
                $error_url = config('constants.frontend_url').'error?error='. base64_encode(json_encode($return_data));
                return redirect($error_url);

            }
      
        }
        return redirect($frontend_url . $redirect_page . http_build_query($query_parameters));
    }

    public static function renewal_data_migration(Request $request)
    {
        if(isset($request->isPolicyNumber) && $request->isPolicyNumber == 'Y')
        {
            $search_prarameter = $request->policyNumber;

            $renewal_data = DB::table('bcl_renewal_data_migration')
            ->where('POLICY_NUMBER', $search_prarameter)
            ->select('*')
            ->get();
        }else
        {
            $search_prarameter = $request->registration_no;

            if (str_contains($search_prarameter, '-')) { 
                $search_prarameter = str_replace("-","",$search_prarameter); 
            }

            $renewal_data = DB::table('bcl_renewal_data_migration')
            ->where('VEHICLE_REGISTRATION_NUMBER', $search_prarameter)
            ->select('*')
            ->get();
        }

        $selected_renewal_data = [];
        if(isset($renewal_data[0]))
        {
            if(count($renewal_data) > 1)
            {
                $expiry_date = date("Y-m-d");

                foreach ($renewal_data as $key => $value) {
                    
                    $exp_date = strtotime($value->POLICY_END_DATE);
                    $start_date = strtotime($value->POLICY_START_DATE);
                    $prev_expiry_date = date('d-m-Y',$exp_date);
                    $prev_start_date = date('d-m-Y',$start_date);
                    if($prev_expiry_date > $expiry_date)
                    {
                        $expiry_date = $prev_expiry_date;
                        $value->POLICY_END_DATE = $prev_expiry_date;
                        $value->POLICY_START_DATE = $prev_start_date;
                        $selected_renewal_data = $value;
                        
                    }else
                    {
                        $expiry_date = $prev_expiry_date;
                        $value->POLICY_END_DATE = $prev_expiry_date;
                        $value->POLICY_START_DATE = $prev_start_date;
                        $selected_renewal_data = $value;

                    }
                    
                }
            }else
            {
                $exp_date = strtotime($renewal_data[0]->POLICY_END_DATE);
                $start_date = strtotime($renewal_data[0]->POLICY_START_DATE);
                $prev_expiry_date = date('d-m-Y',$exp_date);
                $prev_start_date = date('d-m-Y',$start_date);
                $renewal_data[0]->POLICY_END_DATE = $prev_expiry_date;
                $renewal_data[0]->POLICY_START_DATE = $prev_start_date;
                $selected_renewal_data = $renewal_data[0];
            }
        }else
        {
            return [
                'status' => false,
                'msg' => 'No Data found for Renewal using '.$search_prarameter
            ];

        }

        if(empty($selected_renewal_data))
        {
            return [
                'status' => false,
                'msg' => 'No Data found for Renewal'
            ];

        }else
        {
            if(empty($selected_renewal_data->COMPANY_NAME))
            {
                return [
                    'status' => false,
                    'msg' => 'No Data found for Renewal of '.$selected_renewal_data->COMPANY_NAME
                ];
            }else
            {
                $fetch_insurer_code = PreviousInsurerList::where('name',$selected_renewal_data->COMPANY_NAME)
                ->where('company_alias','BCL_DATA')
                ->get()
                ->first();

                if(!empty($fetch_insurer_code))
                {
                    $renewal_allowed_ic = config(strtoupper($request->section).'_RENEWAL_ALLOWED_IC');
                    if(!empty($renewal_allowed_ic))
                    {
                        $renewal_allowed_ic_list = explode(',',$renewal_allowed_ic);
                        $ic_allowed_for_renewal = false;
                        foreach($renewal_allowed_ic_list as $r_key => $r_value)
                        {
                            if($r_value == $fetch_insurer_code->code)
                            {
                                $ic_allowed_for_renewal = true;
                            }
                        }

                        if($ic_allowed_for_renewal)
                        {
                            $userProductJourneyId = customDecrypt($request->enquiryId);

                            $address_line = '';
                            $dob = null;
                            $tp_start_date = null;
                            $tp_end_date = null;
                            $PROPOSER_NAME = null;
                            $PROPOSER_MOBILE = null;
                            $PROPOSER_EMAILID = null;
                            $PINCODE = null;

                            if(!empty($renewal_data[0]->PROPOSER_DOB) && $renewal_data[0]->PROPOSER_DOB !== 'NULL' && strtotime($renewal_data[0]->PROPOSER_DOB))
                            {
                                $dob = date('d-m-Y',strtotime($renewal_data[0]->PROPOSER_DOB));
                            }

                            if(!empty($renewal_data[0]->TP_START_DATE) && $renewal_data[0]->TP_START_DATE !== 'NULL' && strtotime($renewal_data[0]->TP_START_DATE))
                            {
                                $tp_start_date = date('d-m-Y',strtotime($renewal_data[0]->TP_START_DATE));
                            }

                            if(!empty($renewal_data[0]->TP_END_DATE) && $renewal_data[0]->TP_END_DATE !== 'NULL' && strtotime($renewal_data[0]->TP_END_DATE))
                            {
                                $tp_end_date = date('d-m-Y',strtotime($renewal_data[0]->TP_END_DATE));
                            }

                            if(!empty($selected_renewal_data->ADDRESS_LINE_1) && $selected_renewal_data->ADDRESS_LINE_1 !== 'NULL')
                            {
                                $address_line = $selected_renewal_data->ADDRESS_LINE_1.' ';
                            }

                            if(!empty($selected_renewal_data->ADDRESS_LINE_2) && $selected_renewal_data->ADDRESS_LINE_2 !== 'NULL')
                            {
                                $address_line .= $selected_renewal_data->ADDRESS_LINE_2.' ';
                            }

                            if(!empty($selected_renewal_data->ADDRESS_LINE_3) && $selected_renewal_data->ADDRESS_LINE_3 !== 'NULL')
                            {
                                $address_line .= $selected_renewal_data->ADDRESS_LINE_3;
                            }

                            if(!empty($selected_renewal_data->PROPOSER_NAME) && $selected_renewal_data->PROPOSER_NAME !== 'NULL')
                            {
                                $PROPOSER_NAME = $selected_renewal_data->PROPOSER_NAME;
                            }

                            if(!empty($selected_renewal_data->PROPOSER_MOBILE) && $selected_renewal_data->PROPOSER_MOBILE !== 'NULL')
                            {
                                $PROPOSER_MOBILE = $selected_renewal_data->PROPOSER_MOBILE;
                            }

                            if(!empty($selected_renewal_data->PROPOSER_EMAILID) && $selected_renewal_data->PROPOSER_EMAILID !== 'NULL')
                            {
                                $PROPOSER_EMAILID = $selected_renewal_data->PROPOSER_EMAILID;
                            }

                            if(!empty($selected_renewal_data->PINCODE) && $selected_renewal_data->PINCODE !== 'NULL')
                            {
                                $PINCODE = $selected_renewal_data->PINCODE;
                            }

                            $company = MasterCompany::where('company_alias',$fetch_insurer_code->code)
                            ->select('company_name')
                            ->pluck('company_name')
                            ->toArray();

                            $premium_type = [
                               '1' => 'COMPREHENSIVE',
                               '2' => 'TP',
                               '3' => 'OD'
                            ];

                            $applicable_premium_type_id = array_search($selected_renewal_data->PREV_POLICY_TYPE,$premium_type);
                          
                            CorporateVehiclesQuotesRequest::updateOrCreate(
                                ['user_product_journey_id' => $userProductJourneyId ],
                                [
                                    'is_renewal' => 'Y',
                                    'previous_insurer' => $company[0],
                                    'previous_insurer_code' => $fetch_insurer_code->code,
                                    'applicable_premium_type_id' =>  $applicable_premium_type_id
                                ]
                                );
                            $selected_renewal_data->previous_insurer = $company[0];
                            $selected_renewal_data->previous_insurer_code = $fetch_insurer_code->code;

                            UserProposal::updateOrCreate(
                                [ 'user_product_journey_id' => $userProductJourneyId ],
                                [
                                    'fullName' => $PROPOSER_NAME,
                                    'mobile_number' => $PROPOSER_MOBILE,
                                    'email' => $PROPOSER_EMAILID,
                                    'address_line1' => $address_line,
                                    'pincode' => $PINCODE,
                                    'dob' => $dob,
                                    'previous_policy_number' => $selected_renewal_data->POLICY_NUMBER,
                                    'previous_insurance_company' => $company[0],

                            ]);

                            return [
                                'status' => true,
                                'data' => $selected_renewal_data,
                                'msg' => 'Data found.'
                            ]; 

                        }else
                        {
                            return [
                                'status' => false,
                                'msg' => 'Renewal Not Allowed for '.$fetch_insurer_code->name
                            ]; 
                        }

                    }else
                    {
                        return [
                            'status' => false,
                            'msg' => 'Renewal Not Allowed for '.$selected_renewal_data->COMPANY_NAME
                        ];

                    }

                }else
                {
                    return [
                        'status' => false,
                        'msg' => 'No Data found for Renewal of '.$selected_renewal_data->COMPANY_NAME
                    ];
                }
            }
        }

    }
    
    function GenerateLead(Request $request)
    {
        return $this->renewalGenerateLead($request);
    }

    public function getRtoCodeWithHyphen($rtoCode)
    {
        // if rtoCode is DL3C or DL03 it will return DL-03
        
        if (strlen($rtoCode)>3 && is_numeric($rtoCode[2]) && !is_numeric($rtoCode[3])) {
            $temp = $rtoCode[2];
            $rtoCode[2] = "0";
            $rtoCode[3] = $temp;
        }

        $rtoCode = substr_replace($rtoCode, '-', 2, 0);

        if (in_array(strtoupper(substr($rtoCode,0,2)), ['DL'])) {
            $result = explode('-',$rtoCode);
            $result[1] = preg_replace("/[^0-9]/", "", $result[1] ?? "");
            $rtoCode = implode('-', $result);
        }

        return $rtoCode;
    }

    public static function getRenewalDataWithRegistrationNo($request, $registration_no)
    {
        $policy_details = UserProposal::join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        ->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        ->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        ->join('policy_details as pd', 'pd.proposal_id', '=', 'user_proposal.user_proposal_id')
        ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
        ->where('user_proposal.vehicale_registration_number','=', $registration_no)
        ->where('s.stage','=',STAGE_NAMES['POLICY_ISSUED'])
        ->select('user_proposal.*','pd.policy_number', 'ql.master_policy_id')
        ->orderBy('s.user_product_journey_id', 'DESC')
        ->first();

        $validatePolicyEndDateInterval = self::validatePolicyEndDateInterval($policy_details);
        if(!($validatePolicyEndDateInterval['status'] ?? true)) return $validatePolicyEndDateInterval;

        $validatePolicyStartDateInterval = self::validatePolicyStartDateInterval($policy_details);
        if(!($validatePolicyStartDateInterval['status'] ?? true)) return $validatePolicyStartDateInterval;

        return  ['status' => true, 'data' => $policy_details];
    }

    public static function validatePolicyEndDateInterval($policy_details)
    {
        //git id - https://github.com/Fyntune/motor_2.0_backend/issues/10553
        //As per the git more than 70 days breakin, calling Vehcile service and not getting data from DB
        if(isset($policy_details->policy_end_date) && $policy_details->policy_end_date != NULL)
        {
            $date1 = new \DateTime();
            $date2 = new \DateTime($policy_details->policy_end_date);
            $interval = $date1->diff($date2);
            if($interval->invert == 1 && $interval->days > 70)
            {
                return [
                    'status' => false,
                    'msg' => 'Breakin Policy Found. Expiry Date is '.$policy_details->policy_end_date.'. Total Breakin Days is '.$interval->days
                ];
            }
        }
        
        return ['status' => true];
    }

    public static function validatePolicyStartDateInterval($policy_details)
    {
        if(!empty($policy_details->master_policy_id))
        {
            $oldProductdata = (getProductDataByIc($policy_details->master_policy_id));

            if(isset($policy_details->policy_start_date) && $policy_details->policy_start_date != NULL && !empty($oldProductdata))
            {
                $date1 = new \DateTime();
                $date2 = new \DateTime($policy_details->policy_start_date);
                $interval = $date1->diff($date2);

                $limitArray = json_decode(config('ISSUED_POLICY_RENEWAL_DAY_LIMIT'),1);

                if(($interval->invert == 0) || ($interval->invert == 1 && isset($limitArray[$oldProductdata->premium_type_code]) && $interval->days < ($limitArray[$oldProductdata->premium_type_code])))
                {
                    return [
                        'status' => false,
                        'msg' => 'Policy already issued with Policy Number = '. $policy_details->policy_number .' and Policy Period is '.$policy_details->policy_start_date.' to '.$policy_details->policy_end_date,
                        'overrideMsg' => 'Policy already issued with Policy Number = '. $policy_details->policy_number .' and Policy Period is '.$policy_details->policy_start_date.' to '.$policy_details->policy_end_date,
                        'request_data' => [
                            'policy_start_date' => $date2,
                            'current_date' => $date1,
                            'interval' => $interval
                        ],
                        'show_error' => true
                    ];
                }
            }
        }
        
        return ['status' => true];
    }
    
    public static function getExistingPolicy($data)
    {
        $registration_no = $data['registration_no'];
        $policy_details = UserProposal::join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->join('policy_details as pd', 'pd.proposal_id', '=', 'user_proposal.user_proposal_id')
            ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'user_proposal.user_product_journey_id')
            ->where('user_proposal.vehicale_registration_number','=', $registration_no)
            ->where('s.stage','=',STAGE_NAMES['POLICY_ISSUED'])
            ->select('user_proposal.*','pd.policy_number', 'ql.master_policy_id')
            ->orderBy('s.user_product_journey_id', 'DESC')
            ->first();
        return $policy_details;
    }

    public static function renewalAttributes(&$user_requestd_data)
    {
        $user_requestd_data['renewal_attributes'] = [
            'ncb' => false,
            'idv' => false,
            'claim' => false,
            'proposal' => false,
            'ownership' => false,
        ];
        $config = 'renewalConfig';
        $hide_renewal = $user_requestd_data['corporate_vehicles_quote_request']['frontend_tags'] ?? '';
        $hide_renewal = json_decode($hide_renewal, true);
        
        if (($hide_renewal['hideRenewal'] ?? false) &&
            config('constants.motorConstant.SMS_FOLDER') == 'bajaj'
        ) {
            /*
            front end will send this tag, incase journey is made through registration page
            in such case renewal product will be blocked, and the journey will become rollover renewal
            only for BCL.
            */
            $user_requestd_data['renewal_attributes'] = [
                'ncb' => true,
                'idv' => true,
                'claim' => true,
                'proposal' => true,
                'ownership' => true,
            ];
        } elseif (($user_requestd_data['corporate_vehicles_quote_request']['rollover_renewal'] ?? 'N') != 'Y') {
            //renewal case
            $old_journey_source = null;
            if (!empty($user_requestd_data['old_journey_id'])) {
                $old_journey_source = UserProductJourney::where(
                    'user_product_journey_id',
                    $user_requestd_data['old_journey_id']
                )
                ->select('lead_source', 'sub_source')
                ->first();
            }
            if (!empty($old_journey_source) && $old_journey_source->lead_source == 'RENEWAL_DATA_UPLOAD') {
                //offline
                $config.='.offline';
                $sub_source = $old_journey_source->sub_source;
                $sub_source_list = [
                    'IC' => 'icFetch',
                    'VAHAN' => 'vahan'
                ];
                if (!empty($sub_source) && !empty($sub_source_list[$sub_source])) {
                    $config.='.'. $sub_source_list[$sub_source];

                    $idv_config = $config.'.idv.edit';
                    $ncb_config = $config.'.ncb.edit';
                    $claim_config = $config.'.claim.edit';
                    $proposal_config = $config.'.proposal.edit';
                    $ownership_config = $config . '.ownership.edit';


                    self::setConfigValue(
                        $idv_config,
                        $ncb_config,
                        $claim_config,
                        $proposal_config,
                        $ownership_config,
                        $user_requestd_data
                    );

                    if (!($user_requestd_data['renewal_attributes']['ncb'] ?? true) &&
                        ($user_requestd_data['corporate_vehicles_quote_request']['is_ncb_verified'] ?? 'N') != 'Y'
                    ) {
                        /*
                        in case offline renewal,
                        if  ncb is not available from ic/vahan then ncb should be editable
                        */
                        $user_requestd_data['renewal_attributes']['ncb'] = true;
                    }
                }
            } else {
                if (($user_requestd_data['sub_source'] ?? '') == 'CLIENT_DATA') {
                    //renewal through renewal api
                    $config.='.offline.clientData';
                    $idv_config = $config.'.idv.edit';
                    $ncb_config = $config.'.ncb.edit';
                    $claim_config = $config.'.claim.edit';
                    $proposal_config = $config.'.proposal.edit';
                    $ownership_config = $config . '.ownership.edit';

                    self::setConfigValue(
                        $idv_config,
                        $ncb_config,
                        $claim_config,
                        $proposal_config,
                        $ownership_config,
                        $user_requestd_data
                    );

                } else {
                    //online case
                    $config .= '.online';
                    $idv_config = $config . '.idv.edit';
                    $ncb_config = $config . '.ncb.edit';
                    $claim_config = $config . '.claim.edit';
                    $proposal_config = $config . '.proposal.edit';
                    $ownership_config = $config . '.ownership.edit';

                    self::setConfigValue(
                        $idv_config,
                        $ncb_config,
                        $claim_config,
                        $proposal_config,
                        $ownership_config,
                        $user_requestd_data
                    );
                }
            }
        } else {
            //rollover renewal
            $user_requestd_data['renewal_attributes'] = [
                'ncb' => true,
                'idv' => true,
                'claim' => true,
                'proposal' => true,
                'ownership' => true,
            ];
        }
    }

    public static function setConfigValue(
        $idv_config,
        $ncb_config,
        $claim_config,
        $proposal_config,
        $ownership_config,
        &$user_requestd_data
    ) {
        $idv_editable = getCommonConfig($idv_config, 'N');
        $ncb_editable = getCommonConfig($ncb_config, 'N');
        $claim_editable = getCommonConfig($claim_config, 'N');
        $proposal_editable = getCommonConfig($proposal_config, 'N');
        $ownership_editable = getCommonConfig($ownership_config, 'N');

        $user_requestd_data['renewal_attributes'] = [
            'idv' => $idv_editable == 'Y',
            'ncb' => $ncb_editable == 'Y',
            'claim' => $claim_editable == 'Y',
            'proposal' => $proposal_editable == 'Y',
            'ownership' => $ownership_editable == 'Y'
        ];
    }

    public static function calculateNcb($old_ncb)
    {
        $ncb_slab = [
            '0'     => 20,
            '20'    => 25,
            '25'    => 35,
            '35'    => 45,
            '45'    => 50,
            '50'    => 50
        ];
        if (isset($ncb_slab[$old_ncb])) {
            return [
                'status' => true,
                'ncb' => $ncb_slab[$old_ncb]
            ];
        }

        return [
            'status' => false,
            'message' => 'Invalid previous ncb'
        ];
    }
}
