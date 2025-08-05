<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\KafkaDataPushLogs;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class downloadKafkaDetailsController
{

    public function index(Request $request)
    {
        return view("downloadKafka.kafkadownload");
    }




    public function downloadKafka(Request $request)
    {
        $traceId = $request->name;
        // $data = kafkadatapushlogs::where('user_product_journey_id', $traceId)->ordreBy('created_on','desc')->first();
        $enu_id = explode(",", $traceId);
        foreach ($enu_id as $key => $value) {
         $traceid[] = substr($value,-8); 
        }

        $manualData = $realTimeData = $records = [];

        $data = DB::table('kafka_data_push_logs')
            ->whereIn('user_product_journey_id', $traceid)
            ->orderBy('user_product_journey_id')
            ->orderBy('created_on', 'desc')
            ->chunk(1000, function ($data) use (&$manualData, &$realTimeData, &$records) {

                foreach ($data as $key => $record) {
                    if ($record->source == 'manual') {
                        $manualData[json_decode($record->request)->ft_track_id][] = $record;
                    } else if ($record->source == 'RealTime') {
                        $realTimeData[json_decode($record->request)->ft_track_id][] = $record;
                    }
                }
                collect($realTimeData)->each(function ($item, $key) use (&$records) {
                    $records[$key]['realtime'][] = $item[0];
                });
                collect($manualData)->each(function ($item, $key) use (&$records) {
                    $records[$key]['manual'] = $item[0];
                });
            });
           return view('admin.kafka-logs.kafkalogsbifurcate', compact('records'));
            
    }
}
