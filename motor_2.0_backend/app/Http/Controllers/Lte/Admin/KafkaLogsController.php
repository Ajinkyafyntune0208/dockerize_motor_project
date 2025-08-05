<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\KafkaDataPushLogs;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KafkaLogsController extends Controller
{

    public function index(Request $request)
    {
    
        if (!auth()->user()->can('kafka_logs.list')){
        abort(403, 'Unauthorized action.');
        }
        if ($request->enquiryId != null) {
            $validator = Validator::make($request->all(), [
                'enquiryId' => ['required']
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator->errors())->withInput();
            }
        }

        $logs = array();

        if ($request->enquiryId != null) {

            try {
                $user_product_journey_id = customDecrypt(trim($request->enquiryId));
                $logs =  KafkaDataPushLogs::where('user_product_journey_id', $user_product_journey_id)
                ->orderBy('id', 'desc')->get();

                foreach ($logs as $key => $log) {
                    $logs[$key]['encryptId'] = urlencode(customEncrypt($log->id, false));
                }
            } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Sorry, Something Wents Wrong !',
                    'class' => 'danger',
                ]);
            }
        }

        return view('admin_lte.kafka-logs.index', compact('logs'));
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
    public function show($id)
    {
        try {
            $id = customDecrypt(urldecode($id), false);
            $logsDetails = KafkaDataPushLogs::find($id);
            $logsDetails['enquiryId'] = customEncrypt($logsDetails->user_product_journey_id);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Sorry, Something Wents Wrong !',
                'class' => 'danger',
            ]);
        }
        
        return view('admin_lte.kafka-logs.show', compact('logsDetails'));
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
    public function syncData(Request $request)
    {
      
        // if (!auth()->user()->can('kafka_logs.list')){
        //  abort(403, 'Unauthorized action.');
        // } 

        $logs = [];
        if($request->form_submit){
            $validator =  Validator::make($request->all(),[
                'from_date' =>'required|date',
                'to_date' =>'required|date',
            ]);
            if($validator->fails()){
                return redirect()->back()->withInput()->withErrors($validator->errors());
            }
        }
        if(isset($request->from_date) && isset($request->to_date)){
            $logs = httpRequest('kafka_logs_report_count', [
                "start_date" => Carbon::parse($request->from_date)->format('d/m/Y'),
                "end_date" => Carbon::parse($request->to_date)->format('d/m/Y')
            ])['response'];
        }

        $startDate = Carbon::parse($request->from_date)->format('Y-m-d');
        $endDate = Carbon::parse($request->to_date)->format('Y-m-d');

        $paymentStatus = [];
        $paymentStatus = DB::table(DB::raw('user_product_journey j'))
        ->select(DB::raw('COUNT(*) as total'),DB::raw('(CASE WHEN (TRIM(COALESCE(d.pdf_url)) != \'\' AND 
        TRIM(COALESCE(d.policy_number)) != \'\') THEN \'success\' WHEN ((TRIM(COALESCE(d.policy_number)) = \'\' AND 
        r.`status` = \'payment success\') OR (s.stage IN (\'policy issued\', \'payment received\', \'payment success\', \'policy issued, but pdf not generated\'))) THEN \'payment_deducted\' WHEN 
        r.`status` IN (\'failure\', \'payment failed\') THEN \'failure\' ELSE \'pending\' END) `rb_status`'))
        ->join(DB::raw('payment_request_response r'),'r.user_product_journey_id','=','j.user_product_journey_id')
        ->join(DB::raw('cv_journey_stages s'),'s.user_product_journey_id','=','r.user_product_journey_id')
        ->leftJoin(DB::raw('policy_details d'),'d.proposal_id','=','r.user_proposal_id')
        ->whereIn('s.stage',[ STAGE_NAMES['PAYMENT_FAILED'], STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']])
        ->where('r.active','=',1)
        ->wherebetween('r.created_at', [$startDate ." 00:00:00", $endDate ." 23:59:59"])
        ->groupByRaw('rb_status')
        ->get();
        return view('admin_lte.kafka-logs.syncData', compact('logs','paymentStatus'));
    }
}
