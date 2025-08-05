<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class InspectionConfirmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /*
        $inspectionList = Http::accept('application/json')->post(url('api/getInspectionList'), [
            "from" => now()->subDays(2)->format('Y-m-d'),
            "to" => now()->format('Y-m-d'),
            "seller_type" => ["E", "P", "U"],
            "product_type" => \App\Models\MasterProductSubType::select('product_sub_type_id')->get()->pluck('product_sub_type_id')
        ])->json();
        */

        info("InspectionConfirmJob started at ".now());
        $inspectionController = new \App\Http\Controllers\Inspection\InspectionController();  
        $data = [
            "from" => now()->subDays(2)->format('Y-m-d'),
            "to" => now()->format('Y-m-d'),
            "seller_type" => ["E", "P", "U"],
            "product_type" => \App\Models\MasterProductSubType::select('product_sub_type_id')->get()->pluck('product_sub_type_id')->toArray()
        ];

        $request = new \Illuminate\Http\Request($data);
        $inspectionList = json_decode($inspectionController->getInspectionList($request)->getContent(), true);
        
        if (!isset($inspectionList['data'])) {
            return false;
        }
        $enquiry_ids = collect($inspectionList['data'])->pluck('breakin_number');
        $result = [];
        foreach ($enquiry_ids as $key => $value) {
            $inspectionData = ["inspectionNo" => $value];
            $inspectionRequest = new \Illuminate\Http\Request($inspectionData);
            /* $response = json_decode($inspectionController->inspectionConfirm($inspectionRequest)->getContent(), true); */
            try {
                $response = $inspectionController->inspectionConfirm($inspectionRequest);
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $response = json_decode($response->getContent(), true);
                    array_push($result, $response);
                } elseif (is_array($response)) {
                    array_push($result, $response);
                } else {
                    info("Error Breakin Number " . $value . " " . json_encode($response));
                    continue;
                }
            } catch (\Exception $e) {
                info('Error Breakin Number: ' . $value . " " . $e->getMessage() . 'File : ' . $e->getFile() . 'Line No. : ' . $e->getLine());
                continue;
            }
        }
    /*
        $master = curl_multi_init();
        foreach ($enquiry_ids as $key => $value) {
            $url = url('api/inspectionConfirm');
    
            $curl_arr[$key] = curl_init($url);
            $data = [
                "inspectionNo" => $value,
            ];
            curl_setopt($curl_arr[$key], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_arr[$key], CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl_arr[$key], CURLOPT_HTTPHEADER, [
                'Accept' => 'application/json',
            ]);
            curl_setopt($curl_arr[$key], CURLOPT_POSTFIELDS, $data);
            curl_multi_add_handle($master, $curl_arr[$key]);
        }
        do {
            curl_multi_exec($master, $running);
        } while ($running > 0);
    
        $result = [];
        foreach ($enquiry_ids as $key => $value) {
            $result[$key] = json_decode(curl_multi_getcontent($curl_arr[$key]), true);
        }
    */
    }
}
