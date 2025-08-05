<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RenewalDataMigrationStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RenewalDataMigrationStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('renewal-data-migration.report')) {
            return abort(403, 'Unauthorized action.');
        }
        $enquiryId = acceptBothEncryptDecryptTraceId($request->enquiryId );
        $reports = collect();
        if ($request->from ?? $request->to ?? false) {
            if (empty($request->from) || empty($request->to)) {
                return redirect()->back()->with(['status' => 'From and to date is required', 'class' => 'danger']);
            }
        } 
        if (!empty($request->from ?? $request->to ?? $request->enquiryId ?? $request->rcNumber ?? $request->policyNumber ?? null)) {
            $reports = RenewalDataMigrationStatus::select('*')
            ->with(['updation_log', 'user_product_journey'])
            ->when(!empty($request->from && $request->to), function ($query) {
                $query->whereBetween('created_at', [
                    Carbon::parse(request()->from)->startOfDay(),
                    Carbon::parse(request()->to)->endOfDay(),
                ]);
            })
            ->when(!empty($request->enquiryId), function ($query) use($enquiryId){
                $query->where('user_product_journey_id', ltrim($enquiryId,'0'));
            })
            ->when(!empty($request->rcNumber), function ($query) {
                try {
                    $withHyphen = getRegisterNumberWithHyphen(request()->rcNumber);
                } catch (\Throwable $th) {
                    $withHyphen = request()->rcNumber;
                }
                $rcNumberArray = [
                    str_replace('-', '', request()->rcNumber),
                    request()->rcNumber,
                    $withHyphen
                ];
                $rcNumberArray = array_values(array_unique($rcNumberArray));
                $query->whereIn('registration_number', $rcNumberArray);
            })
            ->when(!empty($request->policyNumber), function ($query) use ($request){
                $query->where("policy_number", $request->policyNumber);
            })
            ->get();

            if ($request->viewType == 'excel') {
                $excelData[] = [
                    'Created At',
                    'Enquiry ID',
                    'RC Number',
                    'Policy Number',
                    'Policy Data',
                    'Old Data',
                    'New Data',
                    'Attempts',
                    'Status'
                ];
                foreach ($reports as $value) {
                    $old_data = [];
                    $new_data = [];
                    foreach($value->updation_log as $log) {
                        $old_data[$log->type][] = $log->old_data;
                        $new_data[$log->type][] = $log->new_data;
                    }

                    $excelData[] = [
                        $value->created_at,
                        !empty($value->user_product_journey_id ?? null) ? '"'.customEncrypt($value->user_product_journey_id) : '',
                        $value->registration_number,
                        $value->policy_number,
                        $value->request,
                        json_encode($old_data),
                        json_encode($new_data),
                        $value->attempts,
                        $value->status,
                    ];
                }
                return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($excelData), now() . ' Renewal Data Migration Report.xls');
            }
        }
        return view('admin.renewalDataMigration.list', compact('reports'));
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
    public function show(Request $request, $id)
    {
        if (!auth()->user()->can('renewal-data-migration.report')) {
            return abort(403, 'Unauthorized action.');
        }
        $report = RenewalDataMigrationStatus::with(['migration_attempt_logs', 'wealth_maker_api_log', 'updation_log'])->find($id);
        $from_date=Carbon::now()->startOfMonth();
        $to_date = Carbon::now();
        $url=route('admin.log.index',[
            'enquiryId' => customEncrypt($report->user_product_journey_id),
            'company' => '',
            'transaction_type' => '',
            'from_date' => $from_date->format('Y-m-d'),
            'to_date' => $to_date->format('Y-m-d'),
            'view_type' => 'view'
        ]);
        if (empty($report)) {
            return response()->json(['message' => 'Data not found']);
        }
        return view('admin.renewalDataMigration.view', compact('report','url'));
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

    public function download(Request $request, $id)
    {
        if (!auth()->user()->can('renewal-data-migration.report')) {
            return abort(403, 'Unauthorized action.');
        }
        $log = RenewalDataMigrationStatus::with(['wealth_maker_api_log', 'updation_log'])->find($id);

        if (empty($log)) {
            return response()->json(['message' => 'Data not found']);
        }
        $enquiryId = isset($log->user_product_journey_id) ? customEncrypt($log->user_product_journey_id) : '';
        $text = "Trace ID : " . $enquiryId;
        $text .= "\n\n\nRC Number : " . $log->registration_number ?? '';
        $text .= "\n\n\nPolicy Number : " . $log->policy_number ?? '';
        $text .= "\n\n\nStatus : " . $log->status ?? '';
        $text .= "\n\n\Action : " . $log->action ?? '';
        $text .= "\n\n\nAttempts : " . $log->attempts ?? '';
        $text .= "\n\n\nCreated At : " . (isset($log->created_at) && !empty($log->created_at) ? date('d-M-Y h:i:s A', strtotime($log->created_at)) : '');
        $text .= "\n\n\nPolicy Data : \n\n" . ($log->request ?? '');

        if (!empty($log->updation_log ?? null)) {
            $old_data = [];
            $new_data = [];
            foreach ($log->updation_log as $item) {
                $old_data[$item->type][] = $item->old_data;
                $new_data[$item->type][] = $item->new_data;
            }
            $text .= "\n\n\nOld Data : \n\n" . (json_encode($old_data));
            $text .= "\n\n\nNew Data : \n\n" . (json_encode($new_data));
        }

        if (!empty($log->wealth_maker_api_log ?? null)) {
            $text .= "\n\n\n\nWealth Maker Api Logs  : ";
            $text .= "\n\nStatus : " . ($log->wealth_maker_api_log->status);
            $text .= "\n\nCreated At : " . ($log->wealth_maker_api_log->created_at);
            $text .= "\n\nRequest : \n" . (stripslashes($log->wealth_maker_api_log->request));
            $text .= "\n\nResponse : \n" . ($log->wealth_maker_api_log->response);
        }
        $fileName = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s').'-renewal-data-migration-'.(empty($enquiryId) ? ($log->registration_number ?? $log->policy_number ?? '') : $enquiryId).'.txt');
        return response($text, 200, [
            "Content-Type" => "text/plain",
            'Content-Disposition' => sprintf('attachment; filename="' . $fileName .  '"')
        ]);
    }

    public function logs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
            'policy_number' => 'nullable|array',
            'status' => 'nullable|in:Success,Failed,Pending',
            'action' => 'nullable|in:update,migrate'
        ]);

        $request->action = $request->action == 'migrate' ? 'migration' : $request->action;

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 403);
        }

        $logs = RenewalDataMigrationStatus::with([
            'migration_attempt_logs' => function ($query) {
                return $query->select('renewal_data_migration_status_id', 'attempt', 'type', 'status', 'created_at', 'extras');
            },
            'updation_log' => function ($query) {
                return $query->select('old_data', 'new_data', 'created_at', 'renewal_data_migration_status_id', 'type');
            },
        ])
        ->when(!empty($request->from_date) && !empty($request->to_date), function ($query) use($request) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from_date)->startOfDay(),
                Carbon::parse($request->to_date)->endOfDay(),
            ]);
        })
        ->when(!empty($request->policy_number), function ($query) use($request) {
            $query->whereIn('policy_number', $request->policy_number);
        })
        ->when(!empty($request->status), function ($query) use($request) {
            $query->where('status', $request->status);
        })
        ->when(!empty($request->action), function ($query) use($request) {
            $query->where('action', $request->action);
        })
        ->select('policy_number', 'status', 'created_at', 'action', 'id')
        ->get()
        ->map(function ($item) {
            if (count($item->migration_attempt_logs) > 0) {
                foreach ($item->migration_attempt_logs as $value) {
                    $value->reason = json_decode($value->extras, true)['reason'] ?? null;
                    unset($value->extras);
                }
            }
            unset($item->id);
            return $item;
        });

        return response()->json([
            'status' => !$logs->isEmpty(),
            'message' => $logs->isEmpty() ? 'No data found' : count($logs). ' records found',
            'data' => $logs
        ], 200);
    }
}
