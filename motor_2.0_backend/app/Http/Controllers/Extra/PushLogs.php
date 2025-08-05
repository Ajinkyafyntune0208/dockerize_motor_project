<?php
namespace App\Http\Controllers\Extra;

use App\Http\Controllers\Controller;
use App\Models\UserProductJourney;
use App\Models\UserTokenRequestResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class PushLogs extends Controller
{
    public function PushLogsByLeadId(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'lead_id' => 'required',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }

        $push_logs_data = UserTokenRequestResponse::whereIn('user_product_journey_id', function($query) use ($request)
        {
            $query->select('user_product_journey_id')
                ->from('user_product_journey')
                ->where('lead_id', $request->lead_id);

        })->get()->toArray();

        if(!empty($push_logs_data))
        {
            $return_data = [];
            foreach($push_logs_data as $key => $data)
            {
                $request_payload = json_decode($data['request'],true);
                $return_data[] = [
                    'trace_id'          => customEncrypt($data['user_product_journey_id']),
                    'stage'             => $request_payload['status'],
                    'vehicle_reg_no'    => $request_payload['vehicle_registration_number'] ?? NULL,
                    'url'               => $data['url'],
                    'payload'           => $request_payload,
                    'response'          => $data['response'],
                    'request_time'      => $data['created_at'],    
                ];             
            }
            return [
                'status'    => true,
                'msg'       => 'Data found',
                'lead id '  => $request->lead_id,
                'data'      => $return_data
            ];
        }
        else
        {
            return [
                'status' => false,
                'msg'    => 'Data not found for lead id '.$request->lead_id
            ];
        }
    }
}