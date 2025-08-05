<?php

namespace App\Http\Controllers\Admin;
use App\Models\DashboardDataPushModel;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardMongoLogsController extends Controller
{
    
    public function show(Request $request)
    {
        if (!auth()->user()->can('mongodb.list')) {
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
                $logsDetails = DashboardDataPushModel::where('enquiry_id', $enquiryId)
                ->orWhere('trace_id', $enquiryId) 
                ->orWhere('encrypted_trace_id', $enquiryId)
                ->get();
              

                if ($logsDetails->isEmpty()) {
                    return redirect()->back()->with([
                        'status' => 'No records found for the given enquiryId.',
                        'class' => 'warning',
                    ]);
                }
    
                $logs = $logsDetails;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e);
            return redirect()->back()->with([
                'status' => 'Sorry, something went wrong! ' . $e->getMessage(),
                'class' => 'danger',
            ]);
        }
    
        return view('admin.mongodb.index', compact('logs'));
    }
    
    
public function showdata($id)
{
    // Use the $id parameter to find the record in DashboardDataPushModel
    $data = DashboardDataPushModel::find($id);
     // Check if the record exists
    if ($data) {
        // Your logic to handle the found record
        return view('admin.mongodb.show', ['data' => $data]);
    } else {
        // Handle the case where the record is not found
        abort(404);
    }
}


    

    }
   



    


