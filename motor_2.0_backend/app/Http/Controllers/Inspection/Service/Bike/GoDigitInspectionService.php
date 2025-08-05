<?php

namespace App\Http\Controllers\Inspection\Service\Bike;
include_once app_path().'/Helpers/BikeWebServiceHelper.php';
use App\Http\Controllers\Inspection\Service\Bike\V2\GoDigitInspectionService as oneapi;

class GoDigitInspectionService
{
    
    public static function inspectionConfirm($request)
    {
        if (config('IC.GODIGIT.V2.BIKE.ENABLE') == 'Y')
        return  oneapi::oneApiInspectionConfirm($request);


    }
}