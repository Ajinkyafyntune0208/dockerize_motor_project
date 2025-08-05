<?php

namespace App\Http\Controllers\Reports;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use AWS\CRT\HTTP\Request;
use Illuminate\Support\Carbon;
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);
class ReportsController extends Controller
{
    public function GetCount()
    {
        $hyundai_zero_days = 0;
        $hyundai_two_days = 0;
        $hyundai_three_days = 0;
        $hyundai_five_days = 0;
        $hyundai_seven_days = 0;

        $mg_zero_days = 0;
        $mg_two_days = 0;
        $mg_three_days = 0;
        $mg_five_days = 0;
        $mg_seven_days = 0;
        $sent_data = [];
        $hyundai_total_sent = 0;
        $mg_total_sent = 0;
        //return $table_data;
        for($i=1;$i<=4;$i++)
        {
            $sent_records = DB::table('communication_logs_'.$i.' as cl')
                            ->select('upj.lead_source','cl.days',DB::raw('count(*) as total'))                    
                            ->join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'cl.old_user_product_journey_id')
                            ->where('communication_module','RENEWAL') 
                            ->whereDate('created_at', Carbon::today())
//                            ->whereDate('created_at', '2023-06-07')
                            ->groupBy('upj.lead_source','cl.days')
                            ->get();
            
            foreach ($sent_records as $key => $value) 
            {
                //echo $value->lead_source .' '.$value->days.' Days '. 'total '.$value->total.'<BR>';
                if($value->lead_source == 'HYUNDAI')
                {
                    if($value->days == 0)
                    {
                        $hyundai_zero_days += $value->total;
                        $hyundai_total_sent += $value->total;
                    }
                    else if($value->days == 2)
                    {
                        $hyundai_two_days += $value->total;
                        $hyundai_total_sent += $value->total;
                    }
                    else if($value->days == 3)
                    {
                        $hyundai_three_days += $value->total;
                        $hyundai_total_sent += $value->total;
                    }
                    else if($value->days == 5)
                    {
                        $hyundai_five_days += $value->total;
                        $hyundai_total_sent += $value->total;
                    }
                    else if($value->days == 7)
                    {
                        $hyundai_seven_days += $value->total;
                        $hyundai_total_sent += $value->total;
                    }                    
                }
                else if($value->lead_source == 'ABIBL_MG_DATA')
                {
                    if($value->days == 0)
                    {
                        $mg_zero_days += $value->total;
                        $mg_total_sent += $value->total;
                    }
                    else if($value->days == 2)
                    {
                        $mg_two_days += $value->total;
                        $mg_total_sent += $value->total;
                    }
                    else if($value->days == 3)
                    {
                        $mg_three_days += $value->total;
                        $mg_total_sent += $value->total;
                    }
                    else if($value->days == 5)
                    {
                        $mg_five_days += $value->total;
                        $mg_total_sent += $value->total;
                    }
                    else if($value->days == 7)
                    {
                        $mg_seven_days += $value->total;
                        $mg_total_sent += $value->total;
                    }                     
                }               
            }
        }
        
        $hyundai_sent_data = [
            '0 Days'        => $hyundai_zero_days,
            '2 Days'        => $hyundai_two_days,
            '3 Days'        => $hyundai_three_days,
            '5 Days'        => $hyundai_five_days,
            '7 Days'        => $hyundai_seven_days,
            'Total Sent'    => $hyundai_total_sent
        ];
        
        $mg_sent_data = [
            '0 Days'        => $mg_zero_days,
            '2 Days'        => $mg_two_days,
            '3 Days'        => $mg_three_days,
            '5 Days'        => $mg_five_days,
            '7 Days'        => $mg_seven_days,
            'Total Sent'    => $mg_total_sent
        ];
        
        $whatsapp_sent = [
            'MG'        => $mg_sent_data,
            'HYUNDAI'   => $hyundai_sent_data
        ];
       // dd($whatsapp_sent);
        $renewal_days = config('RENEWAL_NOTIFICATION_DAYS');
        $renewal_days = explode(',',$renewal_days);  
        $total_all_records = [];
        foreach ($renewal_days as $key => $days) 
        { 
            $records = NULL;
            $records = DB::table('user_product_journey as upj')
                        ->select('upj.lead_source',DB::raw('count(*) as total'))
                        ->whereIn('upj.lead_source',['ABIBL_MG_DATA','HYUNDAI'])
                        ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'upj.user_product_journey_id')
                        ->join('cv_journey_stages as cjs', 'cjs.user_product_journey_id', '=', 'upj.user_product_journey_id')
                        ->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'upj.user_product_journey_id')
                        //->join('corporate_vehicles_quotes_request as cvqr', 'cvqr.user_product_journey_id', '=', 'cvqr.user_product_journey_id')
                        ->whereNotNull('cvqr.version_id')
                        ->where('cvqr.version_id','!=','')
                        ->where('cjs.stage',STAGE_NAMES['POLICY_ISSUED'])
                        ->whereRaw("DATE_FORMAT(STR_TO_DATE(up.policy_end_date,'%d-%m-%Y'),'%Y-%m-%d') = CURDATE() + INTERVAL {$days} DAY")
                        ->groupBy('upj.lead_source')
                        ->get();
                $total_all_records[$days.' Days'] = $records;
//                $return_data = [];
//                foreach ($records as $key => $value) {
//                   $return_data[][$value->lead_source]['total_leads'] = $value->total;
//                }
//                
//                dd($return_data);
        }
        return [
            "Total Leads as Per Date ".Date('Y-m-d') => $total_all_records,
            "WhatsApp Sent Count"                    => $whatsapp_sent
        ];
    }
    public function GetTraceIds( \Illuminate\Http\Request $request)
    {
        $date = date( 'Y-m-d' );
        if( isset( $request->dt ) && trim($request->dt) != "" )
        {
            $date = $request->dt;
        }
        $results = DB::select( "SELECT CONCAT( DATE_FORMAT(j.created_on, '%Y%m%d'), LPAD(j.user_product_journey_id, 8, 0)) AS 'enquiry_id' FROM user_product_journey j left join cv_journey_stages cjs on j.user_product_journey_id = cjs.user_product_journey_id WHERE LOWER( cjs.stage ) IN ( STAGE_NAMES['PAYMENT_SUCCESS'],STAGE_NAMES['POLICY_ISSUED'],'policy issued but pdf not generated',STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'] ) AND j.created_on >= '".$date." 00:00:01' AND j.created_on <= '".$date." 23:59:59'" );
        return $results;
    }
}
