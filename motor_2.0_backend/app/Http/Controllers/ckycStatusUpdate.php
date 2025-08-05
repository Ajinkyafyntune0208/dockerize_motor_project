<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProposal;

use Illuminate\Http\Request;

class ckycStatusUpdate extends Controller
{
    public static function ckycStatusUpdate(Request $request)
    {
        $response = $request->all();
        if (empty($response)) {
            return response()->json([
                'status' => false,
                'RejectionMsg' => 'Request is null'
            ]);
        }

        if (!isset($response['verification_status'])) {
            return response()->json([
                'status' => false,
                'RejectionMsg' => ''
            ]);
        }
        if ($response['verification_status']) {
            if (!empty($response['ckyc_reference_id'])) {
                $fetch_result = UserProposal::where('ckyc_reference_id', $response['ckyc_reference_id'])
                    ->first();

                if ($fetch_result) {
                    if ($fetch_result['is_ckyc_verified'] != 'Y') {
                        #update details here..
                        UserProposal::where('user_product_journey_id', $fetch_result->user_product_journey_id)->update([
                            "is_ckyc_verified"  => 'Y'
                        ]);
                        event(new \App\Events\CKYCInitiated($fetch_result->user_product_journey_id));
                        return response()->json([
                            'status' => true,
                            'Msg' => 'CKYC details updated successfully!'
                        ]);
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'RejectionMsg' => 'No record Found!'
                    ]);
                }
            } else {
                return response()->json([
                    'status' => false,
                    'RejectionMsg' => 'ckyc_reference_id is empty'
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'RejectionMsg' => 'CKYC not verified'
            ]);
        }
    }
}
