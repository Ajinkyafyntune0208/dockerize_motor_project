<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Exports\PolicyReportsExport;
use App\Http\Controllers\Controller;
use App\Models\IcProduct;
use App\Models\MasterCompany;
use App\Models\MasterPremiumType;
use App\Models\MasterProductSubType;
use App\Models\ProposalFields;
use App\Models\ConfigSetting;
use App\Models\MasterPolicy;
use App\Models\MasterProduct;
use App\Models\AuthorizationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use App\Models\user;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\IcConfigApprovalMail;
use App\Models\PremCalcLabel;
use App\Models\PremCalcMappedAttribute;
use App\Models\PremCalcAttributes;
use Illuminate\Support\Facades\Crypt;
use App\Models\IcVersionConfigurator;
use App\Models\PremCalcPlaceholder;
use App\Models\PremCalcFormula;
use App\Models\PremCalcConfigurator;
use App\Models\IcIntegrationType;
use App\Models\IcVersionActivation;
// use App\Models\IcConfigratorLabel;

class IcConfiguratorController extends Controller
{
    public function index()
    {

        $alias = MasterCompany::select('master_company.company_alias', 'master_company.company_name')
            ->where('status', 'Active')
            ->whereNotNull('company_alias')
            ->orderBy('company_alias')
            ->get();
        return view('admin_lte.ic_configurator.index', ['company' => $alias,'user_details' => auth()->user()]);
    }

    public function fetching(Request $request)
    {
        $companyAlias = $request->input('company_alias');


        $company = MasterCompany::select('master_company.company_alias',
        'ic_credentials.config_name',
        'ic_credentials.config_key',
        'config_settings.value',
        'ic_credentials.section',
        'ic_credentials.default_value',
        DB::raw('COALESCE(config_settings.value, ic_credentials.config_value) AS value'),
        )
        ->orderBy('master_company.company_alias')
        ->whereNotNull('master_company.company_alias')
        ->join('ic_credentials', 'ic_credentials.company_alias', '=', 'master_company.company_alias')
        ->leftjoin('config_settings', 'ic_credentials.config_key', '=', 'config_settings.key')
        ->where('master_company.company_alias', $companyAlias)
        ->whereRaw("FIND_IN_SET('{$request->section}', ic_credentials.section)")
        ->get();
        if ($company->isNotEmpty()) {
            return response()->json([
                'status' => true,
                'data' => $company,
            ]);
        } else {
            return response()->json([
                'status' => false,
                'data' => [],
                'msg' => 'No records found'
            ]);
        }
    }
    public function fetchingProduct()
    {
        $businessType = IcProduct::select('business_type')
        ->distinct('business_type')
        ->get()
        ->pluck('business_type');

        $alias = MasterCompany::select('master_company.company_alias', 'master_company.company_name', 'master_company.company_id as insurance_company_id')
            ->where('status', 'Active')
            ->whereNotNull('company_alias')
            ->orderBy('company_alias')
            ->get();

            $product_dropdown = MasterProductSubType::select('master_product_sub_type.product_sub_type_code', 'master_product_sub_type.product_sub_type_name', 'master_product_sub_type.product_sub_type_id')
            ->whereNotIn('product_sub_type_id', [3, 4, 8])
                    ->where('status', 'Active')
                    ->orderBy('product_sub_type_code')
                    ->get();

        $premiumType = MasterPremiumType::select('master_premium_type.premium_type', 'master_premium_type.id')
        ->orderBy('premium_type_code')
        ->get();


        return view('admin_lte.ic_configurator.product', [
            'businessType' => $businessType,
            'product_dropdown' => $product_dropdown,
            'alias' => $alias,
            'premiumType' => $premiumType
        ]);
    }

    public function downloadExcel(Request $request)
    {
        if(isset($request->product_sub_type_id) && isset($request->insurance_company_id)){
            $object = $this->ProductList($request);
            $object_data = json_decode(json_encode($object), true);
            foreach ($object_data['original']['data'] as $key => $data_value) {
                foreach ($data_value as $key_data => $value_data) {
                    if($value_data == null){
                        unset($object_data['original']['data'][$key][$key_data]);
                    }
                }
            }
            return Excel::download(new PolicyReportsExport($object_data['original']['data']), 'policy_report.xls');
        } else {
            return redirect()->back();
        }
    }

    public function ProductList(Request $request)
    {
        $where = [
            'ic_product.product_sub_type_id' => $request->product_sub_type_id,
            'ic_product.insurance_company_id' => $request->insurance_company_id,
        ];

        if (!empty($request->businessType)) {
            $where['ic_product.business_type'] = $request->businessType;
        }

        if (!empty($request->premiumType)) {
            $where['master_premium_type.id'] = $request->premiumType;
        }

        $companyQuery = IcProduct::select(
            'master_policy.*',
            'ic_product.product_sub_type_id as product_sub_type_ids',
            'ic_product.insurance_company_id as insurance_company_ids',
            'ic_product.premium_type_id as premium_type_ids',
            'master_premium_type.premium_type as premium_type',
            DB::raw('COALESCE(master_policy.is_premium_online, ic_product.is_premium_online) AS is_premium_online'),
            DB::raw('COALESCE(master_policy.is_proposal_online, ic_product.is_proposal_online) AS is_proposal_online'),
            DB::raw('COALESCE(master_policy.is_payment_online, ic_product.is_payment_online) AS is_payment_online'),
            DB::raw('COALESCE(master_policy.product_name, ic_product.product_name) AS product_names'),
            DB::raw('COALESCE(master_policy.product_unique_name, ic_product.product_unique_name) AS product_unique_names'),
            DB::raw('COALESCE(master_policy.product_identifier, ic_product.product_identifier) AS product_identifiers'),
            DB::raw('COALESCE(master_policy.business_type, ic_product.business_type) AS business_types'),
            DB::raw('COALESCE(master_policy.pos_flag, ic_product.pos_flag) AS pos_flags'),
            DB::raw('COALESCE(master_policy.product_key, ic_product.product_key) AS product'),
            DB::raw('COALESCE(master_policy.zero_dep, ic_product.zero_dep) AS zero'),
            DB::raw('COALESCE(master_policy.default_discount, ic_product.default_discount) AS default_discounts'),
            DB::raw('COALESCE(master_policy.gcv_carrier_type, ic_product.gcv_carrier_type) AS gcv_carrier_types'),
            DB::raw('COALESCE(master_policy.owner_type, ic_product.owner_type) AS owner'),
            DB::raw('COALESCE(master_policy.consider_for_visibility_report, ic_product.consider_for_visibility_report) AS consider_for_visibility_report')
        )
        ->leftJoin('master_policy', 'ic_product.product_key', '=', 'master_policy.product_key')
        ->join('master_premium_type', 'master_premium_type.id', '=', 'ic_product.premium_type_id')
        ->where($where);

        $company = $companyQuery->get();
        if ($company) {
            return response()->json([
                'status' => true,
                'data' => $company,
                'msg' => 'Records Found'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'data' => [],
                'msg' => 'No records found'
            ]);
        }
    }

    public function miscellaneous()
    {
        $alias = MasterCompany::select('master_company.company_alias', 'master_company.company_name')
            ->whereNotNull('company_alias')
            ->where('status', 'Active')
            ->orderBy('company_alias')
            ->get();
            $product_dropdown = MasterProductSubType::select('master_product_sub_type.product_sub_type_code', 'master_product_sub_type.product_sub_type_name', 'master_product_sub_type.product_sub_type_id')
            ->where('status', 'Active')
            ->orderBy('product_sub_type_code')
            ->get();

        $company = MasterPremiumType::select('master_premium_type.premium_type', 'master_premium_type.id')
            ->orderBy('premium_type_code')
            ->get();
        return view('admin_lte.ic_configurator.Miscellaneous', ['product_dropdown' => $product_dropdown, 'alias' => $alias, 'company' => $company]);
    }

    public static function getProposalFields(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'company_alias' => 'required',
            'section' => 'required',
            'owner_type' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        }
        try {
            $result = ProposalFields::where([
                "company_alias" =>$request->company_alias,
                "section" =>$request->section,
                "owner_type" =>$request->owner_type,])->select('fields','renewal_fields')->first();
                $defaultarr = [
                    "gstNumber",
                    "maritalStatus",
                    "occupation",
                    "panNumber",
                    "vehicleColor",
                    "hypothecationCity",
                    "dob",
                    "gender",
                    "cpaOptOut",
                    "email",
                    "ckycQuestion"
                ];
                
                $fields=(empty($result) ? $defaultarr :json_decode($result->fields,true));
                if ($request->config) {
                    $fields=$result ? json_decode($result->renewal_fields) : null;
                }
                $godigit_claim_covered = ConfigSetting::where('key','godigit_claim_covered')->pluck('value')->first();

                if($godigit_claim_covered && $request->company_alias=='godigit' && $request->section=='Car')
                $fields['godigit_claim_covered'] = $godigit_claim_covered;

            return [
                'status' => true,
                "data" => $fields
            ];
        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "error " . $e->getMessage(),

            ], 500);
        }
    }

    public static function addProposalfield(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'company_alias' => 'required',
            'section' => 'required',
            'owner_type' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        }
        try {
            $where=[];
            if($request->company_alias=='all' && $request->section=='all'){
                $where=[
                    "owner_type" =>$request->owner_type
                ];
            }elseif($request->company_alias=='all' && $request->section!='all'){
                $where=[
                    "section" =>$request->section,
                    "owner_type" =>$request->owner_type
                ];
            }elseif($request->company_alias!='all' && $request->section=='all'){
                $where=[
                    "company_alias" =>$request->company_alias,
                    "owner_type" =>$request->owner_type
                ];
            }
                $ckycTypeValue = $request->ckyc_type ?? null;
                $poilistValue = $request->poilist ?? null;
                $poalistValue = $request->poalist ?? null;
                $proprietorshipListValue =  $request->proprietorship_list ?? null;
                
                if(isset($request->claimcount_list)){
                    $config = [
                        'label' => 'godigit_claim_covered',
                        'key' => 'godigit_claim_covered',
                        'value' => $request->claimcount_list
                    ];
                    $resul = ConfigSetting::updateOrCreate(['key' => 'godigit_claim_covered'], $config);
                    
                    \Illuminate\Support\Facades\Artisan::call('optimize:clear');

                    }
                
                    

                $mapping_value = [
                    'panNumber' => [
                        "label" => "PAN Number",
                        "placeholder" => "Upload PAN Card Image",
                        "length" => 10,
                        "fileKey" => "panCard"
                    ],
                    'cretificateOfIncorporaion' => [
                        "label" => "Certificate of Incorporation",
                        "placeholder" => "Upload Certificate",
                        "fileKey" => "certificate_of_incorporation_image"
                    ],
                    'nationalPopulationRegisterLetter' => [
                        "label" => "National Population Letter",
                        "placeholder" => "Upload Letter",
                        "fileKey" => "national_population_register_letter_image"
                    ],
                    'registrationCertificate' => [
                        "label" => "Registration Certificate",
                        "placeholder" => "Upload Certificate",
                        "length" => 20,
                        "fileKey" => "registration_certificate_image"
                    ],
                    'cinNumber' => [
                        "label" => "CIN Number",
                        "placeholder" => "Upload CIN Number Certificate",
                        "fileKey" => "cinNumber"
                    ],
                    'aadharNumber' => [
                        "label" => "Adhaar Number",
                        "placeholder" => "Upload Adhaar Card Image",
                        "length" => 12,
                        "fileKey" => "aadharCard"
                    ],
                    'e-eiaNumber' => [
                        "label" => "e-Insurance Account Number",
                        "fileKey" => "eiaNumber"
                    ],
                    'passportNumber' => [
                        "label" => "Passport Number",
                        "placeholder" => "Upload Passport Image",
                        "length" => 8,
                        "fileKey" => "passport_image"
                    ],
                    'voterId' => [
                        "label" => "Voter ID Number",
                        "placeholder" => "Upload Voter ID Card Image",
                        "fileKey" => "voter_card"
                    ],
                    'drivingLicense' => [
                        "label" => "Driving License Number",
                        "placeholder" => "Upload Driving License Image",
                        "fileKey" => "driving_license"
                    ],
                    'gstNumber' => [
                        "label" => "GST Number",
                        "placeholder" => "Upload GST Number Certificate",
                        "length" => 15,
                        "fileKey" => "gst_certificate"
                    ],
                    'nregaJobCard' => [
                        "label" => "NREGA Job Card",
                        "placeholder" => "Upload NREGA Card Image",
                        "length" => 18,
                        "fileKey" => "nrega_job_card_image"
                    ],
                    'udyog' => [
                        "label" => "Udyog Certificate",
                        "placeholder" => "Upload Certificate",
                        "length" => 20,
                        "fileKey" => "udyog"
                    ],
                    'udyam' => [
                        "label" => "Udyam Certificate",
                        "placeholder" => "Upload Certificate",
                        "length" => 20,
                        "fileKey" => "udyam"
                    ],
                    'passportFileNumber' => [
                        "label" => "Passport File Number",
                        "placeholder" => "Upload Certificate",
                        "length" => 20,
                        "fileKey" => "passportFileNumber"
                    ],

                ];
                $specificValues=[];
                if (is_array($ckycTypeValue) || is_object($ckycTypeValue)) {
                    foreach ($ckycTypeValue as $data) {
                        if (isset($data['value']) && isset($mapping_value[$data['value']])) {
                            $specificValues[] = $mapping_value[$data['value']];
                        }
                    }
                }
                if (is_array($poilistValue) || is_object($poilistValue)) {
                    foreach ($poilistValue as $data) {
                        if (isset($data['value']) && isset($mapping_value[$data['value']])) {
                            $specificValues[] = $mapping_value[$data['value']];
                        }
                    }
                }
                if (is_array($poalistValue) || is_object($poalistValue)) {
                    foreach ($poalistValue as $data) {
                        if (isset($data['value']) && isset($mapping_value[$data['value']])) {
                            $specificValues[] = $mapping_value[$data['value']];
                        }
                    }
                }
                if (is_array($proprietorshipListValue) || is_object($proprietorshipListValue)) {
                    foreach ($proprietorshipListValue as $data) {
                        if (isset($data['value']) && isset($mapping_value[$data['value']])) {
                            $specificValues[] = $mapping_value[$data['value']];
                        }
                    }
                }
                    $mapping = [
                        'panNumber' => 'panCard',
                        'cretificateOfIncorporaion' => 'certificate_of_incorporation_image',
                        'nationalPopulationRegisterLetter' => 'national_population_register_letter_image',
                        'cinNumber' => 'cinNumber',
                        'aadharNumber' => 'aadharCard',
                        'e-eiaNumber' => 'eiaNumber',
                        'passportNumber' => 'passport_image',
                        'voterId' => 'voter_card',
                        'drivingLicense' => 'driving_license',
                        'gstNumber' => 'gst_certificate',
                        'nregaJobCard' => 'nrega_job_card_image',
                        'registrationCertificate' => 'registration_certificate_image',
                        'udyog' => 'udyog',
                        'udyam' => 'udyam',
                        'passportFileNumber' => 'passportFileNumber',
                    ];
                        $ckyc_type=[];
                        if (is_array($request->ckyc_type) || is_object($request->ckyc_type)) {
                            foreach ($request->ckyc_type as $ckyc_value) {
                                foreach ($specificValues as $value) {
                                    if (array_key_exists('value', $ckyc_value) && array_key_exists($ckyc_value['value'], $mapping)) {
                                        if (isset($value['fileKey']) && $value['fileKey'] == $mapping[$ckyc_value['value']]) {
                                            $ckyc_type_key = $ckyc_value['value'] . '_' . $value['fileKey'];
                                            if (!isset($ckyc_type[$ckyc_type_key])) {
                                                $ckyc_type[$ckyc_type_key] = array_merge($ckyc_value, $value);
                                            }
                                        }
                                    }
                                }
                            }
                            $ckyc_type = array_values($ckyc_type);
                        }

                        $poi_type=[];
                        if (is_array($request->poilist) || is_object($request->poilist)) {
                            foreach ($request->poilist as $poi_value) {
                                foreach ($specificValues as $value) {
                                    if (array_key_exists($poi_value['value'], $mapping)) {
                                        if ($value['fileKey'] == $mapping[$poi_value['value']]) {
                                            $poi_type_key = $poi_value['value'] . '_' . $value['fileKey'];
                                            if (!isset($poi_type[$poi_type_key])) {
                                                $poi_type[$poi_type_key] = array_merge($poi_value, $value);
                                            }
                                        }
                                    }
                                }
                            }
                            $poi_type = array_values($poi_type);
                        }

                        $poa_type=[];
                        if (is_array($request->poalist) || is_object($request->poalist)) {
                            foreach ($request->poalist as $poa_value) {
                                foreach ($specificValues as $value) {
                                    if (array_key_exists($poa_value['value'], $mapping)) {
                                        if ($value['fileKey'] == $mapping[$poa_value['value']]) {
                                            $poa_type_key = $poa_value['value'] . '_' . $value['fileKey'];
                                            if (!isset($poa_type[$poa_type_key])) {
                                                $poa_type[$poa_type_key] = array_merge($poa_value, $value);
                                            }
                                        }
                                    }
                                }
                            }
                            $poa_type = array_values($poa_type);
                        }

                        // Processing for Proprietorship types
                        $proprietorship_type = [];  
                        if (is_array($proprietorshipListValue) || is_object($proprietorshipListValue)) {
                            foreach ($proprietorshipListValue as $proprietorship_value) {
                                foreach ($specificValues as $value) {
                                    if (array_key_exists($proprietorship_value['value'], $mapping)) {
                                        if ($value['fileKey'] == $mapping[$proprietorship_value['value']]) {
                                            $proprietorship_type_key = $proprietorship_value['value'] . '_' . $value['fileKey'];
                                            if (!isset($proprietorship_type[$proprietorship_type_key])) {
                                                $proprietorship_type[$proprietorship_type_key] = array_merge($proprietorship_value, $value);
                                            }
                                        }
                                    }
                                }
                            }
                            $proprietorship_type = array_values($proprietorship_type);
                        }
            if($request->company_alias=='all' || $request->section=='all'){
                $result = ProposalFields::where($where)->update(
                    [
                        "fields" => json_encode($request->fields)
                    ]);
            } else{
                if($request->section == 'all'){
                    $section = ['car', 'bike', 'cv'];
                    foreach ($section as $key => $value) {
                        $updateValues=[
                            "company_alias" =>$request->company_alias,
                            "section" =>$value,
                            "owner_type" =>$request->owner_type
                        ];
                        if ($request->config) {
                            $updateValues['renewal_fields']=json_encode($request->fields);
                        } elseif(!empty($ckycTypeValue) || !empty($poilistValue) || !empty($poalistValue) || !empty($proprietorshipListValue)) {
                            $fields = array(
                                'fields' => $request->fields,
                                'ckyc_type' => $ckyc_type,
                                'poilist' => $poi_type,
                                'poalist' => $poa_type,
                                'proprietorship_type' => $proprietorship_type
                            );
                            $updateValues['fields'] = json_encode($fields);

                        }else {
                            $updateValues['fields']=json_encode($request->fields);
                        }
                        $result = ProposalFields::updateOrCreate(
                            [
                                "company_alias" =>$request->company_alias,
                                "section" =>$value,
                                "owner_type" =>$request->owner_type
                            ],$updateValues);
                    }
                } else{
                    $updateValues=[
                        "company_alias" =>$request->company_alias,
                        "section" =>$request->section,
                        "owner_type" =>$request->owner_type
                    ];
                    if ($request->config) {
                        $updateValues['renewal_fields']=json_encode($request->fields);
                    } elseif(!empty($ckycTypeValue) || !empty($poilistValue) || !empty($poalistValue) || !empty($proprietorshipListValue)) {
                            $fields = array(
                                'fields' => $request->fields,
                                'ckyc_type' => $ckyc_type,
                                'poilist' => $poi_type,
                                'poalist' => $poa_type,
                                'proprietorship_type' => $proprietorship_type

                            );
                        $updateValues['fields'] = json_encode($fields);
                    }else{
                        $updateValues['fields']=json_encode($request->fields);
                    }
                    $result = ProposalFields::updateOrCreate(
                        [
                            "company_alias" =>$request->company_alias,
                            "section" =>$request->section,
                            "owner_type" =>$request->owner_type
                        ],
                    $updateValues);
                }
            }

            return [
                'status' => true,
                "message" => "Data Inserted Successfully",
            ];
        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "error " . $e->getMessage(),

            ], 500);
        }
    }

    public function storeOrUpdate(Request $request)
    {
        $configurations = [];
        // Extract data from the request
        $configNames = $request->input('config_name');
        $configKeys = $request->input('config_key');
        $configValues = $request->input('config_value');
        $commentTextarea = $request->input('commentTextareaForm');

        foreach ($configNames as $index => $configName) {
            $configurations[] = [
                'label' => $configName,
                'key' => $configKeys[$index],
                'value' => $configValues[$index]
            ];
        }
        // print_pre($configurations); die;
        // Combine the data into an array of configurations
        $auth_msg = '';
        $auth_flag=0;
        if(isset(auth()->user()->authorization_by_user) && auth()->user()->authorization_status=='Y' && auth()->user()->authorization_by_user != ''){
            foreach ($configurations as $config) {
                $authorization_request = AuthorizationRequest::where('reference_search_value',$config['key'])->get()->toArray();
                if(count($authorization_request)>0){
                    if($authorization_request[0]['requested_by'] != auth()->user()->id){      
                        $auth_flag=1;
                        $user_details_requested_by = user::select('email','name')->where('id', $authorization_request[0]['requested_by'])->first();
                        $user_details_approved_by = user::select('email','name')->where('id', $authorization_request[0]['approved_by'])->first();
                        $requested_by_name = $user_details_requested_by['name'].'('.$user_details_requested_by['email'].')';
                        $approved_by_name = $user_details_approved_by['name'].'('.$user_details_approved_by['email'].')';
                        $auth_msg .= '<label> * Update request for "'.$config['key'].'" key is already raised by '.$requested_by_name. ' on '.$authorization_request[0]['requested_date'].' and its pending for approval at '.$approved_by_name.'</label>';
                    }
                }
            }
            if($auth_flag==1){
                return redirect()->route('admin.ic-config.credential.index')->with(['message' => $auth_msg,'class' => 'danger']);
            }else{

                foreach ($configurations as $config) {
                    $authorization_request = AuthorizationRequest::where('reference_search_value',$config['key'])->get()->toArray();
                    
                    if(count($authorization_request)>0){
                        if($authorization_request['requested_by']==auth()->user()->id){
                            AuthorizationRequest::where('reference_search_value',$config['key'])->update(['new_value'=>$config['value']]);
                        }
                    }else{
                        $company = MasterCompany::select('master_company.company_alias', 'ic_credentials.config_name', 'ic_credentials.config_key', 'config_settings.value', 'ic_credentials.section', 'ic_credentials.default_value')
                        ->orderBy('master_company.company_alias')
                        ->whereNotNull('master_company.company_alias')
                        ->join('ic_credentials', 'ic_credentials.company_alias', '=', 'master_company.company_alias')
                        ->leftjoin('config_settings', 'ic_credentials.config_key', '=', 'config_settings.key')
                        ->where('config_settings.key', $config['key'])
                        ->get();
        
                        if(count($company)>0 && $company[0]->value!=$config['value']){
                            $authreq_add = new AuthorizationRequest;
                            $authreq_add->reference_model = 'IC Config-Credentials';
                            $authreq_add->reference_table = 'config_settings';
                            $authreq_add->reference_update_column = 'value';
                            $authreq_add->reference_search_key = 'key';
                            $authreq_add->reference_search_value = $config['key'];
                            $authreq_add->reference_update_value = json_encode($config);
                            $authreq_add->old_value = $company[0]['value'];
                            $authreq_add->new_value = $config['value'];
                            $authreq_add->requested_by = auth()->user()->id;
                            $authreq_add->approved_by = auth()->user()->authorization_by_user;
                            $authreq_add->requested_date = now();
                            $authreq_add->approved_status = 'N';
                            $authreq_add->request_comment = $commentTextarea;
                            $result = $authreq_add->save(); 
                        }
                    }
                }
                Notification::create([
                    'content' => 'Authorization Request',
                    'from_user'    => auth()->user()->id,
                    'to_user'      => auth()->user()->authorization_by_user
                ]);
                $user_details = user::select('email','name')->where('id', auth()->user()->authorization_by_user)->first();
                Mail::to($user_details['email'])->send(new IcConfigApprovalMail($user_details));
                return redirect()->route('admin.ic-config.credential.index')->with(['message' => 'Changes sent for Approval.','class' => 'success']);    
            }
        }else{
            // Insert or update each configuration

            foreach ($configurations as $config) {
                // If record exists, update it
                ConfigSetting::updateOrCreate(['key' => $config['key']], $config);
            }
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            return redirect()->route('admin.ic-config.credential.index')->with(['message' => 'Credential Updated Successfully.','class' => 'success']);    

        }

    
    }

    public function productUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'default_discount.*' => 'nullable|integer|max:100',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $configurations = [];
        $product = [];

        $product_key = $request->input('product_key');
        $business_type = $request->input('business_types');
        // $product_unique_name = $request->input('product_unique_name');
        $product_name = $request->input('product_name');
        $default_discount = $request->input('default_discount');
        $product_identifier = $request->input('product_identifier');
        $pos_flag = $request->input('pos_flag');
        $zero_dep = $request->input('zero_dep');
        $status = $request->input('status');
        $consider_for_visibility_report = $request->input('visibilityCheckBox1');
        $owner_type = $request->input('owner');
        $gcv = $request->input('gcv_carrier_type');
        $policy_id = $request->input('policy_id');
        $product_sub_type_id = $request->input('product_sub_type_id') ?? [];
        $insurance_company_id = $request->input('insurance_company_id') ?? [];
        $premium_type_id = $request->input('premium_type_id') ?? [];
        if (empty($policy_id)) {
            return redirect()->back()->withErrors([
                'msg' => 'No data received for product update.',
            ]);
        }
        foreach ($product_name as $index => $configName) {

            $posFlagValue = !empty($pos_flag[$index] ?? null) ? implode(',', $pos_flag[$index]) : '';
            $ownertypeValue = !empty($owner_type[$index] ?? null) ? implode(',', $owner_type[$index]) : '';
            // $bussinesValue = !empty($business_type[$index] ?? null) ? implode(',', $business_type[$index]) : '';
            $configurations[$index] = [
                'product_key' => $product_key[$index],
                'business_type' =>  $business_type[$index],
                // 'product_unique_name' => $product_unique_name[$index],
                'product_name' =>  $configName,
                'default_discount' => $default_discount[$index],
                'product_identifier' => $product_identifier[$index],
                'pos_flag' =>  $posFlagValue,
                'owner_type' => $ownertypeValue,
                //'gcv_carrier_type' => $gcv[$index],
                'zero_dep' => $zero_dep[$index],
                'status' => $status[$index],
                'consider_for_visibility_report' => $consider_for_visibility_report[$index],
                'product_sub_type_id' => $product_sub_type_id[$index],
                'insurance_company_id' => $insurance_company_id[$index],
                'premium_type_id' => $premium_type_id[$index],
                'is_premium_online' => $request->is_premium_online[$index],
                'is_proposal_online' => $request->is_proposal_online[$index],
                'is_payment_online' => $request->is_payment_online[$index],
            ];
             $product[$index] = [
                'product_name' =>  $configName,
                'product_identifier' => $product_identifier[$index],
                'master_policy_id' => $policy_id[$index],
            ];
        }
        foreach ($configurations as $k => $config) {
            $resul = MasterPolicy::updateOrCreate(['product_key' => $config['product_key']], $config);
            $product[$k]['master_policy_id'] = $resul->policy_id;
            MasterProduct::updateOrCreate(['master_policy_id' => $resul['policy_id']], $product[$k]);
        }
        if ($request->has('prev')) {
            return redirect($request->prev)->with([ 'status' => 'Product Updated Successfully..!' ]);
        } else {
            return redirect()->back()->withInput()->with([ 'status' => 'Product Updated Successfully..!', 'class' => 'success', ]);
        }
    }

    public function labelAttributes(Request $request)
    {
        if (!auth()->user()->can('label_and_attribute.list')) {
            return response('unauthorized action', 401);
        }

        // $count = PremCalcLabel::leftJoin('prem_calc_mapped_attributes as pcmt', 'prem_calc_labels.id', '=', 'pcmt.label_id')
        // ->leftJoin('prem_calc_attributes as pca', 'pcmt.attribute_id', '=', 'pca.id')
        // ->select(
        //     'prem_calc_labels.id',
        //     'prem_calc_labels.label_name',
        //     'prem_calc_labels.label_key',
        //     'prem_calc_labels.label_group',
        //     DB::raw('COUNT(pcmt.label_id) as mapping_count'),
        //     'pca.ic_alias',
        //     'pca.integration_type',
        //     'pca.segment',
        //     'pca.business_type',
        //     'pca.attribute_name',
        //     'pca.attribute_trail'
        // )
        // ->whereNull('prem_calc_labels.deleted_at')
        // ->whereNull('pcmt.deleted_at')
        // ->groupBy(
        //     'prem_calc_labels.id',
        //     'prem_calc_labels.label_name',
        //     'prem_calc_labels.label_key',
        //     'prem_calc_labels.label_group',
        // 'pca.ic_alias',
        // 'pca.integration_type',
        // 'pca.segment',
        // 'pca.business_type',
        // 'pca.attribute_name',
        // 'pca.attribute_trail'
        // )
        // ->get()->toArray();
        $count = PremCalcLabel::leftJoin('prem_calc_mapped_attributes as pcmt', 'prem_calc_labels.id', '=', 'pcmt.label_id')
        ->leftJoin('prem_calc_attributes as pca', 'pcmt.attribute_id', '=', 'pca.id')
        ->select(
            'prem_calc_labels.id',
            'prem_calc_labels.label_name',
            'prem_calc_labels.label_key',
            'prem_calc_labels.label_group',
            DB::raw('COUNT(pcmt.label_id) as mapping_count'),
            DB::raw('ANY_VALUE(pca.ic_alias) as ic_alias'),
            DB::raw('ANY_VALUE(pca.integration_type) as integration_type'),
            DB::raw('ANY_VALUE(pca.segment) as segment'),
            DB::raw('ANY_VALUE(pca.business_type) as business_type'),
            DB::raw('ANY_VALUE(pca.attribute_name) as attribute_name'),
            DB::raw('ANY_VALUE(pca.attribute_trail) as attribute_trail')
        )
        ->whereNull('prem_calc_labels.deleted_at')
        ->whereNull('pcmt.deleted_at')
        ->groupBy(
            'prem_calc_labels.id',
            'prem_calc_labels.label_name',
            'prem_calc_labels.label_key',
            'prem_calc_labels.label_group'
        )
        ->get()
    ->toArray();

            
        return view('admin_lte.ic_configurator.label', ['count' => $count]);
    }

    public function saveLabelData(Request $request)
    {
        if (!auth()->user()->can('label_and_attribute.create')) {
            return response('unauthorized action', 401);
        }
        $validatedData = Validator::make($request->all(), [
            'label' => 'required|string',
            'label_group' => 'required|string',
            'label_key' => 'required|string'
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'message' => $validatedData->errors()->all(),
            ]);
        }
        
        $request['key'] = strtolower($request['key']);      
        $recordExists = PremCalcLabel::where('label_name', $request['label'])
            ->where('label_group', $request['label_group'])
            ->where('label_key', $request['label_key'])
            ->exists();

        if (!$recordExists) {
            PremCalcLabel::create([
                'label_group' => $request['label_group'],
                'label_name' => $request['label'],
                'label_key' => $request['label_key'],
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
            ]);
            return response()->json(['success' => true, 'message' => 'Label Added successfully']);
        } else {
            return response()->json(['success' => false, 'message' => 'Label already exists']);
        }
    }

    public function saveAttributes(Request $request)
    {

        $validatedData = Validator::make($request->all(),[
            'label_id' => 'required|integer',
            'selectIC' => 'nullable|string',
            'attributes' => 'required|integer',
            'vehicle' => 'nullable|string',
            'businessType' => 'nullable|string',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                // 'error' => $validatedData->errors()->all(),
                'message' => 'All Fields are Mandatory.',
            ]);
        }

        $ic_name = $request['selectIC'];
        $ic_name  = explode('#', $ic_name);
        $segment  = $request['vehicle-data'];
        $business_type  = $request['businessType'];


        $block_data = DB::table('prem_calc_mapped_attributes as pcma')
        ->join('prem_calc_attributes as pca', function ($join) {
            $join->on('pcma.attribute_id', '=', 'pca.id')
            ->whereNull('pcma.deleted_at')
            ->whereNull('pca.deleted_at');
        })
            ->join('prem_calc_labels as pcl', function ($join) {
                $join->on('pcma.label_id', '=', 'pcl.id')
                ->whereNull('pcl.deleted_at');
            })
            ->where('pcma.label_id', $request['label_id'])
            ->where('pca.ic_alias', $ic_name[0])
            ->where('pca.integration_type', $ic_name[1])
            ->where('pca.segment', $segment)
            ->where('pca.business_type', $business_type)
            ->exists();

        $recordExists = PremCalcMappedAttribute::where('label_id', $request['label_id'])
        ->where('attribute_id', $request['attributes'])->exists();

        if(!$block_data){
            if (!$recordExists) {
                PremCalcMappedAttribute::Create([
                    'label_id' => $request['label_id'],
                    'attribute_id' => $request['attributes'],
                ]);
                return response()->json(['success' => true, 'message' => 'Attribute Saved successfully.']);
            } else {
                return response()->json(['success' => false, 'message' => 'Attribute  already exists.']);
            }
        }else{
            return response()->json(['success' => false, 'message' => 'Attribute Already Exist for the IC-Integration, Segment , BusinessType Selection.']);
        }
    }

    public function mapAttributes($id)
    {
        if (!auth()->user()->can('label_and_attribute.list')) {
            return response('unauthorized action', 401);
        }
        
        $id = Crypt::decryptString($id);

        $listing = PremCalcAttributes::select('prem_calc_attributes.*' , DB::raw("CONCAT(attribute_name, ' - ', attribute_trail) AS final_attribute") )
        ->join('prem_calc_mapped_attributes', 'prem_calc_attributes.id', '=', 'prem_calc_mapped_attributes.attribute_id')
        ->where('prem_calc_mapped_attributes.label_id', $id)
        ->whereNull('prem_calc_mapped_attributes.deleted_at')
        ->get();

        $labels = PremCalcLabel::select('label_name')
            ->where('id', $id)
            ->get();

        $alias = IcIntegrationType::select('ic_alias', 'integration_type')
        ->distinct()
        ->orderBy('ic_alias', 'ASC')
        ->get()->toArray();

        $segments = IcIntegrationType::select('segment')
        ->distinct()
        ->get()->toArray();


        $bussiness_type = IcIntegrationType::select('business_type')
        ->distinct()
        ->get()->toArray();

        $attribute = PremCalcAttributes::select('attribute_name')
        ->get();
        return view('admin_lte.ic_configurator.map-attributes', [ 'bussiness_type'=> $bussiness_type ,'segments' => $segments , 'alias' => $alias, 'labels' => $labels, 'id' => $id, 'attribute' => $attribute, 'listing' => $listing]);
    }

    public function editLabel(Request $request)
    {
        if (!auth()->user()->can('label_and_attribute.edit')) {
            return response('unauthorized action', 401);
        }

        $validatedData = Validator::make($request->all(), [
            'label_name' => 'required|string',
            'label_id' => 'required|integer',
            'label_group' => 'required|string',
            
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'message' => $validatedData->errors()->all(),
            ]);
        }

        $labelExist = PremCalcLabel::where('id', $request['label_id'])->where('label_name' , $request['label_name'])
        ->where('label_group' , $request['label_group'])->exists();

        if (!$labelExist) {
            $label = PremCalcLabel::find($request['label_id']);
            $label->label_name = $request['label_name'];
            $label->label_group = $request['label_group'];
            $label->save();
            return response()->json(['success' => true, 'message' => 'Label updated successfully']);
        } else {
            return response()->json(['success' => false, 'message' => 'Label Already Exist.']);
        }
    }

    public function deleteLabel(Request $request)
    {
        if (!auth()->user()->can('label_and_attribute.delete')) {
            return response('unauthorized action', 401);
        }

        $validatedData = $request->validate([
            'id' => 'required|integer',
        ]);

        // $item = PremCalcLabel::find($validatedData['id']);
        $Value = $validatedData['id'];
        $searchString = '%|L:' . $Value . '|%';

        $item = PremCalcFormula::where('matrix', 'LIKE', $searchString)
        ->whereNull('deleted_at')
        ->exists();

        if ($item) {
            return response()->json(['success' => false, 'message' => 'Label Already use in Formula']);
        } else {
            PremCalcLabel::where('id', $validatedData['id'])->delete();
            return response()->json(['success' => true, 'message' => 'Label Deleted Successfully.']);
        }
    }

    public function getAttribute(Request $request)
    {

        $ic_name = $request['selectIC'];
        $ic_name  = explode('#', $ic_name);
        $vehicle = $request['vehicle'];
        $business_type = $request['businessType'];

        $segment_type = IcIntegrationType::select('segment')
        ->distinct()
            ->where('ic_alias', $ic_name[0])
            ->where('integration_type', $ic_name[1])
            ->get()->toArray();


        if ($ic_name != null && $vehicle != null) {
            $bussiness_type = IcIntegrationType::select('business_type')
            ->distinct()
                ->where('ic_alias', $ic_name[0])
                ->where('integration_type', $ic_name[1])
                ->where('segment', $vehicle)
                ->get()->toArray();

            if ($ic_name != null && $vehicle != null  && $business_type != null) {
                $getAttribute = PremCalcAttributes::select(
                    DB::raw("CONCAT(attribute_name, ' - ', attribute_trail) AS final_attribute", 'id'),
                    'id'
                )
                    ->whereIn('ic_alias',   (array)$ic_name[0])
                    ->whereIn('business_type',  (array)$business_type)
                    ->whereIn('segment', (array) $vehicle)
                    ->distinct()
                    ->orderBy('final_attribute', 'asc')
                    ->get();
                $getAttribute = json_decode($getAttribute, true);
                if (empty($getAttribute)) {
                    $getAttribute['msg'] = "No Attribute Found";
                }
                return $getAttribute;
            }
            return $bussiness_type;
        }
        return $segment_type;
    }

   

    public function editAttribute(Request $request)
    {

        $validatedData = $request->validate([
            'attribute_id' => 'required|integer', 
            'selectIC' => 'required|string',
            'vehicle-edit' => 'required|string',
            'businessType-edit' => 'required|string',
            'editattributes' => 'required|integer', 
            'pre-attribute_id' => 'required|integer', 
        ]);

        $ic_name = $request['selectIC'];
        $ic_name  = explode('#', $ic_name);
        $segment  = $request['vehicle-edit'];
        $business_type  = $request['businessType-edit'];

        $block_data = DB::table('prem_calc_mapped_attributes as pcma')
        ->join('prem_calc_attributes as pca', function ($join) {
            $join->on('pcma.attribute_id', '=', 'pca.id')
            ->whereNull('pcma.deleted_at')
            ->whereNull('pca.deleted_at');
        })
            ->join('prem_calc_labels as pcl', function ($join) {
                $join->on('pcma.label_id', '=', 'pcl.id')
                ->whereNull('pcl.deleted_at');
            })
            ->where('pcma.label_id', $request['attribute_id'])
            ->where('pcma.attribute_id', '<>', $request['pre-attribute_id'])
            ->where('pca.ic_alias', $ic_name[0])
            ->where('pca.integration_type', $ic_name[1])
            ->where('pca.segment', $segment)
            ->where('pca.business_type', $business_type)
            ->exists();

        $data_exist = PremCalcMappedAttribute::where('label_id', $validatedData['attribute_id'])
        ->where('attribute_id', $validatedData['editattributes'])->exists();



        if (!$block_data) {
            if (!$data_exist) {
                $attribute = PremCalcMappedAttribute::where('label_id', $validatedData['attribute_id'])
                    ->where('attribute_id', $validatedData['pre-attribute_id'])
                    ->first();

                if ($attribute) {
                    $attribute->attribute_id = $validatedData['editattributes'];
                    $attribute->save();
                    return response()->json(['success' => true, 'message' => 'Attribute updated successfully.']);
                } else {
                    return response()->json(['success' => false, 'message' => 'Attribute not found.']);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'Attribute Alredy Exist.']);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Attribute Already Exist for the IC-Integration, Segment , BusinessType Selection.']);
        }
    }

    public function viewLabel($id)
    {
        $id = Crypt::decryptString($id);

        $listing = PremCalcAttributes::select('prem_calc_attributes.*' , 'prem_calc_labels.label_name' , 'prem_calc_labels.label_group' , 'prem_calc_labels.label_key' , DB::raw("CONCAT(attribute_name, ' - ', attribute_trail) AS final_attribute"))
        ->join('prem_calc_mapped_attributes', 'prem_calc_attributes.id', '=', 'prem_calc_mapped_attributes.attribute_id')
        ->join('prem_calc_labels', 'prem_calc_mapped_attributes.label_id', '=', 'prem_calc_labels.id')
        ->where('prem_calc_mapped_attributes.label_id', $id)
        ->whereNull('prem_calc_mapped_attributes.deleted_at')
        ->get();

        $label = PremCalcLabel::select('prem_calc_labels.*')
        ->where('prem_calc_labels.id', $id)
        ->whereNull('prem_calc_labels.deleted_at')
        ->get()->toArray();

        $formula = PremCalcFormula::where('matrix', 'like', "%|L:$id|%")->get();

        $configData = PremCalcConfigurator::select([
            'prem_calc_configurator.id',
            DB::raw("CONCAT(prem_calc_configurator.ic_alias, '.', prem_calc_configurator.integration_type) AS ic_integration_type"),
            'prem_calc_configurator.segment',
            'prem_calc_configurator.business_type',
            'prem_calc_configurator.calculation_type AS type',
            DB::raw("
                CASE prem_calc_configurator.calculation_type
                    WHEN 'formula' THEN prem_calc_formulas.formula_name
                    WHEN 'attribute' THEN CONCAT(prem_calc_attributes.attribute_name, ' (', prem_calc_attributes.attribute_trail, ')')
                    WHEN 'custom_val' THEN prem_calc_configurator.custom_val
                END AS value
            "),
            DB::raw("
                CASE prem_calc_configurator.calculation_type
                    WHEN 'formula' THEN prem_calc_formulas.id
                    WHEN 'attribute' THEN prem_calc_attributes.id
                END AS id
            ")
        ])
        ->leftJoin('prem_calc_formulas', function($join) {
            $join->on('prem_calc_configurator.formula_id', '=', 'prem_calc_formulas.id')
                 ->whereNull('prem_calc_formulas.deleted_at');
        })
        ->leftJoin('prem_calc_attributes', function($join) {
            $join->on('prem_calc_configurator.attribute_id', '=', 'prem_calc_attributes.id')
                 ->whereNull('prem_calc_attributes.deleted_at');
        })
        ->where('prem_calc_configurator.label_id', $id)
        ->where('prem_calc_configurator.calculation_type', '!=', 'na')
        ->whereNull('prem_calc_configurator.deleted_at')
        ->get();
        $configData = $configData->toArray();

        return view('admin_lte.ic_configurator.view-label', ['listing' => $listing , 'label' => $label , 'formula'=>$formula , 'configData'=> $configData]);
    }

    public function deleteAttribute(Request $request)
    {

        $validatedData = $request->validate([
            'id' => 'required|integer',
            'Attribute_id' => 'required|integer',
        ]);

        $item = PremCalcMappedAttribute::where('label_id', $validatedData['id'])
            ->where('attribute_id', $validatedData['Attribute_id'])
            ->exists();

        // $item_2 = DB::table('prem_calc_configurator')->where('label_id', $validatedData['id'])->exists();
        $Value = $validatedData['id'];
        $searchString = '%|L:' . $Value . '|%';

        $item_2 =PremCalcFormula::where('matrix', 'LIKE', $searchString)
        ->exists();

        if ($item) {
            if (!$item_2) {
                // $item->delete();
                PremCalcMappedAttribute::where('label_id', $validatedData['id'])->where('Attribute_id' , $validatedData['Attribute_id'])->delete();
                return response()->json(['success' => true, 'message' => 'Attribute deleted successfully.']);
            } else {
                return response()->json(['success' => false, 'message' => 'Attribute is Already used in Formula']);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Attribute not found.']);
        }
    }

    public function getEditAttribute(Request $request)
    {
        $ic_name = $request['selectIC'];
        $ic_name  = explode('#', $ic_name);

       

        $ic_name = $request['selectIC'];
        $ic_name  = explode('#', $ic_name);
        $vehicle = $request['vehicle'];
        $business_type = $request['businessType'];


        if ($ic_name != null && $vehicle != null  && $business_type != null) {
            $getAttribute = PremCalcAttributes::select(
                'prem_calc_attributes.*',
                DB::raw("CONCAT(attribute_name, ' - ', attribute_trail) AS final_attribute", 'id'),
            )
                ->whereIn('ic_alias',   (array)$ic_name[0])
                ->whereIn('business_type',  (array)$business_type)
                ->whereIn('segment', (array) $vehicle)
                ->distinct()
                ->get()->toArray();
            return $getAttribute;
        }
    }

    public function icVersionConfigurator(Request $request)
    {
        if (!auth()->user()->can('ic_version_configurator.list')) {
            return response('unauthorized action', 401);
        }

        $alias = MasterCompany::select('company_alias', 'company_name', 'company_id')
        ->whereNotNull('company_alias')
        ->distinct()
        ->orderBy('company_name', 'ASC')
        ->get()->toArray();

        $segment = MasterProductSubType::select('product_sub_type_id', 'product_sub_type_code')
        ->where('parent_id', 0)
        ->orderBy('product_sub_type_code', 'ASC')
        ->get()->toArray();

        $listing = IcVersionConfigurator::all()->toArray();

        $versionData = DB::table('ic_version_configurators as ivc')
        ->leftJoin('ic_version_activations as iva', 'ivc.slug', '=', 'iva.slug')
        ->select('ivc.*', DB::raw("CASE WHEN iva.is_active = 1 THEN 'Active' ELSE 'InActive' END as is_active"))
        ->get();
        $versionData = json_decode($versionData , true);
        $integrationType = IcIntegrationType::select('integration_type')
        ->distinct()
        ->get()->toArray();

        $businessType = IcIntegrationType::select('business_type')
        ->distinct()
        ->get()->toArray();

        return view('admin_lte.ic_configurator.ic-version-configurator', ['alias' => $alias, 'segment' => $segment, 'listing' => $listing , 'versionData' => $versionData , 'integrationType' => $integrationType , 'businessType' => $businessType]);
    }

    public function saveVersionData(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'status' => 'required|boolean',
            'slug' => 'required|string',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validatedData->errors()->first(),
            ]);
        }

        $getSlug = IcVersionConfigurator::where('slug', $request['slug'])->first();

        if (empty($getSlug)) {
            return response()->json(['status' => false, 'message' => 'No Data Found'], 400);
        }

        if ($request->status) {

            $getOtherSlug = IcVersionConfigurator::where('segment', $getSlug['segment'])
                ->where('integration_type', $getSlug['integration_type'])
                ->where('ic_alias', $request['company'])
                ->where('business_type', $getSlug['business_type'])->get()->pluck('slug');

            IcVersionActivation::whereIn('slug', $getOtherSlug)->update([
                'is_active' => 0,
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id
            ]);

            IcVersionActivation::updateOrCreate(
                [
                    'slug' => $getSlug->slug,
                ],
                [
                    'is_active'  => 1,            
                    'created_by' => auth()->user()->id,
                    'updated_by' => auth()->user()->id,
                ]
            );
        } else {
            IcVersionActivation::where('slug', $getSlug->slug)->update(
                ['is_active' => 0]
            );
        }
        return response()->json(['status' => true, 'message' => 'Status saved Successfully.'] , 201);
    }  

    public function updateVersionData(Request $request)
    {
        if (!auth()->user()->can('ic_version_configurator.edit')) {
            return response('unauthorized action', 401);
        }

        $validatedData = Validator::make($request->all(), [
            'getId' => 'required|integer',
            'selectIC' => 'required|string',
            'version' => 'required|integer',
            'kitType' => 'required|string',
            'segment' => 'required|integer',
            'comment' => 'nullable|string',
            'business' => 'required|string',
            'type' => 'required|string',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'message' => 'No Data Fount',
            ]);
        }

        $getSlug = IcVersionConfigurator::where('ic_alias', $request['selectIC'])
        ->where('version', $request['version'])
        ->where('kit_type', $request['kitType'])
        ->where('segment_id', $request['segment'])
        ->where('description', $request['comment'])
        ->where('business_type', $request['business'])
        ->where('integration_type', $request['type'])
        ->exists();

        if (!$getSlug) {
            $record = IcVersionConfigurator::find($request['getId']);
            if ($record) {
                $record->ic_alias = $request['selectIC'];
                $record->version = $request['version'];
                $record->kit_type = $request['kitType'];
                $record->segment_id = $request['segment'];
                $record->description = $request['comment'];
                $record->integration_type = $request['type'];
                $record->business_type = $request['business'];
                $record->created_at = now();
                $record->updated_at = now();
                $record->save();
                return response()->json(['success' => true, 'message' => 'Data Stored Successfully.']);
            } else {
                return response()->json(['success' => false, 'message' => 'Record Not Found.']);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Record Already Exists.']);
        }
    }

    public function deleteVerionData(Request $request)
    {
        if (!auth()->user()->can('ic_version_configurator.delete')) {
            return response('unauthorized action', 401);
        }

        $validatedData = $request->validate([
            'Versionid' => 'required|integer',
        ]);

        $item = IcVersionConfigurator::where('id' , $request['Versionid'])
            ->exists();

        if ($item) {
            IcVersionConfigurator::where('id', $validatedData['Versionid'])->delete();
            return response()->json(['success' => true, 'message' => 'Data deleted successfully.']);
        } else {
            return response()->json(['success' => false, 'message' => 'Data not found.']);
        }
    }

    public function icPlaceholder(Request $request)
    {
        if (!auth()->user()->can('ic_placeholder.list')) {
            return response('unauthorized action', 401);
        }

        $placeholderData = PremCalcPlaceholder::select(
            '*',
            DB::raw("CASE 
                    WHEN placeholder_type = 'user' THEN 'User Define' 
                    WHEN placeholder_type = 'system' THEN 'System Generated' 
                    ELSE placeholder_type 
                 END AS placeholder_type_show")
        )
        ->whereNull('deleted_at')
        ->get()->toArray();
        return view('admin_lte.ic_configurator.ic-placeholder', ['placeholderData' => $placeholderData]);
    }

    public function savePlaceholder(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'holderName' => 'required|string',
            'holderKey' => 'required|string',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'message' => $validatedData->errors()->all(),
            ]);
        }

        $recordExists = PremCalcPlaceholder::where('placeholder_name', $request['holderName'])
        ->where('placeholder_key', $request['holderKey'])
        ->exists();

        if (!$recordExists) {
            PremCalcPlaceholder::create([
                'placeholder_name' => $request['holderName'],
                'placeholder_key' => $request['holderKey'],
                'placeholder_value' => '#',
                'placeholder_type' => 'user',
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id
            ]);
            return response()->json(['success' => true, 'message' => 'Data Added successfully']);
        } else {
            return response()->json(['success' => false, 'message' => 'Data already exists']);
        }
    }

    public function editPlaceholder(Request $request)
    {
        if (!auth()->user()->can('ic_placeholder.edit')) {
            return response('unauthorized action', 401);
        }

        $validatedData = Validator::make($request->all(), [
            'holder-name' => 'required|string',
            'holder-key' => 'required|string',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'message' => $validatedData->errors()->all(),
            ]);
        }

        $recordExists = PremCalcPlaceholder::where('placeholder_name', $request['holder-name'])
        ->where('placeholder_key', $request['holder-key'])
        ->exists();

        if (!$recordExists) {
            $record = PremCalcPlaceholder::find($request['holderID']);
            if ($record) {
                $record->placeholder_name = $request['holder-name'];
                $record->placeholder_key = $request['holder-key'];
                $record->placeholder_value = '#';
                $record->save();
                return response()->json(['success' => true, 'message' => 'Data Updated Successfully.']);
            } else {
                return response()->json(['success' => false, 'message' => 'Record Not Found.']);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Data already exists']);
        }
    }

    public function deletePlaceholder(Request $request)
    {
        if (!auth()->user()->can('ic_placeholder.delete')) {
            return response('unauthorized action', 401);
        }
        $validatedData = $request->validate([
            'Attribute_id' => 'required|integer',
        ]);

        $item = PremCalcPlaceholder::where('id', $request['Attribute_id'])
        ->exists();
        $id = $request['Attribute_id'];

        $dataExists =  PremCalcFormula::where('matrix', 'like', "%|PL:$id|%")
            ->whereNull('deleted_at')
            ->exists();

        if ($item) {
            if ($dataExists) {
                return response()->json(['success' => true, 'message' => 'Data already useed in formula Expression']);
            } else {
                PremCalcPlaceholder::where('id', $validatedData['Attribute_id'])->where('placeholder_type', 'user')->delete();
                return response()->json(['success' => true, 'message' => 'Data deleted successfully.']);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Data not found.']);
        }
    }

    public function showPlaceholder(Request $request)
    {
        if (!auth()->user()->can('ic_placeholder.show')) {
            return response('unauthorized action', 401);
        }

        $id = $request['Attribute_id'];
        $dataExists =  PremCalcFormula::where('matrix', 'like', "%|PL:$id|%")
        ->whereNull('deleted_at')
        ->get()->toArray();

        if(!empty($dataExists)){
            return response()->json(['success' => true, 'data' => $dataExists]);
        }else{
            return response()->json(['success' => false, 'message' => 'no data found']);
        }
    }

    public function addVersionData(Request $request)
    {
        if (!auth()->user()->can('ic_placeholder.create')) {
            return response('unauthorized action', 401);
        }

        $alias =MasterCompany::select('company_alias', 'company_name', 'company_id')
        ->whereNotNull('company_alias')
        ->distinct()
        ->orderBy('company_name', 'ASC')
        ->get()->toArray();

        $segment = MasterProductSubType::select('product_sub_type_id', 'product_sub_type_code')
        ->where('parent_id', 0)
        ->orderBy('product_sub_type_code', 'ASC')
        ->get()->toArray();

        $listing = IcVersionConfigurator::all()->toAarray();

        $version = IcVersionConfigurator::select('version', 'ic_version_configurators.*' , 'master_product_sub_type.product_sub_type_code' , 'integration_type' , 'business_type' , 'master_product_sub_type.parent_product_sub_type_id')
        ->join('master_product_sub_type', 'ic_version_configurators.segment_id', '=', 'master_product_sub_type.product_sub_type_id')
        ->get()->toArray();

        $versionData = collect($version)->map(function ($item) {
            $item['version_id'] = $item['version'];
            $item['version'] = $item['version'] == 1 ? 'V1' : ($item['version'] == 2 ? 'V2' : ($item['version'] == 3 ? 'V3' : $item['version']));
            return $item;
        });

        $integrationType = IcIntegrationType::select('integration_type')
        ->distinct()
        ->get()->toArray();

        $businessType = IcIntegrationType::select('business_type')
        ->distinct()
        ->get()->toArray();

        return view('admin_lte.ic_configurator.add-version-data', ['alias' => $alias, 'segment' => $segment, 'listing' => $listing , 'versionData' => $versionData , 'integrationType' => $integrationType , 'businessType' => $businessType]);
        
    }
}