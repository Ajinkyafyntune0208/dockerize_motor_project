<?php

namespace App\Helpers;
use Illuminate\Http\Request;


class VehicleRegistrationNumberFormatHelper
{

    public static function formatRegistrationNumber($registration_number)
    {
        $registration_number = explode('-', $registration_number);
        return (count($registration_number) === 3)
            ? $registration_number[0] . '-' . $registration_number[1] . '--' . $registration_number[2]
            : $registration_number;
    }

}

