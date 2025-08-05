<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AceCrmLeadController extends Controller
{
    public static function updateVahandetails($userProductJourneyId, $registrationNo, $vahanData)
    {
        $versionId = $vahanData['rc_data']['custom_data']['version_id'] ??
            $vahanData['rc_data']['vehicle_data']['custom_data']['version_id'] ??
            null;

        if (empty($versionId)) {
            return [
                'status' => false,
                'msg' => 'Version id not found'
            ];
        }

        DB::table('registration_details')->insert([
            'vehicle_reg_no' => $registrationNo,
            'vehicle_details' => json_encode($vahanData),
            'created_at' => now(),
            'updated_at' => now(),
            'expiry_date' => $vahanData['rc_data']['insurance_data']['expiry_date'] ?? null,
        ]);

        \App\Models\FastlaneRequestResponse::create([
            'enquiry_id' => $userProductJourneyId,
            'request' => $registrationNo,
            'response' => json_encode($vahanData),
            'transaction_type' => "Ongrid Service",
            'ip_address' => request()->ip(),
            'section' => 'cv',
            'response_time' => '00:00:00',
            'created_at' => now(),
            'type' => 'input',
        ]);

        switch (\Illuminate\Support\Str::substr($versionId, 0, 3)) {
            case 'PCV':
                # code...
                $type = 'pcv';
                break;
            case 'GCV':
                $type = 'gcv';
                break;
            case 'CRP':
                $type = 'motor';
                break;
            case 'BYK':
                $type = 'bike';
                break;
        }

        if (!isset($type))
        {
            echo "The Version ID as received from CRM End is :  '. $versionId.'<br/><br/>Please cross check data in the CRM application for details";
            die;
        }

        $vahanData = $vahanData['rc_data'];
        if (!empty($vahanData['custom_data'])) {
            $vahanData['vehicle_data']['custom_data'] = $vahanData['custom_data'];
        }

        $vehicleRequest = new \App\Http\Controllers\EnhanceJourneyController();
        $vehicleRequest = $vehicleRequest->getVehicleDetails(
            $userProductJourneyId,
            $registrationNo,
            $type,
            $vahanData,
            'ongrid',
            false,
            true
        );

        return $vehicleRequest;
    }
}
