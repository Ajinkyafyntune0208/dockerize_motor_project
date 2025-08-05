<?php

use Illuminate\Http\Request;
use App\Models\PremCalcAttributes;
use Illuminate\Support\Facades\DB;

if (!function_exists('get_attribute')) {
    function get_attribute(Request $request)
    {
        $ic_name = $request['selectIC'];
        $ic_name  = explode('#', $ic_name);
        $vehicle = $request['vehicle'];
        $business_type = $request['businessType'];

        $segments = DB::table('ic_integration_type as ict')
            ->select('segment')
            ->distinct()
            ->where('ict.ic_alias', $ic_name[0])
            ->where('ict.integration_type', $ic_name[1])
            ->get();

        $segment_type = json_decode($segments, true);

        if ($ic_name != null && $vehicle != null) {
            $bussiness_type = DB::table('ic_integration_type as ict')
                ->select('business_type')
                ->distinct()
                ->where('ict.ic_alias', $ic_name[0])
                ->where('ict.integration_type', $ic_name[1])
                ->where('ict.segment', $vehicle)
                ->get();
            $bussiness_type = json_decode($bussiness_type, true);

            if ($ic_name != null && $vehicle != null && $business_type != null) {
                $getAttribute = PremCalcAttributes::select(
                    DB::raw("CONCAT(attribute_name, ' - ', attribute_trail) AS final_attribute"),
                    'id'
                )
                    ->whereIn('ic_alias', (array)$ic_name[0])
                    ->whereIn('integration_type', (array)$ic_name[1])
                    ->whereIn('business_type', (array)$business_type)
                    ->whereIn('segment', (array)$vehicle)
                    ->distinct()
                    ->get();
                $getAttribute = json_decode($getAttribute, true);
                return $getAttribute;
            }
            return $bussiness_type;
        }
        return $segment_type;
    }
}