<?php

namespace App\Http\Controllers\Inspection;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Jobs\IciciLombardBrekinStatusUpdate;
use App\Http\Controllers\Inspection\Service\AckoInspectionService;
use App\Http\Controllers\Inspection\Service\ShriramInspectionService;
use App\Http\Controllers\Inspection\Service\HdfcErgoInspectionService;
use App\Http\Controllers\Inspection\Service\RelianceInspectionService;
use App\Http\Controllers\Inspection\Service\Car\GoDigitInspectionService;
use App\Http\Controllers\Inspection\Service\IciciLombardInspectionService;
use App\Http\Controllers\Inspection\Service\Car\RoyalSundaramInspectionService;
use App\Http\Controllers\Inspection\Service\Car\FutureGeneraliInspectionService;
use App\Http\Controllers\Inspection\Service\Car\libertyVideoconInspectionService;
use App\Models\UserProposal;
use App\Http\Controllers\Inspection\Service\Car\ChollaMandalamInspectionService;


class CarInspectionController extends Controller
{
    public function inspectionConfirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inspectionNo' => ['required'],
            'companyAlias' => ['required'],
            'productType' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

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
                IciciLombardBrekinStatusUpdate::dispatch();
                return IciciLombardInspectionService::inspectionConfirm($request);
                break;
            case 'hdfc_ergo':
                return HdfcErgoInspectionService::inspectionConfirm($request);
                break;
            case 'reliance':
                return RelianceInspectionService::inspectionConfirm($request);
                break;
            case 'royal_sundaram':
                return RoyalSundaramInspectionService::inspectionConfirm($request);
                break;
            case 'cholla_mandalam':
                return ChollaMandalamInspectionService::inspectionConfirm($request);
                break;
            default:
                return response()->json([
                    'status' => false,
                    'msg' => 'invalid company alias name'
                ]);
        }
    }
    
    public function getInspectionList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => ['required'],
            'to' => ['required'],
            'seller_type' => ['required'],
            //'seller_id' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';

        $breakin_data = DB::table('cv_agent_mappings as cam')
                ->join('user_proposal as up','up.user_proposal_id','=','cam.user_proposal_id')
                ->join('cv_breakin_status as bs','bs.user_proposal_id','=','cam.user_proposal_id')
                ->leftjoin('payment_request_response as prr','prr.user_proposal_id','=','bs.user_proposal_id')
                ->join('master_company as mc','mc.company_id','=','bs.ic_id')
                ->join('user_product_journey as upj','upj.user_product_journey_id','=','up.user_product_journey_id')
                ->join('master_product_sub_type as mpst','mpst.product_sub_type_id','=','upj.product_sub_type_id')
                ->join('master_product_sub_type as mpst2','mpst2.product_sub_type_id','=','mpst.parent_id')
                ->join('cv_journey_stages as stag','stag.user_product_journey_id','=','up.user_product_journey_id')
                ->whereNull('prr.status')
                ->where('cam.seller_type',$request->seller_type)                
                ->whereDate('bs.created_at', '>=', $request->from)
                ->whereDate('bs.created_at', '<=', $request->to);
                if(!empty($request->seller_id))
                {
                $breakin_data = $breakin_data->whereIn('cam.agent_id',$request->seller_id);
                }
                $breakin_data = $breakin_data->select
                ('up.user_product_journey_id','up.proposal_no','up.first_name','up.last_name','up.mobile_number','up.office_email','up.vehicale_registration_number','up.engine_number','up.chassis_number','up.ic_vehicle_details',
                'bs.breakin_number','bs.breakin_status','bs.breakin_status_final','bs.payment_end_date','bs.created_at','bs.updated_at','bs.payment_url','bs.breakin_check_url',
                'mc.company_name','mc.company_alias',
                'stag.stage as transaction_stage',
                'mpst2.product_sub_type_code as product',
                'mpst.product_sub_type_code as sub_product',
                'cam.agent_id as seller_id','cam.agent_name as seller_name','cam.agent_mobile as seller_mobile'
                )
                ->get();

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
            $breakin_data[$key]->enquiry_id = customEncrypt($value->user_product_journey_id);
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

    public static function updateBreakinStatus(Request $request) 
    {
        
        switch ($request->company_alias) {
            case 'royal_sundaram':
                return RoyalSundaramInspectionService::updateBreakinStatus($request);
                break;
            case 'future_generali':
                return FutureGeneraliInspectionService::updateBreakinStatus($request);
                break;
            case 'liberty_videocon':
                return libertyVideoconInspectionService::updateBreakinStatus($request);
                break;

            default:
                return response()->json([
                    'status' => false,
                    'msg' => 'Invalid company name'
                ]);
        }
    }
}
