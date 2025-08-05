<?php

namespace App\Http\Controllers\Payment;

use Illuminate\Http\Request;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Payment\Services\goDigitPaymentGateway as CV_GODIGIT;
use App\Http\Controllers\Payment\Services\Car\kotakPaymentGateway as CAR_KOTAK;
use App\Http\Controllers\Payment\Services\Bike\kotakPaymentGateway as BIKE_KOTAK;
use App\Http\Controllers\Payment\Services\Car\goDigitPaymentGateway as CAR_GODIGIT;
use App\Http\Controllers\Payment\Services\Bike\goDigitPaymentGateway as BIKE_GODIGIT;
use App\Http\Controllers\Payment\Services\Car\reliancePaymentGateway as CAR_RELIANCE;
use App\Http\Controllers\Payment\Services\Bike\reliancePaymentGateway as BIKE_RELIANCE;
use App\Http\Controllers\Payment\Services\Car\edelweissPaymentGateway as CAR_EDELWEISS;
use App\Http\Controllers\Payment\Services\Bike\edelweissPaymentGateway as BIKE_EDELWEISS;
use App\Http\Controllers\Payment\Services\iciciLombardPaymentGateway as PCV_ICICI_LOMBARD;
use App\Http\Controllers\Payment\Services\Car\royalSundaramPaymentGateway as CAR_ROYAL_SUNDARAM;
use App\Http\Controllers\Payment\Services\Bike\royalSundaramPaymentGateway as BIKE_ROYAL_SUNDARAM;
use App\Http\Controllers\Payment\Services\Car\chollaMandalamPaymentGateway as CAR_CHOLLA_MANDALAM;
use App\Http\Controllers\Payment\Services\Car\futureGeneraliPaymentGateway as CAR_FUTURE_GENERALI;

use App\Http\Controllers\Payment\Services\Bike\chollaMandalamPaymentGateway as BIKE_CHOLLA_MANDALAM;
use App\Http\Controllers\Payment\Services\Bike\futureGeneraliPaymentGateway as BIKE_FUTURE_GENERALI;


class ReconController extends Controller
{
    public function ReconService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enquiryId' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $user_product_journey_id = customDecrypt($request->enquiryId);
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
      
        $product_type = strtolower(get_parent_code($quote_data->product_sub_type_id));
        $ic_name = $quote_data->company_alias;
        $request['product_name'] = $quote_data->product_name;
        $request['product_type'] = $product_type;
        $request['master_policy_id'] = $quote_data->master_policy_id;
        switch($product_type)
        {
            case 'bike' :
                switch($ic_name)
                {
                    case 'godigit':
                        return BIKE_GODIGIT::ReconService($request);
                        break;//end godigit bike case

                    case 'cholla_mandalam':
                        return BIKE_CHOLLA_MANDALAM::ReconService($request);
                        break;//end  cholla bike case

                    case 'kotak':
                        return BIKE_KOTAK::ReconService($request);
                        break;//end Kotak bike case
                    case 'edelweiss':
                        return BIKE_EDELWEISS::ReconService($request);
                        break;

                    case 'future_generali':
                        return BIKE_FUTURE_GENERALI::ReconService($request);
                        break;

                    case 'reliance':
                        return BIKE_RELIANCE::ReconService($request);
                        break;

                    case 'royal_sundaram':
                        return BIKE_ROYAL_SUNDARAM::ReconService($request);
                        break;
                    default:
                        return response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case bike
                break;//end case Bike

            case 'car' :
                switch($ic_name)
                {
                    case 'godigit':
                        return CAR_GODIGIT::ReconService($request);
                        break;//end godigit car case

                    case 'cholla_mandalam':
                        return CAR_CHOLLA_MANDALAM::ReconService($request);
                        break;//end cholla car case

                    case 'kotak':
                        return CAR_KOTAK::ReconService($request);
                        break;//end Kotak car case

                    case 'edelweiss':
                        return CAR_EDELWEISS::ReconService($request);
                        break;

                    case 'future_generali':
                        return CAR_FUTURE_GENERALI::ReconService($request);
                        break;

                    case 'reliance':
                        return CAR_RELIANCE::ReconService($request);
                        break;
                    case 'royal_sundaram':
                        return CAR_ROYAL_SUNDARAM::ReconService($request);

                        break;
                    default:
                        return response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case car
                break;//end case car

            case 'pcv' :
                switch($ic_name)
                {
                    case 'icici_lombard':
                        return PCV_ICICI_LOMBARD::ReconService($request);
                        break;//end godigit pcv gcv case

                    default:
                        return response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case pcv gcv
            case 'gcv' :
                switch($ic_name)
                {
                    case 'godigit':
                        return CV_GODIGIT::ReconService($request);
                        break;//end godigit pcv gcv case

                    default:
                        return response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case pcv gcv
                break;//end case pcv gcv

            default:
                return response()->json([
                    'status' => false,
                    'msg' => 'Invalid Product Type'
                ]);
        }
    }
}
