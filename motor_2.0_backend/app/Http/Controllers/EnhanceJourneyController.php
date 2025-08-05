<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterRto;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterCompany;
use App\Models\CvAgentMapping;
use App\Models\SelectedAddons;
use App\Models\AceTokenRoleData;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Client\Pool;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\EmbeddedLinkRequestData;
use App\Models\JourneyStage;
use App\Models\EmbeddedLinkWhatsappRequests;
use App\Models\EmbeddedScrubData;
use App\Models\EmbeddedScrubPdfData;
use App\Models\FastlaneRequestResponse;
use App\Models\LsqApiRequestResponse;
use App\Models\ProposalFields;
use DateTime;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EnhanceJourneyController extends Controller
{

    function enhanceJourney(Request $request)
    {
        // \App\Jobs\EmbeddedLinkGeneration::dispatch();exit;
        // \App\Jobs\EmbeddedScrubDataGeneration::dispatch();exit;

        foreach ($request->all() as $request_data) {
            if ($request_data['seller_name'] == 'embedded_scrub')
            {
                EmbeddedScrubData::create([
                    'rc_number' => $request_data['rc_number'],
                    'batch_id' => $request_data['batch_id'],
                    'request' => $request_data,
                    'url' => url('/')
                ]);
            }
            else
            {
                EmbeddedLinkRequestData::create([
                    'data' => $request_data,
                    'url' => url('/')
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Data saved successfully'
        ]);
    }

    public function generateEmbeddedLink(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            $rc_number = str_split($request->rc_number);
            if ($rc_number[0] . $rc_number[1] == 'DL') {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2];
                $rto_code = $new_rc_number;
                $str = substr($request->rc_number, 3);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            } else {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2] . $rc_number[3];
                $rto_code = $new_rc_number;
                $str = substr($request->rc_number, 4);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            }
            $template_name = WhatsappTemplate::active()->where(['identifier' => 'renewal_automation_final', 'language' => $request->language ?? 'EN'])->first()->name;
            $response = \Illuminate\Support\Facades\DB::table('registration_details')->where('vehicle_reg_no', $new_rc_number)->where('vehicle_details', 'LIKE', '%Extracted details.%')->where('expiry_date', '>=', now()->format('Y-m-d'))->latest()->first();

            if ($response == null) {
                $startTime = new DateTime(date('Y-m-d H:i:s'));
                $ongrid = httpRequest('ongrid', ['rc_number' => $request->rc_number], [], [], [], false);
                $endTime = new DateTime(date('Y-m-d H:i:s'));

                $responseTime = $startTime->diff($endTime);
                $response = $ongrid['response'];
                DB::table('registration_details')->insert([
                    'vehicle_reg_no' => $new_rc_number,
                    'vehicle_details' => json_encode($response),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? null
                ]);

                $fastlane = FastlaneRequestResponse::create([
                    'request' => $request->rc_number,
                    'response' => json_encode($response),
                    'transaction_type' => "Ongrid Service",
                    'endpoint_url' => $ongrid['url'],
                    'ip_address' => $request->ip(),
                    'section' => 'taxi',
                    'response_time' => $responseTime->format('%Y-%m-%d %H:%i:%s'),
                    'created_at' => now(),
                ]);
            } else {

                $response = json_decode($response->vehicle_details, true);
            }

            if (empty($response) || (isset($response['data']) && $response['data']['code'] != 1000)) {
                $enquiry_data  = $this->createEnquiryIdWithJourney($request);
                if(isset($fastlane) && $fastlane != null){
                    $fastlane->update(['enquiry_id' => customDecrypt($enquiry_data['enquiryId'])]);
                }
                return response()->json([
                    "status" => true,
                    "message" => $response['data']['message'],
                    "data" => $enquiry_data,
                    'upload_excel_id' => $request->upload_excel_id
                ]);
            }
            if (isset($response['error']) || isset($response['message'])) {
                $enquiry_data  = $this->createEnquiryIdWithJourney($request);
                if(isset($fastlane) && $fastlane != null){
                    $fastlane->update(['enquiry_id' => customDecrypt($enquiry_data['enquiryId'])]);
                }
                return response()->json([
                    "status" => true,
                    "message" => (isset($response['error']) ? $response['error']['message'] : $response['message']),
                    "data" => $enquiry_data,
                    'upload_excel_id' => $request->upload_excel_id
                ]);
            }
            if (!isset($response['data']['rc_data']['vehicle_data']['custom_data'])) {
                $enquiry_data  = $this->createEnquiryIdWithJourney($request);
                if(isset($fastlane) && $fastlane != null){
                    $fastlane->update(['enquiry_id' => customDecrypt($enquiry_data['enquiryId'])]);
                }
                return response()->json([
                    "status" => true,
                    "message" => "Vehicle data not found",
                    "data" => $enquiry_data,
                    'upload_excel_id' => $request->upload_excel_id
                ]);
            }
            $response = $response['data']['rc_data'];

            switch (\Illuminate\Support\Str::substr($response['vehicle_data']['custom_data']['version_id'], 0, 3)) {
                case 'PCV':
                    $type = 'pcv';
                    break;
                case 'GCV':
                    $type = 'gcv';
                    break;
                case 'CRP':
                    $type = 'motor';
                    break;
                case 'BYK':
                    $type = 'bike';
                    break;
            }
            $manufacture_year = explode('-', $response['vehicle_data']['manufactured_date']);
            $manufacture_year = $manufacture_year[1] . '-' . $manufacture_year[0];

            $rc_number = str_split($request->rc_number);
            if ($rc_number[0] . $rc_number[1] == 'DL') {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2];
                $rto_code = $new_rc_number;
                $str = substr($request->rc_number, 3);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            } else {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2] . $rc_number[3];
                $rto_code = $new_rc_number;
                $str = substr($request->rc_number, 4);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            }
            $producttype = [
                '1' => 'motor',
                '2' => 'bike',
                '6' => 'pcv',
                '9' => 'gcv',
                '13' => 'gcv',
                '14' => 'gcv',
                '15' => 'gcv',
                '16' => 'gcv',
            ];
            $product_sub_type_id = array_search($type, $producttype);
            // $name = explode(' ', $request->owner_name);
            $name = explode(' ', $response['owner_data']['name']);
            $user_product_journey = [
                'user_fname' => $name[0],
                // 'user_mname' => $response['owner_data']['father_name'],
                'user_lname' => end($name),
                'user_mobile' => $request->mobile_no,
                'product_sub_type_id' => $product_sub_type_id,
                'status' => 'yes',
                'lead_stage_id' => 2
            ];
            $user_product_journey = UserProductJourney::create($user_product_journey);

            if(isset($fastlane) && $fastlane != null){
                $fastlane->update(['enquiry_id' => $user_product_journey->user_product_journey_id]);
            }

            CvAgentMapping::updateOrCreate(
                ['user_product_journey_id' => $user_product_journey->user_product_journey_id,],
                [
                    'seller_type' => $request->seller_type,
                    'agent_id' => $request->seller_id,
                    'agent_name' => $request->seller_name,
                    'agent_name' => $request->seller_name,
                ],
            );
            $enquiryId = $user_product_journey->journey_id;

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
            {
                $lead = createLsqLead($user_product_journey->user_product_journey_id);

                if ($lead['status'])
                {
                    createLsqActivity($user_product_journey->user_product_journey_id, 'lead');
                }
            }

            $SelectedAddons = [
                'user_product_journey_id' => $user_product_journey->user_product_journey_id,
                'compulsory_personal_accident' => [['reason' => 'I have another motor policy with PA owner driver cover in my name']]
            ];

            SelectedAddons::create($SelectedAddons);
            $vehicle_details = $this->getVehicleDetails($user_product_journey->user_product_journey_id, $new_rc_number, $type, $response, $journey_type = 'embeded-excel');

            if (!$vehicle_details['status']) {
                $journey_url = linkDeliverystatus(urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                    'enquiry_id' => $enquiryId,
                    'journey_type' => 'ZW1iZWRlZC1leGNlbA=='
                ])), $user_product_journey->user_product_journey_id);

                $inputs = [
                    '*' . $name[0] . ' ' . end($name) . '*',
                    $journey_url,
                ];
                $inputs = '"' . implode('","', $inputs) . '"';

                EmbeddedLinkWhatsappRequests::create([
                    'enquiry_id' => $enquiryId,
                    'request' => json_encode([
                        "to" => '91' . $request->mobile_no,
                        "type" => "template",
                        "template_name" => $template_name,
                        "params" => $inputs,
                    ]),
                    'lsq_activity_data' => config('constants.LSQ.IS_LSQ_ENABLED') == 'Y' ? json_encode([
                        'enquiry_id' => $user_product_journey->user_product_journey_id,
                        'create_lead_on' => NULL,
                        'message_type' => 'Embedded Link Shared',
                        'additional_data' => [
                            'url' => $journey_url,
                            'destination' => $request->mobile_no
                        ]
                    ], JSON_UNESCAPED_SLASHES) : NULL,
                    'scheduled_at' => now()/* ->addDay(1) */
                ]);

                $vehicle_details['upload_excel_id'] = $request->upload_excel_id;
                $vehicle_details['data'] = [
                    'link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                        'enquiry_id' => $enquiryId,
                        'journey_type' => 'ZW1iZWRlZC1leGNlbA=='
                    ]))
                ];

                if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                {
                    $opportunity = createLsqOpportunity($user_product_journey->user_product_journey_id, 'RC Submitted', ['rc_number' => $new_rc_number]);

                    if ($opportunity['status'])
                    {
                        createLsqActivity($user_product_journey->user_product_journey_id, NULL, 'RC Submitted');
                    }
                }

                return response()->json($vehicle_details);
            }

            $master_policies = \App\Models\MasterPolicy::where('premium_type_id', 5)->join('master_company as mc', 'mc.company_id', '=', 'insurance_company_id')->select('policy_id', 'mc.company_alias')->get();
            $data = [];
            foreach ($master_policies as $key => $value) {
                $data[] = [
                    'url' => /* 'https://api-ola-uat.fynity.in/api/premiumCalculation/' . $value->company_alias, */ url('api/premiumCalculation/' . $value->company_alias),
                    'data' => [
                        "enquiryId" => $enquiryId,
                        "policyId" => $value->policy_id,
                    ]
                ];
            }
            if ($quote_data = $this->getQuotes($data, config('constants.enhance_journey_short_term_3_months'))) {
                $quote = $quote_data['data'];
            } else {
                $master_policies = \App\Models\MasterPolicy::where('premium_type_id', 8)->join('master_company as mc', 'mc.company_id', '=', 'insurance_company_id')->select('policy_id', 'mc.company_alias')->get();
                $data = [];
                foreach ($master_policies as $key => $value) {
                    $data[] = [
                        'url' => /* 'https://api-ola-uat.fynity.in/api/premiumCalculation/' . $value->company_alias, */ url('api/premiumCalculation/' . $value->company_alias),
                        'data' => [
                            "enquiryId" => $enquiryId,
                            "policyId" => $value->policy_id,
                        ]
                    ];
                }

                if ($quote_data = $this->getQuotes($data, config('constants.enhance_journey_short_term_6_months'))) {
                    $quote = $quote_data['data'];
                } else {
                    $product_data = Http::post(url('/api/getProductDetails'), [
                        "productSubTypeId" => $product_sub_type_id,
                        "businessType" => $vehicle_details['data']['additional_details']['businessType'],
                        "policyType" => "comprehensive",
                        "selectedPreviousPolicyType" => "Comprehensive",
                        "premiumType" => $vehicle_details['data']['additional_details']['businessType'],
                        "previousInsurer" => "",
                        "enquiryId" => $enquiryId
                    ])->json();


                    $master = curl_multi_init();
                    foreach ($product_data['data']['comprehensive'] as $key => $value) {
                        $url = url('api/premiumCalculation/' . $value['companyAlias']);
                        // $url = 'https://api-ola-uat.fynity.in/api/premiumCalculation/' . $value['companyAlias'];
                        $curl_arr[$key] = curl_init($url);
                        $data = [
                            "enquiryId" => $enquiryId,
                            "policyId" => $value['policyId'],
                        ];
                        curl_setopt($curl_arr[$key], CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl_arr[$key], CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($curl_arr[$key], CURLOPT_HTTPHEADER, [
                            'Accept' => 'application/json',
                        ]);
                        curl_setopt($curl_arr[$key], CURLOPT_POSTFIELDS, $data);
                        curl_multi_add_handle($master, $curl_arr[$key]);
                    }
                    do {
                        curl_multi_exec($master, $running);
                    } while ($running > 0);

                    $result = [];
                    foreach ($product_data['data']['comprehensive'] as $key => $value) {
                        $result[$key] = json_decode(curl_multi_getcontent($curl_arr[$key]), true);
                    }
                    $newarray = [];
                    // $fail_quote = [];
                    $fail_quote = '';
                    foreach ($result as $index => $row) {
                        if (isset($row['data'])) {
                            array_push($newarray, [$index => $row['data']['tppdPremiumAmount'] + $row['data']['basicPremium']]);
                        } else {
                            if ($row != null) {
                                $fail_quote .= $row['FTS_VERSION_ID'] . ' - ' . (isset($product_data['data']['comprehensive'][$index]['companyName']) ? $product_data['data']['comprehensive'][$index]['companyName'] : $product_data['data']['comprehensive'][$index]) . ' - ' . (isset($row['message']) ? $row['message'] : json_encode($row)) . ',';
                                // array_push($fail_quote, array_merge($row, ['ic_name' => $product_data['data']['comprehensive'][$index]['companyName'], 'policyId' => $product_data['data']['comprehensive'][$index]['policyId'], 'companyAlias' => $product_data['data']['comprehensive'][$index]['companyAlias'], 'productSubTypeName' => $product_data['data']['comprehensive'][$index]['productSubTypeName']]));
                            }
                        }
                    }
                    if (empty($newarray)) {
                        updateJourneyStage([
                            'user_product_journey_id' => customDecrypt($enquiryId),
                            'stage' => 'Embeded Link Genrated',
                            'proposal_url' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId,
                            'quote_url' => config('constants.frontend_url') . "cv/quotes?enquiry_id=" . $enquiryId,
                        ]);

                        $journey_url = linkDeliverystatus(urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                            'enquiry_id' => $enquiryId,
                            'journey_type' => 'ZW1iZWRlZC1leGNlbA=='
                        ])), customDecrypt($enquiryId));

                        $inputs = [
                            '*' . $name[0] . ' ' . end($name) . '*',
                            $journey_url,
                        ];
                        $inputs = '"' . implode('","', $inputs) . '"';

                        // $whatsapp_response = httpRequest('whatsapp', [
                        //     "to" => '91' . $request->mobile_no,
                        //     "type" => "template",
                        //     "template_name" => $template_name,
                        //     "params" => $inputs,
                        // ]);

                        EmbeddedLinkWhatsappRequests::create([
                            'enquiry_id' => $enquiryId,
                            'request' => json_encode([
                                "to" => '91' . $request->mobile_no,
                                "type" => "template",
                                "template_name" => $template_name,
                                "params" => $inputs,
                            ]),
                            'lsq_activity_data' => config('constants.LSQ.IS_LSQ_ENABLED') == 'Y' ? json_encode([
                                'enquiry_id' => $user_product_journey->user_product_journey_id,
                                'create_lead_on' => NULL,
                                'message_type' => 'Embedded Link Shared',
                                'additional_data' => [
                                    'url' => $journey_url,
                                    'destination' => $request->mobile_no
                                ]
                            ], JSON_UNESCAPED_SLASHES) : NULL,
                            'scheduled_at' => now()/* ->addDay(1) */
                        ]);


                        // if ($whatsapp_response != null) {
                        //     \App\Models\WhatsappRequestResponse::create([
                        //         'ip' => request()->ip(),
                        //         'enquiry_id' => $enquiryId,
                        //         'request_id' => $whatsapp_response['response']['data'][0]['message_id'],
                        //         'mobile_no' => $whatsapp_response['response']['data'][0]['recipient'],
                        //         'request' => $whatsapp_response['request'],
                        //         'response' => $whatsapp_response['response'],
                        //     ]);
                        // }

                        if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                        {
                            $opportunity = createLsqOpportunity($user_product_journey->user_product_journey_id, 'RC Submitted', ['rc_number' => $new_rc_number]);

                            if ($opportunity['status'])
                            {
                                createLsqActivity($user_product_journey->user_product_journey_id, NULL, 'RC Submitted');
                            }
                        }

                        return response()->json([
                            "status" => true,
                            'message' => "Quote Not Found",
                            'data' => [
                                $fail_quote,
                                'link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                                    'enquiry_id' => $enquiryId,
                                    'journey_type' => 'ZW1iZWRlZC1leGNlbA=='
                                ]))
                            ],
                            'upload_excel_id' => $request->upload_excel_id
                        ]);
                    }
                    $resultArr = [];
                    foreach ($newarray as $key => $value) {
                        foreach ($value as $key_a => $val) {
                            $resultArr[$key_a] = $val;
                        }
                    }
                    $min = min($resultArr);
                    $index = array_search($min, $resultArr);
                    $quote = $result[$index];
                    $quote = $quote['data'];
                }
            }


            $ic_alias = MasterCompany::where('company_id', $quote['insuraneCompanyId'])
                ->select('company_alias')
                ->first();
            $data = [
                "enquiryId" => $enquiryId,
                "icId" => $quote['insuraneCompanyId'],
                "icAlias" => $quote['companyName'],
                "productSubTypeId" => $quote['masterPolicyId']['productSubTypeId'],
                "masterPolicyId" => $quote['masterPolicyId']['policyId'],
                "premiumJson" => array_merge($quote, ["companyId" => $quote['insuraneCompanyId'], 'company_alias' => $ic_alias->company_alias]),
                "exShowroomPriceIdv" => $quote['showroomPrice'] ?? null,
                "exShowroomPrice" => $quote['showroomPrice'] ?? null,
                "finalPremiumAmount" => $quote['finalPayableAmount'] ?? null,
                "odPremium" => $quote['finalOdPremium'] ?? null,
                "tpPremium" => $quote['finalTpPremium'] ?? null,
                "addonPremiumTotal" => $quote['addOnPremiumTotal'] ?? null,
                "serviceTax" => $quote['serviceTaxAmount'] ?? null,
                "revisedNcb" => $quote['deductionOfNcb'] ?? null,
                "applicableAddons" => $quote['applicableAddons'] ?? null,
                "productSubTypeId" => $product_sub_type_id,
                "prevInsName" => (isset($response['insurance_data']['company']) ? $response['insurance_data']['company'] : "")
            ];

            $updateQuoteRequestData = [
                "enquiryId" => $enquiryId,
                "idvChangedType" => "lowIdv",
                "vehicleElectricAccessories" => 0,
                "vehicleNonElectricAccessories" => 0,
                "externalBiFuelKit" => 0,
                "OwnerDriverPaCover" => "N",
                "antiTheft" => "N",
                "UnnamedPassengerPaCover" => null,
                "voluntarydeductableAmount" => 0,
                "isClaim" => "N",
                "previousNcb" => "",
                "applicableNcb" => "",
                "previousInsurerCode" => "",
                "manufactureYear" => Carbon::parse($response['vehicle_data']['manufactured_date'])->format('m-Y'),
                "policyExpiryDate" => Carbon::parse($response['insurance_data']['expiry_date'])->format('d-m-Y'),
                "vehicleRegisterDate" => Carbon::parse($response['issue_date'])->format('d-m-Y'),
                "previousPolicyType" => "Comprehensive",
                "ownershipChanged" => "N",
                "isIdvChanged" => "N",
                "businessType" => $vehicle_details['data']['additional_details']['businessType'],
                "policyType" => "comprehensive",
                "vehicleOwnerType" => "I",
                "version" => $vehicle_details['data']['results'][0]['vehicle']['vehicle_cd'],
                "versionName" => $vehicle_details['data']['results'][0]['vehicle']['fla_variant'],
                "fuelType" => $vehicle_details['data']['additional_details']['fuelType'],
                "gcvCarrierType" => null,
                "isPopupShown" => "Y"
            ];

            $update_quote_request_data = Http::post(url('api/updateQuoteRequestData'), $updateQuoteRequestData)->json();
            $save_quote_data = Http::post(url('/api/saveQuoteData'), $data)->json();
            $return_data2['owner'] = $vehicle_details['data']['additional_details']; 
            $proposal_additional_details = [
                "owner" => [
                      "lastName" => null, 
                      "firstName" => $vehicle_details['data']['additional_details']['firstName'], 
                      "fullName" => $vehicle_details['data']['additional_details']['fullName'], 
                      "gender" => null, 
                      "mobileNumber" => $request->mobile_no, 
                      "email" => null, 
                      "stateId" => null, 
                      "cityId" => null, 
                      "state" => null, 
                      "city" => null, 
                      "addressLine1" => $vehicle_details['data']['additional_details']['address_line1'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'] . " " . $vehicle_details['data']['additional_details']['address_line2'], 
                      "pincode" => $vehicle_details['data']['additional_details']['pincode'], 
                      "officeEmail" => null, 
                      "genderName" => "Male", 
                      "prevOwnerType" => "I", 
                      "address" => $vehicle_details['data']['additional_details']['address_line1'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'] . " " . $vehicle_details['data']['additional_details']['address_line2'] 
                   ], 
                "nominee" => [
                         "compulsoryPersonalAccident" => "NO", 
                         "cpa" => "NO" 
                      ], 
                "vehicle" => [
                            "regNo1" => $vehicle_details['data']['additional_details']['rto'], 
                            "regNo2" => explode('-', $vehicle_details['data']['additional_details']['vehicleRegistrationNo'])[1], 
                            "regNo3" => explode('-', $vehicle_details['data']['additional_details']['vehicleRegistrationNo'])[2], 
                            "vehicaleRegistrationNumber" => $vehicle_details['data']['additional_details']['vehicleRegistrationNo'], 
                            "engineNumber" => removeSpecialCharactersFromString($response['vehicle_data']['engine_number']), 
                            "chassisNumber" => removeSpecialCharactersFromString($response['vehicle_data']['chassis_number']), 
                            "isValidPuc" => true, 
                            "isVehicleFinance" => false, 
                            "isCarRegistrationAddressSame" => true, 
                            "rtoLocation" => $vehicle_details['data']['additional_details']['rto'], 
                            "registrationDate" => $vehicle_details['data']['additional_details']['vehicleRegisterDate'], 
                            "vehicleManfYear" => $vehicle_details['data']['additional_details']['manufacture_year'],
                         ], 
                "prepolicy" => [
                               "previousInsuranceCompany" => "RELIANCE", 
                               "InsuranceCompanyName" => $response['insurance_data']['company'], 
                               "previousPolicyExpiryDate" => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])), 
                               "previousPolicyNumber" => isset($response['insurance_data']['policy_number']) ? $response['insurance_data']['policy_number'] : null,
                               "isClaim" => "N", 
                               "claim" => "NO", 
                               "previousNcb" => $vehicle_details['data']['additional_details']['previousNcb'], 
                               "applicableNcb" => $vehicle_details['data']['additional_details']['applicableNcb'], 
                               "prevPolicyExpiryDate" => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])) 
                            ] 
                ];

                $proposal_fields = ProposalFields::where('company_alias', $ic_alias->company_alias)->where('fields', 'LIKE', '%email%')->first();
                if($proposal_fields){
                    $proposal_additional_details = [];
                }

            if (isset($vehicle_details['data']['additional_details']['pincode']) && ! empty($vehicle_details['data']['additional_details']['pincode']))
            {
                $address_details = httpRequestNormal(url('/api/getPincode?pincode=' . $vehicle_details['data']['additional_details']['pincode'] . '&companyAlias=' . $ic_alias->company_alias . '&enquiryId=' . customEncrypt($user_product_journey->user_product_journey_id)), 'GET');
            }

            $previous_insurance_company = NULL;

            if (isset($response['insurance_data']['company']) && ! empty($response['insurance_data']['company']))
            {
                $previous_insurance_company = $response['insurance_data']['company'];

                $previous_insurer_details = httpRequestNormal(url('/api/getPreviousInsurerList'), 'POST', [
                    'companyAlias' => $ic_alias->company_alias,
                    'enquiryId' => customEncrypt($user_product_journey->user_product_journey_id)
                ]);

                if ($previous_insurer_details && isset($previous_insurer_details['response']) && ! empty($previous_insurer_details['response']) && isset($previous_insurer_details['response']['status']) && $previous_insurer_details['response']['status'])
                {
                    foreach ($previous_insurer_details['response']['data'] as $previous_insurer)
                    {
                        if ($previous_insurer['name'] == $response['insurance_data']['company'])
                        {
                            $previous_insurance_company = $previous_insurer['code'];
                        }
                    }
                }
            }

            $user_proposal = UserProposal::updateOrCreate(
                ['user_product_journey_id' => $user_product_journey->user_product_journey_id],
                [
                    'first_name' => $name[0],
                    'last_name' => end($name),
                    'mobile_number' => $request->mobile_no,
                    'idv' => $quote['idv'],
                    'final_payable_amount' => $quote['finalPayableAmount'],
                    'service_tax_amount' => $quote['finalGstAmount'],
                    'od_premium' => $quote['finalOdPremium'],
                    'tp_premium' => $quote['finalTpPremium'],
                    'ncb_discount' => $quote['deductionOfNcb'],
                    'total_discount' => $quote['finalTotalDiscount'],
                    'ic_name' => $quote['companyName'],
                    'ic_id' => $quote['insuraneCompanyId'],
                    'applicable_ncb' => $vehicle_details['data']['additional_details']['applicableNcb'],
                    'is_claim' => 'N',
                    'address_line1' => $vehicle_details['data']['additional_details']['address_line1'],
                    'address_line2' => $vehicle_details['data']['additional_details']['address_line2'],
                    'address_line3' => $vehicle_details['data']['additional_details']['address_line3'],
                    'pincode' => $vehicle_details['data']['additional_details']['pincode'],
                    'state' => $address_details['response']['data']['state']['state_name'] ?? NULL,
                    'city' => $address_details['response']['data']['city'][0]['city_name'] ?? NULL,
                    'rto_location' => isBhSeries($rto_code) ? null : $rto_code,
                    'additional_details' => json_encode($proposal_additional_details),
                    'vehicale_registration_number' => $new_rc_number,
                    'vehicle_manf_year' => $vehicle_details['data']['additional_details']['manufacture_year'],
                    'previous_insurance_company' => $previous_insurance_company,
                    'prev_policy_expiry_date' => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])),
                    'engine_number' => removeSpecialCharactersFromString($response['vehicle_data']['engine_number']),
                    'chassis_number' => removeSpecialCharactersFromString($response['vehicle_data']['chassis_number']),
                    'previous_policy_number' => isset($response['insurance_data']['policy_number']) ? $response['insurance_data']['policy_number'] : '',
                    'vehicle_color' => removeSpecialCharactersFromString($response['vehicle_data']['color'] ?? NULL, true),
                    'is_vehicle_finance' => (isset($response['financed']) ? $response['financed'] : ""),
                    'name_of_financer' => (isset($response['financed']) ? ($response['financed'] == '1' ? (isset($response['financier']) ? $response['financier'] : $response['norms_type']) : '') : ""),

                ]
            );

            $journey_url = linkDeliverystatus(config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId, customDecrypt($enquiryId)) . '&dropout=true';

            $inputs = [
                '*' . $name[0] . ' ' . end($name) . '*',
                $journey_url,
                // '*' . config('constants.brokerConstant.tollfree_number') . '*',
            ];
            $inputs = '"' . implode('","', $inputs) . '"';

            // $response = httpRequest('whatsapp', [
            //     "to" => '91' . $request->mobile_no,
            //     "type" => "template",
            //     "template_name" => $template_name,
            //     "params" => $inputs,
            // ]);

            EmbeddedLinkWhatsappRequests::create([
                'enquiry_id' => $enquiryId,
                'request' => json_encode([
                    "to" => '91' . $request->mobile_no,
                    "type" => "template",
                    "template_name" => $template_name,
                    "params" => $inputs,
                ]),
                'lsq_activity_data' => config('constants.LSQ.IS_LSQ_ENABLED') == 'Y' ? json_encode([
                    'enquiry_id' => $user_product_journey->user_product_journey_id,
                    'create_lead_on' => NULL,
                    'message_type' => 'Embedded Link Shared',
                    'additional_data' => [
                        'url' => $journey_url,
                        'destination' => $request->mobile_no
                    ]
                ], JSON_UNESCAPED_SLASHES) : NULL,
                'scheduled_at' => now()/* ->addDay(1) */
            ]);

            // if ($response != null) {
            //     \App\Models\WhatsappRequestResponse::create([
            //         'ip' => request()->ip(),
            //         'enquiry_id' => $enquiryId,
            //         'request_id' => $response['response']['data'][0]['message_id'],
            //         'mobile_no' => $response['response']['data'][0]['recipient'],
            //         'request' => $response['request'],
            //         'response' => $response['response'],
            //     ]);
            // }
            updateJourneyStage([
                'user_product_journey_id' => customDecrypt($enquiryId),
                'proposal_id' => $user_proposal->user_proposal_id,
                'stage' => 'Embeded Link Genrated',
                'proposal_url' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId,
                'quote_url' => config('constants.frontend_url') . "cv/quotes?enquiry_id=" . $enquiryId,
            ]);

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
            {
                $opportunity = createLsqOpportunity($user_product_journey->user_product_journey_id, 'RC Submitted', [
                    'rc_number' => $new_rc_number
                ]);

                if ($opportunity['status'])
                {
                    createLsqActivity($user_product_journey->user_product_journey_id, NULL, 'RC Submitted');
                }
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'enquiryId' => $enquiryId,
                    'link' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId . '&dropout=true',
                    'ic_name' => $quote['companyName'],
                    'finalOdPremium' => $quote['finalOdPremium'],
                    'finalTpPremium' => $quote['finalTpPremium'],
                    'finalGstAmount' => $quote['finalGstAmount'],
                    'deductionOfNcb' => $quote['deductionOfNcb'],
                    'ApplicableNcb' => $vehicle_details['data']['additional_details']['applicableNcb'] . '%',
                    'finalPayableAmount' => $quote['finalPayableAmount'],
                    "batch_id" => $request->batch_id,
                    "client_id" => $request->client_id,
                    "rc_number" => $request->rc_number,
                    "failed_quote" => $fail_quote ?? null
                ],
                'upload_excel_id' => $request->upload_excel_id
            ]);
        } catch (\Exception $e) {
            info('Enahanced Journey: ' . $e->getMessage() . 'File : ' . $e->getFile() . 'Line No. : ' . $e->getLine());
            $enquiry_data  = $this->createEnquiryIdWithJourney($request);
            if(isset($fastlane) && $fastlane != null){
                $fastlane->update(['enquiry_id' => customDecrypt($enquiry_data['enquiryId'])]);
            }
            return response()->json([
                "status" => true,
                "message" => "Something went wrong..!" . $e->getMessage() . 'Line No. : '  . $e->getLine() . $e->getFile(),
                "data" => $enquiry_data,
                'upload_excel_id' => $request->upload_excel_id
            ]);
        }
    }

    public function getVehicleDetails($enquiryId, $new_rc_number, $type, $response, $journey_type, $inapp = false, $save_mmv = true)
    {
        try {
            $version_code = '';
            $version_id = $response['vehicle_data']['custom_data']['version_id'];
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test' || $env == 'live') {
                $env_folder = 'production';
            }
            $product = $type;
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name = $path . $product . '_model_version.json';
            //$data = json_decode(file_get_contents($file_name), true);
            $data = json_decode(\Illuminate\Support\Facades\Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);

            if ($data) {
                foreach ($data as $value) {
                    if ($value['version_id'] == $version_id) {
                        $version_code = $value['version_id'];
                    }
                }
            }

            if (empty($version_code)) {
                return [
                    'status' => false,
                    'message' => 'version id not found in file  for ' . $version_id,
                ];
            }

            $registration_date = date('d-m-Y', strtotime(str_replace('/', '-', $response['issue_date'])));
            if (isset($response['insurance_data']['expiry_date']) && !empty($response['insurance_data']['expiry_date'])) {
                $policy_expiry = date('d-m-Y', strtotime(str_replace('/', '-', $response['insurance_data']['expiry_date'])));
                $date1 = new \DateTime($registration_date);
                //$date2 = new \DateTime($policy_expiry);
                $date2 = new \DateTime(date('d-m-Y'));
                $interval = $date1->diff($date2);
                $car_age = (($interval->y * 12) + $interval->m) + 1; // In Months
                $previous_ncb = 0;
                if ($car_age < 20) {
                    $previous_ncb = 0;
                    $applicable_ncb = 20;
                } elseif ($car_age < 32) {
                    $previous_ncb = 20;
                    $applicable_ncb = 25;
                } elseif ($car_age < 44) {
                    $previous_ncb = 25;
                    $applicable_ncb = 35;
                } elseif ($car_age < 56) {
                    $previous_ncb = 35;
                    $applicable_ncb = 45;
                } elseif ($car_age < 68) {
                    $previous_ncb = 45;
                    $applicable_ncb = 50;
                } else {
                    $previous_ncb = 50;
                    $applicable_ncb = 50;
                }
            } else {
                $policy_expiry = null;
                $previous_ncb = null;
                $applicable_ncb = null;
            }
            $invoiceDate = null;
            if (\Illuminate\Support\Str::contains($response['vehicle_data']['manufactured_date'] ?? '', '-')) {
                $manf_year = explode('-', $response['vehicle_data']['manufactured_date']);
                // $manf_year = date('m', strtotime($registration_date)) . '-' . $manf_year[0];
                $manf_year = $manf_year[1] . '-' . $manf_year[0];
                // $invoiceDate = '01-'.$manf_year;
            } else {
                $manf_year = date('m-Y', strtotime($registration_date));
            }
            $invoiceDate = $registration_date;
            $reg_no = explode('-', $new_rc_number);
            if ($reg_no[1] < 10 && $reg_no[0] == 'DL' && $journey_type != 'ongrid') {
                $reg_no[1] = '0' . $reg_no[1];
            }
            $rto_code = implode('-', [$reg_no[0], $reg_no[1]]);
            // if (count($reg_no) > 3 && $reg_no[0] == 'DL' && strlen($reg_no[2]) > 2 && is_numeric($reg_no[1])) {
            //     $rto_code = $reg_no[0]. '-'.($reg_no[1]*1).substr($reg_no[2], 0, 1);
            // }
            $rto_name = MasterRto::where('rto_code', $rto_code)->pluck('rto_name')->first();
            /* if (!$rto_name) {
                $curlResponse['status'] = 101;
                return [
                    'status' => false,
                    'message' => 'RTO details not found for rto code ' . $rto_code,
                ];
            } */
            // Fetch company alias from
            $previous_insurer = $previous_insurer_code = NULL;

            $expiry_date = \Carbon\Carbon::parse($policy_expiry);
            $today_date = now()->subDay(1);
            if ($expiry_date < $today_date) {
                $businessType = 'breakin';
                // $applicable_ncb = 0;
                $diff = $expiry_date->diffInDays(now());
                if ($diff > 90) {
                    $previous_ncb = 0;
                    $applicable_ncb = 0;
                }
            } else {
                $businessType = 'rollover';
            }
            // If policy expiry date is more than 45 days from the todays date,
            // then pass it as blank so that pop-up will open in frontend.
            if ($expiry_date->diffInDays(now()) > 45) {
                $policy_expiry = '';
            }
            //$fast_lane_product_code = $response['vehicle_data']['category'] ?? '';
            $ft_product_code = $cv_type = '';
            $vehicle_section = DB::table('fastlane_vehicle_description')->where('description', $response['vehicle_data']['category_description'])->first();
            // Currently Vehicle description is available for Car and bike - 24-06-2022 
            if (!empty($vehicle_section)) {
                $ft_product_code = $vehicle_section->section;
                $ft_product_code == 'cv' ? $cv_type = 'cv' : $cv_type = '';
            } else if (isset($response['vehicle_data']['category_description']) && (stripos($response['vehicle_data']['category_description'], 'LMV') !== false) || (stripos($response['vehicle_data']['category_description'], 'Motor Car') !== false)) {
                $ft_product_code = 'car';
            } else if (isset($response['vehicle_data']['category_description']) && stripos($response['vehicle_data']['category_description'], 'SCOOTER') !== false) {
                $ft_product_code = 'bike';
            }
            if(isset($response['vehicle_data']['custom_data']['cv_type'])){
                $ft_product_code = $response['vehicle_data']['custom_data']['cv_type'];
            }
            // if ($fast_lane_product_code == 'LMV') {
            //     $ft_product_code = 'car';
            // } else if ($fast_lane_product_code == '2W') {
            //     $ft_product_code = 'bike';
            // }
            if ($ft_product_code == 'car' || $ft_product_code == 'TAXI') {
                $od_compare_date = now()->subYear(2)->subDay(180);
            } elseif ($ft_product_code == 'bike') {
                $od_compare_date = now()->subYear(4)->subDay(180);
            }

            if (in_array($ft_product_code, ['car', 'bike'])) {
                $policy_type = Carbon::parse($registration_date) > $od_compare_date ? 'own_damage' : 'comprehensive';
            } else {
                $policy_type = 'comprehensive';
            }
            
            if(in_array($type,['pcv','gcv']))
            {
               $policy_type = 'comprehensive'; 
            }

            if ($policy_type == 'own_damage') {
                $policy_expiry = '';
            }
            $producttype = [
                '1' => 'motor',
                '2' => 'bike',
                '6' => 'pcv',
                '9' => 'gcv',
                '13' => 'gcv',
                '14' => 'gcv',
                '15' => 'gcv',
                '16' => 'gcv',
            ];
            $cv_sections = [
                "AUTO-RICKSHAW" => "5",
                "E-RICKSHAW" => "11",
                "AUTO RICKSHAW" => "5",
                "TAXI" => "6",
                "Three Wheeler (Passenger)" => "5",
                "PASSENGER-BUS" => "7",
                "PICK UP/DELIVERY/REFRIGERATED VAN" => "9",
                "SCHOOL-BUS" => "10",
                "ELECTRIC-RICKSHAW" => "11",
                "TEMPO-TRAVELLER" => "12",
                "DUMPER/TIPPER" => "13",
                "TRUCK" => "14",
                "TRACTOR" => "15",
                "TANKER/BULKER" => "16",
                "AGRICULTURAL-TRACTOR" => "17",
                "MISCELLANEOUS-CLASS" => "18"
            ];
            // Ola is live with Taxi, we don't want to stop that code flow.
            if ($cv_type == 'cv' && $ft_product_code != 'TAXI' && isset($cv_sections[$ft_product_code])) {
                // $product_sub_type_id = array_search($ft_product_code, $cv_sections);
                $product_sub_type_id = $cv_sections[$ft_product_code];
            } else {
                $product_sub_type_id = array_search($product, $producttype);
            }
            $curlResponse = [];

            $ncb_previous_ic_popup_disabled = config('NCB_PREVIOUS_IC_POPUP_DISABLED') == 'Y';
            $ncb_previous_ic_popup_seller_types = [];

            $cv_agent_mappings = CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();

            if (config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES')) {
                $ncb_previous_ic_popup_seller_types = explode(',', config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES'));

                if ( ! empty($cv_agent_mappings->user_id)) {
                    array_push($ncb_previous_ic_popup_seller_types, NULL);
                }
            }

            if (isset($response['insurance_data']['company']) && !empty($response['insurance_data']['company'])) {
                $fyntune_ic = DB::table('fastlane_previous_ic_mapping as m')->whereRaw('? LIKE CONCAT("%", m.identifier, "%")', $response['insurance_data']['company'])->first();
                if (( ! $ncb_previous_ic_popup_disabled || ($ncb_previous_ic_popup_disabled && ! in_array($cv_agent_mappings?->seller_type, $ncb_previous_ic_popup_seller_types))) && $fyntune_ic) {
                    $previous_insurer = $fyntune_ic->company_name;
                    $previous_insurer_code = $fyntune_ic->company_alias;
                }
            }

            $mmv_details = get_fyntune_mmv_details($product_sub_type_id, $version_code);
            if (!$mmv_details['status']) {
                return [
                    'status' => false,
                    'message' => 'MMV details not found. - ' . $version_code,
                ];
            }
            $mmv_details = $mmv_details['data'];
            // if 3 wheeler variants are blocked then don't show variants
            if(($mmv_details['version']['no_of_wheels'] ?? '0') == '3' && config('IS_3_WHEELER_BLOCKED') == 'Y') {
                return [
                    'status' => false,
                    'message' => '3 Wheeler quotes are blocked - ' . $version_code,
                ];
            }
            
            if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy' && strtolower($mmv_details['manufacturer']['manf_name'] ?? '') == 'morris garages') {
                return [
                    'status' => false,
                    'message' => 'Morris Garages vehicle are not allowed.',
                ];
            }
            $previous_insurance = '';
            if (!$inapp) {
                if ($previous_insurer) {
                    $previous_insurance = $previous_insurer;
                } else {
                    $previous_insurance = $response['insurance_data']['company'] ?? '';
                }
            }
            $previous_policy_type = "Comprehensive";
            if(config('newBreakinLogic.Enabled') == 'Y')
            {
                $manufactureDate = '01-' . $manf_year;
                $newBreakinLogicHandle = new \App\Http\Controllers\VahanService\VahanServiceController();
                $newBreakinData = $newBreakinLogicHandle->newBreakinLogicHandle($registration_date,$manufactureDate);
                if(!empty($newBreakinData))
                {
                    extract($newBreakinData);
                    $previous_insurance = $previous_insurer;
                }
                else
                {
                    $previous_policy_type = "Comprehensive";
                }
            }
            

            $corporate_vehicles_quote_request = CorporateVehiclesQuotesRequest::where('user_product_journey_id', $enquiryId)->first();

            CorporateVehiclesQuotesRequest::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId
                ],
                [
                    'version_id' => $save_mmv ? $version_code : $corporate_vehicles_quote_request->version_id,
                    'user_product_journey_id' => $enquiryId,
                    'policy_type' => $policy_type,
                    'product_id' => $product_sub_type_id,
                    'business_type' => $businessType, #'rollover',
                    'vehicle_register_date' => $registration_date,
                    'vehicle_registration_no' => $new_rc_number,
                    'previous_policy_expiry_date' => $policy_expiry, //'11-11-2021',
                    'previous_policy_type' => $previous_policy_type,
                    // 'fuel_type' => $response['vehicle_data']['fuel_type'], //'PETROL',
                    'fuel_type' => $response['vehicle_data']['custom_data']['fuel'] ?? $response['vehicle_data']['fuel_type'], //'PETROL',
                    'manufacture_year' => $manf_year,
                    'vehicle_invoice_date' => $invoiceDate,
                    "vehicle_owner_type" => "I",
                    'rto_code' => isBhSeries($rto_code) ? null : $rto_code,
                    'rto_city' => isBhSeries($rto_name) ? null : $rto_name,
                    'previous_ncb' => $previous_ncb,
                    'applicable_ncb' => $applicable_ncb,
                    'previous_insurer' => $previous_insurance,
                    'previous_insurer_code' => $inapp ? '' : $previous_insurer_code,
                    'journey_type' => $journey_type,
                    'zero_dep_in_last_policy' => 'Y',
                    'gcv_carrier_type' => in_array($product_sub_type_id, ['9', '13', '14', '15', '16']) ? 'PUBLIC' : null,
                    // 'is_ncb_verified' => $applicable_ncb > 0 ? 'Y' : 'N' 
                    // As per breakin ncb issue we are setting hardcoded "N", 
                    //  bcoz user was changing policy expiry date and the previous and applicable ncb both was changing to 0 . 
                    // issue git id #20297
                    'is_ncb_verified' => 'N'
                ]
            );

            $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
            $quote_log_data = isset($quote_log->quote_data) ? json_decode($quote_log->quote_data, TRUE) : [];
            $quote_data = [
                "product_sub_type_id" => $product_sub_type_id,
                "manfacture_id" => $save_mmv ? $mmv_details['manufacturer']['manf_id'] : $quote_log_data['manfacture_id'],
                "manfacture_name" => $save_mmv ? $mmv_details['manufacturer']['manf_name'] : $quote_log_data['manfacture_name'],
                "model" => $save_mmv ? $mmv_details['model']['model_id'] : $quote_log_data['model'],
                "model_name" => $save_mmv ? $mmv_details['model']['model_name'] : $quote_log_data['model_name'],
                "version_id" => $save_mmv ? $version_code : $quote_log_data['version_id'],
                "vehicle_usage" => 2,
                "policy_type" => $policy_type,
                "business_type" => $businessType,
                "version_name" => $save_mmv ? $mmv_details['version']['version_name'] : $quote_log_data['version_name'],
                "vehicle_register_date" => $registration_date,
                "previous_policy_expiry_date" => $policy_expiry,
                "previous_policy_type" => $previous_policy_type,
                "fuel_type" => $save_mmv ? $mmv_details['version']['fuel_type'] : $quote_log_data['fuel_type'],
                "manufacture_year" => $manf_year,
                "rto_code" => isBhSeries($rto_code) ? null : $rto_code,
                "vehicle_owner_type" => "I",
                "is_claim" => "N",
                "previous_ncb" => $previous_ncb,
                "applicable_ncb" => $applicable_ncb,
            ];
            $QuoteLog = [
                'user_product_journey_id' => $enquiryId,
                'quote_data' => json_encode($quote_data),
            ];

            QuoteLog::updateOrCreate([
                'user_product_journey_id' => $enquiryId
            ], $QuoteLog);

            $curlResponse['results'][0]['vehicle']['regn_dt'] = $registration_date;
            $curlResponse['results'][0]['vehicle']['vehicle_cd'] = $save_mmv ? $version_code : $quote_log_data['version_id'];
            $curlResponse['results'][0]['vehicle']['fla_maker_desc'] = $quote_data['manfacture_name'];
            $curlResponse['results'][0]['vehicle']['fla_model_desc'] = $quote_data['model_name'];
            $curlResponse['results'][0]['vehicle']['fla_variant'] = $quote_data['version_name'];
            $curlResponse['results'][0]['insurance']['insurance_upto'] = $policy_expiry;
            $name = explode(' ', $response['owner_data']['name']);
            $address = explode(',', $response['owner_data']['permanent_address']);
            preg_match_all('!\d+!', end($address), $matches);
            $pincode = '';
            if (is_array($matches)) {
                $pincode = implode(',', $matches[0]);
            }
            // If pincode is in wrong/dummy then don't store that value in DB - @Amit 03-09-2022 #8325
            if(\Illuminate\Support\Str::contains($pincode, '999999')) {
                $pincode = null;
            }

            $firstName = count($name) > 2 ? implode(' ', [$name[0], $name[1] ?? '']) : $name[0];
            $lastName = end($name);

            $return_data = [
                'applicableNcb' => $applicable_ncb,
                'businessType' => $businessType,
                "product_sub_type_id" => $product_sub_type_id,
                'corpId' => '',
                'emailId' => '',
                'enquiryId' => customEncrypt($enquiryId),
                // 'firstName' =>  count($name) > 2 ? implode(' ', [$name[0], $name[1] ?? '']) : $name[0], 
                'firstName' => $firstName,
                'fuelType' => $quote_data['fuel_type'],
                // 'fullName' => $response['owner_data']['name'],
                'fullName' => $firstName . ' ' . $lastName,
                'hasExpired' => "no",
                'isClaim' => "N",
                'isNcb' => "Yes",
                "engine_no" => removeSpecialCharactersFromString($response['vehicle_data']['engine_number']),
                "chassis_no" => $response['vehicle_data']['chassis_number'],
                // 'lastName' => end($name),
                'lastName' => $lastName,
                'leadJourneyEnd' => true,
                'manfactureId' => $quote_data['manfacture_id'],
                'manfactureName' => $quote_data['manfacture_name'],
                'manufactureYear' => $quote_data['manufacture_year'],
                //'mobileNo' => '',
                'model' => $quote_data['model'],
                'modelName' => $quote_data['model_name'],
                'ownershipChanged' => "N",
                'policyExpiryDate' => $policy_expiry,
                'policyType' => $policy_type,
                'previousInsurer' => $previous_insurance,
                'previousInsurerCode' => $inapp ? '' : $previous_insurer_code,
                'previousNcb' => $previous_ncb,
                'previousPolicyType' => $previous_policy_type,
                'productSubTypeId' => $product_sub_type_id,
                "manufacture_year" => $manf_year,
                'rto' => isBhSeries($rto_code) ? null : $rto_code,
                'stage' => 11,
                //'userId' => '',
                'userProductJourneyId' => $enquiryId,
                //'vehicleLpgCngKitValue' => "",
                'vehicleOwnerType' => "I",
                'vehicleRegisterAt' => isBhSeries($rto_code) ? null : $rto_code,
                'vehicleRegisterDate' => $registration_date,
                'vehicleRegistrationNo' => $new_rc_number,
                'vehicleInvoiceDate' => $invoiceDate,
                'vehicleUsage' => 2,
                'version' => $save_mmv ? $version_code : $quote_log_data['version_id'],
                'versionName' => $quote_data['version_name'],
                'address_line1' => $response['owner_data']['permanent_address'],
                // 'address_line1' => $address[0] ?? $response['owner_data']['permanent_address'],
                'address_line2' => '',// $address[1] ?? '', // address_line2 and address_line3 are not used in front end so complete address is not visible
                'address_line3' => '',// end($address) ?? '',
                'pincode' => ((int)$pincode > 0) ? (int)$pincode : null,
                'financer_sel' => (isset($response['financed']) ? [($response['financed'] == '1' ? ['name' => (isset($response['financier']) ? $response['financier'] : $response['norms_type']), 'code' => (isset($response['financier']) ? $response['financier'] : $response['norms_type'])] : '')] : ""),
                'pucNo' => $response['pucc_data']['pucc_number'] ?? null,
                'pucExpiry' => isset($response['pucc_data']['expiry_date']) ? date('d-m-Y', strtotime($response['pucc_data']['expiry_date'])) : null
            ];
            $curlResponse['additional_details'] = $return_data;
            $curlResponse['additional_details']['vahan_service_code'] = 'ongrid';

            $vehicle_color = removeSpecialCharactersFromString($response['vehicle_data']['color'] ?? null, true);
            //RenewBuy doesn't want to store the vehicle color #8325
            if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
                $vehicle_color = null;
            }

            $proposalData = [
                'first_name'  => $firstName, 
                'last_name' => $lastName,
                'fullName' => $firstName . ' ' . $lastName,
                'applicable_ncb' => $curlResponse['additional_details']['applicableNcb'],
                'is_claim' => 'N',
                'address_line1' => $curlResponse['additional_details']['address_line1'],
                'address_line2' => $curlResponse['additional_details']['address_line2'],
                'address_line3' => $curlResponse['additional_details']['address_line3'],
                'pincode' => $curlResponse['additional_details']['pincode'],
                'rto_location' => isBhSeries($rto_code) ? null : $rto_code,
                // 'additional_details' => json_encode($return_data),
                'vehicale_registration_number' => $new_rc_number,
                'vehicle_manf_year' => $curlResponse['additional_details']['manufacture_year'],
                'previous_insurance_company' => $response['insurance_data']['company'] ?? null,
                'prev_policy_expiry_date' => empty($policy_expiry) ? null : date('d-m-Y', strtotime($policy_expiry)),
                'engine_number' => removeSpecialCharactersFromString($response['vehicle_data']['engine_number']),
                // 'chassis_number' => removeSpecialCharactersFromString($response['vehicle_data']['chassis_number']),
                'chassis_number' => $response['vehicle_data']['chassis_number'],
                'previous_policy_number' => isset($response['insurance_data']['policy_number']) ? $response['insurance_data']['policy_number'] : '',
                'vehicle_color' => removeSpecialCharactersFromString($vehicle_color, true),
                'is_vehicle_finance' => (isset($response['financed']) ? $response['financed'] : ""),
                'name_of_financer' => (isset($response['financed']) ? ($response['financed'] == '1' ? (isset($response['financier']) ? $response['financier'] : $response['norms_type']) : '') : ""),
                'full_name_finance' => (isset($response['financed']) ? ($response['financed'] == '1' ? (isset($response['financier']) ? $response['financier'] : $response['norms_type']) : '') : ""),
                'puc_no' => $response['pucc_data']['pucc_number'] ?? null,
                'puc_expiry' => isset($response['pucc_data']['expiry_date']) ? date('d-m-Y', strtotime($response['pucc_data']['expiry_date'])) :  null
            ];

            if (config('constants.motorConstant.AUTO_FILL_TP_DETAILS_IN_VAHAN') == 'Y') {
                $tpExpiryDate = null;

                if (!empty($response['insurance_data']['expiry_date'])) {
                    $tpExpiryDate = date('d-m-Y', strtotime(str_replace('/', '-', $response['insurance_data']['expiry_date'])));
                }

                $previousTpInsurer = $response['insurance_data']['company'] ?? null;
                if (!empty($previousTpInsurer)) {
                    $fyntuneIc = DB::table('fastlane_previous_ic_mapping as m')
                    ->whereRaw('? LIKE CONCAT("%", m.identifier, "%")', $previousTpInsurer)
                    ->first();
                    if (!empty($fyntuneIc)) {
                        $previousTpInsurer = $fyntune_ic->company_name;
                    }
                }

                $proposalData = array_merge($proposalData, [
                    // 'tp_start_date'                         => $tpExpiryDate,
                    'tp_end_date'                           => $tpExpiryDate,
                    'tp_insurance_company'                  => $previousTpInsurer,
                    'tp_insurance_company_name'             => $previousTpInsurer,
                    'tp_insurance_number'                   => $response['insurance_data']['policy_number'] ?? null,
                ]);
            }

            UserProposal::updateOrCreate(
                ['user_product_journey_id' => $enquiryId],
                $proposalData
            );
            return [
                'status' => true,
                'data' => $curlResponse
            ];
        } catch (\Exception $e) {
            return [
                "status" => false,
                "message" => "Something went wrong..!" . $e->getMessage() . "on Line " . $e->getLine()
            ];
        }
    }

    public function inappJourneyRedirection(Request $request)
    {
        $app_start_time = microtime(true);
        $XTENANTKEY = $request->header('X-TENANT-KEY');
        $XTENANTNAME = $request->header('X-TENANT-NAME');
        if ($XTENANTKEY != config('constants.X-TENANT-KEY') || $XTENANTNAME != config('constants.X-TENANT-NAME')) {
            return response()->json([
                "status" => false,
                "error" => "Authentication Failed..!"
            ], 401);
        }
        $validate = [
            'driverId' => 'required',
            'phoneNumber' => 'required',
            'emailId' => 'required',
            'vehicleRegNo' => 'required',
            'vehicleMake' => 'nullable',
            'vehicleModelVariant' => 'nullable',
        ];
        $validator = Validator::make($request->all(), $validate);
        if($validator->fails()){
            return response()->json(['status' => false, 'message' => $validator->errors()]);
        }
        try {
            $rc_number = str_split($request->vehicleRegNo);
            if ($rc_number[0] . $rc_number[1] == 'DL') {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2];
                $rto_code = $new_rc_number;
                $str = substr($request->vehicleRegNo, 3);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            } else {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2] . $rc_number[3];
                $rto_code = $new_rc_number;
                $str = substr($request->vehicleRegNo, 4);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            }
            $response = \Illuminate\Support\Facades\DB::table('registration_details')->where('vehicle_reg_no', $new_rc_number)->where('vehicle_details', 'LIKE', '%Extracted details.%')->where('expiry_date', '>=', now()->format('Y-m-d'))->latest()->first();

            if ($response == null) {
                $startTime = new DateTime(date('Y-m-d H:i:s'));
                $ongrid_start_time = microtime(true);
                $ongrid = httpRequest('ongrid', ['rc_number' => $request->vehicleRegNo], [], [], [], false);
                $ongrid_response_time = number_format((microtime(true) - $ongrid_start_time) * 1000, 2);
                $endTime = new DateTime(date('Y-m-d H:i:s'));
                $response = $ongrid['response'];
                DB::table('registration_details')->insert([
                    'vehicle_reg_no' => $new_rc_number,
                    'vehicle_details' => json_encode($response),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? null
                ]);
                $responseTime = $startTime->diff($endTime);
                $fastlane = FastlaneRequestResponse::create([
                    'request' => $request->vehicleRegNo,
                    'response' => json_encode($response),
                    'transaction_type' => "Ongrid Service",
                    'endpoint_url' => $ongrid['url'],
                    'ip_address' => $request->ip(),
                    'section' => 'taxi',
                    'response_time' => $responseTime->format('%Y-%m-%d %H:%i:%s'),
                    'created_at' => now(),
                ]);
            } else {

                $response = json_decode($response->vehicle_details, true);
            }

            if (empty($response) || (isset($response['data']) && $response['data']['code'] != 1000)) {
                $enquiryId = $this->createUserProductJourneyId($request, 'ZHJpdmVyLWFwcA==');
                // $this->createCvAgentMappingDriverApp($enquiryId);
                if(isset($fastlane) && $fastlane != null){
                    $fastlane->update(['enquiry_id' => customDecrypt($enquiryId)]);
                }
                updateJourneyStage([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'stage' => STAGE_NAMES['QUOTE'],
                    'quote_url' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                        'enquiry_id' => $enquiryId,
                        'journey_type' => 'ZHJpdmVyLWFwcA=='
                    ])),
                ]);
                $app_response_time = number_format((microtime(true) - $app_start_time) * 1000, 2);
                DB::table('ola_dapp_response_time_limit')->insert([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'rc_number' => $new_rc_number,
                    'ongrid_response_status' => $ongrid['http_status'] ?? null,
                    'ongrid_response_time' => $ongrid_response_time ?? null,
                    'dapp_response_time' => $app_response_time,
                ]);
                return response()->json([
                    "status" => true,
                    "message" => $response['data']['message'],
                    "url" => config('constants.frontend_url') . 'cv/vehicle-type?enquiry_id=' . $enquiryId,
                ]);
            }
            if (isset($response['error']) || isset($response['message'])) {
                $enquiryId = $this->createUserProductJourneyId($request, 'ZHJpdmVyLWFwcA==');
                // $this->createCvAgentMappingDriverApp($enquiryId);
                if(isset($fastlane) && $fastlane != null){
                    $fastlane->update(['enquiry_id' => customDecrypt($enquiryId)]);
                }
                updateJourneyStage([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'stage' => STAGE_NAMES['QUOTE'],
                    'quote_url' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                        'enquiry_id' => $enquiryId,
                        'journey_type' => 'ZHJpdmVyLWFwcA=='
                    ])),
                ]);
                $app_response_time = number_format((microtime(true) - $app_start_time) * 1000, 2);
                DB::table('ola_dapp_response_time_limit')->insert([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'rc_number' => $new_rc_number,
                    'ongrid_response_status' => $ongrid['http_status'] ?? null,
                    'ongrid_response_time' => $ongrid_response_time ?? null,
                    'dapp_response_time' => $app_response_time,
                ]);
                return response()->json([
                    "status" => false,
                    "message" => (isset($response['error']) ? $response['error']['message'] : $response['message']),
                    "url" => config('constants.frontend_url') . 'cv/vehicle-type?enquiry_id=' . $enquiryId,
                ]);
            }
            if (!isset($response['data']['rc_data']['vehicle_data']['custom_data'])) {
                $enquiryId = $this->createUserProductJourneyId($request, 'ZHJpdmVyLWFwcA==');
                // $this->createCvAgentMappingDriverApp($enquiryId);
                if(isset($fastlane) && $fastlane != null){
                    $fastlane->update(['enquiry_id' => customDecrypt($enquiryId)]);
                }
                updateJourneyStage([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'stage' => STAGE_NAMES['QUOTE'],
                    'quote_url' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                        'enquiry_id' => $enquiryId,
                        'journey_type' => 'ZHJpdmVyLWFwcA=='
                    ])),
                ]);
                $app_response_time = number_format((microtime(true) - $app_start_time) * 1000, 2);
                DB::table('ola_dapp_response_time_limit')->insert([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'rc_number' => $new_rc_number,
                    'ongrid_response_status' => $ongrid['http_status'] ?? null,
                    'ongrid_response_time' => $ongrid_response_time ?? null,
                    'dapp_response_time' => $app_response_time,
                ]);
                return response()->json([
                    "status" => true,
                    "message" => "Vehicle data not found",
                    "url" => config('constants.frontend_url') . 'cv/vehicle-type?enquiry_id=' . $enquiryId,
                ]);
            }
            $response = $response['data']['rc_data'];

            switch (\Illuminate\Support\Str::substr($response['vehicle_data']['custom_data']['version_id'], 0, 3)) {
                case 'PCV':
                    $type = 'pcv';
                    break;
                case 'GCV':
                    $type = 'gcv';
                    break;
                case 'CRP':
                    $type = 'motor';
                    break;
                case 'BYK':
                    $type = 'bike';
                    break;
            }
            $manufacture_year = explode('-', $response['vehicle_data']['manufactured_date']);
            $manufacture_year = $manufacture_year[1] . '-' . $manufacture_year[0];
            $rc_number = str_split($request->vehicleRegNo);
            if ($rc_number[0] . $rc_number[1] == 'DL') {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2];
                $rto_code = $new_rc_number;
                $str = substr($request->vehicleRegNo, 3);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            } else {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2] . $rc_number[3];
                $rto_code = $new_rc_number;
                $str = substr($request->vehicleRegNo, 4);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            }
            $producttype = [
                '1' => 'motor',
                '2' => 'bike',
                '6' => 'pcv',
                '9' => 'gcv',
                '13' => 'gcv',
                '14' => 'gcv',
                '15' => 'gcv',
                '16' => 'gcv',
            ];
            $product_sub_type_id = array_search($type, $producttype);
            $name = explode(' ', $response['owner_data']['name']);
            $user_product_journey = [
                'user_fname' => $name[0],
                // 'user_mname' => $response['owner_data']['father_name'],
                'user_lname' => end($name),
                'user_mobile' => $request->phoneNumber,
                'user_email' => $request->emailId,
                'product_sub_type_id' => $product_sub_type_id,
                'status' => 'yes',
                'lead_stage_id' => 2
            ];
            $user_product_journey = UserProductJourney::create($user_product_journey);
            $enquiryId = $user_product_journey->journey_id;
            $this->createCvAgentMappingDriverApp($enquiryId);
            array_push($response, ['user_mobile' => $request->phoneNumber, 'user_email' => $request->emailId]);
            $vehicle_details = $this->getVehicleDetails($user_product_journey->user_product_journey_id, $new_rc_number, $type, $response, $journey_type = 'driver-app', $inapp = true);

            if (!$vehicle_details['status']) {
                updateJourneyStage([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'stage' => STAGE_NAMES['QUOTE'],
                    'quote_url' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                        'enquiry_id' => $enquiryId,
                        'journey_type' => 'ZHJpdmVyLWFwcA=='
                    ])),
                ]);

                if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                {
                    $lead = createLsqLead(customDecrypt($enquiryId));

                    if ($lead['status'])
                    {
                        $opportunity = createLsqOpportunity(customDecrypt($enquiryId), 'RC Submitted', [
                            'rc_number' => $new_rc_number
                        ]);

                        if ($opportunity['status'])
                        {
                            createLsqActivity(customDecrypt($enquiryId), NULL, 'RC Submitted');
                        }
                    }
                }
                $app_response_time = number_format((microtime(true) - $app_start_time) * 1000, 2);
                DB::table('ola_dapp_response_time_limit')->insert([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'rc_number' => $new_rc_number,
                    'ongrid_response_status' => $ongrid['http_status'] ?? null,
                    'ongrid_response_time' => $ongrid_response_time ?? null,
                    'dapp_response_time' => $app_response_time,
                ]);
                return response()->json([
                    "status" => true,
                    "message" => $vehicle_details,
                    "url" => config('constants.frontend_url') . 'cv/vehicle-type?enquiry_id=' . $enquiryId,
                ]);
            }

            updateJourneyStage([
                'user_product_journey_id' => customDecrypt($enquiryId),
                'stage' => STAGE_NAMES['QUOTE'],
                'quote_url' => urldecode(config('constants.frontend_url') . 'cv/quotes?' . http_build_query([
                    'enquiry_id' => $enquiryId,
                    'journey_type' => 'ZHJpdmVyLWFwcA=='
                ])),
            ]);

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
            {
                $lead = createLsqLead(customDecrypt($enquiryId));

                if ($lead['status'])
                {
                    $opportunity = createLsqOpportunity(customDecrypt($enquiryId), NULL, [
                        'rc_number' => $new_rc_number
                    ]);

                    if ($opportunity['status'])
                    {
                        createLsqActivity(customDecrypt($enquiryId));
                    }
                }
            }
            // $ongrid_response_time;
            $app_response_time = number_format((microtime(true) - $app_start_time) * 1000, 2);
            DB::table('ola_dapp_response_time_limit')->insert([
                'user_product_journey_id' => customDecrypt($enquiryId),
                'rc_number' => $new_rc_number,
                'ongrid_response_status' => $ongrid['http_status'] ?? null,
                'ongrid_response_time' => $ongrid_response_time ?? null,
                'dapp_response_time' => $app_response_time,
            ]);
            return response()->json([
                'status' => true,
                'url' => config('constants.frontend_url') . "cv/quotes?enquiry_id=" . $enquiryId,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "status" => true,
                "message" => "error " . $e->getMessage(),
                "url" => config('constants.frontend_url'),
            ], 500);
        }

    }

    public function createUserProductJourneyId($request, $journey_type = '')
    {
        $rc_number = str_split($request->vehicleRegNo);
        if ($rc_number[0] . $rc_number[1] == 'DL') {
            $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2];
            $rto_code = $new_rc_number;
            $str = substr($request->vehicleRegNo, 3);
            $str2 = substr($str, 0, -4);
            $str3 = substr($str, -4);
            $new_rc_number .= '-' . $str2 . '-' . $str3;
        } else {
            $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2] . $rc_number[3];
            $rto_code = $new_rc_number;
            $str = substr($request->vehicleRegNo, 4);
            $str2 = substr($str, 0, -4);
            $str3 = substr($str, -4);
            $new_rc_number .= '-' . $str2 . '-' . $str3;
        }

        $firstName = NULL;
        $lastName = NULL;

        if (isset($request->owner_name) && ! empty($request->owner_name) & ! is_null($request->owner_name))
        {
            $owner_name = explode(' ', $request->owner_name);

            $firstName = isset($owner_name[0]) ? $owner_name[0] : NULL;
            unset($owner_name[0]);
            $lastName = isset($owner_name[1]) ? implode(' ', $owner_name) : NULL;
        }

        $userProductJourneyData = [
            'product_sub_type_id' => 6,
            'user_fname' => $request->firstName ?? $firstName,
            'user_lname' => $request->lastName ?? $lastName,
            'user_email' => request()->emailId ?? null,
            'user_mobile' => request()->phoneNumber ?? null,
        ];
        $UserProductJourney = UserProductJourney::create($userProductJourneyData);

        $enquiryId = $UserProductJourney->journey_id;

        $data1 = \Illuminate\Support\Facades\Http::withoutVerifying()->acceptJson()->post(
            url('api/saveQuoteRequestData'),
            [
                "stage" => 1,
                "mobileNo" => request()->phoneNumber ?? null,
                "emailId" => request()->emailId ?? null,
                "userProductJourneyId" => $enquiryId ?? null,
                "enquiryId" => $enquiryId ?? null,
                "corpId" => "",
                "userId" => "",
                "productSubTypeId" => "6",
                'journeyType' => $journey_type,
                "sellerType" => $request->seller_type,
                "userName" => null,
                "agentId" => $request->seller_id,
                "agentName" => $request->seller_name,
                "agentMobile" => '97',
                "agentEmail" => 'admin@fyntune.com'
            ]
        )->json();

        updateJourneyStage([
            'user_product_journey_id' => customDecrypt($enquiryId),
            'stage' => STAGE_NAMES['QUOTE'],
            'quote_url' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                'enquiry_id' => $enquiryId,
                'journey_type' => $journey_type
            ])),
        ]);
        
        $data2 = \Illuminate\Support\Facades\Http::withoutVerifying()->acceptJson()->post(
            url('api/saveQuoteRequestData'),
            [
                "stage" => 2,
                "vehicleRegistrationNo" => $new_rc_number,
                "rtoNumber" => isBhSeries($rto_code) ? null : $rto_code,
                "rto" => isBhSeries($rto_code) ? null : $rto_code,
                "userProductJourneyId" => $enquiryId ?? null,
                "vehicleRegisterAt" => isBhSeries($rto_code) ? null : $rto_code,
                "enquiryId" => $enquiryId ?? null,
                "vehicleRegisterDate" => NULL,
                "policyExpiryDate" => NULL,
                "previousInsurerCode" => NULL,
                "previousInsurer" => NULL,
                "previousPolicyType" => NULL,
                "businessType" => NULL,
                "policyType" => NULL,
                "previousNcb" => NULL,
                "applicableNcb" => NULL,
                "fastlaneJourney" => false
            ]
        )->json();

        \Illuminate\Support\Facades\Http::withoutVerifying()->acceptJson()->post(
            url('api/saveQuoteRequestData'),
            [
                "stage" => 3,
                "userProductJourneyId" => $enquiryId ?? null,
                "enquiryId" => $enquiryId ?? null,
                "productSubTypeName" => "TAXI",
                "productSubTypeId" => 6,
            ]
        )->json();
        return $enquiryId;
    }

    public function createEnquiryIdWithJourney(Request $request)
    {
        $template_name = WhatsappTemplate::active()->where(['identifier' => 'renewal_automation_final', 'language' => $request->language ?? 'EN'])->first()->name;
        $request->phoneNumber = $request->mobile_no;
        $request->vehicleRegNo = $request->rc_number;

        $enquiryId = $this->createUserProductJourneyId($request, 'ZW1iZWRlZC1leGNlbA==');

        $journey_url = linkDeliverystatus(urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
            'enquiry_id' => $enquiryId,
            'journey_type' => 'ZW1iZWRlZC1leGNlbA=='
        ])), customDecrypt($enquiryId));

        $inputs = [
            '*' . $request->owner_name . '*',
            $journey_url,
        ];
        $inputs = '"' . implode('","', $inputs) . '"';

        // $whatsapp_response = httpRequest('whatsapp', [
        //     "to" => '91' . $request->mobile_no,
        //     "type" => "template",
        //     "template_name" => $template_name,
        //     "params" => $inputs,
        // ]);

        // if ($whatsapp_response != null) {
        //     \App\Models\WhatsappRequestResponse::create([
        //         'ip' => request()->ip(),
        //         'enquiry_id' => $enquiryId,
        //         'request_id' => $whatsapp_response['response']['data'][0]['message_id'],
        //         'mobile_no' => $whatsapp_response['response']['data'][0]['recipient'],
        //         'request' => $whatsapp_response['request'],
        //         'response' => $whatsapp_response['response'],
        //     ]);
        // }

        EmbeddedLinkWhatsappRequests::create([
            'enquiry_id' => $enquiryId,
            'request' => json_encode([
                "to" => '91' . $request->mobile_no,
                "type" => "template",
                "template_name" => $template_name,
                "params" => $inputs,
            ]),
            'lsq_activity_data' => config('constants.LSQ.IS_LSQ_ENABLED') == 'Y' ? json_encode([
                'enquiry_id' => customDecrypt($enquiryId),
                'create_lead_on' => NULL,
                'message_type' => 'Embedded Link Shared',
                'additional_data' => [
                    'url' => $journey_url,
                    'destination' => $request->mobile_no
                ]
            ], JSON_UNESCAPED_SLASHES) : NULL,
            'scheduled_at' => now()/* ->addDay(1) */
        ]);

        updateJourneyStage([
            'user_product_journey_id' => customDecrypt($enquiryId),
            'stage' => 'Embeded Link Genrated',
            'proposal_url' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId,
            'quote_url' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                'enquiry_id' => $enquiryId,
                'journey_type' => 'ZW1iZWRlZC1leGNlbA=='
            ])),
        ]);

        return [
            "link" => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                'enquiry_id' => $enquiryId,
                'journey_type' => 'ZW1iZWRlZC1leGNlbA=='
            ])),
            'enquiryId' => $enquiryId
        ];
    }

    public function createCvAgentMappingDriverApp($enquiry_id)
    {
        if(config('CvAgentMapping_FILTERED') == 'Y') {
            $agentEntryData = createCvAgentMappingEntryForAgent([
                "user_product_journey_id" => customDecrypt($enquiry_id),
                "stage" => "quote",
                "seller_type" => "E",
                "agent_id" => "11",
                "agent_name" => "driver_app",
            ]);
            if(!$agentEntryData['status']) {
                return response()->json($agentEntryData);
            }
        } else {
            return CvAgentMapping::create([
                "user_product_journey_id" => customDecrypt($enquiry_id),
                "stage" => "quote",
                "seller_type" => "E",
                "agent_id" => "11",
                "agent_name" => "driver_app",
            ]);
        }
    } 

    public function aceCrmLeadId(Request $request)
    {
        try
        {
            if ((empty($request->xutm) && empty($request->token)) || empty($request->crm_lead_id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Request is Empty'
                ]); // redirect(request()->headers->get('referer')); exit;
            }
            $validate = $request->only([
                'token', 'crm_lead_id','xutm'
            ]);
            $request_xutm = $validate['xutm'] ?? $validate['token'];
            $crm_lead_id = $validate['crm_lead_id'];
            $data_by_crm_id  = UserProductJourney::where('lead_id', $crm_lead_id)
            // ->where('status', '!=', 'Inactive')
            ->orderBy('user_product_journey_id', 'desc')
            ->get()
            ->first();
            if (!empty($data_by_crm_id) && $data_by_crm_id->status != 'Inactive') {
                $user_product_journey_id = $data_by_crm_id->user_product_journey_id;
                $JourneyStage_data = JourneyStage::where('user_product_journey_id', $user_product_journey_id)->first();
                if(!empty($JourneyStage_data) && !empty($JourneyStage_data->stage))
                {
                    if ($JourneyStage_data->stage == STAGE_NAMES['QUOTE'] || $JourneyStage_data->stage == STAGE_NAMES['LEAD_GENERATION']) {
                        $return_url = $JourneyStage_data->quote_url;
                    } else {
                        $return_url = $JourneyStage_data->proposal_url;
                    }
                    //return redirect($return_url);
                }
            }
            $tokenValidate = 'ace-crm-token-validate';
            $getUserDetails = 'ace-crm-get-enquiry-id-details';
            
            $isV2 = false;
            $tokenRequest = [
                'token' => $request_xutm
            ];
            if (config('constants.brokerConstant.ENABLE_NEW_CRM_API') == 'Y') {
                $tokenValidate = 'ace-crm-token-validate-v2';
                $getUserDetails = 'ace-crm-get-enquiry-id-details-v2';
                $isV2 = true;
                $tokenRequest['Id'] = $validate['crm_lead_id'];
            }
            $aceToken = httpRequest($tokenValidate, $tokenRequest);
            if (!$isV2 && !isset($aceToken["response"]["data"]['role'])) {
                return $aceToken;
            }
            if ($isV2 && empty($aceToken['response']['data'][0]['data']['role'])) {
                return $aceToken;
            }
            $role = $isV2 ? $aceToken['response']['data'][0]['data']['role'] : $aceToken["response"]["data"]['role'];
            $aceTokenRoleData = AceTokenRoleData::create([
                "role" => $role,
                "lead_id" => $validate["crm_lead_id"],
                "token" => $request_xutm,
                "response" => json_encode($aceToken['response']),
                "status" => 'Active',
            ]);
            \App\Models\UserTokenRequestResponse::create(['request' => json_encode($aceToken['request']), 'response' => json_encode($aceToken['response'])]);

            if ($isV2) {
                $aceToken['response'] = $aceToken['response']['data'][0];
            }
            if (!$aceToken['response']['status']) {
                return 0;
            }
            $enquiryData = httpRequest($getUserDetails, ['Id' => $request->crm_lead_id]);
            \App\Models\UserTokenRequestResponse::create(['request' => json_encode($enquiryData['request']), 'response' => json_encode($enquiryData['response'])]);
            
            if (empty($enquiryData)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Inavlid response from '.$getUserDetails
                ]);
            }
            if ($isV2) {
                $enquiryData['response']['data'] = $enquiryData['response']['data'][0]['data']['data'] ??
                    $enquiryData['response']['data'][0]['data'] ?? null;
            }
            
            $new_rc_number = \Illuminate\Support\Str::upper($enquiryData['response']['data']['regnNmber']);
            $rto_code = explode('-', $new_rc_number);
            $rto_code = implode('-', [$rto_code[0], $rto_code[1]]);
            if (!empty($data_by_crm_id)) {
                $UserProductJourney = $data_by_crm_id;
            } else {
                $userProductJourneyData = [
                    'product_sub_type_id' => 6,
                    'user_fname' => $enquiryData['response']['data']['name'] ?? null,
                    'user_lname' => null,
                    'user_email' => $enquiryData['response']['data']['email'] ?? null,
                    'user_mobile' => $enquiryData['response']['data']['mobile'] ?? null,
                    'api_token' => $request_xutm,
                    'lead_id' => $request->crm_lead_id,
                    'lead_source' => 'ACE CRM',
                    'lead_stage_id' => 2
                ];
                $UserProductJourney = UserProductJourney::create($userProductJourneyData);
            }
            
            $aceTokenRoleData->user_product_journey_id = $UserProductJourney->user_product_journey_id;
            $aceTokenRoleData->lead_response = json_encode($enquiryData);
            $aceTokenRoleData->save();

            // CvAgentMapping::updateOrCreate([
            //     'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
            //     "agent_id" => $aceToken['response']['data']['userId'],
            // ],
            // [
            //     "user_name" => $aceToken['response']['data']['userName']
            // ]);
            
            $seller_details = httpRequest('get_seller_details', ["email" => $aceToken['response']['data']['userName']])['response'];
            if ($seller_details['status'] == 'false') {
                $seller_details['email'] = $aceToken['response']['data']['userName'];
                return json_encode($seller_details);
            } elseif ($seller_details['status'] == 'true') {
                $ALLOWED_SELLER_TYPES = explode(',', config('ALLOWED_SELLER_TYPES'));
                if (!(in_array($seller_details['data']['seller_type'], $ALLOWED_SELLER_TYPES))) {
                    return response()->json([
                        'status' => false,
                        'message' => ($seller_details['data']['seller_type']) . ' Invalid Seller...!'
                    ]);
                }
            }

            $agentData = [
                'agent_id'      => $seller_details['data']['seller_id'] ?? null,
                'seller_type'   => $seller_details['data']['seller_type'] ?? null,
                'user_name'     => $seller_details['data']['user_name'] ?? null,
                'agent_name'    => $seller_details['data']['seller_name'] ?? null,
                'agent_mobile'  => $seller_details['data']['mobile'] ?? null,
                'agent_email'   => $seller_details['data']['email'] ?? null,
                'unique_number' => $seller_details['data']['unique_number'] ?? null,
                'aadhar_no'     => $seller_details['data']['aadhar_no'] ?? null,
                'pan_no'        => $seller_details['data']['pan_no'] ?? null,
                "source"        => $seller_details['data']['source'] ?? null,
                'token'         => $aceToken['response']['data']['userId']
            ];

            CvAgentMapping::updateOrCreate([
                'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
            ],$agentData);

            if(!empty($return_url)) {
                return redirect($return_url);
            }

            $enquiryId = $UserProductJourney->journey_id;
            $oldRequest = $request;

            //stage 2
            $request = new \Illuminate\Http\Request([
                "stage" => 1,
                "userProductJourneyId" => $enquiryId,
                "enquiryId" => $enquiryId,
                "productSubTypeId" => "6",
            ]);

            app()->instance('request', $request);

            $controller = new CommonController();
            $controller->saveQuoteRequestData($request);

            $request = new \Illuminate\Http\Request([
                "stage" => 2,
                "vehicleRegistrationNo" => $new_rc_number,
                "rtoNumber" => isBhSeries($rto_code) ? null : $rto_code,
                "rto" => isBhSeries($rto_code) ? null : $rto_code,
                "userProductJourneyId" => $enquiryId ?? null,
                "vehicleRegisterAt" => isBhSeries($rto_code) ? null : $rto_code,
                "enquiryId" => $enquiryId ?? null,
                "vehicleRegisterDate" => null,
                "policyExpiryDate" => null,
                "previousInsurerCode" => null,
                "previousInsurer" => null,
                "previousPolicyType" => null,
                "businessType" => null,
                "policyType" => null,
                "previousNcb" => null,
                "applicableNcb" => null,
                "fastlaneJourney" => false
            ]);

            app()->instance('request', $request);

            $controller = new CommonController();
            $controller->saveQuoteRequestData($request);

            //stage 3
            $request = new \Illuminate\Http\Request([
                "stage" => 3,
                "userProductJourneyId" => $enquiryId,
                "enquiryId" => $enquiryId,
                "productSubTypeName" => "TAXI",
                "productSubTypeId" => 6,
            ]);

            app()->instance('request', $request);

            $controller = new CommonController();
            $controller->saveQuoteRequestData($request);

            //check for renewal
            $request = new \Illuminate\Http\Request([
                "enquiryId" => $enquiryId,
                "registration_no" => $new_rc_number,
                "productSubType" => 6,
                "section" => "cv",
                "is_renewal" => "Y",
            ]);

            app()->instance('request', $request);

            $controller = new CommonController();
            $vehicleDetails = $controller->getVehicleDetails($request);

            if ($vehicleDetails instanceof \Illuminate\Http\JsonResponse) {
                $vehicleDetails = json_decode($vehicleDetails->getContent(), true);
            }

            //revert the request to original
            $request = $oldRequest;
            app()->instance('request', $request);

            $userProductJourneyId = customDecrypt($enquiryId);

            UserProductJourney::where(['user_product_journey_id' => $userProductJourneyId])
                ->update(['api_token' => $request->token]);

            $isValidvahan = false;
            
            if (!($vehicleDetails['status'] ?? true)) {

                $isVahanDataPresent = config('constants.brokerConstant.IS_VAHAN_DATA_PRESENT_IN_CRM') == 'Y';
                $vahanData = $enquiryData['response']['data']['vahanData'] ?? null;

                if (
                    !($vehicleDetails['show_error'] ?? false) && $isVahanDataPresent &&
                    !empty($vahanData['rc_data'])
                ) {
                    $vahanValidation = AceCrmLeadController::updateVahandetails(
                        $userProductJourneyId,
                        $new_rc_number,
                        $vahanData
                    );

                    if ($vahanValidation['status'] ?? false) {
                        $isValidvahan = true;
                    }
                } else {
                    $error_message = $vehicleDetails['msg'] ?? $vehicleDetails['message'] ?? null;

                    $validations  = config('constants.brokerConstant.ACE_CRM_LEAD_VALIDATION_LIST', '');
                    $validations = json_decode($validations, true);

                    if (!empty($error_message) && !empty($validations)) {
                        foreach ($validations as $validation) {
                            if (strpos(strtoupper($error_message), strtoupper($validation)) !== false) {
                                return response()->json([
                                    'status' => false,
                                    'message' => $error_message
                                ]);
                            }
                        }
                    }
                }
            }

            if(config('OVERRIDE_AGENT_DATA_IN_ACE_CRM') == 'Y'){
                CvAgentMapping::updateOrCreate([
                    'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                ],$agentData);
            }

            if (
                config('OVERRIDE_USER_PRODUCT_JOURNEY_DATA_IN_ACE_CRM') == 'Y' &&
                empty($data_by_crm_id) && !empty($userProductJourneyData)
            ) {
                unset($userProductJourneyData['product_sub_type_id']);
                UserProductJourney::where('user_product_journey_id', $UserProductJourney->user_product_journey_id)
                    ->update($userProductJourneyData);
            }

            if (
                isset($vehicleDetails['data']['redirection_data']) &&
                !empty($vehicleDetails['data']['redirection_data'])
            ) {

                $request = new \Illuminate\Http\Request([
                    "enquiryId" => $enquiryId,
                    "vehicleRegistrationNo" => $vehicleDetails['data']['additional_details']['vehicleRegistrationNo'],
                    "userProductJourneyId" => $enquiryId,
                    "corpId" => null,
                    "userId" => null,
                    "fullName" => $vehicleDetails['data']['additional_details']['fullName'],
                    "firstName" => $vehicleDetails['data']['additional_details']['firstName'],
                    "lastName" => $vehicleDetails['data']['additional_details']['lastName'],
                    "emailId" => $vehicleDetails['data']['additional_details']['emailId'],
                    "mobileNo" => $vehicleDetails['data']['additional_details']['mobileNo'],
                    "policyType" => $vehicleDetails['data']['additional_details']['policyType'],
                    "businessType" => $vehicleDetails['data']['additional_details']['businessType'],
                    "rto" => $vehicleDetails['data']['additional_details']['rto'],
                    "manufactureYear" => $vehicleDetails['data']['additional_details']['manufactureYear'],
                    "version" => $vehicleDetails['data']['additional_details']['version'],
                    "versionName" => $vehicleDetails['data']['additional_details']['versionName'],
                    "vehicleRegisterAt" => $vehicleDetails['data']['additional_details']["vehicleRegisterAt"],
                    "vehicleRegisterDate" => $vehicleDetails['data']['additional_details']["vehicleRegisterDate"],
                    "vehicleOwnerType" => $vehicleDetails['data']['additional_details']["vehicleOwnerType"],
                    "hasExpired" => $vehicleDetails['data']['additional_details']['hasExpired'],
                    "isNcb" => $vehicleDetails['data']['additional_details']['isNcb'],
                    "isClaim" => $vehicleDetails['data']['additional_details']['isClaim'],
                    "fuelType" => $vehicleDetails['data']['additional_details']['fuelType'],
                    "vehicleUsage" =>  $vehicleDetails['data']['additional_details']['vehicleUsage'],
                    "vehicleLpgCngKitValue" => "",
                    "previousInsurer" => $vehicleDetails['data']['additional_details']["previousInsurer"],
                    "previousInsurerCode" => $vehicleDetails['data']['additional_details']['previousInsurerCode'],
                    "previousPolicyType" => $vehicleDetails['data']['additional_details']["previousPolicyType"],
                    "modelName" => $vehicleDetails['data']['additional_details']["modelName"],
                    "manfactureName" => $vehicleDetails['data']['additional_details']["manfactureName"],
                    "ownershipChanged" => $vehicleDetails['data']['additional_details']["ownershipChanged"],
                    "leadJourneyEnd" => $vehicleDetails['data']['additional_details']["leadJourneyEnd"],
                    "stage" => 11,
                    "applicableNcb" => $vehicleDetails['data']['additional_details']["applicableNcb"],
                    "product_sub_type_id" => $vehicleDetails['data']['additional_details']["product_sub_type_id"],
                    "manfactureId" => $vehicleDetails['data']['additional_details']["manfactureId"],
                    "model" => $vehicleDetails['data']['additional_details']["model"],
                    "previousNcb" => $vehicleDetails['data']['additional_details']["previousNcb"],
                    "productSubTypeId" => $vehicleDetails['data']['additional_details']["productSubTypeId"],
                    "engine_number" => removeSpecialCharactersFromString($vehicleDetails['data']['additional_details']["engine_number"]),
                    "chassis_number" => removeSpecialCharactersFromString($vehicleDetails['data']['additional_details']["chassis_number"]),
                    "pincode" => $vehicleDetails['data']['additional_details']["pincode"],
                    "oldenquiryId" => $vehicleDetails['data']['additional_details']["oldenquiryId"] ?? null
                ]);

                app()->instance('request', $request);

                $controller = new CommonController();
                $controller->saveQuoteRequestData($request);

                //revert the request to original
                $request = $oldRequest;
                app()->instance('request', $request);
            }

            $query_data = [
                'enquiry_id' => $enquiryId,
                //'token'      => $request->token,
                'xutm'       => $request_xutm,
                'lead_id'    => $request->crm_lead_id
            ];
            $journey_stage['user_product_journey_id'] = customDecrypt($enquiryId);
            $journey_stage['stage'] = STAGE_NAMES['LEAD_GENERATION'];
            //$journey_stage['proposal_url'] = \Illuminate\Support\Str::replace(request()->enquiryId, $user_product_journey->journey_id, $journey_stage['proposal_url']);
            $journey_stage['quote_url'] = config('constants.motorConstant.CV_FRONTEND_URL') . '/vehicle-details?' . http_build_query($query_data);
            JourneyStage::updateOrCreate(
                ['user_product_journey_id' => customDecrypt($enquiryId)],
                $journey_stage
            );
            updateJourneyStage($journey_stage);

            if (($vehicleDetails['status'] ?? false) ||  $isValidvahan) {
                return redirect(config('constants.motorConstant.CV_FRONTEND_URL') . '/quotes?' . http_build_query($query_data));
            }

            return redirect(config('constants.motorConstant.CV_FRONTEND_URL') . '/vehicle-details?' . http_build_query($query_data));
        
        } catch (\Throwable $th) {
            info($th);
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ]);
        }
        
    }

    public function getQuotes($data, $procced)
    {
        if ($procced != 'true') {
            return false;
        }
        $responses = Http::pool(function (Pool $pool) use ($data) {
            return collect($data)->each(function ($url, $key) use ($pool) {
                return $pool->as($key)->withoutVerifying()->acceptJson()->post($url['url'], $url['data']);
            })->toArray();
        });
        foreach ($responses as $key => $value) {
            $result[$key] = $value->json() ?? $value->body();
        }
        $fail_quote = null;
        $newarray = [];
        foreach ($result as $index => $row) {

            if (isset($row['data'])) {
                array_push($newarray, /* [$index =>  */ $row/* ['data']['tppdPremiumAmount'] + $row['data']['basicPremium']] */);
            } else {
                if ($row != null) {
                    $fail_quote .= ($row['FTS_VERSION_ID'] ?? "") . ' - ' . (isset($row['message']) ? $row['message'] : json_encode($row)) . ',';
                    // array_push($fail_quote, array_merge($row, ['ic_name' => $product_data['data']['comprehensive'][$index]['companyName'], 'policyId' => $product_data['data']['comprehensive'][$index]['policyId'], 'companyAlias' => $product_data['data']['comprehensive'][$index]['companyAlias'], 'productSubTypeName' => $product_data['data']['comprehensive'][$index]['productSubTypeName']]));
                }
            }
        }
        if (empty($newarray))
            return false;
        $min = collect($newarray)->min('data.finalPayableAmount');
        return collect($newarray)->where('data.finalPayableAmount', $min)->first();
    }

    public function renewbuyGenerateLead(Request $request)
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

        $params = ['reg_no', 'policy_no', 'is_renewal', 'is_partner'];
        foreach ($params as $v) {
            if (isset($request->{$v})) {
                $query_parameters[$v] = $request->{$v};
            }
        }

        ##---
        ## RenewBuy Multiple Domain -- start
        ##
            $frontend_url = config('constants.motorConstant.' . strtoupper($request->product_type) . '_FRONTEND_URL');
            $frontend_url = str_replace( 'renewbuyinsurance.in', 'rbstaging.in',  $frontend_url );
            $frontend_url = str_replace( 'renewbuyinsurance.com', 'renewbuy.com',  $frontend_url );
            
            if( config( 'RENEWBUY_INSURANCE_DOMAIN_ENABLED' ) == 'Y' )
            {
                if( $request->rdt == 'rbi' )
                {
                    $frontend_url = str_replace( 'renewbuy.com', 'renewbuyinsurance.com',  $frontend_url );
                    $frontend_url = str_replace( 'rbstaging.in', 'renewbuyinsurance.in',  $frontend_url );
                }
                else if( $request->rdt == 'rb' )
                {
                    $frontend_url = str_replace( 'renewbuyinsurance.com', 'renewbuy.com',  $frontend_url );
                    $frontend_url = str_replace( 'renewbuyinsurance.in', 'rbstaging.in',  $frontend_url );
                }
            }
        ##
        ## RenewBuy Multiple Domain -- end
        ##---
 
        if ( isset( $query_parameters['is_renewal'] ) && $query_parameters['is_renewal'] == 'Y' ) 
        {
            // https://uatcar.rbstaging.in/car/quotes?enquiry_id=2022101400025430
            // $redirect_page = '/renewal?';
            // $redirect_page = $frontend_url.'/quotes?enquiry_id='.$enc_id;
            $product_type = [
                'car'   => '1',
                'bike'  => '2'
            ];
        
            $payload = [
               'enquiryId'         => $enc_id,
               'registration_no'   => 'NULL',
               'productSubType'    => $product_type[$request->product_type],
               'section'           => $request->product_type,
               'is_renewal'        => 'Y',
            ];
            if(strtoupper($request->reg_no) == 'NEW' || strtoupper($request->reg_no) == 'NONE')
            {
                $request->reg_no = '';
            }
            
            if (!empty($request->reg_no)) 
            {
                $payload['registration_no'] = getRegisterNumberWithHyphen(str_replace("-", "", $request->reg_no));
                $payload['vendor_rc']       = $request->reg_no;
            } 
            else 
            {
                $payload['isPolicyNumber'] = 'Y';
                $payload['policyNumber'] = $request->policy_no; 
            }
            
            $oldRequest=$request->all();
            $common = new CommonController;
            $getVehicleDetails = $common->getVehicleDetails(request()->replace($payload));

            if (isset($oldRequest['is_whatsapp'])) {
                CorporateVehiclesQuotesRequest::updateOrCreate(
                    [
                        'user_product_journey_id' => $UserProductJourney->user_product_journey_id
                    ],
                    [
                        'whatsapp_consent' => in_array(strtoupper($oldRequest['is_whatsapp']), ['TRUE','1']) ? 'Y' : 'N'
                    ]
                );
            }

            if(is_array($getVehicleDetails) && isset($getVehicleDetails['status']) && $getVehicleDetails['status'] == false)
            {
                return json_encode($getVehicleDetails);
            }
            if ($getVehicleDetails instanceof JsonResponse) {
                $getVehicleDetails = $getVehicleDetails->content();
            }
            $getVehicleDetails = is_array($getVehicleDetails) ? $getVehicleDetails : json_decode($getVehicleDetails, TRUE);
        
            if(isset($getVehicleDetails['data']['status']) && $getVehicleDetails['data']['status'] == 100)
            {
                try{                    
          
                    if(isset($getVehicleDetails['data']['pos_code']) && !empty($getVehicleDetails['data']['pos_code']))
                    {
                        $agentMapping = new \App\Http\Controllers\AgentMappingController($UserProductJourney->user_product_journey_id);
                        $token = $query_parameters['token'] = (string) \Illuminate\Support\Str::uuid();
                        $request->tokenResp = [
                            'token'     => $token,
                            'username'  => $getVehicleDetails['data']['pos_code'],
                            'source'    => 'RENEWAL'
                        ];
                        $agentMapping->mapAgent($request);
                    }

                    UserProductJourney::where('user_product_journey_id', $UserProductJourney->user_product_journey_id)
                        ->update(['lead_stage_id' => 2]);

                    $quote_url = $frontend_url.'/quotes?'.http_build_query($query_parameters);
                    $proposal_url = $frontend_url.'/proposal-page?'.http_build_query($query_parameters);
                    updateJourneyStage([
                        'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
                        'stage'         => STAGE_NAMES['QUOTE'],
                        'proposal_url'  => $proposal_url,
                        'quote_url'     => $quote_url
                    ]);
                    return redirect($quote_url);
                }
                catch(\Exception $e) 
                {                
                    return response()->json([
                        'status' => false,
                        'msg' => $e->getMessage()
                    ]);
                }
            }
            else
            {
                return $getVehicleDetails;
            }
        }

        updateJourneyStage([
            'user_product_journey_id' => $UserProductJourney->user_product_journey_id,
            'stage' => STAGE_NAMES['LEAD_GENERATION'],
            'proposal_url' => $frontend_url . "/proposal-page?enquiry_id=" . $enc_id,
            'quote_url' => $frontend_url . "/quotes?enquiry_id=" . $enc_id,
        ]);

        if(stripos(\Illuminate\Support\Facades\URL::current(), 'GenerateLeadB2C') !== false) {
            CvAgentMapping::updateOrCreate(
                [
                    "seller_type" => "U",
                    "user_product_journey_id" => $UserProductJourney->user_product_journey_id
                ],
                [
                    "user_product_journey_id" => $UserProductJourney->user_product_journey_id,
                    "stage" => "quote",
                    "seller_type" => "U",
                    "agent_id" => null,
                    "agent_name" => null,
                ]
            );
        } else if ($request->has('agent_id')) {
            try {
                $source = $request->app == 'true' ? 'app' : ($request->app == 'ios' ? 'ios' : 'cse');
                $agentMapping = new \App\Http\Controllers\AgentMappingController($UserProductJourney->user_product_journey_id);
                $token = $query_parameters['token'] = (string) \Illuminate\Support\Str::uuid();
                $request->tokenResp = [
                    'token' => $token,
                    'username' => $request->agent_id,
                    'source' => $source,
                ];
                $agentMapping->mapAgent($request);
            }catch(\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }
        } else if ($request->has('executive')) {
            try {
                $startTime = new DateTime(date('Y-m-d H:i:s'));
                $dataDecryption = httpRequest('decrypt-RenewBuy-POSDetails', ['code' => $request->executive], [], [], [], false);
                $endTime = new DateTime(date('Y-m-d H:i:s'));
                $wsLogdata = [
                    'enquiry_id'     => $UserProductJourney->user_product_journey_id,
                    'product'       => \Illuminate\Support\Str::upper($request->product_type),
                    'section'       => \Illuminate\Support\Str::upper($request->product_type),
                    'method_name'   => 'Decrypt Renewbuy POS detail',
                    'company'       => 'renewbuy',
                    'method'        => 'get',
                    'transaction_type' => 'quote',
                    'request'       => json_encode($dataDecryption['request']),
                    'response'      => json_encode($dataDecryption['response']),
                    'endpoint_url'  => $dataDecryption['url'],
                    'ip_address'    => request()->ip(),
                    'start_time'    => $startTime->format('Y-m-d H:i:s'),
                    'end_time'      => $endTime->format('Y-m-d H:i:s'),
                    'response_time'	=> $endTime->getTimestamp() - $startTime->getTimestamp(),
                    'created_at'    => Carbon::now(),
                    'headers'       => json_encode($dataDecryption['request_headers']),
                ];
                \App\Models\QuoteServiceRequestResponse::create($wsLogdata);
                $username = null;
                if ($dataDecryption['status'] == 200) {
                    if (!empty($dataDecryption['response']['partner_code'])) {
                        $username = $dataDecryption['response']['partner_code'];
                    } else {
                        return response()->json([
                            'status' => false,
                            'msg' => 'Renewbuy decryption API failed. Invalid username found in response - ' . $dataDecryption['response']['partner_code'] ?? '',
                        ]);
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Renewbuy decryption API failed. Status code :' . $dataDecryption['status'],
                    ]);
                }
                $source = 'qr';
                $agentMapping = new \App\Http\Controllers\AgentMappingController($UserProductJourney->user_product_journey_id);
                $token = $query_parameters['token'] = (string) \Illuminate\Support\Str::uuid();
                $request->tokenResp = [
                    'token' => $token,
                    'username' => $username,
                    'source' => $source,
                ];
                $agentMapping->mapAgent($request);
            } catch(\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }
        }
        return redirect($frontend_url . $redirect_page . http_build_query($query_parameters));
    }

    public function getJourneyDetailsByRCNumber(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        DB::disableQueryLog();

        $validate = $request->validate([
            'registrationNumber' => 'required',
        ]);

        $request->rc_number = str_replace('-', '', $request->registrationNumber);

        try {
            $lsq_api_request_response = LsqApiRequestResponse::create([
                'request' => json_encode($request->all()),
                'rc_number' => $request->rc_number
            ]);

            $response = \Illuminate\Support\Facades\DB::table('registration_details')->where('vehicle_reg_no', $request->registrationNumber)->where('vehicle_details', 'LIKE', '%Extracted details.%')->where('expiry_date', '>=', now()->format('Y-m-d'))->latest()->first();

            if ($response == null)
            {
                $service = 'online';
                $startTime = new DateTime(date('Y-m-d H:i:s'));

                $response = httpRequest('ongrid', ['rc_number' => $request->rc_number]);
                $request_url = $response['url'];

                $response = $response['response'];

                $endTime = new DateTime(date('Y-m-d H:i:s'));

                if (isset($response['data']['rc_data']['vehicle_data']['custom_data']['version_id']) && ! empty($response['data']['rc_data']['vehicle_data']['custom_data']['version_id']))
                {
                    DB::table('registration_details')->insert([
                        'vehicle_reg_no' => $request->registrationNumber,
                        'vehicle_details' => json_encode($response),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? null
                    ]);
                }
            }
            else
            {
                $service = 'offline';

                $response = json_decode($response->vehicle_details, true);
            }

            $rc_number = str_split($request->rc_number);

            if ($rc_number[0] . $rc_number[1] == 'DL')
            {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2];
                $rto_code = $new_rc_number;
                $str = substr($request->rc_number, 3);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            }
            else
            {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2] . $rc_number[3];
                $rto_code = $new_rc_number;
                $str = substr($request->rc_number, 4);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            }

            if (empty($response) || (isset($response['data']) && $response['data']['code'] != 1000) || isset($response['error']) || isset($response['message']) || ! isset($response['data']['rc_data']['vehicle_data']['custom_data']))
            {
                $enquiryId = $this->createUserProductJourneyId(new Request([
                    'vehicleRegNo' => $request->rc_number
                ]));

                if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                {
                    $opportunity = createLsqOpportunity(customDecrypt($enquiryId), 'RC Submitted', [
                        'rc_number' => $new_rc_number
                    ]);

                    if ($opportunity['status'])
                    {
                        createLsqActivity(customDecrypt($enquiryId), NULL, 'RC Submitted');
                    }
                }

                if ($service == 'online')
                {
                    $responseTime = $startTime->diff($endTime);

                    FastlaneRequestResponse::create([
                        'enquiry_id' => customDecrypt($enquiryId),
                        'request' => $request->rc_number,
                        'response' => json_encode($response),
                        'transaction_type' => 'Ongrid Service',
                        'endpoint_url' => $request_url,
                        'ip_address' => $request->ip(),
                        'section' => 'CV',
                        'response_time' => $responseTime->format('%H:%i:%s'),
                        'created_at' => now(),
                    ]);
                }

                updateJourneyStage([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'stage' => STAGE_NAMES['QUOTE'],
                    'proposal_url' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId,
                    'quote_url' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                        'enquiry_id' => $enquiryId
                    ])),
                ]);

                $user_product_journey = UserProductJourney::find(customDecrypt($enquiryId));
                $journey_stage = $user_product_journey->journey_stage;

                $error_message = '';

                if (empty($response) || (isset($response['data']) && $response['data']['code'] != 1000))
                {
                    $error_message = $response['data']['message'];
                }
                elseif (isset($response['error']) || isset($response['message']))
                {
                    $error_message = (isset($response['error']) ? $response['error']['message'] : $response['message']);
                }
                elseif (!isset($response['data']['rc_data']['vehicle_data']['custom_data']))
                {
                    $error_message = 'Vehicle data not found';
                }

                $response_data = camelCase([
                    'status' => true,
                    'message' => $error_message,
                    'data' => [
                        'previous_ncb' => 0,
                        'current_ncb' => 0,
                        'claim_status' => '',
                        'ic_name' => '',
                        'od_premium' => 0,
                        'tp_premium' => 0,
                        'cpa_premium' => 0,
                        'addon_premium' => 0,
                        'total_premium' => 0,
                        'policy_start_date' => '',
                        'enquiry_id' => $enquiryId,
                        'enquiry_link' => $journey_stage->quote_url,
                        'policy_type' => '',
                        'policy_tenure' => '',
                        'proposal_id' => '',
                        'quote_id' => $enquiryId,
                        'quote_link' => $journey_stage->quote_url,
                        'owner_name' => '',
                    ]
                ]);

                $this->saveLsqApiData($response_data, $lsq_api_request_response->id);

                return response()->json($response_data);
            }

            $response = $response['data']['rc_data'];

            switch (\Illuminate\Support\Str::substr($response['vehicle_data']['custom_data']['version_id'], 0, 3)) {
                case 'PCV':
                    $type = 'pcv';
                    break;
                case 'GCV':
                    $type = 'gcv';
                    break;
                case 'CRP':
                    $type = 'motor';
                    break;
                case 'BYK':
                    $type = 'bike';
                    break;
            }

            $manufacture_year = explode('-', $response['vehicle_data']['manufactured_date']);
            $manufacture_year = $manufacture_year[1] . '-' . $manufacture_year[0];

            $producttype = [
                '1' => 'motor',
                '2' => 'bike',
                '6' => 'pcv',
                '9' => 'gcv',
                '13' => 'gcv',
                '14' => 'gcv',
                '15' => 'gcv',
                '16' => 'gcv',
            ];

            $product_sub_type_id = array_search($type, $producttype);

            $name = explode(' ', $response['owner_data']['name']);

            $user_product_journey = [
                'user_fname' => $name[0],
                'user_lname' => end($name),
                'user_mobile' => $request->mobile_no,
                'product_sub_type_id' => $product_sub_type_id,
                'status' => 'yes',
                'lead_stage_id' => 2
            ];

            $user_product_journey = UserProductJourney::create($user_product_journey);

            CvAgentMapping::updateOrCreate(
                ['user_product_journey_id' => $user_product_journey->user_product_journey_id,],
                [
                    'seller_type' => $request->seller_type,
                    'agent_id' => $request->seller_id,
                    'agent_name' => $request->seller_name,
                    'agent_name' => $request->seller_name,
                ],
            );

            $enquiryId = $user_product_journey->journey_id;

            if ($service == 'online')
            {
                $responseTime = $startTime->diff($endTime);

                FastlaneRequestResponse::create([
                    'enquiry_id' => $user_product_journey->user_product_journey_id,
                    'request' => $request->rc_number,
                    'response' => json_encode($response),
                    'transaction_type' => 'Ongrid Service',
                    'endpoint_url' => $request_url,
                    'ip_address' => $request->ip(),
                    'section' => 'CV',
                    'response_time' => $responseTime->format('%H:%i:%s'),
                    'created_at' => now(),
                ]);
            }

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
            {
                $lead = createLsqLead($user_product_journey->user_product_journey_id);

                if ($lead['status'])
                {
                    createLsqActivity($user_product_journey->user_product_journey_id, 'lead');
                }
            }

            $SelectedAddons = [
                'user_product_journey_id' => $user_product_journey->user_product_journey_id,
                'compulsory_personal_accident' => [['reason' => 'I have another motor policy with PA owner driver cover in my name']]
            ];

            SelectedAddons::create($SelectedAddons);

            $vehicle_details = $this->getVehicleDetails($user_product_journey->user_product_journey_id, $new_rc_number, $type, $response, NULL);

            if ( ! $vehicle_details['status'])
            {
                if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                {
                    $opportunity = createLsqOpportunity($user_product_journey->user_product_journey_id, 'RC Submitted', ['rc_number' => $new_rc_number]);

                    if ($opportunity['status'])
                    {
                        createLsqActivity($user_product_journey->user_product_journey_id, NULL, 'RC Submitted');
                    }
                }

                $user_product_journey = UserProductJourney::find($user_product_journey->user_product_journey_id);
                $journey_stage = $user_product_journey->journey_stage;
                $response_data = camelCase([
                    'status' => true,
                    'message' => $vehicle_details['message'],
                    'data' => [
                        'previous_ncb' => 0,
                        'current_ncb' => 0,
                        'claim_status' => '',
                        'ic_name' => '',
                        'od_premium' => 0,
                        'tp_premium' => 0,
                        'cpa_premium' => 0,
                        'addon_premium' => 0,
                        'total_premium' => 0,
                        'policy_start_date' => '',
                        'enquiry_id' => $enquiryId,
                        'enquiry_link' => $journey_stage->quote_url,
                        'policy_type' => '',
                        'policy_tenure' => '',
                        'proposal_id' => '',
                        'quote_id' => $enquiryId,
                        'quote_link' => $journey_stage->quote_url,
                        'owner_name' => '',
                    ]
                ]);

                $this->saveLsqApiData($response_data, $lsq_api_request_response->id);

                return response()->json($response_data);
            }

            $master_policies = \App\Models\MasterPolicy::where('premium_type_id', 5)->join('master_company as mc', 'mc.company_id', '=', 'insurance_company_id')->select('policy_id', 'mc.company_alias')->get();

            $data = [];

            foreach ($master_policies as $value)
            {
                $data[] = [
                    'url' => url('api/premiumCalculation/' . $value->company_alias),
                    'data' => [
                        "enquiryId" => $enquiryId,
                        "policyId" => $value->policy_id,
                    ]
                ];
            }

            if ($quote_data = $this->getQuotes($data, config('constants.enhance_journey_short_term_3_months')))
            {
                $quote = $quote_data['data'];
            }
            else
            {
                $master_policies = \App\Models\MasterPolicy::where('premium_type_id', 8)->join('master_company as mc', 'mc.company_id', '=', 'insurance_company_id')->select('policy_id', 'mc.company_alias')->get();

                $data = [];

                foreach ($master_policies as $key => $value)
                {
                    $data[] = [
                        'url' => url('api/premiumCalculation/' . $value->company_alias),
                        'data' => [
                            "enquiryId" => $enquiryId,
                            "policyId" => $value->policy_id,
                        ]
                    ];
                }
    
                if ($quote_data = $this->getQuotes($data, config('constants.enhance_journey_short_term_6_months')))
                {
                    $quote = $quote_data['data'];
                }
                else
                {
                    $product_data = Http::post(url('/api/getProductDetails'), [
                        "productSubTypeId" => $product_sub_type_id,
                        "businessType" => $vehicle_details['data']['additional_details']['businessType'],
                        "policyType" => "comprehensive",
                        "selectedPreviousPolicyType" => "Comprehensive",
                        "premiumType" => $vehicle_details['data']['additional_details']['businessType'],
                        "previousInsurer" => "",
                        "enquiryId" => $enquiryId
                    ])->json();
    
                    $master = curl_multi_init();

                    foreach ($product_data['data']['comprehensive'] as $key => $value)
                    {
                        $url = url('api/premiumCalculation/' . $value['companyAlias']);

                        $curl_arr[$key] = curl_init($url);

                        $data = [
                            "enquiryId" => $enquiryId,
                            "policyId" => $value['policyId'],
                        ];

                        curl_setopt($curl_arr[$key], CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl_arr[$key], CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($curl_arr[$key], CURLOPT_HTTPHEADER, [
                            'Accept' => 'application/json',
                        ]);
                        curl_setopt($curl_arr[$key], CURLOPT_POSTFIELDS, $data);
                        curl_multi_add_handle($master, $curl_arr[$key]);
                    }

                    do {
                        curl_multi_exec($master, $running);
                    } while ($running > 0);
    
                    $result = [];

                    foreach ($product_data['data']['comprehensive'] as $key => $value)
                    {
                        $result[$key] = json_decode(curl_multi_getcontent($curl_arr[$key]), true);
                    }

                    $newarray = [];

                    $fail_quote = '';

                    foreach ($result as $index => $row)
                    {
                        if (isset($row['data']))
                        {
                            array_push($newarray, [$index => $row['data']['tppdPremiumAmount'] + $row['data']['basicPremium']]);
                        }
                        else
                        {
                            if ($row != null)
                            {
                                $fail_quote .= $row['FTS_VERSION_ID'] . ' - ' . (isset($product_data['data']['comprehensive'][$index]['companyName']) ? $product_data['data']['comprehensive'][$index]['companyName'] : $product_data['data']['comprehensive'][$index]) . ' - ' . (isset($row['message']) ? $row['message'] : json_encode($row)) . ',';
                            }
                        }
                    }

                    if (empty($newarray))
                    {   
                        if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                        {
                            $opportunity = createLsqOpportunity($user_product_journey->user_product_journey_id, 'RC Submitted', ['rc_number' => $new_rc_number]);
    
                            if ($opportunity['status'])
                            {
                                createLsqActivity($user_product_journey->user_product_journey_id, NULL, 'RC Submitted');
                            }
                        }
    
                        $user_product_journey = UserProductJourney::find($user_product_journey->user_product_journey_id);
                        $journey_stage = $user_product_journey->journey_stage;
                        $response_data = camelCase([
                            'status' => true,
                            'message' => 'Quote Not Found',
                            'data' => [
                                'previous_ncb' => 0,
                                'current_ncb' => 0,
                                'claim_status' => '',
                                'ic_name' => '',
                                'od_premium' => 0,
                                'tp_premium' => 0,
                                'cpa_premium' => 0,
                                'addon_premium' => 0,
                                'total_premium' => 0,
                                'policy_start_date' => '',
                                'enquiry_id' => $enquiryId,
                                'enquiry_link' => $journey_stage->quote_url,
                                'policy_type' => '',
                                'policy_tenure' => '',
                                'proposal_id' => '',
                                'quote_id' => $enquiryId,
                                'quote_link' => $journey_stage->quote_url,
                                'owner_name' => '',
                            ]
                        ]);

                        $this->saveLsqApiData($response_data, $lsq_api_request_response->id);

                        return response()->json($response_data);
                    }

                    $resultArr = [];

                    foreach ($newarray as $key => $value)
                    {
                        foreach ($value as $key_a => $val)
                        {
                            $resultArr[$key_a] = $val;
                        }
                    }

                    $min = min($resultArr);
                    $index = array_search($min, $resultArr);
                    $quote = $result[$index];
                    $quote = $quote['data'];
                }
            }

            $ic_alias = MasterCompany::where('company_id', $quote['insuraneCompanyId'])
                ->select('company_alias')
                ->first();

            $data = [
                "enquiryId" => $enquiryId,
                "icId" => $quote['insuraneCompanyId'],
                "icAlias" => $quote['companyName'],
                "productSubTypeId" => $quote['masterPolicyId']['productSubTypeId'],
                "masterPolicyId" => $quote['masterPolicyId']['policyId'],
                "premiumJson" => array_merge($quote, ["companyId" => $quote['insuraneCompanyId'], 'company_alias' => $ic_alias->company_alias]),
                "exShowroomPriceIdv" => $quote['showroomPrice'] ?? null,
                "exShowroomPrice" => $quote['showroomPrice'] ?? null,
                "finalPremiumAmount" => $quote['finalPayableAmount'] ?? null,
                "odPremium" => $quote['finalOdPremium'] ?? null,
                "tpPremium" => $quote['finalTpPremium'] ?? null,
                "addonPremiumTotal" => $quote['addOnPremiumTotal'] ?? null,
                "serviceTax" => $quote['serviceTaxAmount'] ?? null,
                "revisedNcb" => $quote['deductionOfNcb'] ?? null,
                "applicableAddons" => $quote['applicableAddons'] ?? null,
                "productSubTypeId" => $product_sub_type_id,
                "prevInsName" => (isset($response['insurance_data']['company']) ? $response['insurance_data']['company'] : "")
            ];

            $updateQuoteRequestData = [
                "enquiryId" => $enquiryId,
                "idvChangedType" => "lowIdv",
                "vehicleElectricAccessories" => 0,
                "vehicleNonElectricAccessories" => 0,
                "externalBiFuelKit" => 0,
                "OwnerDriverPaCover" => "N",
                "antiTheft" => "N",
                "UnnamedPassengerPaCover" => null,
                "voluntarydeductableAmount" => 0,
                "isClaim" => "N",
                "previousNcb" => "",
                "applicableNcb" => "",
                "previousInsurerCode" => "",
                "manufactureYear" => Carbon::parse($response['vehicle_data']['manufactured_date'])->format('m-Y'),
                "policyExpiryDate" => Carbon::parse($response['insurance_data']['expiry_date'])->format('d-m-Y'),
                "vehicleRegisterDate" => Carbon::parse($response['issue_date'])->format('d-m-Y'),
                "previousPolicyType" => "Comprehensive",
                "ownershipChanged" => "N",
                "isIdvChanged" => "N",
                "businessType" => $vehicle_details['data']['additional_details']['businessType'],
                "policyType" => "comprehensive",
                "vehicleOwnerType" => "I",
                "version" => $vehicle_details['data']['results'][0]['vehicle']['vehicle_cd'],
                "versionName" => $vehicle_details['data']['results'][0]['vehicle']['fla_variant'],
                "fuelType" => $vehicle_details['data']['additional_details']['fuelType'],
                "gcvCarrierType" => null,
                "isPopupShown" => "Y"
            ];

            $update_quote_request_data = Http::post(url('api/updateQuoteRequestData'), $updateQuoteRequestData)->json();
            $save_quote_data = Http::post(url('/api/saveQuoteData'), $data)->json();
            $return_data2['owner'] = $vehicle_details['data']['additional_details'];

            $proposal_additional_details = [
                'owner' => [
                    'lastName' => null, 
                    'firstName' => $vehicle_details['data']['additional_details']['firstName'], 
                    'fullName' => $vehicle_details['data']['additional_details']['fullName'], 
                    'gender' => null, 
                    'mobileNumber' => $request->mobile_no, 
                    'email' => null, 
                    'stateId' => null, 
                    'cityId' => null, 
                    'state' => null, 
                    "city" => null, 
                    'addressLine1' => $vehicle_details['data']['additional_details']['address_line1'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'], 
                    'pincode' => $vehicle_details['data']['additional_details']['pincode'], 
                    'officeEmail' => null, 
                    'genderName' => 'Male', 
                    'prevOwnerType' => 'I', 
                    'address' => $vehicle_details['data']['additional_details']['address_line1'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'] 
                ], 
                'nominee' => [
                    'compulsoryPersonalAccident' => 'NO', 
                    'cpa' => 'NO' 
                ], 
                'vehicle' => [
                    'regNo1' => $vehicle_details['data']['additional_details']['rto'], 
                    'regNo2' => explode('-', $vehicle_details['data']['additional_details']['vehicleRegistrationNo'])[1], 
                    'regNo3' => explode('-', $vehicle_details['data']['additional_details']['vehicleRegistrationNo'])[2], 
                    'vehicaleRegistrationNumber' => $vehicle_details['data']['additional_details']['vehicleRegistrationNo'], 
                    'engineNumber' => removeSpecialCharactersFromString($response['vehicle_data']['engine_number']), 
                    'chassisNumber' => removeSpecialCharactersFromString($response['vehicle_data']['chassis_number']), 
                    'isValidPuc' => true, 
                    'isVehicleFinance' => false, 
                    'isCarRegistrationAddressSame' => true, 
                    'rtoLocation' => $vehicle_details['data']['additional_details']['rto'], 
                    'registrationDate' => $vehicle_details['data']['additional_details']['vehicleRegisterDate'], 
                    'vehicleManfYear' => $vehicle_details['data']['additional_details']['manufacture_year'],
                ], 
                'prepolicy' => [
                    'previousInsuranceCompany' => 'RELIANCE', 
                    'InsuranceCompanyName' => $response['insurance_data']['company'], 
                    'previousPolicyExpiryDate' => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])), 
                    'previousPolicyNumber' => isset($response['insurance_data']['policy_number']) ? $response['insurance_data']['policy_number'] : null,
                    'isClaim' => 'N', 
                    'claim' => 'NO', 
                    'previousNcb' => $vehicle_details['data']['additional_details']['previousNcb'], 
                    'applicableNcb' => $vehicle_details['data']['additional_details']['applicableNcb'], 
                    'prevPolicyExpiryDate' => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])) 
                ] 
            ];

            $proposal_fields = ProposalFields::where('company_alias', $ic_alias->company_alias)->where('fields', 'LIKE', '%email%')->first();

            if ($proposal_fields)
            {
                $proposal_additional_details = [];
            }

            if (isset($vehicle_details['data']['additional_details']['pincode']) && ! empty($vehicle_details['data']['additional_details']['pincode']))
            {
                $address_details = httpRequestNormal(url('/api/getPincode?pincode=' . $vehicle_details['data']['additional_details']['pincode'] . '&companyAlias=' . $ic_alias->company_alias . '&enquiryId=' . customEncrypt($user_product_journey->user_product_journey_id)), 'GET');
            }

            $previous_insurance_company = NULL;

            if (isset($response['insurance_data']['company']) && ! empty($response['insurance_data']['company']))
            {
                $previous_insurance_company = $response['insurance_data']['company'];

                $previous_insurer_details = httpRequestNormal(url('/api/getPreviousInsurerList'), 'POST', [
                    'companyAlias' => $ic_alias->company_alias,
                    'enquiryId' => customEncrypt($user_product_journey->user_product_journey_id)
                ]);

                if ($previous_insurer_details && isset($previous_insurer_details['response']) && ! empty($previous_insurer_details['response']) && isset($previous_insurer_details['response']['status']) && $previous_insurer_details['response']['status'])
                {
                    foreach ($previous_insurer_details['response']['data'] as $previous_insurer)
                    {
                        if ($previous_insurer['name'] == $response['insurance_data']['company'])
                        {
                            $previous_insurance_company = $previous_insurer['code'];
                        }
                    }
                }
            }

            $user_proposal = UserProposal::updateOrCreate(
                ['user_product_journey_id' => $user_product_journey->user_product_journey_id],
                [
                    'first_name' => $name[0],
                    'last_name' => end($name),
                    'mobile_number' => $request->mobile_no,
                    'idv' => $quote['idv'],
                    'final_payable_amount' => $quote['finalPayableAmount'],
                    'service_tax_amount' => $quote['finalGstAmount'],
                    'od_premium' => $quote['finalOdPremium'],
                    'tp_premium' => $quote['finalTpPremium'],
                    'ncb_discount' => $quote['deductionOfNcb'],
                    'total_discount' => $quote['finalTotalDiscount'],
                    'ic_name' => $quote['companyName'],
                    'ic_id' => $quote['insuraneCompanyId'],
                    'applicable_ncb' => $vehicle_details['data']['additional_details']['applicableNcb'],
                    'is_claim' => 'N',
                    'address_line1' => $vehicle_details['data']['additional_details']['address_line1'],
                    'address_line2' => $vehicle_details['data']['additional_details']['address_line2'],
                    'address_line3' => $vehicle_details['data']['additional_details']['address_line3'],
                    'pincode' => $vehicle_details['data']['additional_details']['pincode'],
                    'state' => $address_details['response']['data']['state']['state_name'] ?? NULL,
                    'city' => $address_details['response']['data']['city'][0]['city_name'] ?? NULL,
                    'rto_location' => isBhSeries($rto_code) ? null : $rto_code,
                    'additional_details' => json_encode($proposal_additional_details),
                    'vehicale_registration_number' => $new_rc_number,
                    'vehicle_manf_year' => $vehicle_details['data']['additional_details']['manufacture_year'],
                    'previous_insurance_company' => $previous_insurance_company,
                    'prev_policy_expiry_date' => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])),
                    'engine_number' => removeSpecialCharactersFromString($response['vehicle_data']['engine_number']),
                    'chassis_number' => removeSpecialCharactersFromString($response['vehicle_data']['chassis_number']),
                    'previous_policy_number' => isset($response['insurance_data']['policy_number']) ? $response['insurance_data']['policy_number'] : '',
                    'vehicle_color' => removeSpecialCharactersFromString($response['vehicle_data']['color'] ?? NULL, true),
                    'is_vehicle_finance' => (isset($response['financed']) ? $response['financed'] : ""),
                    'name_of_financer' => (isset($response['financed']) ? ($response['financed'] == '1' ? (isset($response['financier']) ? $response['financier'] : $response['norms_type']) : '') : ""),
                ]
            );

            if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
            {
                $opportunity = createLsqOpportunity($user_product_journey->user_product_journey_id, 'RC Submitted', [
                    'rc_number' => $new_rc_number
                ]);

                if ($opportunity['status'])
                {
                    createLsqActivity($user_product_journey->user_product_journey_id, NULL, 'RC Submitted');
                }
            }

            $user_product_journey = UserProductJourney::find($user_product_journey->user_product_journey_id);
            $quote_log = $user_product_journey->quote_log;
            $corporate_vehicles_quote_request = $user_product_journey->corporate_vehicles_quote_request;
            $journey_stage = $user_product_journey->journey_stage;
            $addons = $user_product_journey->addons;
            $user_proposal = $user_product_journey->user_proposal;

            $cpa = 0;

            if ($addons)
            {
                if (isset($addons[count($addons) - 1]['compulsory_personal_accident'][0]['name']))
                {
                    $cpa = $quote_log->premium_json['compulsoryPaOwnDriver'];
                }
            }

            $od_premium = 0;
            $tp_premium = 0;
            $addon_premium = 0;

            $electrical_accessories = isset($quote_log->premium_json['motorElectricAccessoriesValue']) && ! is_null($quote_log->premium_json['motorElectricAccessoriesValue']) ? (int) $quote_log->premium_json['motorElectricAccessoriesValue'] : 0;
            $non_electrical_accessories = isset($quote_log->premium_json['motorNonElectricAccessoriesValue']) && ! is_null($quote_log->premium_json['motorNonElectricAccessoriesValue']) ? (int) $quote_log->premium_json['motorNonElectricAccessoriesValue'] : 0;
            $lpg_cng_kit_od = isset($quote_log->premium_json['motorLpgCngKitValue']) && ! is_null($quote_log->premium_json['motorLpgCngKitValue']) ? (int) $quote_log->premium_json['motorLpgCngKitValue'] : 0;
            $ncb = isset($quote_log->revised_ncb) && ! is_null($quote_log->revised_ncb) ? (int) $quote_log->revised_ncb : 0;
            $anit_theft = isset($quote_log->premium_json['antitheftDiscount']) && ! is_null($quote_log->premium_json['antitheftDiscount']) ? (int) $quote_log->premium_json['antitheftDiscount'] : 0;
            $voluntary_excess = isset($quote_log->premium_json['voluntaryExcess']) && ! is_null($quote_log->premium_json['voluntaryExcess']) ? (int) $quote_log->premium_json['voluntaryExcess'] : 0;
            $tppd_discount = isset($quote_log->premium_json['tppdDiscount']) && ! is_null($quote_log->premium_json['tppdDiscount']) ? (int) $quote_log->premium_json['tppdDiscount'] : 0;
            $ic_vehicle_discount = isset($quote_log->premium_json['icVehicleDiscount']) && ! is_null($quote_log->premium_json['icVehicleDiscount']) ? (int) $quote_log->premium_json['icVehicleDiscount'] : 0;

            if ($quote_log->od_premium != 0 && ! is_null($quote_log->od_premium))
            {
                $od_premium = $quote_log->od_premium - $electrical_accessories - $non_electrical_accessories - $lpg_cng_kit_od - $ncb - $anit_theft - $voluntary_excess - $ic_vehicle_discount;
            }

            if ($quote_log->tp_premium != 0 && ! is_null($quote_log->tp_premium))
            {
                $tp_premium = $quote_log->tp_premium - $tppd_discount;
            }

            if ($quote_log->addon_premium != 0 && ! is_null($quote_log->addon_premium))
            {
                $addon_premium = $quote_log->addon_premium + $electrical_accessories + $non_electrical_accessories + $lpg_cng_kit_od;
            }

            $policy_type = '';
            $policy_tenure = '';

            if (isset($quote_log->master_policy->premium_type) && ! is_null($quote_log->master_policy->premium_type)) 
            {
                switch ($quote_log->master_policy->premium_type->premium_type_code)
                {
                    case 'third_party':
                    case 'third_party_breakin':
                        $policy_type = 'TP Only';
                        break;

                    case 'own_damage':
                    case 'own_damage_breakin':
                        $policy_type = 'OD Only';
                        break;

                    default:
                        $policy_type = 'Comprehensive';
                }

                switch ($quote_log->master_policy->premium_type->premium_type_code)
                {
                    case 'short_term_3':
                    case 'short_term_3_breakin':
                        $policy_tenure = '3 months';
                        break;

                    case 'short_term_6':
                    case 'short_term_6_breakin':
                        $policy_tenure = '6 months';
                        break;

                    default:
                        $policy_tenure = '12 months';
                        break;
                }
            }

            $response_data = camelCase([
                'status' => true,
                'message' => '',
                'data' => [
                    'previous_ncb' => $corporate_vehicles_quote_request->previous_ncb,
                    'current_ncb' => $corporate_vehicles_quote_request->applicable_ncb,
                    'claim_status' => $corporate_vehicles_quote_request->is_claim,
                    'ic_name' => $quote_log->premium_json['companyName'],
                    'od_premium' => $od_premium,
                    'tp_premium' => $tp_premium,
                    'cpa_premium' => $cpa,
                    'addon_premium' => $addon_premium,
                    'total_premium' => $quote_log->final_premium_amount,
                    'policy_start_date' => '',
                    'enquiry_id' => $enquiryId,
                    'enquiry_link' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId . '&dropout=true',
                    'policy_type' => $policy_type,
                    'policy_tenure' => $policy_tenure,
                    'proposal_id' => '',
                    'quote_id' => $enquiryId,
                    'quote_link' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId . '&dropout=true',
                    'owner_name' => $user_proposal->first_name . ' ' . $user_proposal->last_name
                ]
            ]);

            $this->saveLsqApiData($response_data, $lsq_api_request_response->id);

            return response()->json($response_data);
        } catch (Exception $e) {
            info('LSQ API: ' . $e->getMessage() . 'File : ' . $e->getFile() . 'Line No. : ' . $e->getLine());

            $response_data = camelCase([
                'status' => true,
                'message' => 'Something went wrong..!' . $e->getMessage() . 'Line No. : '  . $e->getLine() . $e->getFile(),
                'data' => []
            ]);

            $lsq_api_request_response->response = json_encode($response_data, JSON_UNESCAPED_SLASHES);
            $lsq_api_request_response->save();

            return response()->json($response_data);
        }
    }

    public function saveLsqApiData($data, $id)
    {
        return LsqApiRequestResponse::where('id', $id)
            ->update([
                'response' => json_encode($data, JSON_UNESCAPED_SLASHES),
                'previous_ncb' => $data['data']['previousNcb'],
                'current_ncb' => $data['data']['currentNcb'],
                'claim_status'=> ! empty($data['data']['claimStatus']) ? $data['data']['claimStatus'] : 'N',
                'ic_name'=> ! empty($data['data']['icName']) ? $data['data']['icName'] : NULL,
                'od_premium'=> $data['data']['odPremium'],
                'tp_premium' => $data['data']['tpPremium'],
                'cpa_premium' => $data['data']['cpaPremium'],
                'addon_premium' => $data['data']['addonPremium'],
                'total_premium' => $data['data']['totalPremium'],
                'policy_start_date' => ! empty($data['data']['policyStartDate']) ? $data['data']['policyStartDate'] : NULL,
                'enquiry_id' => $data['data']['enquiryId'],
                'enquiry_link' => $data['data']['enquiryLink'],
                'policy_type' => ! empty($data['data']['policyType']) ? $data['data']['policyType'] : NULL,
                'policy_tenure' => ! empty($data['data']['policyTenure']) ? $data['data']['policyTenure'] : NULL,
                'proposal_id' => ! empty($data['data']['proposalId']) ? $data['data']['proposalId'] : NULL,
                'quote_id' => $data['data']['quoteId'],
                'quote_link' => $data['data']['quoteLink'],
                'owner_name' => ! empty($data['data']['ownerName']) ? $data['data']['ownerName'] : NULL
            ]);
    }

    public function getScrubData(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        DB::disableQueryLog();

        $common_controller = new CommonController;

        $request->registrationNumber = $request->vehicleRegNo = $request->rc_number;
        $request->phoneNumber = $request->mobile_no;

        try
        {
            $response = \Illuminate\Support\Facades\DB::table('registration_details')->where('vehicle_reg_no', $request->registrationNumber)->where('vehicle_details', 'LIKE', '%Extracted details.%')->where('expiry_date', '>=', now()->format('Y-m-d'))->latest()->first();

            if ($response == NULL)
            {
                $service = 'online';
                $startTime = new DateTime(date('Y-m-d H:i:s'));

                $response = httpRequest('ongrid', ['rc_number' => $request->rc_number], [], [], [], false);
                $request_url = $response['url'];

                $response = $response['response'];

                $endTime = new DateTime(date('Y-m-d H:i:s'));

                if (isset($response['data']['rc_data']['vehicle_data']['custom_data']['version_id']) && ! empty($response['data']['rc_data']['vehicle_data']['custom_data']['version_id']))
                {
                    DB::table('registration_details')->insert([
                        'vehicle_reg_no' => $request->registrationNumber,
                        'vehicle_details' => json_encode($response),
                        'created_at' => now(),
                        'updated_at' => now(),
                        'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? NULL
                    ]);
                }
            }
            else
            {
                $service = 'offline';

                $response = json_decode($response->vehicle_details, true);
            }

            $rc_number = str_split($request->rc_number);

            if ($rc_number[0] . $rc_number[1] == 'DL')
            {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2];
                $rto_code = $new_rc_number;
                $str = substr($request->rc_number, 3);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            }
            else
            {
                $new_rc_number = $rc_number[0] . $rc_number[1] . '-' . $rc_number[2] . $rc_number[3];
                $rto_code = $new_rc_number;
                $str = substr($request->rc_number, 4);
                $str2 = substr($str, 0, -4);
                $str3 = substr($str, -4);
                $new_rc_number .= '-' . $str2 . '-' . $str3;
            }

            if (empty($response) || (isset($response['data']) && $response['data']['code'] != 1000) || isset($response['error']) || isset($response['message']) || ! isset($response['data']['rc_data']['vehicle_data']['custom_data']))
            {
                $quote_array = [];

                $enquiryId = $this->createUserProductJourneyId(new Request([
                    'vehicleRegNo' => $request->rc_number
                ]), 'ZW1iZWRkZWRfc2NydWI=');

                /* if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                {
                    $opportunity = createLsqOpportunity(customDecrypt($enquiryId), 'RC Submitted', [
                        'rc_number' => $new_rc_number
                    ]);

                    if ($opportunity['status'])
                    {
                        createLsqActivity(customDecrypt($enquiryId), NULL, 'RC Submitted');
                    }
                } */

                if ($service == 'online')
                {
                    $responseTime = $startTime->diff($endTime);

                    FastlaneRequestResponse::create([
                        'enquiry_id' => customDecrypt($enquiryId),
                        'request' => $request->rc_number,
                        'response' => json_encode($response),
                        'transaction_type' => 'Ongrid Service',
                        'endpoint_url' => $request_url,
                        'ip_address' => $request->ip(),
                        'section' => 'CV',
                        'response_time' => $responseTime->format('%H:%i:%s'),
                        'created_at' => now(),
                    ]);
                }

                updateJourneyStage([
                    'user_product_journey_id' => customDecrypt($enquiryId),
                    'stage' => STAGE_NAMES['QUOTE'],
                    'proposal_url' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . $enquiryId,
                    'quote_url' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                        'enquiry_id' => $enquiryId
                    ])),
                ]);

                $user_product_journey = UserProductJourney::find(customDecrypt($enquiryId));

                $error_message = '';

                if (empty($response) || (isset($response['data']) && $response['data']['code'] != 1000))
                {
                    $error_message = $response['data']['message'];
                }
                elseif (isset($response['error']) || isset($response['message']))
                {
                    $error_message = (isset($response['error']) ? $response['error']['message'] : $response['message']);
                }
                elseif (!isset($response['data']['rc_data']['vehicle_data']['custom_data']))
                {
                    $error_message = 'Vehicle data not found';
                }

                $vahan_response = [
                    'rc_number' => $request->rc_number,
                    'expiry_date' => NULL,
                    'make' => NULL,
                    'model' => NULL,
                    'varient' => NULL,
                    'vehicle_owner_name' => NULL,
                    'ongrid_success' => 'Failure'
                ];

                $response_data = [
                    'status' => TRUE,
                    'message' => $error_message,
                    'data' => [
                        'vahan_response' => $vahan_response,
                        '3_months_policy' => NULL,
                        '6_months_policy' => NULL,
                        '12_months_policy' => NULL,
                        'ongrid_failure_data' => [
                            'enquiry_id' => $enquiryId,
                            'proposal_link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                                'enquiry_id' => $enquiryId
                            ]))
                        ],
                        'batch_id' => $request->batch_id
                    ],
                    'upload_excel_id' => $request->upload_excel_id
                ];

                return response()->json($response_data);
            }

            if ( ! isset($response['data']['rc_data']['insurance_data']))
            {
                $enquiryId = $this->createUserProductJourneyId(new Request([
                    'vehicleRegNo' => $request->rc_number
                ]), 'ZW1iZWRkZWRfc2NydWI=');

                return response()->json([
                    'status' => TRUE,
                    'message' => 'Insurance data not recieved from Ongrid',
                    'data' => [
                        'vahan_response' => [
                            'rc_number' => $request->rc_number,
                            'expiry_date' => NULL,
                            'make' => NULL,
                            'model' => NULL,
                            'varient' => NULL,
                            'vehicle_owner_name' => NULL,
                            'ongrid_success' => 'Failure'
                        ],
                        '3_months_policy' => NULL,
                        '6_months_policy' => NULL,
                        '12_months_policy' => NULL,
                        'ongrid_failure_data' => [
                            'enquiry_id' => $enquiryId,
                            'proposal_link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                                'enquiry_id' => $enquiryId
                            ]))
                        ],
                        'batch_id' => $request->batch_id
                    ],
                    'upload_excel_id' => $request->upload_excel_id
                ]);
            }

            $vahan_response = [
                'rc_number' => $request->rc_number,
                'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? '',
                'make' => $response['data']['rc_data']['vehicle_data']['custom_data']['manf_name'],
                'model' => $response['data']['rc_data']['vehicle_data']['custom_data']['model_name'],
                'varient' => $response['data']['rc_data']['vehicle_data']['custom_data']['version_name'],
                'vehicle_owner_name' => $response['data']['rc_data']['owner_data']['name'],
                'ongrid_success' => 'Success'
            ];

            $response = $response['data']['rc_data'];

            switch (\Illuminate\Support\Str::substr($response['vehicle_data']['custom_data']['version_id'], 0, 3)) {
                case 'PCV':
                    $type = 'pcv';
                    break;
                case 'GCV':
                    $type = 'gcv';
                    break;
                case 'CRP':
                    $type = 'motor';
                    break;
                case 'BYK':
                    $type = 'bike';
                    break;
            }

            if (isset($response['vehicle_data']['manufactured_date']) && ! empty($response['vehicle_data']['manufactured_date'])) {
                $manufacture_year = explode('-', $response['vehicle_data']['manufactured_date']);
                // $manufacture_year = $manufacture_year[1] . '-' . $manufacture_year[0];
            }

            if ( empty($response['insurance_data']['expiry_date']))
            {
                $enquiryId = $this->createUserProductJourneyId(new Request([
                    'vehicleRegNo' => $request->rc_number
                ]), 'ZW1iZWRkZWRfc2NydWI=');

                return response()->json([
                    'status' => TRUE,
                    'message' => 'Expiry date not available',
                    'data' => [
                        'vahan_response' => $vahan_response,
                        '3_months_policy' => NULL,
                        '6_months_policy' => NULL,
                        '12_months_policy' => NULL,
                        'ongrid_failure_data' => [
                            'enquiry_id' => $enquiryId,
                            'proposal_link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                                'enquiry_id' => $enquiryId
                            ]))
                        ],
                        'batch_id' => $request->batch_id
                    ],
                    'upload_excel_id' => $request->upload_excel_id
                ]);
            }

            if ( ! isset($manufacture_year[1]))
            {
                $enquiryId = $this->createUserProductJourneyId(new Request([
                    'vehicleRegNo' => $request->rc_number
                ]), 'ZW1iZWRkZWRfc2NydWI=');

                return response()->json([
                    'status' => TRUE,
                    'message' => isset($response['vehicle_data']['manufactured_date']) && ! empty($response['vehicle_data']['manufactured_date']) ? 'Incorrect Manufacture Date: ' . $response['vehicle_data']['manufactured_date'] : 'Manufacture date not available',
                    'data' => [
                        'vahan_response' => $vahan_response,
                        '3_months_policy' => NULL,
                        '6_months_policy' => NULL,
                        '12_months_policy' => NULL,
                        'ongrid_failure_data' => [
                            'enquiry_id' => $enquiryId,
                            'proposal_link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                                'enquiry_id' => $enquiryId
                            ]))
                        ],
                        'batch_id' => $request->batch_id
                    ],
                    'upload_excel_id' => $request->upload_excel_id
                ]);
            }

            $producttype = [
                '1' => 'motor',
                '2' => 'bike',
                '6' => 'pcv',
                '9' => 'gcv',
                '13' => 'gcv',
                '14' => 'gcv',
                '15' => 'gcv',
                '16' => 'gcv',
            ];

            $product_sub_type_id = array_search($type, $producttype);

            $name = explode(' ', $response['owner_data']['name']);

            $quote_array = [];
            $st_3_months_policy = $st_6_months_policy = $annual_policy = [];
            $plan_types = ['short_term_3', 'short_term_6', 'annual'];

            $vehicle_details_status = TRUE;

            $rc_num = $request->rc_number;
            $batch_id = $request->batch_id;
            $upload_excel_id = $request->upload_excel_id;

            for ($i = 0; $i <= count($plan_types) - 1; $i++)
            {
                $user_product_journey = [
                    'user_fname' => $name[0],
                    'user_lname' => end($name),
                    'user_mobile' => $request->mobile_no ?? NULL,
                    'product_sub_type_id' => $product_sub_type_id,
                    'status' => 'yes',
                    'lead_stage_id' => 2
                ];

                $user_product_journey = UserProductJourney::create($user_product_journey);

                CvAgentMapping::updateOrCreate(
                    ['user_product_journey_id' => $user_product_journey->user_product_journey_id],
                    [
                        'seller_type' => $request->seller_type,
                        'agent_id' => $request->seller_id,
                        'agent_name' => $request->seller_name
                    ]
                );

                updateJourneyStage([
                    'user_product_journey_id' => $user_product_journey->user_product_journey_id,
                    'stage' => STAGE_NAMES['QUOTE']
                ]);

                $enquiryId = $user_product_journey->journey_id;

                if ($service == 'online')
                {
                    $responseTime = $startTime->diff($endTime);

                    FastlaneRequestResponse::create([
                        'enquiry_id' => $user_product_journey->user_product_journey_id,
                        'request' => $rc_num,
                        'response' => json_encode($response),
                        'transaction_type' => 'Ongrid Service',
                        'endpoint_url' => $request_url,
                        'ip_address' => $request->ip(),
                        'section' => 'CV',
                        'response_time' => $responseTime->format('%H:%i:%s'),
                        'created_at' => now(),
                    ]);
                }

                if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                {
                    $lead = createLsqLead($user_product_journey->user_product_journey_id);

                    if ($lead['status'])
                    {
                        createLsqActivity($user_product_journey->user_product_journey_id, 'lead');
                    }
                }

                $SelectedAddons = [
                    'user_product_journey_id' => $user_product_journey->user_product_journey_id,
                    'compulsory_personal_accident' => [['reason' => 'I have another motor policy with PA owner driver cover in my name']]
                ];

                SelectedAddons::create($SelectedAddons);

                $vehicle_details = $this->getVehicleDetails($user_product_journey->user_product_journey_id, $new_rc_number, $type, $response, 'embedded_scrub');

                if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                {
                    $opportunity = createLsqOpportunity($user_product_journey->user_product_journey_id, 'RC Submitted', ['rc_number' => $new_rc_number]);

                    if ($opportunity['status'])
                    {
                        createLsqActivity($user_product_journey->user_product_journey_id, NULL, 'RC Submitted');
                    }
                }

                if ( ! $vehicle_details['status'])
                {
                    $user_product_journey = UserProductJourney::find($user_product_journey->user_product_journey_id);

                    $quote_array = [
                        'ic_name' => '',
                        'net_premium' => '',
                        'policy_type' => '',
                        'enquiry_id' => customEncrypt($user_product_journey->user_product_journey_id),
                        'proposal_link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                            'enquiry_id' => customEncrypt($user_product_journey->user_product_journey_id)])),
                        'quote_pdf' => '',
                        'cpa_premium' => ''
                    ];

                    $st_3_months_policy = $quote_array;
                    $st_6_months_policy = $quote_array;
                    $annual_policy = $quote_array;

                    $vehicle_details_status = FALSE;

                    break;
                }

                $data = [
                    "enquiryId" => customEncrypt($user_product_journey->user_product_journey_id),
                    "productSubTypeId" => $vehicle_details['data']['additional_details']['product_sub_type_id'],
                    "prevInsName" => isset($response['insurance_data']['company']) ? $response['insurance_data']['company'] : ""
                ];

                $updateQuoteRequestData = [
                    "enquiryId" => customEncrypt($user_product_journey->user_product_journey_id),
                    "idvChangedType" => "lowIdv",
                    "vehicleElectricAccessories" => 0,
                    "vehicleNonElectricAccessories" => 0,
                    "externalBiFuelKit" => 0,
                    "OwnerDriverPaCover" => "N",
                    "antiTheft" => "N",
                    "UnnamedPassengerPaCover" => NULL,
                    "voluntarydeductableAmount" => 0,
                    "isClaim" => "N",
                    "previousNcb" => NULL,
                    "applicableNcb" => NULL,
                    "previousInsurerCode" => NULL,
                    "manufactureYear" => Carbon::parse($response['vehicle_data']['manufactured_date'])->format('m-Y'),
                    "policyExpiryDate" => Carbon::parse($response['insurance_data']['expiry_date'])->format('d-m-Y'),
                    "vehicleRegisterDate" => Carbon::parse($response['issue_date'])->format('d-m-Y'),
                    "previousPolicyType" => "Comprehensive",
                    "ownershipChanged" => "N",
                    "isIdvChanged" => "N",
                    "businessType" => $vehicle_details['data']['additional_details']['businessType'],
                    "policyType" => "comprehensive",
                    "vehicleOwnerType" => "I",
                    "version" => $vehicle_details['data']['results'][0]['vehicle']['vehicle_cd'],
                    "versionName" => $vehicle_details['data']['results'][0]['vehicle']['fla_variant'],
                    "fuelType" => $vehicle_details['data']['additional_details']['fuelType'],
                    "gcvCarrierType" => NULL,
                    "isPopupShown" => "Y"
                ];

                if (config('constants.motor.USE_CONTROLLER_FOR_API_CALL') == 'Y')
                {
                    $update_quote_request_data = $common_controller->updateQuoteRequestData(request()->replace($updateQuoteRequestData));
                    $update_quote_request_data = json_decode($update_quote_request_data->content(), TRUE);
                }
                else
                {
                    $update_quote_request_data = Http::post(url('api/updateQuoteRequestData'), $updateQuoteRequestData)->json();
                }

                if (config('constants.motor.USE_CONTROLLER_FOR_API_CALL') == 'Y')
                {
                    $save_quote_data = $common_controller->saveQuoteData(request()->replace($data));
                    $save_quote_data = json_decode($save_quote_data->content(), TRUE);
                }
                else
                {
                    $save_quote_data = Http::post(url('api/saveQuoteData'), $data)->json();
                }

                if ($plan_types[$i] == 'short_term_3')
                {
                    $master_policies = \App\Models\MasterPolicy::where([
                        ['premium_type_id', '=', 5],
                        ['zero_dep', '!=', '0'],
                        ['master_policy.status', '=', 'Active']
                        ])->join('master_company as mc', 'mc.company_id', '=', 'insurance_company_id')->select('policy_id', 'mc.company_alias')->get();

                    $data_3_months = [];

                    foreach ($master_policies as $value)
                    {
                        $data_3_months[] = [
                            'url' => url('api/premiumCalculation/' . $value->company_alias),
                            'data' => [
                                "enquiryId" => $enquiryId,
                                "policyId" => $value->policy_id,
                            ]
                        ];
                    }

                    if ($quote_data = $this->getQuotes($data_3_months, config('constants.enhance_journey_short_term_3_months')))
                    {
                        $quote_array[] = $quote_data['data'];
                    }
                    else
                    {
                        $st_3_months_policy = [
                            'ic_name' => '',
                            'net_premium' => '',
                            'policy_type' => '',
                            'enquiry_id' => $enquiryId,
                            'proposal_link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                                'enquiry_id' => $enquiryId])),
                            'quote_pdf' => '',
                            'cpa_premium' => ''
                        ];
                    }
                }
                elseif ($plan_types[$i] == 'short_term_6')
                {
                    $master_policies = \App\Models\MasterPolicy::where([
                        ['premium_type_id', '=', 8],
                        ['zero_dep', '!=', '0'],
                        ['master_policy.status', '=', 'Active']
                        ])->join('master_company as mc', 'mc.company_id', '=', 'insurance_company_id')->select('policy_id', 'mc.company_alias')->get();

                    $data_6_months = [];

                    foreach ($master_policies as $key => $value)
                    {
                        $data_6_months[] = [
                            'url' => url('api/premiumCalculation/' . $value->company_alias),
                            'data' => [
                                "enquiryId" => $enquiryId,
                                "policyId" => $value->policy_id,
                            ]
                        ];
                    }
            
                    if ($quote_data = $this->getQuotes($data_6_months, config('constants.enhance_journey_short_term_6_months')))
                    {
                        $quote_array[] = $quote_data['data'];
                    }
                    else
                    {
                        $st_6_months_policy = [
                            'ic_name' => '',
                            'net_premium' => '',
                            'policy_type' => '',
                            'enquiry_id' => $enquiryId,
                            'proposal_link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                                'enquiry_id' => $enquiryId])),
                            'quote_pdf' => '',
                            'cpa_premium' => ''
                        ];
                    }
                }
                else
                {
                    $comprehensive_quote = $this->getLowestPremiumForScrub([
                        'product_sub_type_id' => $product_sub_type_id,
                        'policy_type' => 'comprehensive',
                        'enquiryId' => $enquiryId
                    ]);
        
                    if ( ! empty($comprehensive_quote))
                    {
                        $quote_array[] = $comprehensive_quote;
                    }
                    else // if Short Term 3 Months, Short Term 6 Months and 12 months quotes are not available, then fetch TP quote
                    {
                        $third_party_quote = $this->getLowestPremiumForScrub([
                            'product_sub_type_id' => $product_sub_type_id,
                            'policy_type' => 'third_party',
                            'enquiryId' => $enquiryId
                        ]);
        
                        if ( ! empty($third_party_quote))
                        {
                            $quote_array[] = $third_party_quote;
                        }
                        else
                        {
                            $annual_policy = [
                                'ic_name' => '',
                                'net_premium' => '',
                                'policy_type' => '',
                                'enquiry_id' => $enquiryId,
                                'proposal_link' => urldecode(config('constants.frontend_url') . 'cv/vehicle-type?' . http_build_query([
                                    'enquiry_id' => $enquiryId])),
                                'quote_pdf' => '',
                                'cpa_premium' => ''
                            ];
                        }
                    }
                }
            }

            if ( ! empty($quote_array) && $vehicle_details_status)
            {
                foreach ($quote_array as $quote)
                {
                    $ic_alias = MasterCompany::where('company_id', $quote['insuraneCompanyId'])
                        ->select('company_alias')
                        ->first();

                    $data = [
                        "enquiryId" =>customEncrypt($quote['userProductJourneyId']),
                        "icId" => $quote['insuraneCompanyId'],
                        "icAlias" => $quote['companyName'],
                        "masterPolicyId" => $quote['masterPolicyId']['policyId'],
                        "premiumJson" => array_merge($quote, ["companyId" => $quote['insuraneCompanyId'], 'company_alias' => $ic_alias->company_alias]),
                        "exShowroomPriceIdv" => $quote['showroomPrice'] ?? null,
                        "exShowroomPrice" => $quote['showroomPrice'] ?? null,
                        "finalPremiumAmount" => $quote['finalPayableAmount'] ?? null,
                        "odPremium" => $quote['finalOdPremium'] ?? null,
                        "tpPremium" => $quote['finalTpPremium'] ?? null,
                        "addonPremiumTotal" => $quote['addOnPremiumTotal'] ?? null,
                        "serviceTax" => $quote['serviceTaxAmount'] ?? null,
                        "revisedNcb" => $quote['deductionOfNcb'] ?? null,
                        // "applicableAddons" => $quote['applicableAddons'] ?? null,
                        "productSubTypeId" => $quote['productSubTypeId'],
                    ];

                    if (config('constants.motor.USE_CONTROLLER_FOR_API_CALL') == 'Y')
                    {
                        $save_quote_data = $common_controller->saveQuoteData(request()->replace($data));
                        $save_quote_data = json_decode($save_quote_data->content(), TRUE);
                    }
                    else
                    {
                        $save_quote_data = Http::post(url('api/saveQuoteData'), $data)->json();
                    }

                    $return_data2['owner'] = $vehicle_details['data']['additional_details'];

                    if (isset($vehicle_details['data']['additional_details']['pincode']) && ! empty($vehicle_details['data']['additional_details']['pincode']))
                    {
                        $address_request_data = [
                            'pincode' => $vehicle_details['data']['additional_details']['pincode'],
                            'companyAlias' => $ic_alias->company_alias,
                            'enquiryId' => customEncrypt($user_product_journey->user_product_journey_id)
                        ];

                        $address_details = $common_controller->getIcPincode(request()->replace($address_request_data));
                        $address_details = json_decode($address_details->content(), TRUE);
                    }

                    $previous_insurance_company = NULL;
                    $previous_insurance_company_name = NULL;

                    if (isset($response['insurance_data']['company']) && ! empty($response['insurance_data']['company']))
                    {
                        $previous_insurance_company = $response['insurance_data']['company'];
                        $previous_insurance_company_name = $response['insurance_data']['company'];

                        $previous_insurer_request_data = [
                            'companyAlias' => $ic_alias->company_alias,
                            'enquiryId' => customEncrypt($user_product_journey->user_product_journey_id)
                        ];

                        $previous_insurer_details = $common_controller->getPreviousInsurerList(request()->replace($previous_insurer_request_data));
                        $previous_insurer_details = json_decode($previous_insurer_details->content(), TRUE);

                        if ($previous_insurer_details && isset($previous_insurer_details['status']) && $previous_insurer_details['status'])
                        {
                            // foreach ($previous_insurer_details['data'] as $previous_insurer)
                            // {
                            //     if ($previous_insurer['name'] == $response['insurance_data']['company'])
                            //     {
                            //         $previous_insurance_company = $previous_insurer['code'];
                            //     }
                            // }

                            $previous_insurer_data = ongridPreviousInsurerMapping($response['insurance_data']['company'], $previous_insurer_details['data']);

                            $previous_insurance_company = ! empty($previous_insurer_data) ? $previous_insurer_data['code'] : $previous_insurance_company;
                            $previous_insurance_company_name = ! empty($previous_insurer_data) ? $previous_insurer_data['name'] : $previous_insurance_company_name;
                        }
                    }

                    $reg_no_1 = $vehicle_details['data']['additional_details']['rto'];
                    $reg_nos = explode('-', $vehicle_details['data']['additional_details']['vehicleRegistrationNo']);
                    $reg_no_2 = isset($reg_nos[3]) ? $reg_nos[2] : '';
                    $reg_no_3 = isset($reg_nos[3]) ? $reg_nos[3] : $reg_nos[2];

                    $proposal_additional_details = [
                        'owner' => [
                            'lastName' => null, 
                            'firstName' => $vehicle_details['data']['additional_details']['firstName'], 
                            'fullName' => $vehicle_details['data']['additional_details']['fullName'], 
                            'gender' => null, 
                            'mobileNumber' => $request->phoneNumber, 
                            'email' => null, 
                            'stateId' => $address_details['data']['state']['state_id'] ?? NULL, 
                            'cityId' => $address_details['data']['city'][0]['city_id'] ?? NULL, 
                            'state' => $address_details['data']['state']['state_name'] ?? NULL, 
                            "city" => $address_details['data']['city'][0]['city_name'] ?? NULL, 
                            'addressLine1' => $vehicle_details['data']['additional_details']['address_line1'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'], 
                            'pincode' => $vehicle_details['data']['additional_details']['pincode'], 
                            'officeEmail' => null, 
                            'genderName' => 'Male', 
                            'prevOwnerType' => 'I', 
                            'address' => $vehicle_details['data']['additional_details']['address_line1'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'] . ' ' . $vehicle_details['data']['additional_details']['address_line2'] 
                        ], 
                        'nominee' => [
                            'compulsoryPersonalAccident' => 'NO', 
                            'cpa' => 'NO' 
                        ], 
                        'vehicle' => [
                            'regNo1' => $reg_no_1, 
                            'regNo2' => $reg_no_2, 
                            'regNo3' => $reg_no_3, 
                            'vehicaleRegistrationNumber' => $vehicle_details['data']['additional_details']['vehicleRegistrationNo'], 
                            'engineNumber' => removeSpecialCharactersFromString($response['vehicle_data']['engine_number']), 
                            'chassisNumber' => removeSpecialCharactersFromString($response['vehicle_data']['chassis_number']), 
                            'isValidPuc' => true, 
                            'isVehicleFinance' => false, 
                            'isCarRegistrationAddressSame' => true, 
                            'rtoLocation' => $vehicle_details['data']['additional_details']['rto'], 
                            'registrationDate' => $vehicle_details['data']['additional_details']['vehicleRegisterDate'], 
                            'vehicleManfYear' => $vehicle_details['data']['additional_details']['manufacture_year'],
                        ], 
                        'prepolicy' => [
                            'previousInsuranceCompany' => $previous_insurance_company, 
                            'InsuranceCompanyName' => $previous_insurance_company_name, 
                            'previousPolicyExpiryDate' => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])), 
                            'previousPolicyNumber' => isset($response['insurance_data']['policy_number']) ? $response['insurance_data']['policy_number'] : null,
                            'isClaim' => 'N', 
                            'claim' => 'NO', 
                            'previousNcb' => $vehicle_details['data']['additional_details']['previousNcb'], 
                            'applicableNcb' => $vehicle_details['data']['additional_details']['applicableNcb'], 
                            'prevPolicyExpiryDate' => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])) 
                        ] 
                    ];

                    $proposal_fields = ProposalFields::where('company_alias', $ic_alias->company_alias)->where('fields', 'LIKE', '%email%')->first();

                    if ($proposal_fields)
                    {
                        $proposal_additional_details = [];
                    }

                    if (isset($response['financed']) && $response['financed'] == '1')
                    {
                        unset($proposal_additional_details['vehicle']);
                        unset($proposal_additional_details['prepolicy']);
                    }

                    $user_proposal = UserProposal::updateOrCreate(
                        ['user_product_journey_id' => $quote['userProductJourneyId']],
                        [
                            'first_name' => $name[0],
                            'last_name' => end($name),
                            'mobile_number' => $request->phoneNumber, 
                            'idv' => $quote['idv'],
                            'ic_name' => $quote['companyName'],
                            'ic_id' => $quote['insuraneCompanyId'],
                            'applicable_ncb' => $vehicle_details['data']['additional_details']['applicableNcb'],
                            'is_claim' => 'N',
                            'address_line1' => $vehicle_details['data']['additional_details']['address_line1'],
                            'address_line2' => $vehicle_details['data']['additional_details']['address_line2'],
                            'address_line3' => $vehicle_details['data']['additional_details']['address_line3'],
                            'pincode' => $vehicle_details['data']['additional_details']['pincode'],
                            'state' => $address_details['data']['state']['state_name'] ?? NULL,
                            'city' => $address_details['data']['city'][0]['city_name'] ?? NULL,
                            'rto_location' => isBhSeries($rto_code) ? null : $rto_code,
                            'additional_details' => json_encode($proposal_additional_details),
                            'vehicale_registration_number' => $new_rc_number,
                            'vehicle_manf_year' => $vehicle_details['data']['additional_details']['manufacture_year'],
                            'previous_insurance_company' => $previous_insurance_company,
                            'prev_policy_expiry_date' => date('d-m-Y', strtotime($response['insurance_data']['expiry_date'])),
                            'engine_number' => removeSpecialCharactersFromString($response['vehicle_data']['engine_number']),
                            'chassis_number' => removeSpecialCharactersFromString($response['vehicle_data']['chassis_number']),
                            'previous_policy_number' => isset($response['insurance_data']['policy_number']) ? $response['insurance_data']['policy_number'] : '',
                            'vehicle_color' => removeSpecialCharactersFromString($response['vehicle_data']['color'] ?? NULL, true),
                            'is_vehicle_finance' => isset($response['financed']) ? $response['financed'] : "",
                            'name_of_financer' => isset($response['financed']) ? ($response['financed'] == '1' ? (isset($response['financier']) ? $response['financier'] : $response['norms_type']) : '') : "",
                        ]
                    );

                    $user_product_journey = UserProductJourney::find($quote['userProductJourneyId']);

                    $quote_response = [
                        'ic_name' => $quote['companyName'],
                        'net_premium' => $quote['finalNetPremium'],
                        'policy_type' => $quote['policyType'] == 'Third Party' ? 'Third Party' : 'Comprehensive',
                        'enquiry_id' => customEncrypt($quote['userProductJourneyId']),
                        'proposal_link' => config('constants.frontend_url') . "cv/proposal-page?enquiry_id=" . customEncrypt($quote['userProductJourneyId']) . '&dropout=true',
                        'quote_pdf' => route('cv.getQuotePdf', [customEncrypt($quote['userProductJourneyId'])]),
                        'cpa_premium' => $quote['compulsoryPaOwnDriver']
                    ];

                    $quote_pdf_data = [
                        'logo' => $quote['companyLogo'],
                        'name' => $quote['companyName'],
                        'productName' => $quote['productName'],
                        'idv' => $quote['idv'],
                        'premiumWithGst' => $quote['finalPayableAmount'],
                        'action' => config('constants.frontend_url') . 'cv/proposal-page?enquiry_id=' . customEncrypt($quote['userProductJourneyId']) . '&dropout=true'
                    ];

                    if (isset($quote['premiumTypeCode']) && $quote['premiumTypeCode'] == 'short_term_3')
                    {
                        $st_3_months_policy = $quote_response;
                    }
                    elseif (isset($quote['premiumTypeCode']) && $quote['premiumTypeCode'] == 'short_term_6')
                    {
                        $st_6_months_policy = $quote_response;
                    }
                    else
                    {
                        $annual_policy = $quote_response;
                    }

                    EmbeddedScrubPdfData::create([
                        'enquiry_id' => $quote['userProductJourneyId'],
                        'pdf_data' => json_encode($quote_pdf_data, JSON_UNESCAPED_SLASHES)
                    ]);

                    $update_journey_request_data = [
                        'proposalUrl' => config('constants.frontend_url') . 'cv/proposal-page?enquiry_id=' . customEncrypt($quote['userProductJourneyId']) . '&dropout=true',
                        'quoteUrl' => config('constants.frontend_url') . 'cv/quotes?enquiry_id=' . customEncrypt($quote['userProductJourneyId']),
                        'stage' => STAGE_NAMES['PROPOSAL_DRAFTED'],
                        'userProductJourneyId' => customEncrypt($quote['userProductJourneyId'])
                    ];

                    if (config('constants.motor.USE_CONTROLLER_FOR_API_CALL') == 'Y')
                    {
                        $common_controller->updateJourneyUrl(request()->replace($update_journey_request_data));
                    }
                    else
                    {
                        Http::post(url('/api/updateJourneyUrl'), $update_journey_request_data);
                    }
                }
            }

            return response()->json([
                'status' => TRUE,
                'data' => [
                    'vahan_response' => $vahan_response,
                    '3_months_policy' => $st_3_months_policy ?? NULL,
                    '6_months_policy' => $st_6_months_policy ?? NULL,
                    '12_months_policy' => $annual_policy ?? NULL,
                    'batch_id' => $batch_id
                ],
                'upload_excel_id' => $upload_excel_id
            ]);
        }
        catch (Exception $e)
        {
            info('Embedded Scrub: ' . $e->getMessage() . 'File : ' . $e->getFile() . 'Line No. : ' . $e->getLine());
            return response()->json([
                'status' => FALSE,
                'message' => 'Something went wrong..!' . $e->getMessage() . 'Line No. : '  . $e->getLine() . $e->getFile(),
                'data' => [
                    'batch_id' => $request->batch_id
                ],
                'upload_excel_id' => $request->upload_excel_id
            ]);
        }
    }

    public function getLowestPremiumForScrub($additional_data)
    {
        extract($additional_data);

        $product_data = \App\Models\MasterPolicy::where([
            ['premium_type_id', '=', $additional_data['policy_type'] == 'comprehensive' ? 1 : 2],
            ['zero_dep', '!=', '0'],
            ['product_sub_type_id', '=', $product_sub_type_id],
            ['master_policy.status', '=', 'Active']
        ])->join('master_company as mc', 'mc.company_id', '=', 'insurance_company_id')->select('policy_id', 'mc.company_alias')->get();

        if ($product_data)
        {
            $master = curl_multi_init();

            foreach ($product_data as $key => $value)
            {
                $url = url('api/premiumCalculation/' . $value['company_alias']);

                $curl_arr[$key] = curl_init($url);

                $data = [
                    "enquiryId" => $enquiryId,
                    "policyId" => $value['policy_id'],
                ];

                curl_setopt($curl_arr[$key], CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl_arr[$key], CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($curl_arr[$key], CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl_arr[$key], CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl_arr[$key], CURLOPT_HTTPHEADER, [
                    'Accept' => 'application/json',
                ]);
                curl_setopt($curl_arr[$key], CURLOPT_POSTFIELDS, $data);
                curl_multi_add_handle($master, $curl_arr[$key]);
            }

            do {
                curl_multi_exec($master, $running);
            } while ($running > 0);

            $result = [];

            foreach ($product_data as $key => $value)
            {
                $result[$key] = json_decode(curl_multi_getcontent($curl_arr[$key]), true);
            }

            $newarray = [];

            $fail_quote = '';

            foreach ($result as $index => $row)
            {
                if (isset($row['data']))
                {
                    array_push($newarray, [$index => $row['data']['tppdPremiumAmount'] + $row['data']['basicPremium']]);
                }
                else
                {
                    if ($row != null)
                    {
                        $fail_quote .= $row['FTS_VERSION_ID'] . ' - ' . (isset($product_data[$index]['company_name']) ? $product_data[$index]['company_name'] : $product_data[$index]) . ' - ' . (isset($row['message']) ? $row['message'] : json_encode($row)) . ',';
                    }
                }
            }

            if ( ! empty($newarray))
            {
                $resultArr = [];

                foreach ($newarray as $key => $value)
                {
                    foreach ($value as $key_a => $val)
                    {
                        $resultArr[$key_a] = $val;
                    }
                }

                $min = min($resultArr);
                $index = array_search($min, $resultArr);
                $quote = $result[$index];

                return $quote['data'];
            }
            else
            {
                return [];
            }
        }
        else
        {
            return [];
        }
    }

    public function getQuotePdf(Request $request)
    {
        $enquiry_id = customDecrypt($request->enquiry_id);

        $pdf_data = EmbeddedScrubPdfData::where('enquiry_id', $enquiry_id)
            ->pluck('pdf_data')
            ->first();

        $pdf_data = json_decode($pdf_data, TRUE);

        return response(\PDF::loadView('pdf', ['data' => [$pdf_data]])->output())->header('Content-Type', 'application/pdf');
    }
}
