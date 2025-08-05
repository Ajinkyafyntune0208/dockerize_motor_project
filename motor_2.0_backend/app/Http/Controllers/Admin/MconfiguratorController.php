<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CommonController;
use App\Http\Controllers\Controller;

use App\Models\{
    BrokerConfigAsset,
    ProposalValidation, 
    MasterCompany, 
    BrokerDetail,
    ThemeConfig
};

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MconfiguratorController extends Controller
{
    public function proposalShow()
    {
        if (!auth()->user()->can('configurator.proposal')) {
            return response('unauthorized action', 401);
        }

        $data = ProposalValidation::first();
        $company = DB::table('master_company')
                        ->distinct('company_alias')
                        ->where('company_alias','!=',null,'or','company_alias','!=','')
                        ->join('master_policy' , 'master_policy.insurance_company_id','=','master_company.company_id')
                        ->join('master_product', 'master_product.master_policy_id','=','master_policy.policy_id')
                        ->where('master_policy.status','active')
                        ->where('master_company.status','active')
                        ->select('company_alias')
                        ->pluck('company_alias')
                        ->toArray();                              
        return view ('master_configurator.ProposalVal',compact('data'))->with('comp',$company);
    }
    public function onboardingShow()
    {
        if (!auth()->user()->can('configurator.onboarding')) {
            return response('unauthorized action', 401);
        }

        if(config("constants.motorConstant.SMS_FOLDER") == "fyntune"){
            $broker = BrokerDetail::select('name')->get();
            return view ('master_configurator.Onboarding',compact('broker'));
        }else {  
            $bname = 'Current';
            // $url = route("api.themeConfig");
            // $data = httpRequestNormal($url,'GET',[],[],[],[],false);
            
            $CommonController = new CommonController;
            $getBrokerconfig = new Request([]);
            $getBrokerconfig->method('get');
            $data = $CommonController->themeConfig($getBrokerconfig)->getOriginalContent();

            if(!$data['status']){
                //error handling when server status code is other than 200
                return view ('master_configurator.OnboardingShow',compact('bname','data'))->with('error','Something went wrong while sending request to api! '.'Server Response Code: '.$data['status']);
                // return response()->json([
                //     "status" => $data['status'],
                //     "message" => $data['response'] ?? 'Something went wrong while sending request to api!',
                // ]);
            }
  
            $data = $data['data']['broker_config'] ;
            if ($data == [] || null ){

                $data = [
                    "irdanumber" => '',
                    "cinnumber" => '',
                    "BrokerName" => '',
                    "BrokerCategory" => '',
                    "lead_page_title" => '',
                    "mobile_lead_page_title" => '',
                    "p_declaration" => '',
                    "time_out" => '',
                    "email" => '',
                    "phone" => '',
                    "brokerSupportEmail" => '',
                    "gst_style" => '',
                    "quoteview" => '',
                    //yes or no field
                    "renewal" => null,
                    "gst" =>  null,
                    "cpa" =>  null ,
                    "multicpa" =>  null,
                    "ncbconfig" =>  null,
                    //true or false field
                    "noBack" =>  false,
                    "fullName" =>  false,
                    "mobileNo" =>  false,
                    "lead_email" => false,
                    "lead_otp" =>  false,
                    "vahan_err" => false,
                    'allow_multipayment' => false,
                    "ckyc_mandate" => false,
                    "payment_redirection" => false,
                    "ckyc_redirection" =>  false,
                    "fastlane_error" =>  false,
                    "journey_block" =>  false,
                    "hide_retry" =>  false,
                    "block_home_redirection" => false,
                    "showBreadcrumbs" =>  false,
                    "enableMultiLanguages" =>  false,
                    'pc_redirection' => false,
                    "vahan_error" =>  "",
                    "mandate_title" => "",
                    "mandate_h" =>  "",
                    "mandate_p1" =>  "",
                    "mandate_p2" =>  "",
                    "payment_redirection_message" =>  "",
                    "ckyc_redirection_message" =>  "",          
                    "fastlane_error_message" =>  "",   
                    "file_ic_config" =>  "", 
                ];
    
                // dd($data);
                $warning ='WARNING! Settings set on this broker is set to default which is configured from Front-end. 
                Any changes here on submit will overwrite default settings.';
                
                return view ('master_configurator.OnboardingShow',compact('bname','data','warning'));
    
            }

            $company = DB::table('master_company')
                ->distinct('company_alias')
                ->where('company_alias','!=',null,'or','company_alias','!=','')
                ->join('master_policy' , 'master_policy.insurance_company_id','=','master_company.company_id')
                ->join('master_product', 'master_product.master_policy_id','=','master_policy.policy_id')
                ->where('master_policy.status','active')
                ->where('master_company.status','active')
                ->select('company_alias')
                ->pluck('company_alias')
                ->toArray();     

                $brokerConfigAsset = BrokerConfigAsset::select('key', 'value')->get();

            return view ('master_configurator.OnboardingShow',compact('bname','data', 'brokerConfigAsset'))->with('comp',$company);
        }
      
    }
    public function onboardingFetch(Request $request)
    {
        if (!auth()->user()->can('configurator.onboarding')) {
            return response('unauthorized action', 401);
        }
        
        $validator = Validator::make($request->all(), [
            'broker' => 'required',
        ]);
        if ($validator->fails()) {
            return back()->with('validatorerror','Please Select a broker');
            // return response()->json([
            //     "status" => false,
            //     "message" => $validator->errors(),
            // ]);
        }
        $bname = $request->broker;
        $broker = BrokerDetail::where('name',$bname)->first();
        $url = $broker['backend_url']."/api/themeConfig?test=true";
        // dd($url);
        $data = httpRequestNormal($url,'GET',[],[],[],[],false) ;
        if($data['status'] != 200){
            //error handling when server status code is other than 200
            return back()->with('error','Something went wrong while sending request to api! '.'Server Response Code: '.$data['status']);
            // return response()->json([
            //     "status" => $data['status'],
            //     "message" => $data['response'],
            // ]);
        }

        $data = $data['response']['data']['broker_config'] ;
      
        if ($data == [] || null ){
            $data = [
                "irdanumber" => '',
                "cinnumber" => '',
                "BrokerName" => '',
                "BrokerCategory" => '',
                "lead_page_title" => '',
                "mobile_lead_page_title" => '',
                "p_declaration" => '',
                "time_out" => '',
                "email" => '',
                "phone" => '',
                "brokerSupportEmail" => '',
                "gst_style" => '',
                "quoteview" => '',
                //yes or no field
                "renewal" => null,
                "gst" =>  null,
                "cpa" =>  null ,
                "multicpa" =>  null,
                "ncbconfig" =>  null,
           
                //true or false field
                "noBack" =>  false,
                "fullName" =>  false,
                "mobileNo" =>  false,
                "lead_email" => false,
                "lead_otp" =>  false,
                "vahan_err" => false,
                'allow_multipayment' => false,
                "ckyc_mandate" => false,
                "payment_redirection" => false,
                "ckyc_redirection" =>  false,
                "cpaOptOut" => false, 
                "feedbackModule" => false,
                "consentModule" => false,
                "fastlane_error" =>  false,
                "journey_block" =>  false,
                "showBreadcrumbs" =>  false,
                "hide_retry" =>  false,
                "block_home_redirection" => false,
                "enableMultiLanguages" =>  false,
                'pc_redirection' => false,
                "vahan_error" =>  "",
                "mandate_title" => "",
                "mandate_h" =>  "",
                "mandate_p1" =>  "",
                "mandate_p2" =>  "",
                "payment_redirection_message" =>  "",
                "ckyc_redirection_message" =>  "",          
                "fastlane_error_message" =>  "",    
                "file_ic_config" =>  "",
            ];

            // dd($data);
            $warning ='WARNING! Settings set on this broker is set to default which is configured from Front-end. 
            Any changes here on submit will overwrite default settings.';
            
            return view ('master_configurator.OnboardingShow',compact('bname','data','warning'));

        }

        $company = DB::table('master_company')
            ->distinct('company_alias')
            ->where('company_alias','!=',null,'or','company_alias','!=','')
            ->join('master_policy' , 'master_policy.insurance_company_id','=','master_company.company_id')
            ->join('master_product', 'master_product.master_policy_id','=','master_policy.policy_id')
            ->where('master_policy.status','active')
            ->where('master_company.status','active')
            ->select('company_alias')
            ->pluck('company_alias')
            ->toArray();     
       
        return view ('master_configurator.OnboardingShow',compact('bname','data'))->with('comp',$company);


    }
    public function onboardingStore($broker,Request $request)
    {
        if (!auth()->user()->can('configurator.onboarding')) {
            return response('unauthorized action', 401);
        }

        // fetch existing data
        $existing_broker_config = \App\Models\ThemeConfig::active()->where('key', null)->select('broker_config')->first();
        $broker_config = $existing_broker_config['broker_config'];
        $file_ic_config = $broker_config['file_ic_config'] ?? [];

        
        $changedbrokerconfig = [
            "irdanumber" => $request->irdanumber,
            "cinnumber" => $request->cinnumber,
            "BrokerName" => $request->BrokerName,
            "BrokerCategory" => $request->BrokerCategory,
            "lead_page_title" => $request->lead_page_title,
            "mobile_lead_page_title" => $request->mobile_lead_page_title,
            "p_declaration" => $request->p_declaration,
            "time_out" => $request->time_out,
            "email" => $request->email,
            "phone" => $request->phone,
            "brokerSupportEmail" => $request->brokerSupportEmail,
            "gst_style" => $request->gst_style,
            "quoteview" => $request->quoteview,

            //yes or no field
            "renewal" => $request->renewal ?? null,
            "gst" => $request->gst ?? null,
            "cpa" => $request->cpa ?? null ,
            "multicpa" => $request->multicpa ?? null,
            "ncbconfig" => $request->ncbconfig ?? null,
            "fiftyLakhNonPos" => $request->fiftyLakhNonPos ?? null,
            "threeMonthShortTermEnable" => $request->threeMonthShortTermEnable ?? null,
            "sixMonthShortTermEnable" => $request->sixMonthShortTermEnable ?? null,

            //true or false field
            "noBack" => $request->noBack ?? null,
            "fullName" => $request->fullName ?? null,
            "mobileNo" => $request->mobileNo ?? null,
            "lead_email" => $request->lead_email ?? null,
            "lead_otp" => $request->lead_otp ?? null,
            "vahan_err" => $request->vahan_err ?? null,
            'allow_multipayment' => $request->allow_multipayment ?? null,
            'enable_vahan' => $request->enable_vahan ?? null,
            'enableVehicleCategory' => $request->enableVehicleCategory ?? null,
            "ckyc_mandate" => $request->ckyc_mandate ?? null,
            "payment_redirection" => $request->payment_redirection ?? null,
            "ckyc_redirection" => $request->ckyc_redirection ?? null,
            "cpaOptOut" => $request->cpaOptOut ?? null, 
            "feedbackModule" => $request->feedbackModule ?? null,
            "consentModule" => $request->consentModule ?? null,
            "fastlane_error" => $request->fastlane_error ?? null,
            "journey_block" => $request->journey_block ?? null,
            "showBreadcrumbs" => $request->showBreadcrumbs ?? null,
            "hide_retry" => $request->hide_retry ?? null,
            "block_home_redirection" => $request->block_home_redirection ?? null,
            "enableMultiLanguages" => $request->enableMultiLanguages ?? null,
            'pc_redirection' => $request->pc_redirection ?? null
        ];
        if($request->redirection_url_status == "true"){
            $changedbrokerconfig['redirection_url_status'] = $request->redirection_url_status; 
            $changedbrokerconfig['pospRetUrl'] = $request->pospRetUrl ?? null;
            $changedbrokerconfig['employeeRetUrl'] = $request->employeeRetUrl ?? null;
            $changedbrokerconfig['b2cRetUrl'] = $request->b2cRetUrl ?? null;
            $changedbrokerconfig['b2cDashRetUrl'] = $request->b2cDashRetUrl ?? null;
        } else {
            $changedbrokerconfig['redirection_url_status'] = false;
            $changedbrokerconfig['pospRetUrl'] = null;
            $changedbrokerconfig['employeeRetUrl'] = null;
            $changedbrokerconfig['b2cRetUrl'] = null;
            $changedbrokerconfig['b2cDashRetUrl'] = null;
        }
        if( $changedbrokerconfig ["gst_style"] == "notFromTheme"){
            $changedbrokerconfig ["gst_text_color"] = $request->gst_text_color ?? "";
            $changedbrokerconfig ["gst_color"] = $request->gst_color ?? "";
            $changedbrokerconfig ["gst_color_no"] = $request->gst_color_no ?? "";
        }
        if($request->vahan_err == "true"){
            $changedbrokerconfig ["vahan_error"] = $request->vahan_error ?? "";
        }
        if($request->ckyc_mandate == "true"){
            $changedbrokerconfig ["mandate_title"] = $request->mandate_title ?? "";
            $changedbrokerconfig ["mandate_h"] = $request->mandate_h ?? "";
            $changedbrokerconfig ["mandate_p1"] = $request->mandate_p1 ?? "";
            $changedbrokerconfig ["mandate_p2"] = $request->mandate_p2 ?? "";
        }
        if($request->payment_redirection == "true"){
            $changedbrokerconfig ["payment_redirection_message"] = $request->payment_redirection_message ?? "";
        }
        if($request->ckyc_redirection == "true"){
            $changedbrokerconfig ["ckyc_redirection_message"] = $request->ckyc_redirection_message ?? "";
        }
        if($request->fastlane_error == "true"){
            $changedbrokerconfig ["fastlane_error_message"] = $request->fastlane_error_message ?? "";
        }
        if($request->cpaOptOut == "true"){
            $changedbrokerconfig ["cpaOptOutReasons"] = $request->cpaOptOutReasons ?? "";
        }

        $changedbrokerconfig = collect($changedbrokerconfig)->whereNotNull()->all();
        $newbrokerconfig = [];
        $changedbrokerconfig['file_ic_config'] = $file_ic_config;
        $newbrokerconfig['broker_config'] = json_encode($changedbrokerconfig,JSON_UNESCAPED_SLASHES);
 
        if( $broker == 'Current'){
            $postCommonController = new CommonController;
            $postBrokerconfig = new Request(['broker_config' => json_encode($changedbrokerconfig,JSON_UNESCAPED_SLASHES)]);
            $postBrokerconfig->setMethod('POST');
            $data = $postCommonController->themeConfig($postBrokerconfig)->getOriginalContent();

        } else {
            $cbroker = BrokerDetail::where('name',$broker)->first();
            $url = $cbroker['backend_url']."/api/themeConfig?test=true";
            $data = httpRequestNormal($url,'POST',$newbrokerconfig,[],[],[],false)['response'];
        }

        if($data['status'] && !empty($data['data']))
        {
            return redirect()->route('admin.config-onboarding')->with('success','New Broker config settings Successfully saved!');
        }

        return back()->with('error','Something went wrong while sending request to api!');

    }

    public function saveFileIcConfig(Request $request) {
        $existing_file_config = \App\Models\ThemeConfig::active()->where('key', null)->select('broker_config')->first();
        $broker_config = $existing_file_config['broker_config'];
        $file_ic_config = $broker_config['file_ic_config'] ?? [];
        foreach ($request->fileConfigIC as $fileConfigIC) {
            // check if the upcoming ic's data is already there or not
            if (($index = array_search($fileConfigIC, array_column($file_ic_config, 'ic'))) !== false) {
                // now we need to modify the data with the upcoming data
                $file_ic_config[$index] = [
                    "ic" => $fileConfigIC,
                    "maxFileSize" => $request->maxFileSize,
                    "acceptedExtensions" => $request->acceptedExtensions
                ];
            } else {
                // we need to insert the upcoming data into the existing array
                array_push($file_ic_config, [
                    "ic" => $fileConfigIC,
                    "maxFileSize" => $request->maxFileSize,
                    "acceptedExtensions" => $request->acceptedExtensions
                ]);
            }

            $broker_config['file_ic_config'] = $file_ic_config;

            \App\Models\ThemeConfig::updateOrCreate(['key' => null], ['broker_config' => $broker_config]);
        }
        return redirect()->route('admin.config-onboarding')->with('success','New Broker config settings Successfully saved!');
    }

    public function fieldShow(){

        if (!auth()->user()->can('configurator.field')) {
            return response('unauthorized action', 401);
        }
        
        return view ('master_configurator.FieldConfig');

    }
    public function otpShow(){

        if (!auth()->user()->can('configurator.OTP')) {
            return response('unauthorized action', 401);
        }

        return view ('master_configurator.OtpConfig');
    }

    /**
     * Check if the short term products are enabled or not.
     * @param array $returnArray : A reference variable from the CommonController's getProductDetails function
     * @return array : Filtered array of IC products on the basis of whether the short term is enabled or not.
     */
    public static function checkShortTermProducts(&$returnArray) {
        // Fetch theme config values
        $config = \App\Models\ThemeConfig::active()->where('key', null)->first('broker_config')->broker_config ?? null;
        if ($config) {
            if (($config['threeMonthShortTermEnable'] ?? '') == 'no') {
                $returnArray['short_term'] = array_values(array_filter($returnArray['short_term'], function($ic) {
                    return $ic['premiumTypeCode'] != 'short_term_3';
                }));
            }
            if (($config['sixMonthShortTermEnable'] ?? '') == 'no') {
                $returnArray['short_term'] = array_values(array_filter($returnArray['short_term'], function($ic) {
                    return $ic['premiumTypeCode'] != 'short_term_6';
                }));
            }
        }
        return $returnArray;
    }

    public function brokerLogo(Request $request)
    {
        $logoSize = config('constants.brokerConstant.logo.size');
        $faviconSize = config('constants.brokerConstant.favicon.size');

        $logoDimension = config('constants.brokerConstant.logo.dimension');
        $faviconDimension = config('constants.brokerConstant.favicon.dimension');

        $rules =
        [
            'brokerLogo' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/jpg',
            ],
            'brokerFavicon' => [
                'nullable',
                'file',
                'mimetypes:image/x-icon,image/vnd.microsoft.icon'
            ]
        ];
        if (!empty($logoSize)) {
            $rules['brokerLogo'][] = 'max:' . $logoSize;
        }
        if (!empty($faviconSize)) {
            $rules['brokerFavicon'][] = 'max:' . $faviconSize;
        }

        if (!empty($logoDimension)) {
            $logoDimension = explode(',', $logoDimension);
            $rules['brokerLogo'][] = 'dimensions:width=' . $logoDimension[0] . ',height=' . $logoDimension[1];
        }

        if (!empty($faviconDimension)) {
            $faviconDimension = explode(',', $faviconDimension);
            $rules['brokerFavicon'][] = 'dimensions:width=' . $faviconDimension[0] . ',height=' . $faviconDimension[1];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        if (empty($request->file('brokerLogo')) && empty($request->file('brokerFavicon'))) {
            return response()->json([
                'status' => false,
                'message' => 'Broker Logo or Favicon is required'
            ], 400);
        }

        $message = '';
        if ($request->file('brokerLogo')) {
            $brokerLogo = $this->convertImageToBase64($request->file('brokerLogo'));
            BrokerConfigAsset::updateOrCreate([
                'key' => 'logo'
            ], [

                'created_by' => Auth::user()->id,
                'updated_by' => Auth::user()->id,
                'value' => [
                    'base64' => $brokerLogo
                ]
            ]);
            $message = 'Broker logo ';
        }

        if ($request->file('brokerFavicon')) {
            $brokerFavicon = $this->convertImageToBase64($request->file('brokerFavicon'));

            BrokerConfigAsset::updateOrCreate([
                'key' => 'favicon'
            ], [

                'created_by' => Auth::user()->id,
                'updated_by' => Auth::user()->id,
                'value' => [
                    'base64' => $brokerFavicon
                ]
            ]);

            if (empty($message)) {
                $message = 'Broker favicon ';
            } else {
                $message .=' and favicon ';
            }
        }

        $message .= 'updated successfully';

        return response()->json([
            'status' => true,
            'message' => $message
        ]);
    }

    public function brokerScripts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brokerScript.*' => 'required',
            'brokerPage.*' => 'required|in:all,quote,proposal,thank_you',
            'scriptPriority.*' => 'required|in:start,middle,end',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }
        $input = $request->input();
        $data = [];
        foreach ($input['brokerScript'] as $key => $value) {
            array_push($data, [
                'scripts' => base64_encode($value),
                'appliedPage' => $input['brokerPage'][$key],
                'priority' => $input['scriptPriority'][$key],
            ]);
        }

        BrokerConfigAsset::updateOrCreate([
            'key' => 'scripts'
        ], [

            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
            'value' => $data
        ]);

        return response()->json([
            'status' => true,
            'message' => 'scripts saved successfully'
        ], 201);

    }

    public function convertImageToBase64($file)
    {
        // Get the contents of the uploaded file
        $fileContents = file_get_contents($file->getPathname());

        // Encode the file contents to base64
        $base64 = base64_encode($fileContents);

        // Get the MIME type of the uploaded file
        $mimeType = $file->getMimeType();

        // Return the base64-encoded string with MIME type
        return "data:$mimeType;base64,$base64";
    }

    public function saveTitleData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'generic.title' => 'required|string|max:255',
            'generic.description' => 'required|string|max:1000',
            'car.title' => 'required|string|max:255',
            'car.description' => 'required|string|max:1000',
            'bike.title' => 'required|string|max:255',
            'bike.description' => 'required|string|max:1000',
            'cv.title' => 'required|string|max:255',
            'cv.description' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $data = $request->only(['generic', 'car', 'bike', 'cv']);
        $brokerConfigAsset =  BrokerConfigAsset::updateOrCreate([
            'key' => 'title_description'
        ], [

            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
            'value' => $data
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data stored successfully',
            'data' => $brokerConfigAsset,
        ]);
    }
    
    public function brokerUrlRedirection(Request $request)
    {
       
        $changedbrokerconfigs = [];
        if ($request->has('logo_url')) {
            $changedbrokerconfigs['logo_url'] = [
                'isEnabled' => true,
                'url' => $request->logo_url
            ];
        } else {
            $changedbrokerconfigs['logo_url'] = [
                'isEnabled' => false,
                'url' => null
            ];
        }

        if ($request->has('success_payment_url_redirection')) {
            $changedbrokerconfigs['success_payment_url_redirection'] = [
                'isEnabled' => true,
                'url' => $request->success_payment_url_redirection ?? null
            ];
        } else {
            $changedbrokerconfigs['success_payment_url_redirection'] = [
                'isEnabled' => false,
                'url' => null
            ];
        }

        if ($request->has('other_failure_url')) {
            $changedbrokerconfigs['other_failure_url'] = [
                'isEnabled' => true,
                'url' => $request->other_failure_url ?? null
            ];
        } else {
            $changedbrokerconfigs['other_failure_url'] = [
                'isEnabled' => false,
                'url' => null
            ];
        }

        foreach ($changedbrokerconfigs as $key => $value) {
            BrokerConfigAsset::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,  
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id()
                ]
            );
        }
        return redirect()->route('admin.config-onboarding')->with('success','New Broker config settings Successfully saved!');
    }

    public function journeyConfigurator(Request $request)
    {
        $isEnabled = $request->input('enable', false) ? 'true' : 'false';
        $selectedOptions = $request->input('options', []);
        $data = [
            'is_enabled' => $isEnabled,
            'b2b_journey' => in_array('B2B', $selectedOptions),
            'b2c_journey' => in_array('B2C', $selectedOptions),
        ];
        BrokerConfigAsset::updateOrCreate(
            ['key' => 'journey_config'],
            [
                'value' => $data,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id()
            ]
        );
        return redirect()->route('admin.config-onboarding')->with('success', 'Data submitted successfully');
    }
}
