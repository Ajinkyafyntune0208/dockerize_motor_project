<?php

namespace App\Http\Controllers;

use App\Models\CommunicationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommunicationPreferenceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $valid = Validator::make($request->all(), [
            'mode' => 'required|in:fetch,set',
            'mobile_no' => 'nullable',
            'email' => 'nullable',
        ]);

        if ($valid->fails()) {
            
            return response()->json([
                'status' => false,
                'message' => $valid->errors()
            ]);
        }

        try {

            if ($request->mode == 'fetch'){
                
                $data = CommunicationPreference::when($request->has('mobile_no'), function ($query) use ($request){
                    $query->where( 'mobile', $request->mobile_no);
                })
                ->when($request->has('email'), function ($query) use ($request) {
                    $query->where( 'email', $request->email);
                })
                ->select('id','mobile', 'email', 'on_call', 'on_sms', 'on_email', 'on_whatsapp')
                ->get();

                if ($data) {
                    
                    return response()->json([
                        'status' => true,
                        'data' => $data
                    ]);

                } else {

                    return response()->json([
                        'status' => false,
                        'message' => 'No data found'
                    ]);
                }
                
            } else if ($request->mode == 'set'){

                if ( $request->has('mobile_no') && $request->has('email')) {

                    $records = CommunicationPreference::where([
                        'mobile' => $request->mobile_no,
                        'email' => $request->email
                    ])->get();

                    if ($records->isEmpty()) {

                        // dd("casdsa");
                        CommunicationPreference::updateOrInsert(
                            [
                                'mobile' => $request->mobile_no,
                                'email' => $request->email,
                            ],
                            [
                                'mobile' => $request->mobile_no,
                                'email' => $request->email,
                                'on_call' => $request->on_call ?? 'Y',
                                'on_sms' => $request->on_sms ?? 'Y',
                                'on_email' => $request->on_email ?? 'Y',
                                'on_whatsapp' => $request->on_whatsapp ?? 'Y',
                            ]);

                    } else {

                        CommunicationPreference::updateOrInsert(
                        [
                            'mobile' => $request->mobile_no,
                            'email' => $request->email,
                        ],
                        [
                            'on_call' => $request->on_call ?? 'Y',
                            'on_sms' => $request->on_sms ?? 'Y',
                            'on_email' => $request->on_email ?? 'Y',
                            'on_whatsapp' => $request->on_whatsapp ?? 'Y',
                        ]);
                    }


                } else {

                    $matchColumn = $request->has('mobile_no') ? 'mobile' : 'email';
                    $valueToMatch = $request->has('mobile_no') ? $request->mobile_no : $request->email;
                    $values = [
                        'on_call' => $request->on_call ?? 'Y',
                        'on_sms' => $request->on_sms ?? 'Y',
                        'on_email' => $request->on_email ?? 'Y',
                        'on_whatsapp' => $request->on_whatsapp ?? 'Y',
                    ];
    
                    $records = CommunicationPreference::where($matchColumn, $valueToMatch)->get();
    
                    if ($records->isEmpty()) {
                        CommunicationPreference::create(array_merge([$matchColumn => $valueToMatch]), $values);
                    } else {
                      
                        $records->each(function ($record) use ($values) {
                            $record->update($values);
                        });
                    }
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Changes saved successfully.',
                ]);

            }

        }  catch (\Exception $e) {

            return [
                'status' => false,
                'message' => 'Something wents wrong While saving!',
                'error-msg' => $e->getMessage()
            ];
        }
        
    }
  

 
}
