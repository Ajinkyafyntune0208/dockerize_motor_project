<?php

namespace App\Http\Controllers\Lte\Admin;
use App\Models\DashboardDataPushModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardMongoLogsController extends Controller
{
    
    public function show(Request $request)
    {
        if (!auth()->user()->can('dashboard_mongo_logs.list')) {
            abort(403, 'Unauthorized action.');
        }
    
        $enquiryId = $request->enquiryId;
       
        $logs = [];
    
        try {
            if(config('constants.motorConstant.DASHBOARD_DATA_PUSH_ENABLED') != 'Y'){
                return redirect()->back()->with([
                    'status' => 'Mongo is not enabled!',
                    'class' => 'danger',
                ]);
            }
            if ($enquiryId !== null) {
                $where = ($request->type == 'ENCRYPTED')
                    ? ['enquiry_id' => $enquiryId]
                    : ['trace_id' => $enquiryId];

                    // $logsDetails = DashboardDataPushModel::where($where)->get();
                $dashboard = new \App\Helpers\Mongo\Models\DashboardTransaction();
                $logsDetails = $dashboard->where($where)
                ->select([
                    'transaction_stage',
                    '_id',
                    'created_at',
                    'updated_at',
                ])
                ->get();

                if ($logsDetails->isEmpty()) {
                    return redirect()->route('admin.mongodb')->with([
                        'status' => 'No records found for the given enquiryId.',
                        'class' => 'warning',
                    ])->withInput();
                }
    
                $logs = $logsDetails;
            }
        } catch (\Exception $e) {
            return redirect()->route('admin.mongodb')->with([
                'status' => 'Sorry, something went wrong!',
                'class' => 'danger',
            ])->withInput();
        }
    
        return view('admin_lte.mongodb.index', compact('logs'));
    }
    
    
public function showdata($id)
{
    // Use the $id parameter to find the record in DashboardDataPushModel
    // $data = DashboardDataPushModel::find($id);

    $data = new \App\Helpers\Mongo\Models\DashboardTransaction();
    $data = $data->find($id);
     // Check if the record exists
    if ($data) {
        // Your logic to handle the found record
        return view('admin_lte.mongodb.show', ['data' => $data]);
    } else {
        // Handle the case where the record is not found
        abort(404);
    }
}


    

    }
   



    


