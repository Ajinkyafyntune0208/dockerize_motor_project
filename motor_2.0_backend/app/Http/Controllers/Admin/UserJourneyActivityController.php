<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserJourneyActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserJourneyActivityController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('user-journey-activities.clear')) {
            return abort(403, 'Unauthorized action.');
        }
        if ($request->method() == 'GET') {
            return view('admin.userJourneyActivity.index');
        } else {
            $validator = Validator::make($request->all(), [
                'enquiryId' => [
                    'required',
                    'string',
                    function($attribute, $value, $fail){
                    if(is_numeric($value) && strlen($value) != 16 ){
                        $fail("Enquiry Id should be 16 digit.");
                    }
                }]
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->errors()->first());
            }
            $enquiryId = acceptBothEncryptDecryptTraceId($request->enquiryId);

            try{
                UserJourneyActivity::where('user_product_journey_id', ltrim($enquiryId,'0'))->delete();
                
            }catch(\Exception $e)
            {
                return redirect()->back()->with('error', $e->getMessage());
            }
            return redirect()->back()->with('success', 'User activity session cleared..');
        }
    }
}
