<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Models\ServerToServerModel;
use App\Models\PaymentRequestResponse;

use Illuminate\Http\Request;


use App\Http\Controllers\Payment\Services\Car\{
    orientalPaymentGateway as CAR_OIC,
    edelweissPaymentGateway as CAR_EDELWEISS,
};

use App\Http\Controllers\Payment\Services\Bike\{
    orientalPaymentGateway as BIKE_OIC,
    edelweissPaymentGateway as BIKE_EDELWEISS,
};

class ServerToServerController extends Controller
{
    public function serverToServer(Request $request)
    {
        $request_data = $request->all();
        ServerToServerModel::create([
            'response'  => json_encode($request_data)
            ]);
        
        if($request_data!=null && isset($_REQUEST['msg'])) 
        {

        $response   = $_REQUEST['msg'];
        $response   = explode('|', $response);
        $enquiry_id = $response['18'];//oic
        $order_id = $response['1'];//edelweiss order id
        $payment_data = PaymentRequestResponse::where('order_id',$order_id)->first();
        if(empty($payment_data))
        {
            $user_product_journey_id = customDecrypt($enquiry_id);//oic
        }else
        {
            $user_product_journey_id = $payment_data->user_product_journey_id; //edeweiss
        }
        
        
        $quote_data = DB::table('quote_log as ql')
            ->join('master_company as mc', 'mc.company_id', '=', 'ql.ic_id')
            ->join('master_product as mp', 'mp.master_policy_id', '=', 'ql.master_policy_id')
            ->where('ql.user_product_journey_id', $user_product_journey_id)
            ->select(
                'ql.user_product_journey_id', 'ql.product_sub_type_id', 'ql.ic_id', 'ql.master_policy_id',
                'mc.company_name', 'mc.company_alias',
                'mp.product_name'
            )
            ->first();
        $product_type = strtolower(get_parent_code($quote_data->product_sub_type_id));
        $ic_name = $quote_data->company_alias;
        $request['product_name'] = $quote_data->product_name;
        $request['product_type'] = $product_type;
        $request['master_policy_id'] = $quote_data->master_policy_id;
       $servertoserver_data = ServerToServerModel::create([
            'enquiry_id'=> $user_product_journey_id,
            'response'  => json_encode($request_data),
            'section'   => $product_type,
            'ic_id'     => $quote_data->ic_id
            ]);
        switch ($product_type) {
            //case bike start
            case 'bike' :
                switch ($ic_name) {

                    case 'oriental':
                        $return_data = BIKE_OIC::serverToServer($request);
                    break;
                    case 'edelweiss':
                        #job should not be available for processing until 10 minutes after it has been dispatched:
                        \App\Jobs\EdelweissS2S::dispatch($request->all(), 'bike', $servertoserver_data->id)->delay(now()->addMinutes(10));
                        //$return_data = BIKE_EDELWEISS::serverToServer($request);
                    break;
                    
                    default:
                        return response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case bike
                break;//end case Bike

            case 'car' :
                switch ($ic_name) {

                    case 'oriental':
                        $return_data = CAR_OIC::serverToServer($request);
                    break;
                    case 'edelweiss':
                        \App\Jobs\EdelweissS2S::dispatch($request->all(), 'car', $servertoserver_data->id)->delay(now()->addMinutes(10));
                        //$return_data = CAR_EDELWEISS::serverToServer($request);
                    break;

                    default:
                        return response()->json([
                            'status' => false,
                            'msg' => 'Service Not Available for Requested IC'
                        ]);
                }//end switch case car
                break;//end case car

            case 'pcv' :
            case 'gcv' :
                switch ($ic_name) {
                    

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
        /* $servertoserver_data->system_response = json_encode($return_data);
        $servertoserver_data->update(); */
    }else{
        return response()->json([
            'status' => false,
            'msg' => 'Invalid Response'
        ]);
    }
}

   
}
