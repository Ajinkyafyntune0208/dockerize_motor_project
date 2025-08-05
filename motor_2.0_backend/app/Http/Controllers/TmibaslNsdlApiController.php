<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Mail\MailController;
use App\Models\UserProposal;
use App\Models\MasterCompany;
use Illuminate\Http\Request;

//include_once app_path() . '/Helpers/CarWebServiceHelper.php';


class TmibaslNsdlApiController extends Controller
{
    public function getNsdlLink(Request $request)
    {
        if ($request) {
            $nsdlRequest = [
                'proposal_no' => $request->policy_no,
                'email' => $request->email,
                'contact' => $request->contact,
                'first_name' => $request->first_name,
                'middle_name' => '',
                'last_name' => $request->last_name,
                'section' => 'motor',
                'company' => $request->company,
            ];
        }
        $response = httpRequestNormal(config('constants.IcConstants.tmibasl.TMIBASL_NSDL_LINK_GENERATION_URL'), 'POST', $nsdlRequest, [], [
            'Content-Type' => 'application/json'
        ], [], false, false,true);

        $nsdl_response = $response['response'];
        //dd($nsdl_response);
        request()->merge($nsdl_response ? $nsdl_response : '');
        if ($nsdl_response && in_array($nsdl_response['status'], ['success', 'true'])) {
            MailController::TmibaslNsdlShareLink($nsdl_response, $request);
        }else{
            return response()->json([
                'message' => "Something went wrong,try after sometimes.",
                'status' => false,
                'nsdl_response' => $nsdl_response
            ], 200);
        }
    }
}
