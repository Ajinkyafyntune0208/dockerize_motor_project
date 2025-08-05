<?php

namespace App\Http\Controllers;

use App\Models\UserProfilingModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserProfilingController extends Controller
{
    public function addData(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'userProductJourneyId' => 'required',
            'request' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Data not found in request..!',
            ]);
        }

        $requestdata = UserProfilingModel::create([
            'user_product_journey_id' => customDecrypt($request['userProductJourneyId']),
            'request' => $request['request'],
        ]);
        // dd($requestdata);

        return response()->json([
            'status' => true,
            'msg' => 'Data saved succesfully',
            'data' => $requestdata->request,
        ]);

    }
}
