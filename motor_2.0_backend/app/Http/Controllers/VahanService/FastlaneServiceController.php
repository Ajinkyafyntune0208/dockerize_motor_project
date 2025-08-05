<?php

namespace App\Http\Controllers\VahanService;

use Exception;
use App\Models\QuoteLog;
use App\Models\MasterRto;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use App\Models\VahanService;
use Illuminate\Http\Request;
use App\Models\CvAgentMapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\FastlaneVehDescripModel;
use App\Models\VahanServiceCredentials;
use Illuminate\Support\Facades\Storage;
use App\Interfaces\VahanServiceInterface;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\RenewalController;
use App\Models\ProposalVehicleValidationLogs;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\ProposalExtraFields;

class FastlaneServiceController extends VahanServiceController implements VahanServiceInterface
{
    const VAHAN_SERVICE_CODE = 'fastlane';
    static $blockConstantString = 'Record not found. Block journey.';
    static $blockStatusCode = 102;
    protected $credentials;

    public function __construct()
    {
        $this->setCredentials();
    }

    protected function setCredentials()
    {
        $vahanId = VahanService::where('vahan_service_name_code', self::VAHAN_SERVICE_CODE)->select('id')->get()->first();

        if (empty($vahanId)) {
            throw new Exception('Fastlane Vahan Service is not configured.');
        }

        $this->credentials = VahanServiceCredentials::where('vahan_service_id', $vahanId->id)->select('value', 'key')->get()->pluck('value', 'key')->toArray();
    }

    public function getCredential($keyName)
    {
        return $this->credentials[$keyName] ?? null;
    }

    public function getVahanDetails(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'enquiryId' => 'required',
            'registration_no' => 'required',
            'productSubType' => 'nullable|numeric',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        }
        $productSubType = $request->productSubType;
        $userProductJourneyId = customDecrypt($request->enquiryId);
        $registration_no = $request->registration_no;
        if (config('constants.motorConstant.SMS_FOLDER') == 'bajaj' && isset($request->is_renewal) && $request->is_renewal == 'Y' && config('FETCH_RENEWAL_DATA_FROM_MIGRATION_TABLE') == 'Y') {
            $returned_data = RenewalController::renewal_data_migration($request);

            if ($returned_data['status']) {
                $bajaj_renewal_data = $returned_data['data'];
                if (isset($request->isPolicyNumber) && $request->isPolicyNumber == 'Y') {
                    $registration_no = getRegisterNumberWithHyphen($bajaj_renewal_data->VEHICLE_REGISTRATION_NUMBER);
                }
            } else {
                return $returned_data;
            }
        }

        if ($request->section == 'cv') {
            $username = $this->getCredential('vahan.fastlane.attribute.username_cv') != '' ? $this->getCredential('vahan.fastlane.attribute.username_cv') : $this->getCredential('vahan.fastlane.attribute.username');
            $password = $this->getCredential('vahan.fastlane.attribute.password_cv') != '' ? $this->getCredential('vahan.fastlane.attribute.password_cv') : $this->getCredential('vahan.fastlane.attribute.password');
            $url = $this->getCredential('vahan.fastlane.attribute.url_cv') != '' ? $this->getCredential('vahan.fastlane.attribute.url_cv') : $this->getCredential('vahan.fastlane.attribute.url');
        } else {
            $username = $this->getCredential('vahan.fastlane.attribute.username');
            $password = $this->getCredential('vahan.fastlane.attribute.password');
            $url = $this->getCredential('vahan.fastlane.attribute.url');
        }

        $isTenMonthsLogic = config('constants.isTenMonthsLogicEnabled') == 'Y';
        $query = DB::table('registration_details')->where('vehicle_reg_no', $registration_no);
        $query->where(function ($subquery) use ($isTenMonthsLogic) {
            $subquery->where('expiry_date', '>=', now()->format('Y-m-d'));
            if ($isTenMonthsLogic) {
                $subquery->orWhere('created_at', '<=', now()->subMonth(10)->format('Y-m-d 00:00:00'));
            }
        });
        $registration_details = $query->latest()->first();
        $service = null;
        $isResponseValid = false;
        if (!empty($registration_details)) {
            $service = "offline";
            $curlResponse = json_decode($registration_details->vehicle_details, true);
            // If we get the vh_class_desc tag in response then proceed else hit vahan API again.
            $isResponseValid = isset($curlResponse['results'][0]['vehicle']['vh_class_desc']);
        }
        if (!$isResponseValid) {
            $service = "online";
            $get_response = getFastLaneData($url . '?' . 'regn_no=' . $registration_no, [
                'requestMethod' => 'get',
                'enquiryId' => $userProductJourneyId,
                'transaction_type' => 'Fast Lane Service',
                'username' => $username,
                'password' => $password,
                'reg_no' => $registration_no,
                'section' => trim($request->section),
                'type' => 'input',
            ]);
            $curlResponse = $get_response['response'];
            if (empty($curlResponse)) {
                $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'API not working.');
                return response()->json([
                    'status' => false,
                    'msg' => 'API not working.',
                    'data' => [
                        'dataSource' => $service,
                    ],
                ]);
            }
            if (stripos($curlResponse, 'Bad credentials') !== false) {
                $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'Bad Credentials.');
                return response()->json([
                    'status' => false,
                    'msg' => $curlResponse,
                    'data' => [
                        'dataSource' => $service,
                    ],
                ]);
            }
            $curlResponse = json_decode($curlResponse, true);
            $curlResponse['dataSource'] = $service;
            if ($curlResponse['status'] != 100) {
                $curlResponse['status'] = 101;
                $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'Record not found.');
                return response()->json([
                    'status' => false,
                    'msg' => 'Record not found.',
                    'data' => $curlResponse,
                ]);
            }
            if (isset($curlResponse['results'][0]['vehicle']['vehicle_cd']) && $curlResponse['results'][0]['vehicle']['vehicle_cd'] != null) {
                DB::table('registration_details')->insert([
                    'vehicle_reg_no' => $registration_no,
                    'vehicle_details' => json_encode($curlResponse),
                    'created_at' => date('Y-m-d H:i:s'),
                    'expiry_date' => isset($curlResponse['results'][0]['insurance']['insurance_upto']) ? Carbon::parse(str_replace('/', '-', $curlResponse['results'][0]['insurance']['insurance_upto']))->format('Y-m-d') : null,
                ]);
            }
        }
        $curlResponse['dataSource'] = $service;
        if ($curlResponse['status'] != 100) {
            $curlResponse['status'] = 101;
            $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'Record not found.');
            return response()->json([
                'status' => false,
                'msg' => 'Record not found.',
                'data' => $curlResponse,
            ]);
        }
        $curlResponse['old_status'] = $curlResponse['status'];
        $vehicle_details = $curlResponse['results'][0];
        $fast_lane_product_code = $curlResponse['results'][0]['vehicle']['fla_vh_class_desc'] ?? '';

        $ft_product_code = '';

        $categoryResponse = self::getVehicleCategory($request, $curlResponse['results'][0]['vehicle']);

        $ft_product_code = $categoryResponse['productSubType'];
        $productSubType = $categoryResponse['productSubTypeID'];

        // 2W, 2WT, 3W, CV, E-RIC, MISC, PCV, PV, TRACTOR - #19893
        if (!$categoryResponse['status']) {
            if (in_array($fast_lane_product_code, ['LMV', 'PV'])) {
                $ft_product_code = 'car';
                $productSubType = '1';
            } else if (in_array($fast_lane_product_code, ['2W', '2WT'])) {
                $ft_product_code = 'bike';
                $productSubType = '2';
            } else if ($fast_lane_product_code == 'PCV') {
                $ft_product_code = 'pcv';
                $productSubType = '6';
            } else if ($fast_lane_product_code == 'GCV') {
                $ft_product_code = 'gcv';
                $productSubType = '9';
            } else if ($fast_lane_product_code == 'E-RIC') {
                $ft_product_code = 'pcv';
                $productSubType = '11';
            } else if ($fast_lane_product_code == 'TRACTOR') {
                $ft_product_code = 'pcv';
                $productSubType = '15';
            } else if ($fast_lane_product_code == '3W' && in_array($code = Str::substr($vehicle_details['vehicle']['vehicle_cd'] ?? '', 0, 5), ['PCV3W', 'GCV3W'])) {
                if ($code == 'PCV3W') {
                    $ft_product_code = 'pcv';
                    $productSubType = '5';
                } else {
                    $ft_product_code = 'gcv';
                    $productSubType = '9';
                }
            }
        }

        if (empty($vehicle_details['vehicle']['vehicle_cd'])) {
            if (in_array($ft_product_code, ['pcv', 'gcv'])) {
                $ft_product_code = 'cv';
            }
            $curlResponse['status'] = 101;
            $curlResponse['ft_product_code'] = $ft_product_code;
            $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'Vehicle code not found.');
            return response()->json([
                'status' => false,
                'msg' => 'Vehicle code not found.',
                'data' => $curlResponse,
            ]);
        }
        $this->updateLogData($get_response['webservice_id'] ?? null, 'Success');
        //$vehicle_code1 = $vehicle_details['vehicle']['vehicle_cd'];

        $fast_lane_version_code = $vehicle_details['vehicle']['vehicle_cd'];
        $version_code = '';
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
        } else if ($env == 'test' || $env == 'live') {
            $env_folder = 'production';
        }

        $product = strtolower(get_parent_code($productSubType));
        $product = $product == 'car' ? 'motor' : $product;
        if (empty($product)) {
            $product = strtolower($ft_product_code);
        }

        $path = 'mmv_masters/' . $env_folder . '/';
        $file_name = $path . $product . '_model_version.json';
        $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
        // if ($data) {
        //     foreach ($data as $value)
        //     {
        //         if ($product == 'bike' && $value['mmv_fastlane_2wl'] == $fast_lane_version_code) {
        //             $version_code = $value['version_id'];
        //             break;
        //         }
        //         else if($product == 'motor' && $value['mmv_fastlane_car'] == $fast_lane_version_code) {
        //             $version_code = $value['version_id'];
        //             break;
        //         }
        //     }
        // }
        $is_version_available = "Y";
        if ($ft_product_code == 'car' || $ft_product_code == 'bike') {
            $file_name = $path . 'fyntune_fastlane_' . $ft_product_code . '_relation.json';
            $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
            $is_version_available = $data[$fast_lane_version_code]['is_version_available'] ?? null;
            $version_code = $data[$fast_lane_version_code]['fyntune_version_id'] ?? '';
        } else {
            $version_code = $fast_lane_version_code;
        }

        $registration_date = date('d-m-Y', strtotime(str_replace('/', '-', $vehicle_details['vehicle']['regn_dt'])));
        if (isset($vehicle_details['insurance']['insurance_upto']) && !empty($vehicle_details['insurance']['insurance_upto'])) {
            $policy_expiry = date('d-m-Y', strtotime(str_replace('/', '-', $vehicle_details['insurance']['insurance_upto'])));

            $date1 = new \DateTime($registration_date);
            $date2 = new \DateTime(date('d-m-Y'));
            $interval = $date1->diff($date2);
            $car_age = (($interval->y * 12) + $interval->m) + 1; // In Months
            $car_age = $car_age / 12;
            $previous_ncb = 0;

            if ($car_age <= 1) {
                $previous_ncb = 0;
                $applicable_ncb = 20;
            } else if ($car_age <= 2) {
                $previous_ncb = 20;
                $applicable_ncb = 25;
            } else if ($car_age <= 3) {
                $previous_ncb = 25;
                $applicable_ncb = 35;
            } else if ($car_age <= 4) {
                $previous_ncb = 35;
                $applicable_ncb = 45;
            } else if ($car_age <= 5) {
                $previous_ncb = 45;
                $applicable_ncb = 50;
            } else {
                $previous_ncb = 50;
                $applicable_ncb = 50;
            }

//            if($car_age < 20){
//                $previous_ncb = 0;
//                $applicable_ncb = 20;
//            }elseif($car_age < 32){
//                $previous_ncb = 20;
//                $applicable_ncb = 25;
//            }elseif($car_age < 44){
//                $previous_ncb = 25;
//                $applicable_ncb = 35;
//            }elseif($car_age < 56){
//                $previous_ncb = 35;
//                $applicable_ncb = 45;
//            }elseif($car_age < 68){
//                $previous_ncb = 45;
//                $applicable_ncb = 50;
//            }else{
//                $previous_ncb = 50;
//                $applicable_ncb = 50;
//            }

        } else {
            $policy_expiry = null;
            $previous_ncb = null;
            $applicable_ncb = null;
        }

        $invoiceDate = $registration_date;
        #sometimes we are getting maf year as 0 04-11-2022@hrishikesh
        $manf_year = date('m-Y', strtotime(str_replace('/', '-', $vehicle_details['vehicle']['regn_dt'])));
        if (!empty($vehicle_details['vehicle']['manu_yr'])) {
            $manf_year = date('m', strtotime($registration_date)) . '-' . $vehicle_details['vehicle']['manu_yr'];
            // $invoiceDate = '01-'.$manf_year;
        }

        $reg_no = explode('-', $registration_no);
        $rto_code = implode('-', [$reg_no[0], $reg_no[1]]);
        $rto_name = MasterRto::where('rto_code', $rto_code)->pluck('rto_name')->first();

        // Fetch company alias from
        $previous_insurer = $previous_insurer_code = null;

        $ncb_previous_ic_popup_disabled = config('NCB_PREVIOUS_IC_POPUP_DISABLED') == 'Y';
        $ncb_previous_ic_popup_seller_types = [];

        $cv_agent_mappings = CvAgentMapping::where('user_product_journey_id', $userProductJourneyId)->first();

        if (config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES')) {
            $ncb_previous_ic_popup_seller_types = explode(',', config('NCB_PREVIOUS_IC_POPUP_SELLER_TYPES'));

            if (!empty($cv_agent_mappings->user_id)) {
                array_push($ncb_previous_ic_popup_seller_types, null);
            }
        }

        if ((!$ncb_previous_ic_popup_disabled || ($ncb_previous_ic_popup_disabled && !in_array($cv_agent_mappings?->seller_type, $ncb_previous_ic_popup_seller_types))) && (isset($vehicle_details['insurance']['insurance_comp']) || isset($vehicle_details['insurance']['fla_insurance_comp']))) {
            $fastlane_ic_name = (isset($vehicle_details['insurance']['insurance_comp']) && !empty($vehicle_details['insurance']['insurance_comp']) ? $vehicle_details['insurance']['insurance_comp'] : $vehicle_details['insurance']['fla_insurance_comp']);
            if (!empty($fastlane_ic_name)) {
                $fyntune_ic = DB::table('fastlane_previous_ic_mapping as m')->whereRaw('? LIKE CONCAT("%", m.identifier, "%")', $fastlane_ic_name)->first();
                if ($fyntune_ic) {
                    $previous_insurer = $fyntune_ic->company_name;
                    $previous_insurer_code = $fyntune_ic->company_alias;
                }
            }
        }
        $expiry_date = \Carbon\Carbon::parse($policy_expiry);
        $today_date = now()->subDay(1);

        if ($expiry_date < $today_date) {
            $businessType = 'breakin';
            $diff = $expiry_date->diffInDays(now());
            if ($diff > 90) {
                $previous_ncb = 0;
                $applicable_ncb = 0;
            }
        } else {
            $businessType = 'rollover';
        }

        if ($ft_product_code == 'car') {
            $od_compare_date = now()->subYear(3)->subDay(45);
        } else if ($ft_product_code == 'bike') {
            $od_compare_date = Carbon::parse('01-09-2018')->subDay(1);
        } else {
            $od_compare_date = now();
        }

        if (Carbon::parse($registration_date) > $od_compare_date) {
            $policy_type = 'own_damage';
        } else {
            $policy_type = 'comprehensive';
        }

        $renewal_days = today()->addDay(60);
        if (Carbon::parse($policy_expiry)->format('Y-m-d') > $renewal_days) {
            $policy_expiry = '';
        }

        if ($policy_type == 'own_damage') {
            $policy_expiry = '';
        }
        $previous_policy_type = 'Comprehensive';
        if(config('newBreakinLogic.Enabled') == 'Y')
        {
            $manufactureDate = '01-' . $manf_year;
            $newBreakinData = $this->newBreakinLogicHandle($registration_date,$manufactureDate);
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
        CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
            'version_id' => $is_version_available == "Y" ? $version_code : null,
            'policy_type' => $policy_type,
            'business_type' => $businessType,
            'vehicle_register_date' => $vehicle_details['vehicle']['regn_dt'] == '' ? null : $registration_date,
            'vehicle_registration_no' => $registration_no,
            'previous_policy_expiry_date' => $policy_expiry, //'11-11-2021',
            'previous_policy_type' => $previous_policy_type,
            'fuel_type' => $vehicle_details['vehicle']['fla_fuel_type_desc'], //'PETROL',
            'manufacture_year' => $manf_year,
            'vehicle_invoice_date' => $invoiceDate,
            'rto_code' => isBhSeries($rto_code) ? null : $rto_code,
            'rto_city' => isBhSeries($rto_name) ? null : $rto_name,
            'previous_ncb' => $previous_ncb,
            'applicable_ncb' => $applicable_ncb,
            'previous_insurer' => $previous_insurer,
            'previous_insurer_code' => $previous_insurer_code,
            'journey_type' => self::VAHAN_SERVICE_CODE,
            'zero_dep_in_last_policy' => 'Y',
            'is_ncb_verified' => $applicable_ncb > 0 ? 'Y' : 'N',
        ]);

        // TODO : Fetch Commercial vehicle's product subtype id
        $quote_log = QuoteLog::where('user_product_journey_id', $userProductJourneyId)->first();
        if (!$quote_log) {
            $curlResponse['status'] = 101;
            return response()->json([
                'status' => false,
                'msg' => 'Quote log entry not found.',
                'data' => $curlResponse,
            ]);
        }

        // $mmv_details = get_fyntune_mmv_details($request->productSubType, $version_code);
        // We'll pass only initials of version code, in return we'll get the id and mmv details - @AmitE0156 16-10-2023
        $mmv_details = get_fyntune_mmv_details(Str::substr($version_code, 0, 3), $version_code);
        if (!$mmv_details['status']) {
            //If journey is of car and bike RC number is searched then we'll redirect it to bike page and again hit the service, for that we need to pass 'status' as 100 - @Amit:08-03-2022
            //$curlResponse['status'] = $ft_product_code != '' ? 100 : 101;
            if (in_array($ft_product_code, ['pcv', 'gcv'])) {
                $ft_product_code = 'cv';
            }
            $curlResponse['ft_product_code'] = $ft_product_code;
            $status_bool = true;
            $curlResponse['status'] = 100;

            if (strtolower($ft_product_code) == strtolower($request->section)) {
                $status_bool = false;
                $curlResponse['status'] = 101;
            }

            if ($version_code == null) {
                $status_bool = false;
                $curlResponse['status'] = 101;
            }
            return response()->json([
                'status' => $status_bool, //$ft_product_code != '' ? true : false,
                'msg' => 'MMV details not found. - ' . $version_code,
                'data' => $curlResponse,
            ]);
        }
        $mmv_details = $mmv_details['data'];
        // $request->productSubType = $productSubType;
        if (!in_array($request->productSubType, ['1', '2']) && !empty($mmv_details['product_sub_type_id'])) {
            $productSubType = $mmv_details['product_sub_type_id'];
        }

        $quote_data = [
            "product_sub_type_id" => $productSubType,
            "manfacture_id" => $mmv_details['manufacturer']['manf_id'],
            "manfacture_name" => $mmv_details['manufacturer']['manf_name'],
            "model" => $mmv_details['model']['model_id'],
            "model_name" => $mmv_details['model']['model_name'],
            "version_id" => $is_version_available == "Y" ? $version_code : null,
            "vehicle_usage" => 2,
            "policy_type" => $policy_type,
            "business_type" => $businessType,
            "version_name" => $is_version_available == "Y" ? $mmv_details['version']['version_name'] : null,
            "vehicle_register_date" => $registration_date,
            "previous_policy_expiry_date" => $policy_expiry,
            "previous_policy_type" => $previous_policy_type,
            "fuel_type" => $is_version_available == "Y" ? $mmv_details['version']['fuel_type'] : null,
            "manufacture_year" => $manf_year,
            "rto_code" => isBhSeries($rto_code) ? null : $rto_code,
            "vehicle_owner_type" => "I",
            "is_claim" => "N",
            "previous_ncb" => $previous_ncb,
            "applicable_ncb" => $applicable_ncb,
        ];

        $quote_log->quote_data = json_encode($quote_data);
        $quote_log->save();

        $curlResponse['results'][0]['vehicle']['regn_dt'] = $registration_date;
        $curlResponse['results'][0]['vehicle']['vehicle_cd'] = $is_version_available == "Y" ? $version_code : null;
        $curlResponse['results'][0]['vehicle']['fla_maker_desc'] = $quote_data['manfacture_name'];
        $curlResponse['results'][0]['vehicle']['fla_model_desc'] = $quote_data['model_name'];
        $curlResponse['results'][0]['vehicle']['fla_variant'] = $is_version_available == "Y" ? $quote_data['version_name'] : null;
        $curlResponse['results'][0]['insurance']['insurance_upto'] = $policy_expiry;

        $return_data = [
            'firstName' => $vehicle_details['vehicle']['owner_name'] ?? null,
            'lastName' => '',
            'fullName' => $vehicle_details['vehicle']['owner_name'] ?? null,
            'mobileNo' => '',
            'applicableNcb' => $applicable_ncb,
            'businessType' => $businessType,
            'emailId' => '',
            'enquiryId' => $request->enquiryId,
            'fuelType' => $quote_data['fuel_type'],
            'hasExpired' => "no",
            'isClaim' => "N",
            'isNcb' => "Yes",
            'leadJourneyEnd' => true,
            'manfactureId' => $quote_data['manfacture_id'],
            'manfactureName' => $quote_data['manfacture_name'],
            'manufactureYear' => $quote_data['manufacture_year'],
            'model' => $quote_data['model'],
            'modelName' => $quote_data['model_name'],
            'ownershipChanged' => "N",
            'policyExpiryDate' => $policy_expiry,
            'policyType' => $policy_type,
            'previousNcb' => $previous_ncb,
            'previousPolicyType' => $previous_policy_type,
            'productSubTypeId' => $productSubType,
            'rto' => isBhSeries($rto_code) ? null : $rto_code,
            'stage' => 11,
            'userProductJourneyId' => $request->enquiryId,
            //'vehicleLpgCngKitValue' => "",
            'vehicleOwnerType' => "I",
            'vehicleRegisterAt' => isBhSeries($rto_code) ? null : $rto_code,
            'vehicleRegisterDate' => $registration_date,
            'vehicleInvoiceDate' => $invoiceDate,
            'vehicleRegistrationNo' => $registration_no,
            'vehicleUsage' => 2,
            'version' => $is_version_available == "Y" ? $version_code : null,
            'versionName' => $is_version_available == "Y" ? $quote_data['version_name'] : null,
            'previous_insurer' => $previous_insurer,
            'previous_insurer_code' => $previous_insurer_code,
            'address_line1' => $vehicle_details['vehicle']['cAddress'] ?? null,
            'address_line2' => '',
            'address_line3' => '',
            'pincode' => '',
        ];
        $is_vehicle_finance = '';
        $name_of_financer = '';
        if (isset($vehicle_details['hypth']['fncr_name']) && $vehicle_details['hypth']['fncr_name'] != null) {
            $is_vehicle_finance = 1;
            $name_of_financer = $vehicle_details['hypth']['fncr_name'];
        }
        $curlResponse['additional_details'] = $return_data;
        $curlResponse['additional_details']['vahan_service_code'] = self::VAHAN_SERVICE_CODE;

        $proposalData = [
            'first_name' => $vehicle_details['vehicle']['owner_name'] ?? null,
            'last_name' => '',
            'fullName' => $vehicle_details['vehicle']['owner_name'] ?? null,
            'applicable_ncb' => $applicable_ncb,
            'is_claim' => 'N',
            'rto_location' => isBhSeries($rto_code) ? null : $rto_code,
            // 'additional_details' => json_encode($return_data),
            'vehicale_registration_number' => $registration_no,
            'vehicle_manf_year' => $quote_data['manufacture_year'],
            'previous_insurance_company' => $previous_insurer,
            'prev_policy_expiry_date' => $policy_expiry,
            'engine_number' => removeSpecialCharactersFromString($vehicle_details['vehicle']['eng_no']),
            // 'chassis_number' => removeSpecialCharactersFromString($vehicle_details['vehicle']['chasi_no']),
            'chassis_number' => $vehicle_details['vehicle']['chasi_no'],
            'previous_policy_number' => isset($vehicle_details['insurance']['insurance_policy_no']) ? $vehicle_details['insurance']['insurance_policy_no'] : '',
            'vehicle_color' => $vehicle_details['vehicle']['color'],
            'is_vehicle_finance' => $is_vehicle_finance,
            'name_of_financer' => $name_of_financer,
            'full_name_finance' => $name_of_financer,
            'address_line1' => $vehicle_details['vehicle']['cAddress'] ?? null,
            'address_line2' => '',
            'address_line3' => '',
            // 'pincode'                       => ''
            'puc_no' => $vehicle_details['vehicle']['pucc_no'] ?? null,
            'puc_expiry' => isset($vehicle_details['vehicle']['pucc_upto']) ? date('d-m-Y', strtotime(str_replace('/', '-', $vehicle_details['vehicle']['pucc_upto']))) :  null,
        ];

        if (config('constants.motorConstant.AUTO_FILL_TP_DETAILS_IN_VAHAN') == 'Y') {
            $tpExpiryDate = null;

            if (!empty($vehicle_details['insurance']['insurance_upto'])) {
                $tpExpiryDate = date('d-m-Y', strtotime(str_replace('/', '-', $vehicle_details['insurance']['insurance_upto'])));
            }

            $proposalData = array_merge($proposalData, [
                // 'tp_start_date'                         => $tpExpiryDate,
                'tp_end_date'                           => $tpExpiryDate,
                'tp_insurance_company'                  => $previous_insurer_code ?? null,
                'tp_insurance_company_name'             => $previous_insurer ?? null,
                'tp_insurance_number'                   => $vehicle_details['insurance']['insurance_policy_no'] ?? null,
            ]);
        }

        UserProposal::updateOrCreate(
            ['user_product_journey_id' => $userProductJourneyId],
            $proposalData);

        if (config('constants.motorConstant.SMS_FOLDER') == 'bajaj' && isset($request->is_renewal) && $request->is_renewal == 'Y' && config('FETCH_RENEWAL_DATA_FROM_MIGRATION_TABLE') == 'Y') {
            $previous_policy_type_renewal = [
                'Comprehensive' => 'COMPREHENSIVE',
                'Third-party' => 'TP',
                'Own-damage' => 'OD',
            ];

            $previouspolicytype = array_search($bajaj_renewal_data->PREV_POLICY_TYPE, $previous_policy_type_renewal);

            $curlResponse['additional_details']['previousInsurer'] = $bajaj_renewal_data->previous_insurer;
            $curlResponse['additional_details']['previous_insurer'] = $bajaj_renewal_data->previous_insurer;
            $curlResponse['additional_details']['previous_insurer_code'] = $bajaj_renewal_data->previous_insurer_code;
            $curlResponse['additional_details']['previousInsurerCode'] = $bajaj_renewal_data->previous_insurer_code;
            $curlResponse['additional_details']['policyExpiryDate'] = $bajaj_renewal_data->POLICY_END_DATE;
            $curlResponse['RenwalData'] = 'Y';
            UserProposal::updateOrCreate(
                ['user_product_journey_id' => $userProductJourneyId],
                [
                    'previous_policy_number' => $bajaj_renewal_data->POLICY_NUMBER,
                    'prev_policy_expiry_date' => $bajaj_renewal_data->POLICY_END_DATE,
                ]);

            $expiry_date_renewal = \Carbon\Carbon::parse($bajaj_renewal_data->POLICY_END_DATE);
            $today_date_renewal = now()->subDay(1);
            if ($expiry_date_renewal < $today_date_renewal) {
                $businessType = 'breakin';
            } else {
                $businessType = 'rollover';
            }
            $curlResponse['additional_details']['businessType'] = $businessType;
            CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->update([
                'previous_policy_type' => !empty($previouspolicytype) ? $previouspolicytype : 'Comprehensive',
                'previous_policy_expiry_date' => $bajaj_renewal_data->POLICY_END_DATE,
                'business_type' => $businessType,
            ]);

            $curlResponse['additional_details']['previousPolicyType'] = !empty($previouspolicytype) ? $previouspolicytype : 'Comprehensive';
        }
        if (in_array($ft_product_code, ['pcv', 'gcv'])) {
            $ft_product_code = 'cv';
        }

        $serialNumber = $curlResponse['results'][0]['vehicle']['owner_sr'] ?? null;
        if ($serialNumber) {
            ProposalExtraFields::updateOrCreate(
                ['enquiry_id' => $userProductJourneyId], 
                [
                    'vahan_serial_number_count' => $serialNumber,
                ]
            );
        }
        $curlResponse['ft_product_code'] = $ft_product_code;
        return response()->json([
            'status' => true,
            'msg' => $curlResponse['description'],
            'data' => $curlResponse,
        ]);
    }

    public function validateVehicleService(Request $request)
    {
        $userProductJourneyId = customDecrypt($request->enquiryId);

        $module_code = $this->getCredential('vahan.fastlane.attribute.module_code');
        if (!empty($module_code)) {
            $existing_record = ProposalVehicleValidationLogs::select('response')
                ->where('vehicle_reg_no', $request->registration_no)
                ->where('service_type', self::VAHAN_SERVICE_CODE)
                ->orderBy('id', 'DESC')->first();
        } else {
            $existing_record = DB::table('registration_details')
                ->where('vehicle_reg_no', $request->registration_no)
                ->orderBy('id', 'DESC')
                ->select('vehicle_details as response')
                ->first();
        }
        $isResponseValid = false;
        if (!empty($existing_record)) {
            $tempCurlResponse = json_decode($existing_record->response, true);
            //In Some Broker 'vh_class_desc' tag giving null so assign 'fla_vh_class_desc' value
            if (isset($tempCurlResponse['results'][0]['vehicle']['fla_vh_class_desc']) && empty($tempCurlResponse['results'][0]['vehicle']['vh_class_desc'])) {
                $tempCurlResponse['results'][0]['vehicle']['vh_class_desc'] = $tempCurlResponse['results'][0]['vehicle']['fla_vh_class_desc'];
            }
            // If we get the vh_class_desc tag in response then proceed else hit vahan API again.
            $isResponseValid = isset($tempCurlResponse['results'][0]['vehicle']['vh_class_desc']);
            unset($tempCurlResponse);
        }

        if ($isResponseValid) {
            $service = 'offline';
            $curlResponse = json_decode($existing_record->response, true);
            if (isset($curlResponse['results'][0]['vehicle']['fla_vh_class_desc']) && empty($curlResponse['results'][0]['vehicle']['vh_class_desc'])) {
                $curlResponse['results'][0]['vehicle']['vh_class_desc'] = $curlResponse['results'][0]['vehicle']['fla_vh_class_desc'];
            }
        } else {
            $service = 'online';
            $url = $this->getCredential('vahan.fastlane.attribute.url');
            $build_query = [
                'regn_no' => $request->registration_no,
            ];
            if (!empty($module_code)) {
                $build_query['module_code'] = $module_code;
            }
            $url .= '?' . http_build_query($build_query);
            $username = $this->getCredential('vahan.fastlane.attribute.username');
            $password = $this->getCredential('vahan.fastlane.attribute.password');
            // Logs get stored into fastlane_request_response after using getWsData function.
            // $get_response = getWsData($url, '', 'fastlane', [
            $get_response = getFastLaneData($url, [
                'requestMethod' => 'get',
                'enquiryId' => $userProductJourneyId,
                'transaction_type' => 'Fast Lane Service',
                'username' => $username,
                'password' => $password,
                'reg_no' => $request->registration_no,
                'section' => trim($request->section),
                'headers' => [
                    "Content-type" => "application/json",
                    "Accept" => "application/json",
                    "Authorization" => "Basic " . base64_encode($username . ':' . $password),
                ],
                'type' => 'proposal',
            ]);
            $curlResponse = $get_response['response'];

            if (empty($curlResponse)) {
                $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'Empty response from fastlane.');
                return response()->json([
                    'status' => false,
                    'msg' => 'Empty response from fastlane.',
                    'data' => [
                        'status' => 101,
                        'dataSource' => $service,
                    ],
                ]);
            }
            if (stripos($curlResponse, 'Bad credentials') !== false) {
                $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'Bad Credentials');
                return response()->json([
                    'status' => false,
                    'msg' => 'Bad Credentials',
                    'data' => [
                        'status' => 101,
                        'dataSource' => $service,
                    ],
                ]);
            }
            $curlResponse = json_decode($curlResponse, true);
            $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'Empty response from fastlane.');
            if (empty($curlResponse['results'])) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Empty response from fastlane.',
                    'data' => [
                        'status' => 101,
                        'dataSource' => $service,
                    ],
                ]);
            }
            if (isset($curlResponse['results'][0]['vehicle']['fla_vh_class_desc']) && empty($curlResponse['results'][0]['vehicle']['vh_class_desc'])) {
                $curlResponse['results'][0]['vehicle']['vh_class_desc'] = $curlResponse['results'][0]['vehicle']['fla_vh_class_desc'];
            }
            if ($curlResponse['status'] != 100) {
                $this->updateLogData($get_response['webservice_id'] ?? null, 'Failed', 'Record not found.');
                return response()->json([
                    'status' => false,
                    'msg' => 'Record not found.',
                    'data' => [
                        'status' => 101,
                        'dataSource' => $service,
                    ],
                ]);
            }
            if (isset($curlResponse['results'][0]['vehicle']['vehicle_cd']) && $curlResponse['results'][0]['vehicle']['vehicle_cd'] != null && empty($module_code)) {
                DB::table('registration_details')->insert([
                    'vehicle_reg_no' => $request->registration_no,
                    'vehicle_details' => json_encode($curlResponse),
                    'created_at' => date('Y-m-d H:i:s'),
                    'expiry_date' => isset($curlResponse['results'][0]['insurance']['insurance_upto']) ? Carbon::parse(str_replace('/', '-', $curlResponse['results'][0]['insurance']['insurance_upto']))->format('Y-m-d') : null,
                ]);
            } else {
                ProposalVehicleValidationLogs::insert([
                    'vehicle_reg_no' => $request->registration_no,
                    'response' => json_encode($curlResponse),
                    'service_type' => self::VAHAN_SERVICE_CODE,
                    'endpoint_url' => $url,
                    'created_at' => now(),
                ]);
            }
        }
        $fast_lane_product_code = trim($curlResponse['results'][0]['vehicle']['vh_class_desc'] ?? '');
        if (self::isVehicleCategoryBlocked($fast_lane_product_code)) {
            $this->updateLogData($get_response['webservice_id'] ?? null, 'Success');
            return response()->json([
                'status' => false,
                'msg' => self::$blockConstantString,
                'data' => [
                    'status' => self::$blockStatusCode,
                    'dataSource' => $service,
                ],
            ]);
        }

        $ft_product_code = '';
        $vehicle_section = FastlaneVehDescripModel::where('description', $fast_lane_product_code)->first();
        if (!empty($vehicle_section)) {
            $ft_product_code = trim($vehicle_section->section);
        } else if (stripos($fast_lane_product_code, 'LMV') !== false) {
            $ft_product_code = 'car';
        } else if (stripos($fast_lane_product_code, 'LPV') !== false) {
            $ft_product_code = 'cv';
        } else if (stripos($fast_lane_product_code, '2W') !== false) {
            $ft_product_code = 'bike';
        }
        $curlResponse['ft_product_code'] = $ft_product_code;
        $curlResponse['status'] = 101;
        $curlResponse['dataSource'] = $service;

        // If journey is of car and bike RC number is searched then we'll redirect it to bike page,
        // and again hit the service, for that we need to pass 'status' as 100 - @Amit:08-03-2022
        if (strtolower($ft_product_code) == strtolower($request->section)) {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test' || $env == 'live') {
                $env_folder = 'production';
            }
            $productSubType = $request->productSubType;
            $product = strtolower(get_parent_code($productSubType));
            $product = $product == 'car' ? 'motor' : $product;
            if (empty($product)) {
                $product = strtolower($ft_product_code);
            }
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name = $path . $product . '_model_version.json';
            $vehicle_details = $curlResponse['results'][0];
            $fast_lane_version_code = $vehicle_details['vehicle']['vehicle_cd'];
            
            if ($ft_product_code == 'car' || $ft_product_code == 'bike') {
                $file_name = $path . 'fyntune_fastlane_' . $ft_product_code . '_relation.json';
                $data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
                $version_code = $data[$fast_lane_version_code]['fyntune_version_id'] ?? '';
            } else {
                $version_code = $fast_lane_version_code;
            }
            
            if (!empty($version_code)) {
                $ValidateQuoteAndVahanMmv = \App\Helpers\VahanHelper::validateQuouteAndVahanMMv($request, $version_code, $userProductJourneyId);
                if (!$ValidateQuoteAndVahanMmv['status']) {
                    return response()->json($ValidateQuoteAndVahanMmv);
                }
            }
            $curlResponse['status'] = 100;
        } else {
            $curlResponse['status'] = 100;
            if (empty($ft_product_code)) {
                $row_exists = FastlaneVehDescripModel::where("description", $fast_lane_product_code)
                    ->where('section', null)
                    ->first();
                if (!$row_exists && !empty(trim($fast_lane_product_code))) {
                    FastlaneVehDescripModel::insert([
                        "section" => null,
                        "description" => $fast_lane_product_code,
                    ]);
                }
                // If FT code is empty we need to pass status code as 101 only in data array tag - @Amit:18-10-2022
                unset($curlResponse);
                $curlResponse['status'] = 101;
                $curlResponse['description'] = 'Unable to match vehicle description.';
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
        $this->updateLogData($get_response['webservice_id'] ?? null, 'Success');
        return response()->json([
            'status' => true,
            'msg' => $curlResponse['status'] == 100 ? $curlResponse['description'] : 'Vehicle validation failed.',
            'data' => $curlResponse,
        ]);
    }
    public static function isVehicleCategoryBlocked($vehicleDescription = '')
    {
        $categories = config('proposalPage.vehicleValidation.blockedCategories');
        $blockedVehicles = empty($categories) ? [] : explode(',', $categories);
        return in_array($vehicleDescription, $blockedVehicles);
    }

    public static function getVehicleCategory($request, $data)
    {
        $productSubTypeFound = false;
        $productSubType = 'pcv';
        $productSubTypeID = '6';

        $fast_lane_product_code = $data['fla_vh_class_desc'];
        $subcode = Str::substr($data['vehicle_cd'] ?? '', 0, 5);
        // CHECK THROUGH VERSION ID PREFIX
        if (!empty($data['vehicle_cd'])) {
            switch (\Illuminate\Support\Str::substr($data['vehicle_cd'], 0, 3)) {
                case 'PCV':
                    $productSubTypeFound = true;
                    $productSubType = 'pcv';
                    $productSubTypeID = '6';
                    if ($subcode == 'PCV3W') { // This is a PCV 3 Wheeler normal rickshaw
                        $productSubTypeID = '5';
                    }
                    if ($fast_lane_product_code == 'E-RIC') { // This is a PCV 3 Wheeler electric rickshaw
                        $productSubTypeID = '11';
                    }
                    break;
                case 'GCV':
                    $productSubTypeFound = true;
                    $productSubType = 'gcv';
                    $productSubTypeID = '9';
                    // For GCV3W the product subtype code will be 9
                    if ($fast_lane_product_code == 'TRACTOR') {
                        $productSubTypeID = '15';
                    }
                    break;
                case 'CRP':
                    $productSubTypeFound = true;
                    $productSubType = 'car';
                    $productSubTypeID = '1';
                    break;
                case 'BYK':
                    $productSubTypeFound = true;
                    $productSubType = 'bike';
                    $productSubTypeID = '2';
                    break;
                default:
                    $productSubTypeFound = false;
                    break;
            }
            if ($productSubTypeFound) {
                return [
                    'status' => $productSubTypeFound,
                    'productSubType' => $productSubType,
                    'productSubTypeID' => $productSubTypeID,
                ];
            }
        }
        // END CHECK THROUGH VERSION ID PREFIX

        // CHECK THROUGH CATEGORY DESCRIPTION
        // if($data['vh_class_desc'])
        // {
        //     $vehicle_section = FastlaneVehDescripModel::where('description', $data['vh_class_desc'])->first();
        //     if (!empty($vehicle_section)) {
        //         $section_found = true;
        //         $productSubType = $vehicle_section->section;
        //         $productSubTypeID = '';
        //     } else if (self::findMyWord($data['vh_class_desc'], 'LMV') || self::findMyWord($data['vh_class_desc'], 'Motor Car')) {
        //         $section_found = true;
        //         $productSubType = 'car';
        //         $productSubTypeID = '1';
        //     } else if (self::findMyWord($data['vh_class_desc'], 'SCOOTER') || self::findMyWord($data['vh_class_desc'], '2WN')) {
        //         $section_found = true;
        //         $productSubType = 'bike';
        //         $productSubTypeID = '2';
        //     }
        // }

        if (!empty($data['fla_vh_class_desc'])) {

            switch (strtoupper($data['fla_vh_class_desc'])) {
                case 'PCV':
                    $productSubTypeFound = true;
                    $productSubType = 'pcv';
                    $productSubTypeID = '6';
                    break;
                case 'GCV':
                    $productSubTypeFound = true;
                    $productSubType = 'gcv';
                    $productSubTypeID = '9';
                    break;
                case 'LMV':
                    $productSubTypeFound = true;
                    $productSubType = 'car';
                    $productSubTypeID = '1';
                    break;
                case '2W':
                    $productSubTypeFound = true;
                    $productSubType = 'bike';
                    $productSubTypeID = '2';
                    break;
                default:
                    $productSubTypeFound = false;
                    break;
            }
            if ($productSubTypeFound) {
                return [
                    'status' => $productSubTypeFound,
                    'productSubType' => $productSubType,
                    'productSubTypeID' => $productSubTypeID,
                ];
            }
        }

        return [
            'status' => $productSubTypeFound,
            'productSubType' => $productSubType,
            'productSubTypeID' => $productSubTypeID,
        ];
        // END CHECK THROUGH CATEGORY DESCRIPTION
    }
}
