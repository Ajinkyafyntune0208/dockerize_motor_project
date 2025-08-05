<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfigSetting;
use App\Models\AuthorizationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\User;

class AuthorizationRequestController extends Controller
{
     public function index(Request $request){

        $request_list = AuthorizationRequest::select('authorization_request.*','user.name as request_raised_by')
        ->join("user","user.id","=","authorization_request.requested_by")
        ->where('approved_by', auth()->user()->id)
        ->where('approved_status', 'N')
        ->orderBy('requested_date')
        ->get();

    return view('admin_lte.authorization_request.index', ['request_list' => $request_list]);
    }


    public function approve_request(Request $request){

        $request_id = $request->request_id;
        $request_action = $request->request_action;

        if($request_action=='Approve'){
            $request_list = AuthorizationRequest::select('authorization_request.*','user.name as request_raised_by')
            ->join("user","user.id","=","authorization_request.requested_by")
            ->where('authorization_request_id', $request_id)
            ->where('approved_status', 'N')
            ->orderBy('requested_date')
            ->get();
    
            $updated_data = json_decode($request_list[0]->reference_update_value, true);
    
            ConfigSetting::updateOrCreate(['key' => $updated_data['key']], $updated_data);
    
            AuthorizationRequest::where('authorization_request_id', $request_id)->update([
                'approved_status' => 'Y',
                'approved_date' => now(),
            ]);        
        }else{
            $reject_comment = $request->reject_comment;
            AuthorizationRequest::where('authorization_request_id', $request_id)->update([
                'approved_status' => 'R',
                'approved_date' => now(),
                'reject_comment' => $reject_comment,
            ]);
        }

        return 'Success';
    }
    
    public function markAsRead(Request $request)
    {
        $notification = Notification::find($request->id);
  
        if ($notification) {
            $notification->is_read = 'Y';
            $notification->save();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false], 404);
    }

    public function approvalStatus(){

        $request_data = AuthorizationRequest::where('requested_by', auth()->user()->id)->orderBy('authorization_request_id', 'desc')->get();
        if($request_data){
            foreach($request_data as $req_data){
               $user = User::select('name')->where('id',$req_data->approved_by)->first();
               $req_data['requested_user'] = ucfirst($user->name);
            }
        }

        $response_data = AuthorizationRequest::where('approved_by', auth()->user()->id)->orderBy('authorization_request_id', 'desc')->get();
    
        if($response_data){
            foreach($response_data as $res_data){
               $user = User::select('name')->where('id',$res_data->requested_by)->first();
               $res_data['requested_user'] = ucfirst($user->name);
            }
        }

        return  view('admin_lte.authorization_request.approval-status', compact('request_data', 'response_data'));
    }
}
