<?php

namespace App\Http\Controllers\Inspection;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\MasterProductSubType as ModelsMasterProductSubType;
use App\Http\Controllers\LiveCheck\LivechekBreakinController;
use App\Http\Controllers\wimwisure\WimwisureBreakinController;
use App\Models\UserTokenRequestResponse;
use Illuminate\Support\Facades\Http;

// [define] : CV Import
use App\Http\Controllers\Inspection\Service\{
    AckoInspectionService as CV_ACKO,
    GoDigitInspectionService as CV_GODIGIT,
    HdfcErgoInspectionService as CV_HDFC,
    IciciLombardInspectionService as CV_ICICI,
    RelianceInspectionService as CV_RELIANCE,
    ShriramInspectionService as CV_SHRIRAM,
    UniversalSompoInspectionService as CV_US,
    TataAigInspectionService as CV_TATA
};

// [define] : Car Import
use App\Http\Controllers\Inspection\Service\Car\{
    UniversalSompoInspectionService as CAR_US,
    BajajAllianzInspectionService as CAR_BA,
    FutureGeneraliInspectionService as CAR_FG,
    libertyVideoconInspectionService as CAR_LIBERTY,
    IciciLombardInspectionService as CAR_ICICI_LOMBARD,
    GoDigitInspectionService as CAR_GODIGIT,
    RoyalSundaramInspectionService as CAR_RSA,
    TATAAIGInspectionService as CAR_TATA,
    HdfcErgoInspectionService as CAR_HDFC_ERGO,
    HdfcErgoV1NewFlowInspectionService as CAR_HDFC_ERGO_V1_NEW_FLOW,
    RelianceInspectionService as CAR_RELIANCE,
    KotakInspectionService as CAR_KOTAK,
    IffcotokioInspectionService as CAR_IFFCO_TOKIO,
    ShriramInspectionService as CAR_SHRIRAM
};

//bike section
use App\Http\Controllers\Inspection\Service\Bike\{
    GoDigitInspectionService as BIKE_GODIGIT,
    IciciLombardInspectionService as BIKE_ICICI
};
use App\Http\Controllers\Inspection\Service\Cv\V2\{
    ShriramInspectionService as CV_SHRIRAM_V2
};

use App\Http\Controllers\Mail\MailController;
use App\Models\CvBreakinStatus;
use App\Models\UserProductJourney;
use App\Models\UserProposal;
use Illuminate\Database\Eloquent\Builder;
include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';
use App\Http\Controllers\Inspection\Service\Car\ChollaMandalamInspectionService;


class InspectionController extends Controller
{
   /*  public function inspectionConfirm(Request $request)
    {
        $request->validate([
            'inspectionNo' => ['required'],
            'companyAlias' => ['required'],
            'productType' => ['required']
        ]);

        switch ($request->companyAlias) {
            case 'shriram':
                return ShriramInspectionService::inspectionConfirm($request);
                break;
            case 'godigit':
                return GoDigitInspectionService::inspectionConfirm($request);
                break;
            case 'acko':
                return AckoInspectionService::inspectionConfirm($request);
                break;
            case 'icici_lombard':
                return IciciLombardInspectionService::inspectionConfirm($request);
                break;
            case 'hdfc_ergo':
                return HdfcErgoInspectionService::inspectionConfirm($request);
                break;
            case 'reliance':
                return RelianceInspectionService::inspectionConfirm($request);
                break;
            case 'universal_sompo':
                return UniversalSompoInspectionService::inspectionConfirm($request);
                break;
            case 'future_generali':
                return FutureGeneraliInspectionService::inspectionConfirm($request);
                break;
            default:
                return response()->json([
                    'status' => false,
                    'msg' => 'invalid company alias name'
                ]);
        }
    } */

    public function inspectionConfirm(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'inspectionNo' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }


        try{

            $payload = DB::table('cv_breakin_status as cbs')
                    ->join('user_proposal as up', 'up.user_proposal_id', 'cbs.user_proposal_id')
                    ->where('breakin_number', $request->inspectionNo)
                    ->first();

            if(empty($payload))
            {
                throw new \Exception("Please enter valid inspection number");
            }

            // $user_product_journey_id = customDecrypt($payload->user_product_journey_id);
            $user_product_journey_id = $payload->user_product_journey_id;
            $quote_data = DB::table('quote_log as ql')
                        ->join('master_company as mc','mc.company_id','=','ql.ic_id')
                        ->join('master_product as mp','mp.master_policy_id','=','ql.master_policy_id')
                        ->where('ql.user_product_journey_id',$user_product_journey_id)
                        ->select(
                            'ql.user_product_journey_id','ql.product_sub_type_id','ql.ic_id','ql.master_policy_id',
                            'mc.company_name','mc.company_alias',
                            'mp.product_name'
                            )
                        ->first();

            if(empty($quote_data))
            {
                throw new \Exception("Quote log data not found.");
            }

            $product_type = strtolower(get_parent_code($quote_data->product_sub_type_id));

            $ic_name = $quote_data->company_alias;
            $request['product_name'] = $quote_data->product_name;
            $request['product_type'] = $product_type;
            $request['master_policy_id'] = $quote_data->master_policy_id;
            $request['ic_name'] = $ic_name;

            switch($product_type)
            {
                case 'bike' :
                    switch($ic_name)
                    {
                        case 'godigit':
                            $response = BIKE_GODIGIT::inspectionConfirm($request);
                            break;
                            case 'icici_lombard':
                                $response = BIKE_ICICI::inspectionConfirm($request);
                                break;
                    }//end switch case bike
                break;//end case Bike

                case 'car' :
                    switch($ic_name)
                    {
                        case 'shriram':
                            $response = CAR_SHRIRAM::inspectionConfirm($request);
                            break;
                        case 'bajaj_allianz':
                            $response = CAR_BA::inspectionConfirm($request);
                            break;
                        case 'universal_sompo':
                            $response = CAR_US::inspectionConfirm($request);
                            break;
                        case 'iffco_tokio':
                            $BREAKIN_SERVICE = config('IC.IFFCOTOKIO.CAR.BREAKIN.WIMWISURE.ENABLE');
                            if($BREAKIN_SERVICE == 'Y')
                            {
                                $response = CAR_IFFCO_TOKIO::wimwisureInspectionConfirm($request);
                            }
                            else
                            {
                                $obj = new LivechekBreakinController();
                                $response = $obj::inspectionConfirm($request);
                            }
                            break;
                        case 'future_generali':
                            $obj = new LivechekBreakinController();
                            $response = $obj::inspectionConfirm($request);
                            // return CAR_FG::inspectionConfirm($request);
                            break;
                        case 'liberty_videocon':
                            $response = CAR_LIBERTY::inspectionConfirm($request);
                            break;
                        case 'icici_lombard':
                            $response = CAR_ICICI_LOMBARD::inspectionConfirm($request);
                            break;
                        case 'godigit':
                            $response = CAR_GODIGIT::inspectionConfirm($request);
                            break;
                        case 'royal_sundaram':
                                return CAR_RSA::inspectionConfirm($request);
                            break;

                        case 'tata_aig':
                            return CAR_TATA::inspectionConfirm($request);
                            break;

                        case 'hdfc_ergo':
                            if (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V2_ENABLED_FOR_CAR') == 'Y') {
                                $response = CAR_HDFC_ERGO::v2InspectionConfirm($request);
                            } else if (
                                (config('constants.IcConstants.hdfc_ergo.IS_HDFC_ERGO_JSON_V1_NEW_FLOW_ENABLED_FOR_CAR') == 'Y') &&
                                (config('IC.HDFC_ERGO.V1.CAR.ENABLE') === 'N' || config('IC.HDFC_ERGO.V1.CAR.ENABLE') === null)
                            ) {
                                $response = CAR_HDFC_ERGO_V1_NEW_FLOW::V1NewFlowInspectionConfirm($request);
                            } else {
                                return;
                            }

                            break;

                        case 'reliance':
                            return CAR_RELIANCE::inspectionConfirm($request);
                            break;
                            case 'kotak':
                              
                                    $response = CAR_KOTAK::wimwisureInspectionConfirm(($request));
                                                       
                                break;
                        case 'cholla_mandalam':
                            return ChollaMandalamInspectionService::inspectionConfirm($request);
                            break;
                        default:
                            throw new \Exception("Invalid Car company alias name");
                    }//end switch case car
                break;//end case car

                case 'pcv' :
                //     switch($ic_name){
                //         case 'shriram':
                //         if(config('IC.constant.SHRIRAM_GCV_PCV_JSON_V2_ENABLED') == 'Y'){
                //         $response = CV_SHRIRAM_V2::inspectionConfirm($request);
                //     }
                //     break;
                //     default:
                //             throw new \Exception("Invalid company alias name");

                // }
                case 'misc' :
                case 'gcv' :
                    switch($ic_name)
                    {
                        case 'future_generali':
                            $obj = new LivechekBreakinController();
                            $response = $obj::inspectionConfirm($request);
                            break;
                        case 'iffco_tokio':
                            $obj = new LivechekBreakinController();
                            $response = $obj::inspectionConfirm($request);
                            break;
                        case 'universal_sompo':
                                $response = CV_US::inspectionConfirm($request);
                            break;
                        case 'shriram':
                            if(config('IC.constant.SHRIRAM_GCV_PCV_JSON_V2_ENABLED') == 'Y'){
                                $response = CV_SHRIRAM_V2::inspectionConfirm($request);
                            }else{
                            $response = CV_SHRIRAM::inspectionConfirm($request);}
                            break;
                        case 'godigit':
                            if (config('constants.motor.IS_WIMWISURE_GODIGIT_ENABLED') == 'Y')
                            {
                                $response = CV_GODIGIT::wimwisureInspectionConfirm($request);
                            }
                            else
                            {
                                $response = CV_GODIGIT::inspectionConfirm($request);
                            }
                            break;
                        case 'acko':
                            $response = CV_ACKO::inspectionConfirm($request);
                            break;
                        case 'icici_lombard':
                            return CV_ICICI::inspectionConfirm($request);
                            break;
                        case 'hdfc_ergo':
                            $response = CV_HDFC::inspectionConfirm($request);
                            break;
                        case 'reliance':
                            if (config('constants.IcConstants.reliance.IS_WIMWISURE_RELIANCE_ENABLED') == 'Y')
                            {
                                $response = CV_RELIANCE::wimwisureInspectionConfirm(($request));
                            }
                            else
                            {
                                $response = CV_RELIANCE::inspectionConfirm($request);
                            }                            
                            break;
                        case 'tata_aig':
                            $response  = CV_TATA::inspectionConfirm($request);
                            break;
                        default:
                            throw new \Exception("Invalid company alias name");
                    }
                break;//end case pcv gcv

                default:
                    throw new \Exception("Invalid Product Type");
            }

           
            if ($response)
            {
                if ((is_object($response) && isset($response->original['status']) && $response->original['status']) || (is_array($response) && isset($response['status']) && $response['status']))
                {
                    $cv_breakin_status = CvBreakinStatus::where('breakin_number', $request->inspectionNo)
                            ->first();

                    $enquiry_id = $cv_breakin_status->user_proposal->user_product_journey_id;
                    if(config('constants.motorConstant.SMS_FOLDER') === "renewbuy"){
                        MailController::renewbuyInspectionApprovalNotify($enquiry_id);
                    }
                    if(config('constants.motorConstant.SMS_FOLDER') === "tmibasl"){
                        MailController::tmibaslInspectionApprovalNotify($enquiry_id);
                    }
                    if(config('constants.motorConstant.SMS_FOLDER') === "pinc"){
                        MailController::pincInspectionApprovalNotify($enquiry_id);
                    }
                    if(config('constants.motorConstant.SMS_FOLDER') === "hero"){

                        $user_product_journey = UserProposal::where('user_product_journey_id', $enquiry_id)
                        ->value('mobile_number');
                        $requestData = [
                            'type' => 'inspectionApproval', 
                            'enquiryId' => $enquiry_id,
                            'to' =>  $user_product_journey,
                        ];
                        MailController::hero_whatsapp($requestData);
                    }
                    if(config('constants.motorConstant.SMS_FOLDER') === "shree"){
                        MailController::shreeInspectionApprovalNotify($enquiry_id);
                    }
                    if(config('constants.motorConstant.SMS_FOLDER') === "sib"){
                        MailController::sibInspectionApprovalNotify($enquiry_id);
                    }
                    if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y')
                    {
                        $user_product_journey = UserProductJourney::find($enquiry_id);
                        $lsq_journey_id_mapping = $user_product_journey->lsq_journey_id_mapping;

                        if ($lsq_journey_id_mapping)
                        {
                            updateLsqOpportunity($enquiry_id);
                            createLsqActivity($enquiry_id);
                        }
                    }
                }
            }

            return $response;
        }catch (\Exception $e) {
            return [
                'status' => false,
                'msg'    => $e->getMessage(),
                'error_trace' => basename($e->getFile()) . ' : ' .$e->getLine(),
                'data'   => []
            ];
        }
    }

    public function getInspectionList(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'from' => ['required'],
            'to' => ['required'],
            'seller_type' => ['nullable'],
            'product_type' => ['required'],
            //'seller_id' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        if (!is_array($request->product_type)) {
            $request->product_type = [$request->product_type];
        }

        if (!empty($request->seller_type) && !is_array($request->seller_type)) {
            $request->seller_type = [$request->seller_type];
        }

        $filter_sub_type_id = ModelsMasterProductSubType::whereIn('parent_product_sub_type_id', $request->product_type)->select('product_sub_type_id')->get()->pluck('product_sub_type_id');

        if ($filter_sub_type_id->isEmpty()) {
            $filter_sub_type_id = $request->product_type;
        }

        $breakin_data = DB::table('cv_agent_mappings as cam')
                ->join('user_proposal as up','up.user_proposal_id','=','cam.user_proposal_id')
                ->join('cv_breakin_status as bs','bs.user_proposal_id','=','cam.user_proposal_id')
                ->leftjoin('payment_request_response as prr','prr.user_proposal_id','=','bs.user_proposal_id')
                ->join('master_company as mc','mc.company_id','=','bs.ic_id')
                ->join('user_product_journey as upj','upj.user_product_journey_id','=','up.user_product_journey_id')
                ->join('master_product_sub_type as mpst','mpst.product_sub_type_id','=','upj.product_sub_type_id')
                //->join('master_product_sub_type as mpst2','mpst2.product_sub_type_id','=','mpst.parent_id')
                ->join('cv_journey_stages as stag','stag.user_product_journey_id','=','up.user_product_journey_id')
                ->join('quote_log as ql','ql.user_product_journey_id','=','up.user_product_journey_id')
                ->whereNull('prr.status')
                ->whereIn('mpst.product_sub_type_id', $filter_sub_type_id)
                ->whereBetween('bs.created_at', [
                    \Carbon\Carbon::parse($request->from)->startOfDay(),
                    \Carbon\Carbon::parse($request->to)->endOfDay(),
                ]);

                if(!empty(request()->block_ic)){
                    $breakin_data = $breakin_data->whereNotIn('mc.company_alias', request()->block_ic);
                }
        // ->whereDate('bs.created_at', '>=', $request->from)
        // ->whereDate('bs.created_at', '<=', $request->to);
        /*    $breakin_data->where(function ($query){
                    $i = 0;
                    foreach(request()->combined_seller_ids ?? [] as $key => $value){
                        $key = ($key == 'b2c') ? 'U' : $key;
                        if($i == 0)
                            if(empty($value))
                                $query->where('seller_type', $key);
                                else
                                $query->where('seller_type', $key)->whereIn('agent_id', $value);
                        else
                            if(empty($value))
                                $query->orWhere('seller_type', $key);
                            else
                                $query->orWhere('seller_type', $key)->whereIn('agent_id', $value);
                        $i++;
                    }
                }); */

                if (!empty($request->combined_seller_ids)) {
                    $breakin_data = $breakin_data->where(function ($query) {
                        $i = 0;
                        foreach (request()->combined_seller_ids as $key => $value) {
                            if ($i == 0)
                                $where_condition = 'where';
                            else
                            $where_condition = 'orWhere';

                            $query->$where_condition(function ($query) use ($key, $value) {
                                if (
                                    $key == 'b2c'
                                ) {
                                    $query = $query->WhereNull('cam.seller_type')->whereNotNull('cam.user_id');
                                    if (!empty($value)){
                                        $query->whereIn( 'cam.user_id', $value );
                                    }
                                } else if ($key == 'U') {
                                    $query = $query->where('cam.seller_type', $key)->whereNotNull('cam.user_id');
                                    if (!empty($value)){ 
                                        $query->whereIn('cam.user_id', $value);
                                    }
                                } else {
                                    $query = $query->orWhere('cam.seller_type', $key);
                                    if (!empty($value)) {
                                        $query->whereIn('cam.agent_id', $value);
                                    }
                                }
                            });
                            $i++;
                        }
                    });
                }


                if(!empty($request->seller_type) && !in_array("b2c", $request->seller_type)){
                    $breakin_data = $breakin_data->whereIn('cam.seller_type', $request->seller_type);
                }else{
                    $breakin_data = $breakin_data->whereNull('cam.seller_type');
                }

                $breakin_data = $breakin_data->whereNull('cam.source');
                if(!empty($request->seller_id))
                {
                $breakin_data = $breakin_data->whereIn('cam.agent_id',$request->seller_id);
                }
                $breakin_data = $breakin_data->select
                ('up.user_product_journey_id','up.proposal_no','up.first_name','up.last_name','up.mobile_number','up.office_email','up.vehicale_registration_number','up.engine_number','up.chassis_number','up.idv','up.ic_vehicle_details',
                'bs.breakin_number','bs.breakin_status','bs.breakin_status_final','bs.payment_end_date','bs.created_at','bs.updated_at','stag.proposal_url', 'bs.payment_url','bs.breakin_check_url',
                'mc.company_name','mc.company_alias',
                'stag.stage as transaction_stage',
                //'mpst2.product_sub_type_code as product',
                'mpst.product_sub_type_id',
                'mpst.product_sub_type_code as sub_product',
                'cam.agent_id as seller_id','cam.seller_type as seller_type', 'cam.agent_name as seller_name','cam.agent_mobile as seller_mobile', 'cam.user_id as user_id',
                'ql.quote_data'
            )->get();
                // $addSlashes = str_replace('?', "'?'", $breakin_data->toSql());
                // return vsprintf(str_replace('?', '%s', $addSlashes), $breakin_data->getBindings() ?? []);

                $proposalModel = new UserProposal();
                $casts = [];
                foreach ($proposalModel->getCasts() as $key => $value) {
                    if (
                        $value == 'App\Casts\PersonalDataEncryption'
                    ) {
                        $casts[] = $key;
                    }
                }
        
        foreach ($breakin_data as $key => $value) {

            foreach ($value as $element => $item) {
                if (in_array($element, $casts) && !empty($item)) {
                    $breakin_data[$key]->{$element} = decryptData($item);
                }
            }
            
            if(config('enquiry_id_encryption') == 'Y')
            {
                $breakin_data[$key]->enquiry_id = getDecryptedEnquiryId(customEncrypt($value->user_product_journey_id));
                $breakin_data[$key]->encrypted_enquiry_id = customEncrypt($value->user_product_journey_id);
            }else{
                $breakin_data[$key]->enquiry_id = customEncrypt($value->user_product_journey_id);
            }
            $breakin_data[$key]->product = strtolower(get_parent_code($value->product_sub_type_id));
            $quote_data = json_decode(json_decode($value->quote_data,true),true);
            unset($quote_data['full_name'],$quote_data['user_email'],$quote_data['user_mobile'],
             $quote_data['product_sub_type_id'],$quote_data['manfacture_id'],$quote_data['model'],
             $quote_data['vehicle_usage'],$quote_data['vehicle_registration_no'],$quote_data['vehicle_register_date'],
             $quote_data['is_claim'], $quote_data['previous_policy_expiry_date'],$quote_data['previous_policy_type'],
             $value->user_product_journey_id
            );
            if(empty($value->seller_id) && empty($value->seller_type) && !empty($value->user_id)){
                $value->seller_type = 'b2c';
                $value->seller_id = $value->user_id;
            }
            $breakin_data[$key]->payment_url = $breakin_data[$key]->payment_url ?? $breakin_data[$key]->proposal_url;
            $breakin_data[$key]->vehicle_details = $quote_data;
            unset($breakin_data[$key]->quote_data);
        }

        if(count($breakin_data) > 0)
        {
            return response()->json([
                'status' => true,
                'data'   => $breakin_data
            ]);
        }
        else
        {
            return response()->json([
                'status' => false,
                'data'   => 'No Data Found'
            ]);
        }
    }
    public function getAppInspectionList(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'token' => ['required'],
            'journey_type' => ['nullable'],
            'from' => ['required'],
            'to' => ['required'],
            //'product_type' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        if(isset($request->journey_type) && $request->journey_type == 'Z3JhbWNvdmVyLWFwcC1qb3VybmV5'){
            $response = httpRequest('login_token_validate', ['token' => $request->token]);
            ;
            UserTokenRequestResponse::create([
                'user_type' => base64_decode($request->journey_type),
                'request' => json_encode($response['request']),
                'response' => json_encode($response['response']),
            ]);
            $response['response']['result']['userdata']['dp_token']=$request->journey_type;
            $data = Http::withoutVerifying()->post(config('constants.IcConstants.GRAMCOVER_REMOTE_TOKEN_VERIFY_URL'), ['remote_token' => $request->token])->json();
            if(isset($response['response']) && $response['response']['status'] && $response['response']['status_code'] == 3001 ){
                $request->seller_id = $data['data']['remote_user_id'];#$response['response']['result']['userdata']['user_id'];
                $request->from = $request->from;
                $request->to   = $request->to;
                if (!is_array($request->seller_id)) {
                    $request->seller_id = [$request->seller_id];
                }
                $breakin_data = DB::table('cv_agent_mappings as cam')
                        ->join('user_proposal as up','up.user_proposal_id','=','cam.user_proposal_id')
                        ->join('cv_breakin_status as bs','bs.user_proposal_id','=','cam.user_proposal_id')
                        ->leftjoin('payment_request_response as prr','prr.user_proposal_id','=','bs.user_proposal_id')
                        ->join('master_company as mc','mc.company_id','=','bs.ic_id')
                        ->join('user_product_journey as upj','upj.user_product_journey_id','=','up.user_product_journey_id')
                        ->join('master_product_sub_type as mpst','mpst.product_sub_type_id','=','upj.product_sub_type_id')
                        //->join('master_product_sub_type as mpst2','mpst2.product_sub_type_id','=','mpst.parent_id')
                        ->join('cv_journey_stages as stag','stag.user_product_journey_id','=','up.user_product_journey_id')
                        ->join('quote_log as ql','ql.user_product_journey_id','=','up.user_product_journey_id')
                        ->whereNull('prr.status')
                        //->whereIn('mpst.product_sub_type_id', $filter_sub_type_id)
                        ->where('cam.source','gramcover-app-journey')
                        //->whereIn('cam.seller_type',[$request->seller_type])
                        ->whereDate('bs.created_at', '>=', $request->from)
                        ->whereDate('bs.created_at', '<=', $request->to);
                        if(!empty($request->seller_id))
                        {
                        $breakin_data = $breakin_data->whereIn('cam.agent_id',$request->seller_id);
                        }
                        $breakin_data = $breakin_data->select
                        ('up.user_product_journey_id','up.proposal_no','up.first_name','up.last_name','up.mobile_number','up.office_email','up.vehicale_registration_number','up.engine_number','up.chassis_number','up.idv','up.ic_vehicle_details',
                        'bs.breakin_number','bs.breakin_status','bs.breakin_status_final','bs.payment_end_date','bs.created_at','bs.updated_at','bs.payment_url','bs.breakin_check_url',
                        'mc.company_name','mc.company_alias',
                        'stag.stage as transaction_stage',
                        //'mpst2.product_sub_type_code as product',
                        'mpst.product_sub_type_id',
                        'mpst.product_sub_type_code as sub_product',
                        'cam.agent_id as seller_id','cam.agent_name as seller_name','cam.agent_mobile as seller_mobile',
                        'ql.quote_data'
                        )
                        ->get();
                foreach ($breakin_data as $key => $value) {
                    $breakin_data[$key]->enquiry_id = customEncrypt($value->user_product_journey_id);
                    $breakin_data[$key]->product = strtolower(get_parent_code($value->product_sub_type_id));
                    $quote_data = json_decode(json_decode($value->quote_data,true),true);
                    unset($quote_data['full_name'],$quote_data['user_email'],$quote_data['user_mobile'],
                     $quote_data['product_sub_type_id'],$quote_data['manfacture_id'],$quote_data['model'],
                     $quote_data['vehicle_usage'],$quote_data['vehicle_registration_no'],$quote_data['vehicle_register_date'],
                     $quote_data['is_claim'], $quote_data['previous_policy_expiry_date'],$quote_data['previous_policy_type']
                    );
                    $breakin_data[$key]->vehicle_details = $quote_data;
                    unset($breakin_data[$key]->quote_data);
                }
        
                if(count($breakin_data) > 0)
                {
                    return response()->json([
                        'status' => true,
                        'data'   => $breakin_data
                    ]);
                }
                else
                {
                    return response()->json([
                        'status' => false,
                        'data'   => []#'No Data Found'
                    ]);
                }
            } else{
                return response()->json([
                    "status" => false,
                    "msg" => $response['response']['result'],
                ]);
            }
        }else{
            return response()->json([
                "status" => false,
                "msg" => 'Something Went Wrong',
            ]);
        }
        
    }
}
