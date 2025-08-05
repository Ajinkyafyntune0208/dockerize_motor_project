<?php

namespace App\Http\Controllers\Ckyc;

use App\Models\UserProposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Exception;

class CkycCommonController extends Controller
{
    public function resetKycData(Request $request)
    {
        // dd($request->all());
        if(config('RESET_KYC_DATA_ENABLE') == 'Y') {
            try{
                $proposal = UserProposal::where('user_product_journey_id', customDecrypt($request->enquiryId))->first();
                $ckyc_meta_data = json_decode(($proposal->ckyc_meta_data), 1) ?? [];

                $new_ckyc_meta_data = array_merge($ckyc_meta_data, [
                    'old_data_'.date('Ymdhis') => [
                        'ckyc_number' => $proposal->ckyc_number,
                        'ckyc_reference_id' => $proposal->ckyc_reference_id,
                        'is_ckyc_verified' => $proposal->is_ckyc_verified,
                        'is_ckyc_details_rejected' => $proposal->is_ckyc_details_rejected,
                    ]
                ]);

                $proposal->ckyc_number = null;
                $proposal->ckyc_reference_id = null;
                $proposal->is_ckyc_verified = 'N';
                $proposal->is_ckyc_details_rejected = 'N';
                $proposal->ckyc_meta_data = $new_ckyc_meta_data;

                $proposal->save();

                return response()->json([
                    'status' => true,
                    'msg' => 'reseting ckyc details',
                ]);
            } catch(Exception $e) {
                return response()->json([
                    'status' => true,
                    'msg' => $e->getMessage()
                ]);
            }
        } else {
            return response()->json([
                'status' => true,
                'msg' => 'Action Not Allowed'
            ]);
        }
    }
    public static function GodigitSaveCkyclog($user_product_journey_id,$KycVerfiyApi,$request,$KycVerfiyApiResponseDecoded,$reqHeaders,$end_time,$start_time)
    {
        try{
            
            $data = [
                'section' => 'motor',
                'tenant_id' => config('constants.CKYC_TENANT_ID') ?? null,
                'enquiry_id' => customEncrypt($user_product_journey_id),
                'company_alias' => 'godigit',
                'endpoint_url' => $KycVerfiyApi,
                'request' => json_encode($request, JSON_UNESCAPED_SLASHES),
                'response' => json_encode($KycVerfiyApiResponseDecoded, JSON_UNESCAPED_SLASHES),
                'headers' => json_encode($reqHeaders, JSON_UNESCAPED_SLASHES),
                'status' => 200,
                'response_time' => round(($end_time / 1000) - ($start_time / 1000), 2) , 
            ];

            $ckycUrl = config('constants.CKYC_VERIFICATIONS_URL').'/api/v1/save-ckyc-logs';

            $headers = [];
            if (config('IS_CKYC_WRAPPER_TOKEN_ENABLED') == 'Y') {
                $token = httpRequest('ckyc-wrapper-token', [
                    'api_endpoint' => $ckycUrl
                ], save:false)['response'];

                $headers['validation'] = $token['token'];
            }
    
            httpRequestNormal($ckycUrl, 'POST', $data, [], $headers, [], false);

        } catch (\Exception $e) {
            Log::error($e, ['debugTraceId' => customEncrypt($user_product_journey_id)]);
        }

    }

    public function orientalSaveCkyclog($enquiry_id, $mode_type, $log)
    {
        try {
            $ckyc_logs_array = [
                'enquiry_id' => $enquiry_id ?? null,
                'company_alias' => 'oriental',
                'mode' => $mode_type,
                'request' => ($log['request'] ?? ''),
                'response' => ($log['response'] ?? ''),
                'headers' => ($log['headers'] ?? ''),
                'endpoint_url' => ($log['endpoint_url'] ?? ''),
                'status' => (($log['status'] ?? false) ? 'Success' : 'Failure'),
                'failure_message' =>($log['messgae'] ?? ''),
                'ip_address' => $_SERVER['SERVER_ADDR'] ?? request()->ip(),
                'start_time' => date('Y-m-d H:i:s', $log['start_time'] / 1000),
                'end_time' => date('Y-m-d H:i:s', $log['end_time'] / 1000),
                'response_time' => $log['response_time']
            ];

            \App\Models\CkycLogsRequestResponse::create($ckyc_logs_array);

            unset($log['message']);
            unset($log['start_time']);
            unset($log['end_time']);
            $log['status'] = ($log['status'] ? 200 : 500);

            $this->sendCkyclogstoWrapper($enquiry_id, 'oriental', $log);
        } catch (\Exception $e) {
            Log::error($e, ['debugTraceId' => customEncrypt($enquiry_id)]);
        }
    }

    public function sendCkyclogstoWrapper($enquiry_id, $company, $log)
    {
        try {
            $data = [
                'section' => 'motor',
                'tenant_id' => config('constants.CKYC_TENANT_ID') ?? null,
                'enquiry_id' => customEncrypt($enquiry_id),
                'company_alias' => $company
            ];

            $data = array_merge($data, $log);

            $headers = [];
            $ckycUrl = config('constants.CKYC_VERIFICATIONS_URL') . '/api/v1/save-ckyc-logs';

            if (config('IS_CKYC_WRAPPER_TOKEN_ENABLED') == 'Y') {
                $token = httpRequest('ckyc-wrapper-token',[
                    'api_endpoint' => $ckycUrl
                ], save:false)['response'];
                $headers['validation'] = $token['token'];
            }

            httpRequestNormal($ckycUrl, 'POST', $data, [], $headers, [], false);
        } catch (\Exception $e) {
            Log::error($e, ['debugTraceId' => customEncrypt($enquiry_id)]);
        }
    }
}
