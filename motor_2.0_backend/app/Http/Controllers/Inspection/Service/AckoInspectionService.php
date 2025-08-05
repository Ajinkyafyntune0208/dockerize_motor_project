<?php

namespace App\Http\Controllers\Inspection\Service;

class AckoInspectionService
{
    public static function inspectionConfirm($request)
    {
        return response()->json([
            'status' => true,
            "msg" => $request->toArray()
        ]);
    }
}
