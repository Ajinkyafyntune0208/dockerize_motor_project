<?php

namespace App\Http\Controllers\Inspection;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\InspectionStatusLogs;

class BikeInspectionController extends Controller
{
    public static function updateBikeBreakinStatus(Request $request)
    {

        $incomming_data = [
            'segment' => 'BIKE',
            'ic_name' => $request->company_alias,
            'request' => (array)($request->all())
        ];

        InspectionStatusLogs::create($incomming_data);

        $companyAlias = $request->company_alias;

        switch ($companyAlias) {
            case 'united_india':
                // return true;
                break;

            default:
                return response()->json([
                    'status' => false,
                    'msg' => 'Invalid company name'
                ]);
        }
    }
}