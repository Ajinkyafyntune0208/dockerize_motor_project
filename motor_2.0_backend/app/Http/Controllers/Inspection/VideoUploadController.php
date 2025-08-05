<?php

namespace App\Http\Controllers\Inspection;

use App\Http\Controllers\Controller;
use App\Models\SelfInspectionAppDetail;
use CreateCvBreakinStatusTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VideoUploadController extends Controller
{
    public function upload(Request $request)
    {
        $rules = [
            'video' => 'required|file|mimes:mp4,avi,mov,wmv',
            'lat' => 'nullable',
            'mobile_no' => 'required|numeric|digits:10',
            'vehicle_registration_no' => 'required',
            'user_product_journey_id' => 'nullable',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors())->withInput();
        }
        $validateData = $request->only('video','lat','mobile_no','vehicle_registration_no','user_product_journey_id');
        $video_url = $request->file('video')->store('inspection-app/video');

        SelfInspectionAppDetail::create([
            'user_product_journey_id' => customDecrypt($validateData['user_product_journey_id']),
            'regisration_no' => $validateData['vehicle_registration_no'],
            'mobile_no' => $validateData['mobile_no'],
            'video_url' => $video_url,
        ]);
        return response()->json([
            'status' => true,
            'msg' => 'Video Uploaded Successfully...!'
        ]);
    }
}
