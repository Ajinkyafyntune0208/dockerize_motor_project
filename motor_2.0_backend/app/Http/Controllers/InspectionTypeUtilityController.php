<?php

namespace App\Http\Controllers;

use App\Models\InspectionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InspectionTypeUtilityController extends Controller
{
    public function getInspectionType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'companyAlias' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        else
        {
            $inspection_type= InspectionType::select('Manual_inspection','Self_inspection')
            ->where('company_name',$request->companyAlias)
            ->first();
            $result=[];

            if($inspection_type)
            {
               if($inspection_type->Manual_inspection == 'Y')
               {
                $result[]='MANUAL';
               } 
               if($inspection_type->Self_inspection == 'Y')
               {
                $result[]='SELF';
               }
            }
            if(!empty($result))
            {
                return response()->json([
                    'status' => true,
                    'message' => "Found",
                    "data" => $result
                ]);
            }
            else
            {
                return response()->json([
                    'status' => false,
                    'message' => "Inspection type is not enabled",
                ]);
            }
        }
    }
}
