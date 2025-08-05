<?php

namespace App\Http\Controllers;
use App\Models\JourneyStage;

use Illuminate\Http\Request;
use App\Jobs\KafkaDataPushJob;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;

class GetKafkaData extends Controller
{
    public function getkafkaData()
     {
         $data = DB::table('cv_journey_stages as s')
                    ->join('user_product_journey as j', 's.user_product_journey_id', '=', 'j.user_product_journey_id')
                    ->where('s.stage','=',STAGE_NAMES['INSPECTION_PENDING'])
                    ->selectRaw("CONCAT(DATE_FORMAT(j.created_on, '%Y%m%d' ) , LPAD(j.user_product_journey_id, 8, 0)) as journey_id")
                    ->get();
        if(empty($data))
        {
            return false;
        }
       /*  $data = UserProductJourney::whereHas('journey_stage', function($query){
            $query->where('stage',STAGE_NAMES['INSPECTION_PENDING']);
                
        })->get(['user_product_journey_id','created_on']); */
        
        foreach ($data as $id)
        {
            KafkaDataPushJob::dispatch($id->journey_id, 
            'policy' ,'manual');
          //return  httpRequestNormal(url('kafka/'.$id->journey_id.'/policy'), 'GET')['response'];
        }
        return true;
     }
}
