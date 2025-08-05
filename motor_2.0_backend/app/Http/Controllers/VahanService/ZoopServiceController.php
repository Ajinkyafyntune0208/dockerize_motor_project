<?php

namespace App\Http\Controllers\VahanService;

use DateTime;
use Exception;
use Carbon\Carbon;
use App\Models\QuoteLog;
use App\Models\MasterRto;
use App\Models\UserProposal;
use App\Models\VahanService;
use Illuminate\Http\Request;
use App\Models\CvAgentMapping;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use App\Models\FastlaneRequestResponse;
use App\Models\VahanServiceCredentials;
use App\Models\CorporateVehiclesQuotesRequest;


class ZoopServiceController extends Controller
{
    const VAHAN_SERVICE_CODE = 'zoop';
    static $blockConstantString = 'Record not found. Block journey.';
    static $blockStatusCode = 102;
    protected $credentials;

    public function __construct()
    {
        $this->setCredentials();
    }

    protected function setCredentials()
    {
        $vahanId = VahanService::where('vahan_service_name_code', 'zoop')->select('id')->get()->first();

        if (empty($vahanId)) {
            throw new Exception('Zoop Vahan Service is not configured.');
        }

        $this->credentials = VahanServiceCredentials::where('vahan_service_id', $vahanId->id)->select('value', 'key')->get()->pluck('value', 'key')->toArray();
    }

    public function getCredential($keyName)
    {
        return $this->credentials[$keyName] ?? null;
    }
    public function getVahanDetails(Request $request)
    {

        $validate = $request->validate([
            'enquiryId' => 'required',
            'registration_no' => 'required',
            'section' => 'required'
        ]);
        try {
            $enquiryId = customDecrypt($request->enquiryId);
            $rcno = explode('-', $request->registration_no);
            if ($rcno[0] == 'DL') {
                $request->registration_no = $rcno[0] . ((int)$rcno[1] * 1) . $rcno[2] . $rcno[3];
            } else {
                $request->registration_no = preg_replace('/-/', '', $request->registration_no);
            }

            $rtoCode = $rcno[0] . '-' . $rcno[1];
            $registrationNo = $rcno[0] . '-' . $rcno[1] . '-' . $rcno[2] . '-' . $rcno[3];

            $registration_details = DB::table('registration_details')
                ->where('vehicle_reg_no', $registrationNo)
                ->first();
            // $registration_details = NULL;

            $request->registrationNo = $registrationNo;

            if (!empty($registration_details)) {
                $zoopService = json_decode($registration_details->vehicle_details);
                $zoopService->result->type = 'db';
            } else {
                $zoopService = self::zoopService($request);

                if (!$zoopService['status']) {
                    return response()->json($zoopService);
                }

                $zoopService = $zoopService['data'];
                $zoopService->result->type = 'api';
            }

            $vehicleDetails = $zoopService->result;

            $vehicleCategory = $vehicleDetails->vehicle_category;

            $FTProductCode = '';

            $zoopProduct = DB::table('zoop_vehicle_type_master as zt')
                ->where(['rc_vch_catg' => $vehicleCategory])
                ->where('rc_vh_class_desc', 'LIKE', '%' . $vehicleDetails->vehicle_class_description . '%')
                ->first();

            if (empty($zoopProduct)) {
                $zoopService->status = 101;
                return response()->json([
                    'status' => false,
                    'msg'   => 'Vehicle Category Is Not Found',
                    'data'  => $zoopService
                ]);
            }
            if (empty($zoopProduct->ft_sub_type_id)) {
                $zoopService->status = 101;
                return response()->json([
                    'status' => false,
                    'msg'   => 'Vehicle Category Is Not Mapped',
                    'data'  => $zoopService
                ]);
            }

            if ($request->section == 'cv' && ($zoopProduct->ft_sub_type_name == 'bike' || $zoopProduct->ft_sub_type_name == 'car')) {
                return response()->json([
                    'status' => false,
                    "showMessage" => true,
                    'msg'   => 'Given Registration Number Belongs To ' . strtoupper($zoopProduct->ft_sub_type_name),
                    'data'  => $zoopService
                ]);
            }

            $FTProductCode = $zoopProduct->ft_sub_type_name;

            // if ($vehicleCategory == 'LMV') {
            //     $FTProductCode = 'car';
            // } else if ($vehicleCategory == '2W') {
            //     $FTProductCode = 'bike';
            // } else if ($vehicleCategory == 'LPV') {
            //     $FTProductCode = 'pcv';
            // }

            $versionArray = $vehicleDetails->similarities;

            $versionCode = '';
            $maxConfidance = 0.0;

            if (empty($versionArray)) {
                $zoopService->status = 101;
                return response()->json([
                    'status' => false,
                    'msg'   => 'Version Data Not Found',
                    'data'  => $zoopService
                ]);
            }

            if (isset($versionArray[0])) {
                foreach ($versionArray as $version) {
                    if ($version->confidence > $maxConfidance) {
                        $maxConfidance = $version->confidence;
                        $versionCode = strtoupper($version->code);
                    }
                }
            }

            if (empty($versionCode)) {
                $zoopService->status = 101;
                return response()->json([
                    'status' => false,
                    'msg'   => 'Version Code Not Found',
                    'data'  => $zoopService
                ]);
            }

            // $producttype = [
            //     '1' => 'motor',
            //     '2' => 'bike',
            //     '6' => 'pcv',
            //     '9' => 'gcv',
            //     '13' => 'gcv',
            //     '14' => 'gcv',
            //     '15' => 'gcv',
            //     '16' => 'gcv',
            // ];

            // return [
            //     'FTProductCode' => $FTProductCode,
            //     'vehicleCategory' => $vehicleCategory,
            // ];
            // $product_sub_type_id = array_search($FTProductCode, $producttype);

            if ($request->section == 'car' || $request->section == 'bike') {
                $product_sub_type_id = $request->productSubType;
            } else {
                $product_sub_type_id = $zoopProduct->ft_sub_type_id;
            }

            $mmv_details = get_fyntune_mmv_details($product_sub_type_id,  $versionCode);

            $vehicleDetails->ft_product_code = $FTProductCode;
            if (in_array(strtolower($FTProductCode), ['pcv', 'gcv'])) {
                $vehicleDetails->ft_product_code = 'cv';
            }
            if (empty($mmv_details) || !$mmv_details['status']) {
                $vehicleDetails->status = 100;

                if (strtolower($zoopProduct->ft_sub_type_name) == strtolower($request->section)) {
                    $status_bool = false;
                    $vehicleDetails->status = 101;
                }

                if ($versionCode == NULL) {
                    $status_bool = false;
                    $vehicleDetails->status = 101;
                }

                return [
                    'status' => true,
                    'message' => 'MMV details not found. - ' . $versionCode,
                    'data' => $vehicleDetails,
                    // [
                    //     'status' => 100,
                    //     'result' => $vehicleDetails,
                    //     'ft_product_code' => $FTProductCode
                    // ]
                ];
            }
            $mmv_details = $mmv_details['data'];

            $registrationDate = date('d-m-Y', strtotime($vehicleDetails->rc_registration_date));

            if (isset($vehicleDetails->insurance->expiry_date) && !empty($vehicleDetails->insurance->expiry_date)) {
                $policyExpiry = date('d-m-Y', strtotime($vehicleDetails->insurance->expiry_date));

                $date1 = new \DateTime($registrationDate);
                $date2 = new \DateTime(date('d-m-Y'));
                $interval = $date1->diff($date2);
                $car_age = (($interval->y * 12) + $interval->m) + 1; // In Months
                $car_age = $car_age / 12;
                $previousNCB = 0;

                if ($car_age <= 1) {
                    $previousNCB = 0;
                    $applicableNCB = 20;
                } else if ($car_age <= 2) {
                    $previousNCB = 20;
                    $applicableNCB = 25;
                } else if ($car_age <= 3) {
                    $previousNCB = 25;
                    $applicableNCB = 35;
                } else if ($car_age <= 4) {
                    $previousNCB = 35;
                    $applicableNCB = 45;
                } else if ($car_age <= 5) {
                    $previousNCB = 45;
                    $applicableNCB = 50;
                } else {
                    $previousNCB = 50;
                    $applicableNCB = 50;
                }
            } else {
                $policyExpiry = null;
                $previousNCB = null;
                $applicableNCB = null;
            }

            $previousInsurer = $previousInsurerCode = NULL;

            $invoiceDate = $registrationDate;
            $manufactureYear = str_replace('/', '-', $vehicleDetails->vehicle_manufactured_date);
            // if (!empty($manufactureYear)) {
            // $invoiceDate = '01-'.$manufactureYear;
            // }

            $fuelType = explode('/', $vehicleDetails->vehicle_fuel_description)[0];

            $rtoName = MasterRto::where('rto_code', $rtoCode)->pluck('rto_name')->first();

            $expiry_date = \Carbon\Carbon::parse($policyExpiry);
            $today_date = now()->subDay(1);

            if ($expiry_date < $today_date) {
                $businessType = 'breakin';
                $diff = $expiry_date->diffInDays(now());
                if ($diff > 90) {
                    $previousNCB = 0;
                    $applicableNCB = 0;
                }
            } else {
                $businessType = 'rollover';
            }

            if ($FTProductCode == 'car' || $FTProductCode == 'bike') {
                if ($FTProductCode == 'car') {
                    $od_compare_date = now()->subYear(3)->subDay(45);
                } else if ($FTProductCode == 'bike') {
                    $od_compare_date = Carbon::parse('01-09-2018')->subDay(1);
                }

                if (Carbon::parse($registrationDate) > $od_compare_date) {
                    $policyType = 'own_damage';
                } else {
                    $policyType = 'comprehensive';
                }
            } else {
                $policyType = 'comprehensive';
            }
            if (strpos($vehicleCategory, 'cv') !== false || strpos($vehicleCategory, 'LPV') !== false || strpos($vehicleCategory, 'LGV') !== false) {
                $policyType = 'comprehensive';
            }

            $ncb_previous_ic_popup_disabled = config('NCB_PREVIOUS_IC_POPUP_DISABLED') == 'Y';
            $ncb_previous_ic_popup_seller_types = [];

            $cv_agent_mappings = CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();

            if (config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES')) {
                $ncb_previous_ic_popup_seller_types = explode(',', config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES'));

                if ( ! empty($cv_agent_mappings->user_id)) {
                    array_push($ncb_previous_ic_popup_seller_types, NULL);
                }
            }

            if (isset($vehicleDetails->insurance->company) && !empty($vehicleDetails->insurance->company)) {
                $fyntune_ic = DB::table('fastlane_previous_ic_mapping as m')
                    ->whereRaw('? LIKE CONCAT("%", m.identifier, "%")', $vehicleDetails->insurance->company)->first();
                if (( ! $ncb_previous_ic_popup_disabled || ($ncb_previous_ic_popup_disabled && ! in_array($cv_agent_mappings?->seller_type, $ncb_previous_ic_popup_seller_types))) && !empty($fyntune_ic)) {
                    $previousInsurer = $fyntune_ic->company_name;
                    $previousInsurerCode = $fyntune_ic->company_alias;
                }
            }

            $ownerType = ((isset($vehicleDetails->father_name) && $vehicleDetails->father_name != 'NA' && $vehicleDetails->father_name != '' && $vehicleDetails->father_name !== null) ? 'I' : 'C');

            $previousPolicyExpiryDate = $expiry_date->format('d-m-Y');

            $quote_log = QuoteLog::where('user_product_journey_id', $enquiryId)->first();
            $previous_policy_type = 'Comprehensive';
            if(config('newBreakinLogic.Enabled') == 'Y')
            {
                $manufactureDate = '01-' . $manufactureYear;
                $newBreakinData = $this->newBreakinLogicHandle($registrationDate,$manufactureDate);
                if(!empty($newBreakinData))
                {
                    extract($newBreakinData);
                    $previous_insurance = $previous_insurer;
                }
                else
                {
                    $previous_policy_type = 'Comprehensive';
                }
            }
            // return $quote_log;
            // if (!$quote_log || ) {
            $quote_data = [
                "product_sub_type_id" => $product_sub_type_id,
                "manfacture_id" => $mmv_details['manufacturer']['manf_id'],
                "manfacture_name" => $mmv_details['manufacturer']['manf_name'],
                "model" => $mmv_details['model']['model_id'],
                "model_name" => $mmv_details['model']['model_name'],
                "version_id" => $versionCode,
                "vehicle_usage" => 2,
                "policy_type" => $policyType,
                "business_type" => $businessType,
                "version_name" => $mmv_details['version']['version_name'],
                "vehicle_register_date" => $registrationDate,
                "previous_policy_expiry_date" => $policyExpiry,
                "previous_policy_type" => $previous_policy_type,
                "fuel_type" => $mmv_details['version']['fuel_type'],
                "manufacture_year" => $manufactureYear,
                "rto_code" => isBhSeries($rtoCode) ? null : $rtoCode,
                "vehicle_owner_type" => $ownerType,
                "is_claim" => "N",
                "previous_ncb" => $previousNCB,
                "applicable_ncb" => $applicableNCB,
            ];
            $QuoteLog = [
                'user_product_journey_id' => $enquiryId,
                'quote_data' => json_encode($quote_data),
            ];
            // echo '<pre>'; print_r([$mmv_details]); echo '</pre';die();

            // if ($request->type == 'pro') {
            QuoteLog::updateOrCreate([
                'user_product_journey_id' => $enquiryId
            ], $QuoteLog);
            // }

            // echo '<pre>'; print_r([$QuoteLog]); echo '</pre';die();
            // }
            // else{
            //     $quote_data = json_decode($quote_log->quote_data, 1);
            // }

            $corporateData = [
                'version_id' => $versionCode,
                'policy_type' => $policyType,
                'business_type' => $businessType,
                'vehicle_register_date' => $registrationDate,
                'vehicle_registration_no' => $registrationNo,
                'previous_policy_expiry_date' => $policyExpiry, //'11-11-2021',
                'previous_policy_type' => $previous_policy_type,
                'fuel_type' => $mmv_details['version']['fuel_type'], //'PETROL',
                'manufacture_year' => $manufactureYear,
                'vehicle_invoice_date' => $invoiceDate,
                'rto_code' => isBhSeries($rtoCode) ? null : $rtoCode,
                'rto_city' => isBhSeries($rtoName) ? null : $rtoName,
                'previous_ncb' => $previousNCB,
                'applicable_ncb' => $applicableNCB,
                'previous_insurer' => $previousInsurer,
                'previous_insurer_code' => $previousInsurerCode,
                'journey_type' => 'Zoop',
                'is_ncb_verified' => $applicableNCB > 0 ? 'Y' : 'N'
                // 'zero_dep_in_last_policy' => 'Y',
            ];

            CorporateVehiclesQuotesRequest::updateOrCreate(
                [
                    'user_product_journey_id' => $enquiryId
                ],
                $corporateData
            );

            $addressLine1 = $addressLine2 = $addressLine3 = '';

            if (isset($vehicleDetails->user_permanent_address)) {
                $userAddress = $vehicleDetails->user_permanent_address;

                $address = array_values(array_filter(explode(' ', $userAddress)));
                $length = floor(count($address) / 3);
                // array_pop($address);

                $addressLine1 = implode(' ', array_slice($address, 0, $length));
                $addressLine2 = implode(' ', array_slice($address, $length, $length));
                $addressLine3 = implode(' ', array_slice($address, $length * 2, $length));
            }

            $return_data = [
                'applicableNcb' => $applicableNCB,
                'businessType' => $businessType,
                'corpId' => '',
                'emailId' => '',
                'enquiryId' => $request->enquiryId,
                //'firstName' => '',
                'fuelType' => $mmv_details['version']['fuel_type'],
                //'fullName' => "",
                'hasExpired' => "no",
                'isClaim' => "N",
                'isNcb' => "Yes",
                //'lastName' => '',
                //'leadJourneyEnd' => true,
                'manfactureId' => $mmv_details['manufacturer']['manf_id'],
                'manfactureName' => $quote_data['manfacture_name'],
                'manufactureYear' => $quote_data['manufacture_year'],
                //'mobileNo' => '',
                'model' => $quote_data['model'],
                'modelName' => $quote_data['model_name'],
                'ownershipChanged' => "N",
                'policyExpiryDate' => $policyExpiry,
                'policyType' => $policyType,
                'previousInsurer' => '',
                'previousInsurerCode' => '',
                'previousNcb' => $previousNCB,
                'previousPolicyType' => $previous_policy_type,
                'productSubTypeId' => $product_sub_type_id,
                'rto' => $rtoCode,
                'stage' => 11,
                //'userId' => '',
                'userProductJourneyId' => $request->enquiryId,
                //'vehicleLpgCngKitValue' => "",
                'vehicleOwnerType' => $ownerType,
                'vehicleRegisterAt' => $rtoCode,
                'vehicleRegisterDate' => $registrationDate,
                'vehicleRegistrationNo' => $registrationNo,
                'vehicleUsage' => 2,
                'version' => $versionCode,
                'versionName' => $quote_data['version_name'],
                'previous_insurer' => $previousInsurer,
                'previous_insurer_code' => $previousInsurerCode,

                'manufacture_year' => $manufactureYear,

                'address_line1' => $addressLine1,
                'address_line2' => $addressLine2,
                'address_line3' => $addressLine3,
                'pincode' => (isset($vehicleDetails->pincode) ? $vehicleDetails->pincode : ''),
            ];

            $curlResponse['results'][0]['vehicle']['regn_dt'] = $registrationDate;
            $curlResponse['results'][0]['vehicle']['vehicle_cd'] = $versionCode;
            $curlResponse['results'][0]['vehicle']['fla_maker_desc'] = $quote_data['manfacture_name'];
            $curlResponse['results'][0]['vehicle']['fla_model_desc'] = $quote_data['model_name'];
            $curlResponse['results'][0]['vehicle']['fla_variant'] = $quote_data['version_name'];
            $curlResponse['results'][0]['insurance']['insurance_upto'] = $previousPolicyExpiryDate;
            $curlResponse['additional_details'] = $return_data;
            $curlResponse['additional_details']['vahan_service_code'] = self::VAHAN_SERVICE_CODE;
            $curlResponse["status"] = 100;
            $curlResponse["type"] = 'zoop';

            // if ($request->type == 'pro') {

            $nameArray = explode(' ', $vehicleDetails->user_name);
            $lastName = '';
            $firstName = '';

            if ($ownerType == 'I') {
                $lastName = ((count($nameArray) - 1 > 0) ?  $nameArray[count($nameArray) - 1] : "");
                if (isset($nameArray[count($nameArray) - 1]) && (count($nameArray) - 1 > 0)) {
                    unset($nameArray[count($nameArray) - 1]);
                }
                $firstName = implode(' ', $nameArray);
            } else {
                $firstName = array_values(array_filter($nameArray));

                $firstName = implode(' ', self::countStringInArray($firstName));

                $firstName = trim($firstName);
                $lastName = '';
            }

            $fullName = $firstName . ' ' . $lastName;

            $proposalData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'fullName' => $fullName,
                'applicable_ncb' => $curlResponse['additional_details']['applicableNcb'],
                'is_claim' => 'N',
                'address_line1' => $curlResponse['additional_details']['address_line1'],
                'address_line2' => $curlResponse['additional_details']['address_line2'],
                'address_line3' => $curlResponse['additional_details']['address_line3'],
                'pincode' => $curlResponse['additional_details']['pincode'],
                'rto_location' => $rtoCode,
                // 'additional_details' => json_encode($return_data),
                'vehicale_registration_number' => $registrationNo,
                'vehicle_manf_year' => $curlResponse['additional_details']['manufacture_year'],
                'previous_insurance_company' => $previousInsurer,
                'prev_policy_expiry_date' => $previousPolicyExpiryDate,
                'engine_number' => removeSpecialCharactersFromString($vehicleDetails->rc_engine_number),
                // 'chassis_number' => removeSpecialCharactersFromString($vehicleDetails->rc_chassis_number),
                'chassis_number' => $vehicleDetails->rc_chassis_number,
                'previous_policy_number' => isset($vehicleDetails->insurance->policy_number) ? $vehicleDetails->insurance->policy_number : '',
                'vehicle_color' => $vehicleDetails->vehicle_color,
                'is_vehicle_finance' => ((isset($vehicleDetails->financer) && !empty($vehicleDetails->financer)) ? 'Y' : ''),
                'owner_type' => $ownerType,
                'name_of_financer' => (isset($vehicleDetails->financer) ? $vehicleDetails->financer : ''),
            ];

            if (config('constants.motorConstant.AUTO_FILL_TP_DETAILS_IN_VAHAN') == 'Y') {
                $tpExpiryDate = null;

                if (isset ($vehicleDetails->insurance->expiry_date) && !empty($vehicleDetails->insurance->expiry_date)) {
                    $tpExpiryDate = date('d-m-Y', strtotime($vehicleDetails->insurance->expiry_date));
                }
                $proposalData = array_merge($proposalData, [
                    // 'tp_start_date'                         => $tpExpiryDate,
                    'tp_end_date'                           => $tpExpiryDate,
                    'tp_insurance_company'                  => $previousInsurer ?? null,
                    'tp_insurance_company_name'             => $previousInsurer ?? null,
                    'tp_insurance_number'                   => isset($vehicleDetails->insurance->policy_number) ? $vehicleDetails->insurance->policy_number :  '',
                ]);
            }

            UserProposal::updateOrCreate(
                ['user_product_journey_id' => $enquiryId],
                $proposalData
            );
            $user_product_journey = [
                'user_fname' =>  $firstName,
                'user_lname' =>  $lastName,
            ];
            UserProductJourney::where('user_product_journey_id', $enquiryId)->update($user_product_journey);
            // }

            return response()->json([
                'status' => true,
                // 'msg'   => $curlResponse['description'],
                'data'  => $curlResponse,
                'proposal' => $proposalData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "data" => [
                    "status" => 101,
                ],
                "status" => false,
                "message" => "error " . $e->getMessage(),
                "url" => config('constants.frontend_url'),
                // 'message' => $e->getMessage(),
                'line' => $e->getLine(),

            ], 500);
        }
    }
    public function validateVehicleService(Request $request)
    {
        $userProductJourneyId = customDecrypt($request->enquiryId);
            $existing_record = DB::table('proposal_vehicle_validation_logs')
                ->select('response')
                ->where('vehicle_reg_no', $request->registration_no)
                ->where('service_type', 'zoop')
                ->orderBy('id', 'DESC')->first();
                if (empty($existing_record)) {
            $existing_record = DB::table('registration_details')
                ->where('vehicle_reg_no', $request->registration_no)
                ->orderBy('id', 'DESC')
                ->select('vehicle_details as response')
                ->first();
        }
        $isResponseValid = false;
        if (!empty($existing_record)) {
            $tempCurlResponse = json_decode($existing_record->response, true);
            $isResponseValid = (isset($tempCurlResponse['result']['vehicle_class_description']) && !empty($tempCurlResponse['result']['vehicle_class_description']));
            unset($tempCurlResponse);
        }
        if ($isResponseValid) {
            $curlResponse = json_decode($existing_record->response, true);
            if (isset($curlResponse['result']['vehicle_class_description']) && empty($curlResponse['result']['vehicle_class_description'])) {
                $curlResponse['result']['vehicle_class_description'] = $curlResponse['result']['vehicle_class_description'];
            }
        } else {

            $url = $this->getCredential('vahan.zoop.attribute.url');
            $api_key = $this->getCredential('vahan.zoop.attribute.x_api_key');
            $api_id  = $this->getCredential('vahan.zoop.attribute.x_api_id');
            $headers = [
                "Content-type" => "application/json",
                "api-key" => $api_key,
                "app-id" => $api_id,
            ];
            $body = [
                "data" => [
                    "vehicle_registration_number" => str_replace('-', '', $request->registration_no),
                    "consent" => "Y",
                    "consent_text" => "RC Advance is Verified by author",
                ],
            ];
            $curlResponse = httpRequestNormal($url, "POST", $body, [], $headers, [], true);
            $og_response = $curlResponse = $curlResponse['response'];
            if (empty($curlResponse)) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Empty response from zoop.',
                    'data' => [
                        'status' => 101,
                    ],
                ]);
            }
            // $curlResponse = json_encode($curlResponse, true);
            if ($curlResponse['response_code'] != 100) {
                return response()->json([
                    'status' => false,
                    'msg' => $curlResponse['response_message'],
                    'data' => [
                        'status' => 101,
                    ],
                ]);
            }
            if (empty($curlResponse['result'])) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Empty response from zoop.',
                    'data' => [
                        'status' => 101,
                    ],
                ]);
            }
             
        }
        $zoop_product_code = trim($curlResponse['result']['vehicle_class_description'] ?? '');

        if (self::isVehicleCategoryBlocked($zoop_product_code)) {
            return response()->json([
                'status' => false,
                'msg' => self::$blockConstantString,
                'data' => [
                    'status' => self::$blockStatusCode,
                ],
            ]);
        }
        $ft_product_code = '';
        $vehicle_section = DB::table('fastlane_vehicle_description')->where('description', $zoop_product_code)->first();
        if (!empty($vehicle_section)) {
            $ft_product_code = trim($vehicle_section->section);
        } else if (stripos($zoop_product_code, 'LMV') !== false) {
            $ft_product_code = 'car';
        } else if (stripos($zoop_product_code, 'LPV') !== false) {
            $ft_product_code = 'cv';
        } else if (stripos($zoop_product_code, '2W') !== false) {
            $ft_product_code = 'bike';
        }

        if (empty($existing_record) && !empty($ft_product_code)) {
            DB::table('proposal_vehicle_validation_logs')->insert([
                'vehicle_reg_no' => $request->registration_no,
                'response' => json_encode($curlResponse),
                'service_type' => 'zoop',
                'endpoint_url' => $url,
                'created_at' => now(),
            ]);
        }

        $curlResponse['ft_product_code'] = $ft_product_code;
        $curlResponse['response_code'] = 101;

        if (strtolower($ft_product_code) == strtolower($request->section)) {
            $versionArray = $curlResponse['result']['similarities'];
            $maxConfidance = 0.0;

            if (isset($versionArray[0])) {
                foreach ($versionArray as $version) {
                    if ($version->confidence > $maxConfidance) {
                        $maxConfidance = $version->confidence;
                        $versionCode = strtoupper($version->code);
                    }
                }
            }
            if (!empty($versionCode)) {
                $ValidateQuoteAndVahanMmv = \App\Helpers\VahanHelper::validateQuouteAndVahanMMv($request, $versionCode, $userProductJourneyId);
                if (!$ValidateQuoteAndVahanMmv['status']) {
                    return response()->json($ValidateQuoteAndVahanMmv);
                }
            }
            $vehicle_details['status'] = 100;
            $curlResponse['status'] = 100;
        } else {
            $curlResponse['status'] = 100;
            if (empty($ft_product_code)) {
                $row_exists = DB::table('fastlane_vehicle_description')
                    ->where("description", $zoop_product_code)
                    ->where('section', null)
                    ->first();
                if (!$row_exists && !empty(trim($zoop_product_code))) {
                    DB::table('fastlane_vehicle_description')->insert([
                        "section" => null,
                        "description" => $zoop_product_code,
                    ]);
                }
                unset($curlResponse);
                $curlResponse['status'] = 101;
                $curlResponse['response_message'] = 'Unable to match vehicle description.';
            }

            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
                'vehicle_registration_no' => $request->registration_no,
            ]);
        }
        $frontend_url = config('constants.motorConstant.' . strtoupper($ft_product_code) . '_FRONTEND_URL');

        if (config('constants.motorConstant.DYNAMIC_FRONTEND_URLS') == 'Y') {
            $section = $ft_product_code;
            $frontend_url = getFrontendUrl($section, $userProductJourneyId);
        }

        $curlResponse['redirectionUrl'] = $frontend_url;
        return response()->json([
            'status' => true,
            'msg' => $curlResponse['status'] == 100 ? $curlResponse['response_message'] : 'Vehicle validation failed.',
            'data' => $curlResponse,
        ]);
    }
    public static function isVehicleCategoryBlocked($vehicleDescription = '')
    {
        $categories = config('proposalPage.vehicleValidation.blockedCategories');
        $blockedVehicles = empty($categories) ? [] : explode(',', $categories);
        return in_array($vehicleDescription, $blockedVehicles);
    }
    public static function zoopService($request)
    {
        $zoopServiceRequest = [
            'data' => [
                'vehicle_registration_number' => $request->registration_no,
                'consent' => 'Y',
                'consent_text' => 'RC Advance is Verified by author',
            ],
        ];

        $endpoint_url = config('constants.zoop.ZOOP_SERVICE_URL');

        $startTime = new DateTime(date('Y-m-d H:i:s'));

        $zoopServiceResponse = Http::withHeaders([
            'app-id' => config('constants.zoop.ZOOP_APP_ID'),
            'api-key' => config('constants.zoop.ZOOP_API_KEY')
        ])->post($endpoint_url, $zoopServiceRequest);

        $endTime = new DateTime(date('Y-m-d H:i:s'));

        $responseTime = $startTime->diff($endTime);

        $enquiryId = customDecrypt($request->enquiryId);
        $zoopServiceResponse = json_decode($zoopServiceResponse);

        FastlaneRequestResponse::insert([
            'enquiry_id' => $enquiryId,
            'request' => $request->registration_no,
            'response' => json_encode($zoopServiceResponse),
            'transaction_type' => "Zoop",
            'endpoint_url' => $endpoint_url,
            'ip_address' => $request->ip(),
            'section' => $request->section,
            'response_time' => $responseTime->format('%Y-%m-%d %H:%i:%s'),
            'created_at' => now(),
        ]);

        if (empty($zoopServiceResponse)) {
            $zoopServiceResponse = (object)[];
            $zoopServiceResponse->status = 101;
            return [
                'status' => false,
                'msg'   => 'API Not Working.',
                'data'  => $zoopServiceResponse
            ];
        }

        if ($zoopServiceResponse->response_code != '100') {
            $zoopServiceResponse->status = 101;
            return [
                'status' => false,
                'msg'   => $zoopServiceResponse->response_message,
                'data'  => $zoopServiceResponse
            ];
        }

        DB::table('registration_details')->insert([
            'vehicle_reg_no' => $request->registrationNo,
            'vehicle_details' => json_encode($zoopServiceResponse),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'status' => true,
            'data'  => $zoopServiceResponse
        ];
    }
    public static function countStringInArray($arr)
    {
        $len = 0;

        foreach ($arr as $row) {
            $len += strlen($row);
        }

        if ($len > 33) {
            array_pop($arr);
            self::countStringInArray($arr);
        }

        return $arr;
    }
}
