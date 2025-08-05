<?php

namespace App\Http\Controllers;
use PDF;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterCompany;
use App\Models\PolicyDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Models\MasterProductSubType;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Payment\Services\ackoPaymentGateway;
use App\Http\Controllers\Payment\Services\goDigitPaymentGateway;
use App\Http\Controllers\Payment\Services\Pcv\V2\GoDigitPaymentGateway as GoDigitOneapiPaymentGateway;
use App\Http\Controllers\Payment\Services\iciciLombardPaymentGateway;
use Illuminate\Support\Facades\Log;
use App\Models\PdfRequestResponse;

class PDFController extends Controller
{
    public function rehitPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userProductJourneyId' => 'required',
            'companyAlias' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->userProductJourneyId))->first();

        if ($proposal) {
            $policy_details = PolicyDetails::where('proposal_id', $proposal->user_proposal_id)->first();

            // if ($policy_details) {
                // return response()->json([
                //     'status' => true,
                //     'data' => [
                //         'pdfUrl' => $policy_details->pdf_url
                //     ]
                // ]);
            // } else {
                switch($request->companyAlias) {
                    case 'acko':
                        return ackoPaymentGateway::retry_pdf($proposal);
                        break;
                    case 'icici_lombard':

                        return iciciLombardPaymentGateway::retry_pdf($proposal);
                        break;
                    case 'godigit':
                    if ((config('IC.GODIGIT.V2.CV.ENABLE') == 'Y')) {
                        return GoDigitOneapiPaymentGateway::retry_pdf($proposal);
                    } else {
                        return goDigitPaymentGateway::retry_pdf($proposal);
                    }
                        break;

                    default:
                        break;
                }
            // }
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Proposal does not exists'
            ]);
        }
    }

    public function premiumBreakupPdf(Request $request)
    {
        try {
            if(config('constants.motorConstant.SMS_FOLDER') == 'abibl' || config('pdf_from_third_party_wrapper') == "Y"){
                $response = httpRequest('pdf-api', [
                    'html' => view('demo-copy',['data' => $request->data,true])->render(),
                    'name' => date('Y-m-d H:i:s').' Policy Compare.pdf',
                    'download' => $request->download ?? 'false'
                ],[], [], [], false);
                return response()->json([
                    'status' => true,
                    'data' => $response['response']['response'],
                    'name' => date('Y-m-d H:i:s').' Premium Breakup.pdf'
                ]);
                // return response($response['response'])->withHeaders($response['response_headers']);
            }
            $pdf = \PDF::loadView('demo-copy',['data' => $request->data]);
            return response()->json([
                'status' => true,
                'data' => base64_encode($pdf->output()),
                'name' => date('Y-m-d H:i:s').' Premium Breakup.pdf'
            ]);
        } catch (\Exception $e) {
            return response('Something wents wrong.. Pdf Not Generated...!', 500);
        }
    }

    public function premiumBreakupPdfemail(Request $request)
    {
        try {
            if(config('constants.motorConstant.SMS_FOLDER') == 'abibl'  || config('pdf_from_third_party_wrapper') == "Y"){
                $response = httpRequest('pdf-api', [
                    'html' => view('demo-copy',['data' => json_decode($request->data,true)])->render(),
                    'name' => date('Y-m-d H:i:s').' PremiumBreakup.pdf',
                    'download' => $request->download ?? 'false'
                ],[], [], [], false);
                return response(base64_decode($response['response']['response']))->withHeaders([
                    'Content-Type' => $response['response']['response_headers']['Content-Type'],
                    'Content-Disposition' => $response['response']['response_headers']['Content-Disposition'],
                ]);
            }
            $pdf = \PDF::loadView('demo-copy',['data' => json_decode($request->data,true)]);
            if(isset($request->download) && $request->download == 'false')
            return $pdf->stream(date('Y-m-d H:i:s').' PremiumBreakup.pdf');
            return $pdf->download(date('Y-m-d H:i:s').' PremiumBreakup.pdf');
        } catch (\Exception $e) {
            return response('Something wents wrong.. Pdf Not Generated...!', 500);
        }
    }

    public function policyComparePdf(Request $request)
    {
        try {
            $enquiry_id = NULL;
            $data =$request->data;
            $data = (urldecode($data));
            $data_save = json_decode(urldecode($request->data));
            if(isset($data_save->quote_id))
            {
                if(is_numeric($data_save->quote_id) && strlen($data_save->quote_id) == 16)
                {
                    $enquiry_id = substr($data_save->quote_id, 8);
                }
                else
                {
                    $enquiry_id = customDecrypt($data_save->quote_id,true);
                }
            }
            if($enquiry_id == NULL)
            {
                return response()->json([
                    'status'    => false,
                    'msg'       => 'Invalid Request...'
                    ]);
                die;
            }
            $pdfRequestResponse = new PdfRequestResponse();
            $pdfRequestResponse->enquiry_id = $enquiry_id ?? NULL;
            $pdfRequestResponse->type = 'compare-pdf';
            $pdfRequestResponse->payload = $request->data;
            $pdfRequestResponse->response = null;
            $pdfRequestResponse->save();

            try{
                
                if (json_decode($data, true) === null) {
                    // $trimValue = "/api/policyComparePdf?data=";
                    // $data = urldecode(ltrim(request()->getRequestUri(), $trimValue));
                    $data = base64_decode($data);
                }

            } catch (\Exception $e) {
                Log::error($e);
                return response()->json([
                   "status" => false,
                   "message" => $e->getMessage()
                ]);
            }
           

            if(config('constants.motorConstant.SMS_FOLDER') == 'abibl' || config('pdf_from_third_party_wrapper') == "Y"){
                $response = httpRequest('pdf-api', [
                    'html' => view('comparepdf',['data' => json_decode($data,true)])->render(),
                    'name' => date('Y-m-d H:i:s').' Policy Compare.pdf',
                    'download' => 'true'
                ],[], [], [], false);

                return response(base64_decode($response['response']['response']))->withHeaders([
                    'Content-Type' => $response['response']['response_headers']['Content-Type'],
                    'Content-Disposition' => $response['response']['response_headers']['Content-Disposition'],
                ]);
            }
            $pdf = \PDF::loadView('comparepdf',['data' => json_decode($data,true)]);
            if(isset($request->download) && $request->download == 'false')
            return $pdf->stream(date('Y-m-d H:i:s').' Policy Compare.pdf');
            return $pdf->download(date('Y-m-d H:i:s').' Policy Compare.pdf');
        } catch (\Exception $e) {
            return $e;
            return response('Something wents wrong.. Pdf Not Generated...!', 500);
        }
    }

    public function proposalPagePdf(Request $request) 
    {
        try {
            $broker_theme_color = $request->broker_theme_color ?? '#f27f21';
            $broker_text_color = 0;
            $broker_text_color = isset($request->broker_text_color) ?  $request->broker_text_color : "#000000";
            if (is_array($request->data)){
                $data=$request->data;
            } else {
                $data=json_decode($request->data,true);
            }
        function camelCaseToWords($data) {
            return preg_replace('/(?<!^)([A-Z])/', ' $1', $data);
        }
    
        if (isset($data['policy_details'])) {
   
            $data['policy_details'] = array_unique($data['policy_details'], SORT_REGULAR);
            $transformedPreviousPolicyDetails = [];
            foreach ($data['policy_details'] as $key => $value) {
                $newKey = camelCaseToWords($key);
                $transformedPreviousPolicyDetails[$newKey] = $value;
            }
            $data['policy_details'] = $transformedPreviousPolicyDetails;
        } 
            $subArray1 = $data["vehicle_owner_details"];
            $subArray2 = $data["vehicle_details"];
            $sort1 = [
                "full_name",
                "dob",
                "gender",
                "mobile_number",
                "email",
                "occupation_name",
                "pan_number",
                "communication_address",
            ];
            $sort2 = [
                "rto_location",
                "vehicale_registration_number" ,
                "engine_number",
                "chassis_number",
                "vehicle_color",
                "registration_date",
                "vehicle_manfacture_year" 
            ];
            if(isset($data['vehicle_owner_details']['bankDetails']) && !empty($data['vehicle_owner_details']['bankDetails'])){
                array_push($sort1, 'bankDetails');
            }
            if(isset($subArray1)){
                uksort($subArray1, function ($a, $b) use ($sort1) {
    
                    $keyA = array_search($a, $sort1);
                    $keyB = array_search($b, $sort1);
                    return $keyA - $keyB;
                });
                $data2 = array_merge($data, ["vehicle_owner_details"=>$subArray1]); 
            }
            if (isset($data2["vehicle_details"])){
                uksort($subArray2, function ($a, $b) use ($sort2) {
    
                    $keyA = array_search($a, $sort2);
                    $keyB = array_search($b, $sort2);
                    return $keyA - $keyB;
                });
                $sortedArray = array_merge($data2, ["vehicle_details"=>$subArray2]); 
            }

            $payloadenquiryid = $sortedArray['general_information']['enquiryId'];
            if(config('enquiry_id_encryption') == 'Y' && !is_numeric($payloadenquiryid)){
                $payloadenquiryid = customDecrypt($payloadenquiryid);
                $enquiryId =  cache()->remember($payloadenquiryid, config('cache.expiration_time'), function () use ($payloadenquiryid) {
                    
                    $enquiry_id = DB::table('user_product_journey')->select('created_on')->where('user_product_journey_id', $payloadenquiryid)->first();
                    return \Carbon\Carbon::parse($enquiry_id->created_on)->format('Ymd') . sprintf('%08d', $payloadenquiryid);
                });
            } else {
                $enquiryId = $payloadenquiryid;
            }

            if (config('constants.motorConstant.SMS_FOLDER') == 'tmibasl' || config('pdf_from_third_party_wrapper') == "Y"){
                
                $response = httpRequest('pdf-api', [
                    'html' => view('proposalpagepdf',['data' => $sortedArray,'broker_text_color' => $broker_text_color,'broker_theme_color' => $broker_theme_color,'enquiryId' => $enquiryId])->render(),
                    'name' => date('Y-m-d H:i:s').' PremiumBreakup.pdf',
                    'download' => $request->download ?? 'true',
                    'options' => ['isRemoteEnable' => true,'isPhpEnabled' => true],
                    'dom_options' => ['isPhpEnabled' => true]
                ],[], [], [], false);
    
                // return response(base64_decode($response['response']['response']))->withHeaders([
                //     'Content-Type' => $response['response']['response_headers']['Content-Type'],
                //     'Content-Disposition' => $response['response']['response_headers']['Content-Disposition'],
                // ]);

                return response()->json([
                    'status' => true,
                    'data' => $response['response']['response'],
                    'name' => date('Y-m-d H:i:s').' Proposal.pdf',
                    'Content-Type' => $response['response']['response_headers']['Content-Type'],
                    'Content-Disposition' => $response['response']['response_headers']['Content-Disposition'],
                ]);
            }

            \PDF::setOptions(['isRemoteEnable' => true]);
                 if (isset($sortedArray['vehicle_owner_details']['gender'])) {
                         $gender = strtoupper($sortedArray['vehicle_owner_details']['gender']);
                if ($gender === 'F') 
                {
                    $sortedArray['vehicle_owner_details']['gender'] = 'Female';
                } 
                elseif ($gender === 'M') 
                {
            $sortedArray['vehicle_owner_details']['gender'] = 'Male';
                }
                else
                {
            $gender = strtoupper($sortedArray['vehicle_owner_details']['gender']);
                }
    }
           $pdf = \PDF::loadView('proposalpagepdf',['data' => $sortedArray,'broker_text_color' => $broker_text_color,'broker_theme_color' => $broker_theme_color,'enquiryId' => $enquiryId]);

            $dom_pdf = $pdf->getDomPDF();
            $dom_pdf->set_option('isPhpEnabled', true);

            return response()->json([
                'status' => true,
                'data' => base64_encode($pdf->output()),
                'name' => date('Y-m-d H:i:s').' Proposal.pdf'
            ]);

        } catch (\Exception $e) {
            return [
                'status' => false,
                'msg' => 'Something wents wrong.. Pdf Not Generated...!',
                'error-msg' => $e->getMessage()
            ];
        }
    }
}
