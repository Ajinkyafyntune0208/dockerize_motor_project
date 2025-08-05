<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;

use App\Models\DatapushReqResModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DataPushResReqLogController extends Controller
{
    public function index(Request $req){

        if (!auth()->user()->can('data_push_logs.list')) {
            return [
                'status' => false,
                'msg' => 'Unauthorized access'
            ];
        }
        $enqid = $req->enqid;
        // $rst = $req->resstatus;
        // $ht = $req->httpstatus;
        $df = $req->from_date;
        $dt = $req->to_date;
        // dd($enqid,$rst,$ht,$df,$dt);

        if ( $enqid != null || $df != null || $dt != null 
        ) {
            $logs = DatapushReqResModel::
                when(!empty($enqid), function ($query) use($enqid)  {
                    $query->where('enquiry_id',ltrim(acceptBothEncryptDecryptTraceId($enqid),'0'));
                })
                ->when(!empty($df || $dt), function ($query) {
                    $query->whereBetween('created_at', [
                        date('Y-m-d 00:00:00', strtotime(request()->from_date)),
                        date('Y-m-d 23:59:59', strtotime(request()->to_date ?? request()->from_date))
                    ]);
                })
            ->orderby('created_at', 'DESC');

            $logs = $logs->get();
        }else {
            $logs = DatapushReqResModel::all();
        }
        return view ('admin_lte.datapush_logs.index',compact('logs'));
    }
    public function datapushView(Request $req){
        $id= $req->id;
        if (!auth()->user()->can('data_push_logs.list')) {
            return [
                'status' => false,
                'msg' => 'Unauthorized access'
            ];
        }
        $log = DatapushReqResModel::find($id);
        
        return view('admin_lte.datapush_logs.show', compact('log'));
    }
    // public function downloadDreqreslog(Request $request, $type, $id, $view = 'view')
    public function downloadDreqreslog(Request $request, $type,$id)
    {
        $log = $log = DatapushReqResModel::find($id);

        if ($type !== "datapushlog") {
            return [
                'status' => false,
                'msg' => 'type doesnt match'
            ];
        }
        $encid = customEncrypt($log->enquiry_id);
        $text = "Trace ID : " . (customEncrypt($log->enquiry_id) ?? '');
        $text .= "\n\n\n\tUrl : " . ($log->url ?? '') ;
        $text .= "\n\n\nRequest Headers : \n\t " . (json_encode($log->request_headers ?? '',JSON_PRETTY_PRINT));
        $text .= "\n\n\nStatus : " . ($log->status ?? '');
        $text .= "\n\n\nServer Status code : \t"  . ($log->status_code ?? '');

        // if ($request->with_headers) {
        //     $text .= "\n\n\nHeaders : \n\t" . ($log->headers ?? '');
        // }
        $text .= "\n\n\nRequest : \n\n" . (json_encode($log->request ?? '',JSON_PRETTY_PRINT) );
        $text .= "\n\n\nResponse : \n\n" . (json_encode($log->response ?? '',JSON_PRETTY_PRINT) );
        $file_name = \Illuminate\Support\Str::lower(now()->format('Y-m-d H:i:s') . '-' . $encid . '-' . $type . '.txt');
        
        return response($text, 200, [
            "Content-Type" => "text/plain",
            'Content-Disposition' => sprintf('attachment; filename="' . $file_name .  '"')
        ]);
        
    }

}
