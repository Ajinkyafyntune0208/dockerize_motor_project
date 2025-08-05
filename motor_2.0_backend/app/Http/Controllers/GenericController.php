<?php

namespace App\Http\Controllers;

use App\Models\CkycLogsRequestResponse;
use App\Models\CvJourneyStages;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\UserProposal;
use Illuminate\Http\Request;
use App\Models\MasterCompany;
use Illuminate\Http\Response;
use App\Models\FastlaneRequestResponse;
use App\Models\MasterProductSubType;
use Mtownsend\XmlToArray\XmlToArray;
use App\Models\WebServiceRequestResponse;
use Illuminate\Database\Eloquent\Builder;
use App\Models\QuoteServiceRequestResponse;
use App\Models\QuoteVisibilityLogs;
use Illuminate\Support\Facades\Validator;

class GenericController extends Controller
{
    public function getLogs(Request $request)
    {
        $rules=[
            "trace_ids" => ["nullable", "array"],
            "mobile_nos" => ["nullable", "array"],
            "policy_no" => ["nullable", "array"],
            "proposal_no" => ["nullable", "array"],
            "email_ids" => ["nullable", "array"],
            "registration_no" => ["nullable", "array"],
            "inspection_no" => ["nullable", "array"],
            "from" => ["nullable", "date_format:Y-m-d"],
            "to" => ["nullable", "date_format:Y-m-d"],
            "companies" => ["nullable", "array"],
            "transaction_type" => ["nullable", "array", "max:1"],
            "with_headers" => ["nullable"],
            "per_page" => ["nullable", "numeric"],
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()]);
        }
        $trace_ids = [];
        if (!empty($request->trace_ids)) {
            foreach ($request->trace_ids as $key => $trace_id) {
                $trace_ids[] = customDecrypt($trace_id);
            }
        }
        // $user_proposal = new UserProposal();
        // // $user_proposal = $user_proposal->when(!empty($request->trace_ids) && is_array($request->trace_ids), function ($query) use ($request) {
        // //   
        // //     $query->whereIn('user_product_journey_id', $trace_ids);
        // // });

        // }
        $user_proposal = new UserProposal();

        // $user_proposal = $user_proposal->when(!empty($request->trace_ids) && is_array($request->trace_ids), function ($query) use ($request) {
        //   
        //     $query->whereIn('user_product_journey_id', $trace_ids);
        // });

        $user_proposal = $user_proposal->when(!empty($request->mobile_nos) && is_array($request->mobile_nos), function ($query) use ($request) {
            $query->whereIn('mobile_number', $request->mobile_nos);
        });

        if (!empty($request->policy_no) && is_array($request->policy_no)) {
        }
        $user_proposal = $user_proposal->when(!empty($request->proposal_no) && is_array($request->proposal_no), function ($query) use ($request) {
            $query->whereIn('proposal_no', $request->proposal_no);
        });

        $user_proposal = $user_proposal->when(!empty($request->mobile_nos) && is_array($request->mobile_nos), function ($query) use ($request) {
            $query->whereIn('mobile_number', $request->mobile_nos);
        });

        if (!empty($request->policy_no) && is_array($request->policy_no)) {
        }
        $user_proposal = $user_proposal->when(!empty($request->proposal_no) && is_array($request->proposal_no), function ($query) use ($request) {
            $query->whereIn('proposal_no', $request->proposal_no);
        });

        $user_proposal = $user_proposal->when(!empty($request->email_ids) && is_array($request->email_ids), function ($query) use ($request) {
            $query->whereIn('email', $request->email_ids);
        });

        $user_proposal = $user_proposal->when(!empty($request->registration_no) && is_array($request->registration_no), function ($query) use ($request) {
            $query->whereIn('vehicale_registration_number', $request->registration_no);
        });

        $user_proposal = $user_proposal->when(!empty($request->inspection_no) && is_array($request->inspection_no), function ($query) use ($request) {
            $query->with('breakin_status')->whereHas('agent_details', function (Builder $query) use ($request) {
                $query->whereIn('breakin_number', $request->inspection_no);
            });
        });

        /* Added New Condition Due to Lots of Data from User Proposal Table */

        $user_proposal = $user_proposal->when(!empty($request->from) && !empty($request->to) && !empty($request->transaction_type) && in_array("proposal", $request->transaction_type), function ($query) use ($request) {
            $query->with(['web_service_request_response' => function ($q) {
                $q->select('id');
            }])->whereHas('web_service_request_response', function (Builder $query) use ($request) {
                $query->select('id');
                $query->whereIn('method_name', config('webservicemethods.proposal'));
                $query->whereIn('status', ['Failed', 'Success']);
                $query->whereBetween('created_at', [
                    Carbon::parse(request()->from)->startOfDay(),
                    Carbon::parse(request()->to)->endOfDay(),
                ]);
            });
        });

        $user_proposal = $user_proposal->when(!empty($request->from) && !empty($request->to) && !empty($request->transaction_type) && in_array("quote", $request->transaction_type), function ($query) use ($request) {
            $query->with(['quote_service_request_response' => function ($q) {
                $q->select('id');
            }])->whereHas('quote_service_request_response', function (Builder $query) use ($request) {
                $query->select('id');
                $query->whereIn('method_name', config('webservicemethods.quote'));
                $query->whereIn('status', ['Failed', 'Success']);
                $query->whereBetween('created_at', [
                    Carbon::parse(request()->from)->startOfDay(),
                    Carbon::parse(request()->to)->endOfDay(),
                ]);
            });
        });

        $user_proposal = $user_proposal->when(!empty($request->from) && !empty($request->to) && empty($request->transaction_type), function ($query) use ($request) {
            $query->with(['web_service_request_response' => function ($q) {
                $q->select('id');
            }])->whereHas('web_service_request_response', function (Builder $query) use ($request) {
                $query->select('id');
                $query->whereIn('method_name', config('webservicemethods.proposal'));
                $query->whereIn('status', ['Failed', 'Success']);
                $query->whereBetween('created_at', [
                    Carbon::parse(request()->from)->startOfDay(),
                    Carbon::parse(request()->to)->endOfDay(),
                ]);
            });
        });
    
        $user_proposal = $user_proposal->get('user_product_journey_id')->pluck('user_product_journey_id')->toArray();

        if (!empty($user_proposal))
            $trace_ids = array_merge($trace_ids, $user_proposal);


        // return $user_proposal = $user_proposal->get('user_product_journey_id');
        $transaction_type = $request->transaction_type ?? [];

        if (in_array("quote", $transaction_type)) {
            $logs = new QuoteServiceRequestResponse();
        } else if (in_array("proposal", $transaction_type)) {
            $logs = new WebServiceRequestResponse();
        } else {
            $logs = new WebServiceRequestResponse();
            $transaction_type = ['proposal'];
        }

        $logs = $logs->when(!empty($trace_ids), function ($query) use ($trace_ids) {
            $query->whereIn('enquiry_id', $trace_ids);
        });

        $logs = $logs->when(!empty($request->from) && !empty($request->to), function ($query) {
            $query->whereBetween('created_at', [
                Carbon::parse(request()->from)->startOfDay(),
                Carbon::parse(request()->to)->endOfDay(),
            ]);
        });
        $logs = $logs->when(!empty($request->companies), function ($query) {
            $query->whereIn('company', request()->companies);
        });
        $logs = $logs->select('enquiry_id', 'section', 'product', 'company', 'method_name', 'id', 'transaction_type', 'created_at', 'response_time')->paginate($request->per_page ?? 100);

        $log_response_data = [];
        foreach ($logs as $key => $log) {

            $log_response_data[] = [
                "trace_id" => customEncrypt($log->enquiry_id),
                "data" => [
                    "section" => $log->section,
                    "product" => $log->product
                ],
                "links" => [],
                "company_alias" => $log->company,
                "transaction_type" => $log->method_name,
                "view_link" => route('api.logs.view-download', [$transaction_type[0], $log->id, 'with_headers' => $request->with_headers ?? false]),
                "download_link" => route('api.logs.view-download', [$transaction_type[0], $log->id, 'download', 'with_headers' => $request->with_headers ?? false]),
                "type" => $log->transaction_type,
                "status" => "",
                "timing" => $log->created_at,
                // "response_time" => Carbon::parse($log->response_time)->format('s')
                "response_time" => $log->response_time
            ];
        }

        return response()->json([
            'status' => true,
            'msg' => 'Log Data Fetch Successfully....!',
            'data' => $log_response_data,
            "pagination" =>  [
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                // 'prev_page_page' => $logs->previousPageUrl(),
                // 'next_page_page' => $logs->nextPageUrl(),
                'total_records' => $logs->total(),
                'page' => $logs->lastPage(),
                // 'links' => $logs->getUrlRange(1, $logs->lastPage())
            ]
        ]);
    }

    public function getLog(Request $request, $type, $id, $view = 'view')
    {
        if (in_array ($type, ['quote', 'Internal Service Error'])) {
            $log = new QuoteServiceRequestResponse();
        } else if ($type == "proposal") {
            $log = new WebServiceRequestResponse();
        } else {
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
        if ($view == 'view') {
            return view('logs.show', compact('log'));
        } else if ($view == 'download') {
            $quote_details = json_decode($log->vehicle_details, true)['quote_details'] ?? '';
            $text = "Trace ID : " . (isset($log->enquiry_id) ? customEncrypt($log->enquiry_id) : '');
            $text .= "\n\n\nSection : " . $log->section ?? '';
            $text .= "\n\n\nPolicy Id : " . ($log->policy_id ?? '');
            $text .= "\n\n\nMethod Name : " . ($log->method_name ?? '');
            $text .= "\n\n\nCompany : " . ($log->company ?? '');
            $text .= "\n\n\nVehicle Details : "  /* . $log->vehicle_details */;
            $text .= "\n\n\n\tVersion ID. : " . ($quote_details['version_id'] ?? '');
            $text .= "\n\n\n\tRegistration No. : " . ($log->user_proposal_details?->vehicale_registration_number ?? $quote_details['vehicle_registration_no'] ?? '');
            $text .= "\n\n\n\tRegistration Date. : " . ($quote_details['vehicle_register_date'] ?? '');
            $text .= "\n\n\n\tFuel Type. : " . ($quote_details['fuel_type'] ?? '');
            $text .= "\n\n\n\tMake And Model. : " . ($quote_details['manfacture_name']  ?? '') . '  ' . ($quote_details['model_name'] ?? '') . '  ' . ($quote_details['version_name'] ?? '');
            $text .= "\n\n\nResponse Time : " . ($log->response_time ?? '');
            $text .= "\n\n\nCreated At : " . (isset($log->created_at) && !empty($log->created_at) ? date('d-M-Y h:i:s A', strtotime($log->created_at)) : '');
            $text .= "\n\n\nRequest URL : \t"  . ($log->endpoint_url ?? '');
            $text .= "\n\n\nRequest Method : \t" . ($log->method ?? '');
            if ($request->with_headers) {
                $text .= "\n\n\nHeaders : \n\t" . ($log->headers ?? '');
            }
            $text .= "\n\n\nRequest : \n\n" . ($log->request ?? '');
            $text .= "\n\n\nResponse : \n\n" . ($log->response ?? '');
            $file_name = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s') . '-' . $log->section . '-' . $log->company . '-' . $type . '.txt');
            return response($text, 200, [
                "Content-Type" => "text/plain",
                'Content-Disposition' => sprintf('attachment; filename="' . $file_name .  '"')
            ]);
        } else {
            abort(404);
        }
    }

    public function getVahanLog(Request $request, $id, $view = 'view')
    {
        try {
            if( $id != enquiryIdDecryption($request->enc)){
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid vahan log request.'
                ]);
            }
        } catch(\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid vahan log request.'
            ]);
        }

        $rc_report = FastlaneRequestResponse::findOrFail($id);
        
        if ($view == 'view') {
            return view('rc_report.show', compact('rc_report'));
        } else if ($view == 'download') {
            $text = "\n\n\nRC Number : " . $rc_report->request ?? '';
            $text .= "\n\n\nTransaction Type : " . $rc_report->transaction_type ?? '';
            $text .= "\n\n\nCreated At : " . (isset($rc_report->created_at) && !empty($rc_report->created_at) ? date('d-M-Y h:i:s A', strtotime($rc_report->created_at)) : '');
            $text .= "\n\n\nRequest URL : \t"  . ($rc_report->endpoint_url ?? '');
            $text .= "\n\n\nRequest : \n\n" . ($rc_report->request ?? '');
            $text .= "\n\n\nResponse : \n\n" . ($rc_report->response ?? '');
            $fileName = Str::lower(now()->format('Y-m-d H:i:s').'-'. Str::replace(' ', '-', $rc_report->transaction_type) .' ' . $rc_report->request .'.txt');
            return response($text, 200, [
                "Content-Type" => "text/plain",
                'Content-Disposition' => sprintf('attachment; filename="' . $fileName .  '"')
            ]);
        } else {
            abort(404);
        }
    }

    public function quoteVisibilityCount(Request $request, $type)
    {
        $validation = \Illuminate\Support\Facades\Validator::make(array_merge($request->all(), ['status_type' => $type]), [
            'from' => 'required|numeric',
            'to' => 'required|numeric|gte:from',
            'status_type' => 'required|in:Success,Failed'
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validation->errors(),
            ]);
        }
        $from = Carbon::parse(date('Y-m-d H:i:s', $request->from));
        $to = Carbon::parse(date('Y-m-d H:i:s', $request->to));
        $data = [];
        // $method_names = QuoteServiceRequestResponse::where('status', $type)->groupBy('method_name')->distinct()->get('method_name');
        $method_names = config('webservicemethods.quote');
        
        $counts = QuoteServiceRequestResponse::whereBetween('created_at', [$from, $to])->where('status', $type)
        ->whereIn('method_name', $method_names)
        ->selectRaw('company, count(*) as count')->groupBy('company')->get();

        foreach($counts as $key => $item){
            // $data['Quote ' . $item->method_name] = array_merge(($data['Quote ' . $item->method_name] ?? []), [$item->company => $item->count]); 
            $data['quote_' . strtolower($type)] = array_merge(($data['quote_' . strtolower($type)] ?? []), [$item->company => $item->count]); 
        }
        
        /*
        foreach ($method_names as $key => $value) {
            $counts = QuoteServiceRequestResponse::whereBetween('created_at', [$from, $to])->where('status', $type)->where('method_name', $value->method_name)->selectRaw('company, count(id) as count')->groupBy('company')->distinct()->get();
            foreach ($counts as $key => $count) {
                $data['Quote ' . $value->method_name] = array_merge(($data['Quote ' . $value->method_name] ?? []), [$count->company => $count->count]);
            }
        }
        */
        // $method_names = WebServiceRequestResponse::where('status', $type)->groupBy('method_name')->distinct()->get('method_name');
        $method_names = config('webservicemethods.proposal');
        $counts = WebServiceRequestResponse::whereBetween('created_at', [$from, $to])->where('status', $type)
        ->whereIn('method_name', $method_names)->selectRaw('company, count(*) as count')->groupBy('company')->get();

        foreach($counts as $key => $item){
            // $data['Proposal ' . $item->method_name] = array_merge(($data['Proposal ' . $item->method_name] ?? []), [$item->company => $item->count]);
            $data['proposal_' . strtolower($type)] = array_merge(($data['proposal_' . strtolower($type)] ?? []), [$item->company => $item->count]);
        }
        /*    
        foreach ($method_names as $key => $value) {
            $counts = WebServiceRequestResponse::whereBetween('created_at', [$from, $to])->where('status', $type)->where('method_name', $value->method_name)->selectRaw('company, count(id) as count')->groupBy('company')->distinct()->get();
            foreach ($counts as $key => $count) {
                $data['Proposal ' . $value->method_name] = array_merge(($data['Proposal ' . $value->method_name] ?? []), [$count->company => $count->count]);
            }
        }
        */
        return response()->json($data);
    }

    public function getLogsQuoteDetails(Request $request)
    {
        $rules=[
            "from" => "required",
            "to" => "required",
            "type" => "required",
            "error_types" => "nullable|array",
            "company_aliases" => "nullable|array",
        ];
        $validator = Validator::make($request->all(), $rules);
        if($validator->fails()){
            return response()->json(['status'=>false,'msg' => $validator->errors()]);
        }
        try {
            $from = Carbon::parse(date('Y-m-d H:i:s', $request->from));
            $to = Carbon::parse(date('Y-m-d H:i:s', $request->to));

            $model_name = $error_type = '';
            if (!empty($request->error_types)) {
                // As per cofirmation by Mohsin we'll receive only single value in this variable array
                if (!empty($request->error_types)) {
                    list($model_name, $error_type) = explode('_', $request->error_types[0], 2);
                }
                // $model_name will be either one of these (quote, proposal, ckyc) only.
                if (!in_array($model_name, ['quote', 'proposal', 'ckyc', 'revisedquote', 'vahan'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid Error type',
                    ]);
                }
                $method_names = [
                    ucwords($model_name) => []
                ];
                // foreach ($request->error_types as $key => $value) {
                //     $key = explode('_', $value)[0];
                //     $method_names[$key][] = substr($value, (strlen($key) + 1));
                // }
            } else {
                $method_names = [
                    "Quote" => [],
                    "Proposal" => [],
                ];
            }

            $response_data = [];
            $log_response_data = [];

            $master_product_sub_types = MasterProductSubType::all(['product_sub_type_id', 'product_sub_type_name']);

            $methods = [];
            $quote_methods = config('webservicemethods.quote');
            $proposal_methods = config('webservicemethods.proposal');
            foreach ($method_names as $key => $value) {
                $error_type = empty($error_type) ? $request->type : $error_type;
                if (isset($key) && $key == "Quote") {
                    $logs = new QuoteServiceRequestResponse();
                    $log_type ="quote";
                    $methods = $quote_methods;
                } else if (isset($key) && $key == "Proposal") {
                    $logs = new WebServiceRequestResponse();
                    $log_type ="proposal";
                    $methods = $proposal_methods;
                } else if (isset($key) && $key == "Ckyc") {
                    $logs = new CkycLogsRequestResponse();
                    $log_type ="ckyc";
                } else if (isset($key) && $key == "Revisedquote") {
                    $logs = new QuoteVisibilityLogs();
                    $log_type ="quote";
                    $methods = $quote_methods;
                } else if (isset($key) && $key == "Vahan") {
                    // Dashboard will call the api for single method only, hence returning the data in case of Vahan
                    return $this->getVahanLogsDetails($request, $from, $to, $error_type);
                } else {
                    $logs = new QuoteServiceRequestResponse();
                    $log_type ="quote";
                    $methods = $quote_methods;
                }

                $logs = $logs->with( ['vehicle_details' => function ($query) {
                    $query->select('quote_id', 'user_product_journey_id', 'quote_data', 'idv', 'final_premium_amount');
                }])->with( ['user_proposal_details' => function ($query) {
                    $query->select('user_product_journey_id', 'vehicale_registration_number');
                }])->with( ['corporate_details' => function ($query) {
                    $query->select('user_product_journey_id', 'version_id','previous_policy_type','previous_policy_expiry_date');
                }]);

                $logs = $logs->whereBetween('created_at', [$from, $to])->where('status', $error_type);

                if(!empty($methods)) {
                    $logs->whereIn('method_name', $methods);
                } else if ($key != "Ckyc"){
                    $logs->whereNotNull('method_name');
                }

                // if (!empty($value))
                //    $logs = $logs->whereIn('method_name', $value);

                if (!empty($request->company_aliases)) {
                    if ($key == "Ckyc") {
                        $logs = $logs->whereIn('company_alias', $request->company_aliases);
                    } else {
                        $logs = $logs->whereIn('company', $request->company_aliases);
                    }
                }

                if ($key == "Ckyc") {
                    $logs = $logs->select(["enquiry_id", "id", "company_alias as company", "response_time", "failure_message as message", "mode as method_name", "created_at"])->paginate($request->per_page?? 100);
                } else {
                    $select = [
                        "enquiry_id",
                        "company",
                        "response_time",
                        "product", "message",
                        "method_name",
                        "created_at",
                        "transaction_type",
                        $key == 'Revisedquote' ? "quote_webservice_id as id" : "id"
                    ];
                    $logs = $logs->select($select)->paginate($request->per_page?? 100);
                }
                
                $data = [];
                foreach ($logs as $k => $log) {
                    $enq_id = enquiryIdEncryption($log->id);
                    $log_data = [
                        "Logs link" => route('api.logs.view-download', [$log_type, $log->id, 'enc' => $enq_id]),
                        "enquiry_id" => customEncrypt($log->enquiry_id),
                        "ic_name" => $log->company,
                        "vehicle_type" => isset($log->vehicle_details->quote_details['product_sub_type_id']) ? $master_product_sub_types->where('product_sub_type_id', $log->vehicle_details->quote_details['product_sub_type_id'])->first()->product_sub_type_name : NULL,
                        "make" => $log->vehicle_details->quote_details['manfacture_name'] ?? null,
                        "model" => $log->vehicle_details->quote_details['model_name'] ?? null,
                        "variant" => $log->vehicle_details->quote_details['version_name'] ?? null,
                        "version_id" => $log->corporate_details->version_id ?? null,
                        "fuel_type" => $log->vehicle_details->quote_details['fuel_type'] ?? null,
                        "vehicle_register_date" =>  $log->vehicle_details->quote_details['vehicle_register_date'] ?? null,
                        "manufacture_year" =>  $log->vehicle_details->quote_details['manufacture_year'] ?? null,
                        "insurer_modelid" => "",
                        "lead_id" => "",
                        "quote_reference_no" => "",
                        "insurer_quote_id" => "",
                        "response_time" => $log->response_time,
                        "previous_policy_type" => $log->corporate_details?->previous_policy_type,
                        "previous_policy_expiry_date" => $log->corporate_details?->previous_policy_expiry_date,
                        "case_type" => $log->vehicle_details->quote_details['business_type'] ?? null,
                        "plan_name" => $log->product ?? null,
                        "policy_type" => $log->vehicle_details->quote_details['policy_type'] ?? null,
                        "rto" => $log->vehicle_details->quote_details['rto_code'] ?? null,
                        "idv" => $log->vehicle_details["idv"] ?? null,
                        "quote_response" => $log->message,
                        "actionable_at" => $log->transaction_type == 'Internal Service Error' ? "FT" : "IC",
                        "error_type" => "",
                        "error_category" => $log->method_name,
                        "premium" => $log->vehicle_details["final_premium_amount"] ?? null,
                        "Date" => carbon::parse($log->created_at)->format("d-m-Y"),
                        "Time" => carbon::parse($log->created_at)->format("h:i:s A"),
                        "registration_number" => $log->user_proposal_details?->vehicale_registration_number,
                    ];
                    if (!in_array($log_type, ['quote', 'proposal'])) {
                        unset($log_data['Logs link']);
                    }
                    $data[] = $log_data;
                }
                $log_response_data['data'] = $data;
                $log_response_data["pagination"]  =  [
                    'per_page' => $logs->perPage(),
                    'current_page' => $logs->currentPage(),
                    'total_records' => $logs->total(),
                    'page' => $logs->lastPage(),
                ];
            }

            return response()->json(
                $log_response_data
            );
        } catch (\Exception $e) {
            return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getVahanLogsDetails($request, $from, $to, $error_type) {
        $logs = new FastlaneRequestResponse();

        $logs = $logs->whereBetween('created_at', [$from, $to])->where('status', $error_type);

        if (!empty($request->company_aliases)) {
            $logs = $logs->whereIn('transaction_type', $request->company_aliases);
        }
        
        $logs = $logs->selectRaw("`id`, `transaction_type` as `company`, `request` as `vehicale_registration_number`, TIME_TO_SEC(`response_time`) as `response_time`, `message`, `type`, `created_at`")->paginate($request->per_page?? 100);

        $data = [];
        foreach ($logs as $k => $log) {
            $enq_id = enquiryIdEncryption($log->id);
            $log_data = [
                "Logs link" => route('api.vahan.view-download', [$log->id, 'enc' => $enq_id]),
                "registration_number" => $log->vehicale_registration_number,
                "type" => $log->type,
                "ic_name" => $log->company,
                "quote_response" => $log->message,
                "response_time" => $log->response_time,
                "actionable_at" => "Vahan",
                "Date" => carbon::parse($log->created_at)->format("d-m-Y"),
                "Time" => carbon::parse($log->created_at)->format("h:i:s A"),
            ];
            
            $data[] = $log_data;
        }
        $log_response_data['data'] = $data;
        $log_response_data["pagination"]  =  [
            'per_page' => $logs->perPage(),
            'current_page' => $logs->currentPage(),
            'total_records' => $logs->total(),
            'page' => $logs->lastPage(),
        ];

        return response()->json(
            $log_response_data
        );
    }

    public function getLogsQuote(Request $request)
    {
        $data=[
            "trace_ids" => ["nullable", "array"],
            "mobile_nos" => ["nullable", "array"],
            "policy_no" => ["nullable", "array"],
            "proposal_no" => ["nullable", "array"],
            "email_ids" => ["nullable", "array"],
            "registration_no" => ["nullable", "array"],
            "inspection_no" => ["nullable", "array"],
            "from" => ["nullable", "date_format:Y-m-d"],
            "to" => ["nullable", "date_format:Y-m-d"],
            "companies" => ["nullable", "array"],
            "transaction_type" => ["nullable", "array", "max:1"],
            "with_headers" => ["nullable"],
            "per_page" => ["nullable", "numeric"],
        ];
        $validator = Validator::make($request->all(), $data);
        if ($validator->fails()) {
            return response()->json(['status'=>false,'msg' => $validator->errors()]);
        }
        $trace_ids = [];
        if (!empty($request->trace_ids)) {
            foreach ($request->trace_ids as $key => $trace_id) {
                $trace_ids[] = customDecrypt($trace_id);
            }
        }
        $user_proposal = new UserProposal();

        $user_proposal = $user_proposal->when(!empty($request->mobile_nos) && is_array($request->mobile_nos), function ($query) use ($request) {
            $query->whereIn('mobile_number', $request->mobile_nos);
        });

        if (!empty($request->policy_no) && is_array($request->policy_no)) {
        }
        $user_proposal = $user_proposal->when(!empty($request->proposal_no) && is_array($request->proposal_no), function ($query) use ($request) {
            $query->whereIn('proposal_no', $request->proposal_no);
        });

        $user_proposal = $user_proposal->when(!empty($request->mobile_nos) && is_array($request->mobile_nos), function ($query) use ($request) {
            $query->whereIn('mobile_number', $request->mobile_nos);
        });

        if (!empty($request->policy_no) && is_array($request->policy_no)) {
        }
        $user_proposal = $user_proposal->when(!empty($request->proposal_no) && is_array($request->proposal_no), function ($query) use ($request) {
            $query->whereIn('proposal_no', $request->proposal_no);
        });

        $user_proposal = $user_proposal->when(!empty($request->email_ids) && is_array($request->email_ids), function ($query) use ($request) {
            $query->whereIn('email', $request->email_ids);
        });

        $user_proposal = $user_proposal->when(!empty($request->registration_no) && is_array($request->registration_no), function ($query) use ($request) {
            $query->whereIn('vehicale_registration_number', $request->registration_no);
        });

        $user_proposal = $user_proposal->when(!empty($request->inspection_no) && is_array($request->inspection_no), function ($query) use ($request) {
            $query->with('breakin_status')->whereHas('agent_details', function (Builder $query) use ($request) {
                $query->whereIn('breakin_number', $request->inspection_no);
            });
        });
        $user_proposal = $user_proposal->get('user_product_journey_id')->pluck('user_product_journey_id')->toArray();

        if (!empty($user_proposal))
            $trace_ids = array_merge($trace_ids, $user_proposal);


        // return $user_proposal = $user_proposal->get('user_product_journey_id');
        $transaction_type = $request->transaction_type ?? [];

        if (in_array("quote", $transaction_type)) {
            $logs = new QuoteServiceRequestResponse();
        } else if (in_array("proposal", $transaction_type)) {
            $logs = new WebServiceRequestResponse();
        } else {
            $logs = new WebServiceRequestResponse();
            $transaction_type = ['proposal'];
        }

        $logs = $logs->when(!empty($trace_ids), function ($query) use ($trace_ids) {
            $query->whereIn('enquiry_id', $trace_ids);
        });

        $logs = $logs->when(!empty($request->from) && !empty($request->to), function ($query) {
            $query->whereBetween('created_at', [
                Carbon::parse(request()->from)->startOfDay(),
                Carbon::parse(request()->to)->endOfDay(),
            ]);
        });
        $logs = $logs->when(!empty($request->companies), function ($query) {
            $query->whereIn('company', request()->companies);
        });
        $logs = $logs->with(['vehicle_details' => function ($query) {
            $query->select('quote_details', 'idv', 'final_premium_amount', 'premium_json');
        }])->get(['enquiry_id', 'company', 'response_time', 'product', 'created_at']); //->paginate($request->per_page ?? 100);

        $log_response_data = [];
        foreach ($logs as $key => $log) {

            // $response = $this->getlogMessage($log);
            $log_response_data[] = [
                "enquiry_id" => customEncrypt($log->enquiry_id),
                "ic_name" => $log->company,
                "vehicle_type" => $log->vehicle_details['premium_json']['mmvDetail']["vehicleType"] ?? null,
                "make" => $log->vehicle_details['premium_json']['mmvDetail']['manfName'] ?? null,
                "model" => $log->vehicle_details['premium_json']['mmvDetail']['modelName'] ?? null,
                "variant" => $log->vehicle_details['premium_json']['mmvDetail']['versionName'] ?? null,
                "fuel_type" => $log->vehicle_details['premium_json']['mmvDetail']['fuelType'] ?? null,
                "insurer_modelid" => "",
                "lead_id" => "",
                "quote_reference_no" => "",
                "insurer_quote_id" => "",
                "request_time" => $log->response_time,
                "case_type" => $log->vehicle_details['premium_json']['businessType'],
                "plan_name" => $log->product,
                "policy_type" => $log->vehicle_details['quote_details']['policy_type'],
                "rto" => $log->vehicle_details['quote_details']['rto_code'],
                "idv" => $log->vehicle_details["idv"],
                // "quote_response" => $response["response"],
                "quote_response" => $log->message,
                "error_type" => "",
                "error_category" => "",
                "premium" => $log->vehicle_details["final_premium_amount"],
                "timing" => $log->created_at,
            ];
        }

        return response()->json([
            'status' => true,
            'msg' => 'Log Data Fetch Successfully....!',
            'data' => $log_response_data,
            // "pagination" =>  [
            //     'per_page' => $logs->perPage(),
            //     'current_page' => $logs->currentPage(),
            //     // 'prev_page_page' => $logs->previousPageUrl(),
            //     // 'next_page_page' => $logs->nextPageUrl(),
            //     'total_records' => $logs->total(),
            //     'page' => $logs->lastPage(),
            //     // 'links' => $logs->getUrlRange(1, $logs->lastPage())
            // ]
        ]);
    }

    private function getlogMessage($log)
    {
        $data = [];
        // info('log - ' . $log->response);
        switch ($log->company) {
            case 'godigit':
                if ($log->response == 0) {
                    $data['response'] = $log->response;
                }
                switch ($log->method_name) {
                    case "Premium Calculation":
                        $response = json_decode($log->response, true);
                        if (isset($response['error']['errorCode']) && $response['error']['errorCode'] == 0) {
                            $data['response'] = $response;
                        } else if (isset($response['error']['errorCode']) && $response['error']['errorCode'] != 0) {
                            $data['response'] = $response['error']['validationMessages'];
                        } else {
                            $data['response'] = $log->response;
                        }
                        break;
                    case "Proposal Submit":
                        $response = json_decode($log->response, true);
                        if (isset($response['error']['errorCode']) && $response['error']['errorCode'] == 0) {
                            $data['response'] = $response;
                        } else if (isset($response['error']['errorCode']) && $response['error']['errorCode'] != 0) {
                            $data['response'] = json_encode($response['error']['validationMessages']);
                        } else {
                            $data['response'] = $log->response;
                        }
                        break;
                    case "PG Redirectional":
                        $data['response'] = $log->response;
                        break;
                    case "Policy PDF":
                        $response = json_decode($log->response, true);
                        if (isset($response['errorMessage'])) {
                            $error = json_decode($response['errorMessage'], true);
                            if (isset($error['errorCode']) && $error['errorCode'] == 500) {
                                $data['response'] = $error['errorMessage'];
                            }
                        } else if (isset($response['schedulePath'])) {
                            $data['response'] = $response['schedulePath'];
                        } else {
                            $data['response'] = $response;
                        }
                        break;
                    case "PG Redirection":
                        $data['response'] = $log->response;
                        break;
                    case "Check Policy Status":
                        if ($log->response == 0) {
                            $data['response'] = $log->response;
                        } else {
                            dd($log);
                        }
                        break;
                    case "Quote":
                        $data['response'] = $log->response;
                        break;
                    case "Premium recalculation":
                        $response = json_decode($log->response, true);
                        if (isset($response['error']['errorCode']) && $response['error']['errorCode'] == 0) {
                            $data['response'] = $response;
                        } else if (isset($response['error']['errorCode']) && $response['error']['errorCode'] != 0) {
                            dd($log);
                        } else {
                            $data['response'] = $log->response;
                        }
                        break;
                    default:
                        $data['response'] = $log->response;
                        break;
                }
                break;
            case 'future_generali':
                switch ($log->method_name) {
                    case "Premium Calculation":
                        $log->response = XmlToArray::convert(html_entity_decode($log->response));
                        $data["response"] = $log->response["s:Body"]["CreatePolicyResponse"]["CreatePolicyResult"]['Root']["Policy"]['Status'];
                        break;
                    case "Quote Generation":
                        $log->response = XmlToArray::convert(html_entity_decode($log->response));
                        $data["response"] = $log->response["s:Body"]["CreatePolicyResponse"]["CreatePolicyResult"]['Root']["Policy"]['Status'];
                        break;
                    case "Quote Generation - IDV changed":
                        $log->response = XmlToArray::convert(html_entity_decode($log->response));
                        $data["response"] = $log->response["s:Body"]["CreatePolicyResponse"]["CreatePolicyResult"]['Root']["Policy"]['Status'];
                        break;
                    case "Quote":
                        $data["response"] = $log->response;
                        break;
                    default:
                        $data['response'] = $log->response;
                        break;
                }
            case 'cholla_mandalam':
                switch ($log->method_name) {
                    case "Quote":
                        $data["response"] = $log->response;
                        break;
                    case "Proposal Submition - Proposal":
                        $log->response = json_decode($log->response, true);
                        if (isset($log->response['Code']) && $log->response['Code'] == "200") {
                            $data["response"] = $log->response['Message'];
                        } else if (isset($log->response['Code']) && $log->response['Code'] == "417") {
                            $data["response"] = $log->response["Status"];
                        } else {
                            $data["response"] = $log->response;
                        }
                        break;
                    case "Token Generation":
                        $log->response = json_decode($log->response, TRUE);
                        if (isset($log->response['access_token'])) {
                            $data['response'] = $log->response['access_token'];
                        } else {
                            $data["response"] = $log->response;
                        }
                        break;
                    case "Quote Calculation - Quote":
                        $log->response = json_decode($log->response, true);
                        if (isset($log->response['Code']) && $log->response['Code'] == "200") {
                            $data["response"] = $log->response['Message'];
                        } else if (isset($log->response['Code']) && $log->response['Code'] == "417") {
                            $data["response"] = $log->response["Status"];
                        } else {
                            $data["response"] = $log->response;
                        }
                        break;
                    case "IDV Calculation":
                        $response = json_decode($log->response, true);
                        if (isset($response['Code']) && $response['Code'] == "200") {
                            $data["response"] = $response['Message'];
                        } else if (isset($response['Code']) && $response['Code'] == "417") {
                            $data["response"] = $response["Status"];
                        } else {
                            $data["response"] = $response;
                        }
                        break;
                    default:
                        $data["response"] = $log->response;
                        break;
                }
                break;
            case 'liberty_videocon':
                switch ($log->method_name) {
                    case 'Premium Calculation':
                        if ($log->response == "0") {
                            $data["response"] = $log->response;
                        } else {
                            dd($log->response);
                        }
                        break;
                    case 'Quote':
                        $data["response"] = $log->response;
                        break;
                    default:
                        # code...
                        break;
                }
                break;
            case 'bajaj_allianz':
                switch ($log->method_name) {
                    case "Quote":
                        $data['response'] = $log->response;
                        break;
                    case "Submit Proposal - Basic":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response;
                            // dd("Submit Proposal - Basic", $response);
                        }
                        break;
                    case "Pin Generation":
                        if ($log->response == "0") {
                            $data['response'] = $log->response;
                        } else {
                            $response = XmlToArray::convert($log->response);
                            if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                                $data['response'] = "Success";
                            } else {
                                $data['response'] = $response;
                            }
                        }
                        break;
                    case "Submit Proposal - TELEMATICS_CLASSIC":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            // dd("Submit Proposal - TELEMATICS_CLASSIC", $log);
                            $data["response"] = $log->response;
                        }
                        break;
                    case "Policy Issuing - TELEMATICS_CLASSIC":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:issuePolicyResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            // dd("Policy Issuing - TELEMATICS_CLASSIC", $log);
                            $data["response"] = $log->response;
                        }
                        break;
                    case "Premium Calculation - Prime":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                        }
                        break;
                    case "Premium Calculation - TELEMATICS_CLASSIC":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                        }
                        break;
                    case "Premium Calculation - DRIVE_ASSURE_PACK_PLUS":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                        }
                        break;
                    case "Premium Calculation - DRIVE_ASSURE_PACK":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                        }
                        break;
                    case "Premium Calculation - ":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                        }
                        break;
                    case "Premium Calculation - TELEMATICS_PRESTIGE":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                        }
                        break;
                    case "Premium Calculation - TELEMATICS_PREMIUM":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                        }
                        break;
                    case "Premium Calculation - od only":
                        if ($log->response == "0") {
                            $data['response'] = $log->response;
                        } else {
                            // $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                            // dd("Premium Calculation - od only", $log);
                            $data["response"] = $log->response;
                        }
                        break;
                    case "Premium Calculation - DRIVE_ASSURE_BASIC":
                        if ($log->response == "0") {
                            $data['response'] = $log->response;
                        } else {
                            // $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                            // dd("Premium Calculation - DRIVE_ASSURE_BASIC", $log);
                            $data["response"] = $log->response;
                        }
                        break;
                    case "Premium Calculation - Basic":
                        $response = XmlToArray::convert($log->response);
                        if ($response["env:Body"]['m:calculateMotorPremiumSigResponse']["pErrorCode_out"] == "0") {
                            $data['response'] = "Success";
                        } else {
                            $data['response'] = $response['env:Body']['m:calculateMotorPremiumSigResponse']['pError_out']['typ:WeoTygeErrorMessageUser']['typ:errText'];
                            // dd("Premium Calculation - Basic", $log);
                        }
                        break;
                    default:
                        $data['response'] = $log->response;
                        break;
                }
                break;
            default:
                $data['response'] = $log->response;
                break;
        }
        return $data;
    }

    public function renewalCountByDays(Request $request)
    {
         /* Changes As Per Dashboard Team Requirement. Show All Record in Renewal (Patch Work) */
         if(config('ABIBL_MG_DATA_CHANGE') === "Y"){
            $request->combined_seller_ids = [];
        }

        if (is_array($request->product_type)) {
            $master_product_sub_type = MasterProductSubType::whereIn('parent_product_sub_type_id', $request->product_type ?? [])->get()->pluck('product_sub_type_id')->toArray();
        } else {
            $master_product_sub_type = MasterProductSubType::where('parent_product_sub_type_id', $request->product_type ?? '')->get()->pluck('product_sub_type_id')->toArray();
        }

        return [
            "status" => true,
            "data" => [
                [
                    "Yesterday" => UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
                        $query->agentDetails($query);
                    })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
                        $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                            $query->whereIn('product_id', $master_product_sub_type);
                        });
                    })->whereHas('user_product_journey.journey_stage', function ($query) {
                        $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                    })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') = (CURDATE() - INTERVAL 1 DAY)")->count(),

                    
                    "Today" => UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
                        $query->agentDetails($query);
                    })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
                        $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                            $query->whereIn('product_id', $master_product_sub_type);
                        });
                    })->whereHas('user_product_journey.journey_stage', function ($query) {
                        $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                    })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') = CURDATE()")->count(),


                    "Last_07_Days" => UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
                        $query->agentDetails($query);
                    })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
                        $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                            $query->whereIn('product_id', $master_product_sub_type);
                        });
                    })->whereHas('user_product_journey.journey_stage', function ($query) {
                        $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                    })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') <= (CURDATE() - INTERVAL 1 DAY) AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') >= (CURDATE() - INTERVAL 7 DAY)")->count(),


                    "Next_07_Days" => UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
                        $query->agentDetails($query);
                    })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
                        $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                            $query->whereIn('product_id', $master_product_sub_type);
                        });
                    })->whereHas('user_product_journey.journey_stage', function ($query) {
                        $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                    })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') >= CURDATE() AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') <= (CURDATE() + INTERVAL 6 DAY)")->count(),


                    "Last_15_Days" => UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
                        $query->agentDetails($query);
                    })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
                        $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                            $query->whereIn('product_id', $master_product_sub_type);
                        });
                    })->whereHas('user_product_journey.journey_stage', function ($query) {
                        $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                    })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') <= (CURDATE() - INTERVAL 1 DAY) AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') >= CURDATE() - INTERVAL 15 DAY")->count(),


                    "Next_15_Days" => UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
                        $query->agentDetails($query);
                    })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
                        $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                            $query->whereIn('product_id', $master_product_sub_type);
                        });
                    })->whereHas('user_product_journey.journey_stage', function ($query) {
                        $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                    })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') >= CURDATE() AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') <= (CURDATE() + INTERVAL 14 DAY)")->count(),


                    "Last_30_Days" => UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
                        $query->agentDetails($query);
                    })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
                        $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                            $query->whereIn('product_id', $master_product_sub_type);
                        });
                    })->whereHas('user_product_journey.journey_stage', function ($query) {
                        $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                    })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') <= (CURDATE() - INTERVAL 1 DAY) AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') >= (CURDATE() - INTERVAL 30 DAY)")->count(),


                    "Next_30_Days" => UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
                        $query->agentDetails($query);
                    })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
                        $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                            $query->whereIn('product_id', $master_product_sub_type);
                        });
                    })->whereHas('user_product_journey.journey_stage', function ($query) {
                        $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
                    })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') >= CURDATE() AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') <= (CURDATE() + INTERVAL 29 DAY)")->count(),
                ]
            ]
        ];
    }

    public function renewalCountByIc(Request $request)
    {
        $data=[
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d',
            'combined_seller_ids' => 'nullable|array',
        ];
        $validator = Validator::make($request->all(), $data);
        if($validator->fails()){
            return response()->json(['status'=>false,'msg' => $validator->errors()]);
        }
        if (is_array($request->product_type)) {
            $master_product_sub_type = MasterProductSubType::whereIn('parent_product_sub_type_id', $request->product_type ?? [])->get()->pluck('product_sub_type_id')->toArray();
        } else {
            $master_product_sub_type = MasterProductSubType::where('parent_product_sub_type_id', $request->product_type ?? '')->get()->pluck('product_sub_type_id')->toArray();
        }

         /* Changes As Per Dashboard Team Requirement. Show All Record in Renewal (Patch Work) */
         if(config('ABIBL_MG_DATA_CHANGE') === "Y"){
            $request->combined_seller_ids = [];
        }
        
        $data = UserProposal::when(!empty($request->combined_seller_ids), function ($query) {
            $query->whereHas('user_product_journey.agent_details', function (Builder $query) {
                $i = 0;
                foreach (request()->combined_seller_ids as $key => $value) {
                    $key = ($key == 'b2c') ? 'U' : $key;
                    if ($i == 0)
                        if (empty($value))
                            $query->where('seller_type', $key);
                        else
                            $query->where('seller_type', $key)->whereIn('agent_id', $value);
                    else
                        if (empty($value))
                        $query->orWhere('seller_type', $key);
                    else
                        $query->orWhere('seller_type', $key)->whereIn('agent_id', $value);
                    $i++;
                }
            });
        })->when(!empty($request->product_type), function ($query) use ($master_product_sub_type) {
            $query->whereHas('user_product_journey.corporate_vehicles_quote_request', function (Builder $query) use ($master_product_sub_type) {
                $query->whereIn('product_id', $master_product_sub_type);
            });
        })->whereHas('user_product_journey.journey_stage', function ($query) {
            $query->where('stage', STAGE_NAMES['POLICY_ISSUED']);
        })->whereRaw("DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') >= '{$request->from}' AND DATE_FORMAT(STR_TO_DATE(policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') <= '{$request->to}'")
            ->selectRaw('ic_name, count(*) as count')
            ->groupBy('ic_name')->get();
        $response = [];
        $compay_logo = MasterCompany::where('status', 'Active')->get(['company_alias', 'logo', 'company_name']);

        foreach ($data as $key => $value) {
            if(!empty($value->ic_name)){
                $response[$value->ic_name] = [
                    "company_logo_url" => $compay_logo->where('company_name', $value->ic_name)->first()->logo ?? '',
                    "renewed_policy" => 0,
                    "pending_renewal_policy" => $value->count
                ];
            }
        }
        return response()->json([
            'status' => true,
            'data' => $response,
        ]);
    }

    public function modifySellerType(Request $request)
    {
        if(config('MODIFIED_SELLER_TYPE') == "Y")
            \App\Jobs\ModifySellerTypeJob::dispatch(0);
    }

    public function login(Request $request)
    {
        $data=[
            "email" => "required|email",
            "password" => "required",
        ];
        $validator = Validator::make($request->all(), $data);
        if($validator->fails()){
            return response()->json(['status'=>false,'msg' => $validator->errors()]);
        }
        if(auth()->attempt($request->only(["email", "password"]))){
            auth()->user()->update(['api_token' => Str::random(80)]);
            return response()->json([
                "status" => true,
                "msg" => "Login Successfully...!",
                "data" => [
                    "token" => auth()->user()->api_token
                ]
            ], Response::HTTP_OK);

        } else {
            return response()->json([
                "status" => false,
                "msg" => "Invalid Credentials...!"
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    public static function agentMobileValidator(Request $request) 
    {
        //$date_range = Config('MOBILE_NUMBER_DATE_RANGE_IN_DAYS');
        $mobile_number_limt = Config('MOBILE_NUMBER_USAGE_LIMIT');
        $mobile_no = $request->mobile_number;
        $total_number_count = CvJourneyStages::join('user_proposal as up', 'cv_journey_stages.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->join('user_product_journey as uj', 'cv_journey_stages.user_product_journey_id', '=', 'uj.user_product_journey_id')
        ->whereIn('cv_journey_stages.stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']])
        //->where('uj.created_on', '>=', now()->subDays($date_range))
        ->where('up.mobile_number', encryptData($mobile_no))
        ->count();
        if($total_number_count > $mobile_number_limt)
        {
            $data =  
            [
                    'status' => false,
                    'message' => 'The provided mobile number cannot be used as it has exceeded the allowed usage limit.',
            ];
        } 
        else 
        {
            $data =  
            [
                'status' => true,
                'message' => 'The provided mobile number is within the allowed usage limit.'
            ];
        }
        $data['data'] = $data;
        return $data;
    }

    public static function agentEmailValidator(Request $request) 
    {
        //$date_range = Config('EMAIL_DATE_RANGE_IN_DAYS');
        $email_id_limt = Config('EMAIL_USAGE_LIMIT');
        $email = $request->email_id; 
        $total_email_count = CvJourneyStages::join('user_proposal as up', 'cv_journey_stages.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->join('user_product_journey as uj', 'cv_journey_stages.user_product_journey_id', '=', 'uj.user_product_journey_id')
        ->whereIn('cv_journey_stages.stage', [ STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']])
        //->where('uj.created_on', '>=', now()->subDays($date_range))
        ->where('up.email', encryptData($email))
        ->count();

        if($total_email_count > $email_id_limt)
        {
            $data =  
            [
                'status' => false,
                'message' => 'The provided email cannot be used as it has exceeded the allowed usage limit.',
            ];
        } 
        else 
        {
            $data = [
                'status' => true,
                'message' => 'The provided email is within the allowed usage limit.'
            ];
        }
        $data['data'] = $data;
        return $data;
    }
}
