<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\FastlaneRequestResponse;
use App\Models\WebServiceRequestResponse;
use App\Models\QuoteServiceRequestResponse;
use App\Models\WebserviceRequestResponseDataOptionList;
use Illuminate\Support\Facades\Validator;
use \Illuminate\Http\Response;

ini_set('memory_limit', '-1');
set_time_limit(0);
class LogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('log.list')) {
            abort(403, 'Unauthorized action.');
        }
        $dropdown_values = WebserviceRequestResponseDataOptionList::select('company', 'section')->get();
        $companies = $dropdown_values->pluck('company')->toArray();
        $companies = array_unique($companies);
        $sections = $dropdown_values->pluck('section')->toArray();
        $sections = array_unique($sections);
        $logs = [];
        if (
            $request->enquiryId != null ||
            $request->company != null ||
            $request->section != null ||
            $request->from_date != null ||
            $request->to_date != null ||
            $request->transaction_type != null ||
            $request->internal_service != null ||
            $request->view_type != null
        ) {

            $request->validate([
                'enquiryId' => ['nullable'],
                'company' => ['nullable'],
                'section' => ['nullable'],
                'from_date' => ['required'],
                'to_date' => ['required'],
                'transaction_type' => ['nullable'],
                'internal_service' => ['nullable'],
            ]);
            try {

                if ($request->transaction_type == 'proposal' || $request->internal_service == "Internal Service") {
                    $logs = WebServiceRequestResponse::with('vehicle_details')->when(!empty($request->company), function ($query) {
                        $query->where('company', request()->company);
                    });
                } else if ($request->transaction_type == 'quote') {
                    $logs = QuoteServiceRequestResponse::with('vehicle_details')->when(!empty($request->company), function ($query) {
                        $query->where('company', request()->company);
                    });
                } else {
                    $logs = QuoteServiceRequestResponse::with('vehicle_details')->when(!empty($request->company), function ($query) {
                        $query->where('company', request()->company);
                    });
                }
                $enquiryId  = acceptBothEncryptDecryptTraceId(request()->enquiryId);
                $logs = $logs->when(!empty($request->section), function ($query) {
                    $query->where('section', 'like', '%' . request()->section . '%');
                })
                    ->when(!empty($request->from_date || $request->to_date), function ($query) {
                        $query->whereBetween('start_time', [
                            date('Y-m-d H:i:s', strtotime(request()->from_date)),
                            date('Y-m-d 23:59:59', strtotime(request()->to_date ?? request()->from_date))
                        ]);
                    })
                    ->when(!empty($request->enquiryId), function ($query) use ($enquiryId) {
                        $query->where('enquiry_id', ltrim($enquiryId,'0'));
                    })
                    ->when(!empty($request->internal_service), function ($query) {
                        $query->where('endpoint_url', request()->internal_service);
                    })
                    ->when(!empty($request->transaction_type), function ($query) {
                        $query->where('transaction_type', request()->transaction_type);
                    })
                    ->orderby('id', 'DESC');
                set_time_limit(0);
                if ($request->view_type == 'excel') {
                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\WebServiceExport($logs->get()), now()->format('Y-m-d h:i:s') . ' Webservice Data.xls');
                }
                $logs = $logs->paginate(2000)->withQueryString();
                // return \Illuminate\Support\Facades\DB::getQueryLog();
            } catch (\Exception $e) {
                return $e;
                return abort($e->getMessage(), 500);
            }
        }
        return view('admin_lte.logs.index', compact('logs', 'sections', 'companies'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        if (!auth()->user()->can('log.show')) {
            abort(403, 'Unauthorized action.');
        }
        if (in_array ($request->transaction_type, ['quote', 'Internal Service Error'])) {
            $log = QuoteServiceRequestResponse::find($id);
        } else {
            $log = WebServiceRequestResponse::find($id);
        }
        return view('admin_lte.logs.show', compact('log'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function ongridFastlaneLog(Request $request)
    {
        if (!auth()->user()->can('ongrid_fastlane.list')) {
            abort(403, 'Unauthorized action.');
        }
        $logs = [];
        // return \Illuminate\Support\Facades\DB::table('registration_details')->first();
        if ($request->has('from_date') && $request->has('to_date')) {
            // if (config('constants.motorConstant.REGISTRATION_DETAILS_SERVICE_TYPE') == 'ongrid') {
            // $logs = \Illuminate\Support\Facades\DB::table('registration_details')/* ->where('vehicle_reg_no', $request->registration_no)*/->orderBy('id', 'desc')->whereBetween('created_at', [
            //     \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s'),
            //     \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s'),
            // ])/* ->paginate(30)->withQueryString() */;
            // } else {
            $logs = FastlaneRequestResponse::with('agent_details')/* ->where('request', $request->registration_no) */->orderBy('id', 'desc')->whereBetween('created_at', [
                \Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s'),
                \Carbon\Carbon::parse($request->to_date)->endOfDay()->format('Y-m-d H:i:s'),
            ]);
            // }
        }
        if ($request->view_type == 'excel') {
            $records[] = [
                'Enquiry Id',
                'Source',
                'Registration No',
                'Status',
                'Message',
                'Manufacturer',
                'Model',
                'Variant',
                'Vehicle Code',
                'Created At',
                'Expiry Date',
            ];

            $logs = $logs->get();
            // $logs = json_decode(json_encode($logs), true);
            foreach ($logs as $key => $record) {

                $response = json_decode($record->response, true);

                $message = '';
                if (isset($response['data']['code']))
                    $message = $response['data']['message'] ?? null;
                else
                    $message = $record->response;

                $status = (isset($response['data']['code']) && $response['data']['code'] == '1000') ?  "Success" : "Failed";

                if ($status == 'Failed' && $request->status == 'Success')
                    continue;
                if ($status == 'Success' && $request->status == 'Failed')
                    continue;
                $source = null;
                if (!empty($record->agent_details)) {
                    # code...
                    if ($record->agent_details->agent_name == 'driver_app') {
                        $source = 'Driver App';
                    } else if ($record->agent_details->agent_name == 'embedded_admin') {
                        $source = 'Embeded Link';
                    } else {
                        if ($record->agent_details->seller_type == 'P') {
                            $source = 'POS';
                        } else if ($record->agent_details->seller_type == 'E') {
                            $source = 'Employee';
                        }
                    }
                }

                $records[] = [
                    'enquiry_id' => $record->enquiry_id,
                    'source' => $source,
                    'registration_no' => $record->request,
                    'status' => $status,
                    'message' => $message,
                    'manufacturer' => (isset($response['data']['code']) && $response['data']['code'] == '1000') ? ($response['data']['rc_data']['vehicle_data']['maker_description'] ?? '') : null,
                    'model' => (isset($response['data']['code']) && $response['data']['code'] == '1000') ? ($response['data']['rc_data']['vehicle_data']['maker_model'] ?? '') : null,
                    'variant' => (isset($response['data']['code']) && $response['data']['code'] == '1000') ? $response['results'][0]["vehicle"]["fla_variant"] ?? null : null,
                    'vehicle_code' => (string) (isset($response['data']['code']) && $response['data']['code'] == '1000') ? $response['data']['rc_data']['vehicle_data']['custom_data']['version_id'] ?? "" : null,
                    'created_at' => $record->created_at,
                    'expiry_date' => $response['data']['rc_data']['insurance_data']['expiry_date'] ?? '',
                ];
            }
            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($records), now()->format('Y-m-d h:i:s') . ' Report.xls');
        } else if ($request->view_type == 'view') {
            $logs = $logs->paginate(30)->withQueryString();
        }

        return view('admin_lte.logs.ongrid_fastlane', compact('logs'));
    }

    public function thirdPartyPaymentLog(Request $request)
    {
        if (!auth()->user()->can('third_party_payment_logs.list')) {
            abort(403, 'Unauthorized action.');
        }
            $payment_logs = [];
            if ($request->has('enquiry_id')) {
                $enquiryId  = acceptBothEncryptDecryptTraceId($request->enquiry_id);
                $payment_logs = \App\Models\ThirdPartyPaymentReqResponse::where('enquiry_id', ltrim($enquiryId,'0'))->get();
            }
            return view('admin_lte.logs.third_party_payment', compact('payment_logs'));
    }

    public function olaWhatsappLog(Request $request)
    {
        if (!auth()->user()->can('ola_whatsapp_log.list')) {
            abort(403, 'Unauthorized action.');
        }
        $logs = [];
        if (($request->from_date != null && $request->to_date != null) || $request->enquiry_id != null) {
            $logs = \App\Models\WhatsappRequestResponse::with('status_data')->whereNotNull('enquiry_id');
            if ($request->from_date != null && $request->to_date != null)
                $logs = $logs->whereBetween('created_at', [\Carbon\Carbon::parse($request->from_date)->startOfDay()->format('Y-m-d H:i:s'), \Carbon\Carbon::parse($request->to)->endOfDay()->format('Y-m-d H:i:s')]);

            if ($request->enquiry_id != null)
                $logs = $logs->where('enquiry_id', $request->enquiry_id);

            if ($request->view_type == 'excel') {
                $logs = $logs->paginate(100);

                $reports[] = [
                    'Enquiry ID',
                    'Request ID',
                    'Mobile No',
                    'Template Name',
                    'Parameters',
                    'Sent Time',
                    'Delivered Time',
                    'Seen Time',
                ];
                foreach ($logs as $key => $value) {
                    // dd($value, $value->request, $value->response, $value->status_data);

                    $send_time = $delivered_time = $read_time = null;

                    foreach ($value->status_data as $key => $status_data) {
                        if (isset($status_data->request['status']) && $status_data->request['status'] == "sent")
                            $send_time = $status_data->created_at;

                        if (isset($status_data->request['status']) && $status_data->request['status'] == "delivered")
                            $delivered_time = $status_data->created_at;

                        if (isset($status_data->request['status']) && $status_data->request['status'] == "read")
                            $read_time = $status_data->created_at;
                    }

                    $reports[] = [
                        $value->enquiry_id,
                        $value->request_id,
                        $value->mobile_no,
                        $value->request['template_name'] ?? null,
                        $value->request['params'] ?? null,
                        $send_time,
                        $delivered_time,
                        $read_time,
                    ];
                }
                return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($reports), now()->format('Y-m-d h:i:s') . ' OLA Whats App Reports .xls');
            }
            if ($request->view_type == 'view') {
                $logs = $logs->paginate(50);
            }
        }

        return view('admin_lte.logs.ola_whatsapp', compact('logs'));
    }


    public function  getLog(Request $request, $type, $id, $view = 'view')
    {
        if ($type == "quote") {
            $log = new QuoteServiceRequestResponse();
        } else if ($type == "proposal") {
            $log = new WebServiceRequestResponse();
        }
        else {
            abort(404);
        }

        try {
            if( $id != enquiryIdDecryption($request->enc)){
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid log request.'
                ]);
            }
        } catch(\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid log request.'
            ]);
        }

        $log = $log->find($id);
        if (!$log) {
            abort(404);
        }

        if ($view == 'Show-Request'){
            if($log->headers!=null)
            {
                $headers=json_decode(($log->headers),true);

                $items = '';

                $count = 0;
                foreach($headers as $key => $value) {
                $count++;
                $items .=$key.":".$value.PHP_EOL;
                }
                return view('admin_lte.logs.log_details', compact('log','items', 'count'));
            }else{
                $count = 0;
                return view('admin_lte.logs.log_details', compact('log','count'));
            }
        }else {
            abort(404);
        }
    }


    public function LogReqResponse(Request $request, $view = 'view')
    {
        $cUrl = $request->request_url;
        $method = $request->method;
        $header = $request->request_headers;
        $headers = explode(PHP_EOL, $request->request_headers);

        if (!empty($headers) && is_array($headers)) {
            $headers = array_map('trim', $headers);
        }
        $cRequest =$request->ic_request;

        $curl = curl_init();
        $curl_request= array(
            CURLOPT_URL => $cUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS =>$cRequest,
            CURLOPT_HTTPHEADER => $headers
        );

        if(!empty(config('constants.http_proxy')) && $request->is_proxy=='check'){
            $curl_request[CURLOPT_PROXY] = config('constants.http_proxy');
        }

        curl_setopt_array($curl,$curl_request);

        $ic_response = curl_exec($curl);

        $info = curl_getinfo($curl);

        $http_code=$info['http_code'];
        $response_time = $info['total_time'];
        $request_size = $info['request_size'];

        $status_message='';
        if (array_key_exists($http_code, Response::$statusTexts)) {
            $status_message =  Response::$statusTexts[$http_code];
        }

        $isDocument = in_array(($info['content_type'] ?? ''), [
            'application/octet-stream',
            'application/pdf'
        ]);


        $response_details =  "$http_code $status_message $response_time s  $request_size KB ";

        $returnResponse = [
            'ic_response' => $ic_response,
            'http_code' => $http_code,
            'response_details' => $response_details,
            'response_time' => $response_time,
            'isFile' => $isDocument,
        ];

        return response()->json($returnResponse);
    }

    public function logDownload(Request $request)
    {
        $company = $request->company;
        $url = $request->request_url;
        $headers = $request->request_headers;
        $method = $request->method;
        $ic_request = $request->ic_request;
        $ic_response = $request->ic_response;
        $response_time = $request->response_time;

        $text = "Company : " . ( $company ?? '' );
        $text .= "\n\n\nRequest URL : " . ( $url ?? '');
        $text .= "\n\n\nRequest Method : " . ( $method ?? '');
        $text .= "\n\n\nResponse Time : " . ($response_time ?? '');
        $text .= "\n\n\nHeaders: \n" . ( $headers ?? '');
        $text .= "\n\n\nRequest: \n" . ( $ic_request ?? '');
        $text .= "\n\n\nResponse: \n " . ( $ic_response ?? '');

        $file_name = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s') . '-' .$request->company . '-Request-Response' . '.txt');

        return response()->json(['text'=>$text,'filename'=>$file_name]);
    }

    public function documentDownload(Request $request) {
        $content = $request->pdfContent;

        return response()->pdfAttachment(base64_decode($content), 'application/pdf');
    }
}
