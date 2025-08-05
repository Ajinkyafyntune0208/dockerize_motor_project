<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JourneyStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StageCountController extends Controller
{
    public function view (Request $request)
    {
        if (!auth()->user()->can('report.list')) {
            return abort(404);
        }
        $stageDropDownValue = JourneyStage::distinct('stage')
        ->whereNotNull('stage')
        ->where('stage','!=','')
        ->pluck('stage')
        ->toArray();
        $companies=DB::table('master_company')->select('company_id','company_name')->get();
        $count=[];
        if ($request->has('stage') && $request->has('from_date') && $request->has('to_date')) {
            $count = [
                'bike' => 0,
                'car' => 0,
                'cv' => 0
            ];

            $results = DB::table('user_product_journey as upj')
            ->whereBetween('js.updated_at', [
                date('Y-m-d H:i:s', strtotime(request()->from_date.' 00:00:00')),
                date('Y-m-d H:i:s', strtotime(request()->to_date.' 23:59:59'))
            ])
            ->join('cv_journey_stages as js', 'upj.user_product_journey_id', '=', 'js.user_product_journey_id')
            ->join('master_company as mc','js.ic_id','=','mc.company_id')
            ->select('upj.product_sub_type_id', DB::raw('COUNT(*) as count'))
            ->whereIn('js.stage',$request->get('stage'))
            ->when($request->has('company') && $request->get('company'), function ($query) use ($request) {
                $query->where('mc.company_id', $request->get('company'));
            })
            ->groupBy('upj.product_sub_type_id')
            ->get();

            foreach($results as $r) {
                if ($r->product_sub_type_id) {
                    switch($r->product_sub_type_id) {
                        case 1:
                            $count['car'] = $r->count;
                        break;
                        case 2:
                            $count['bike'] = $r->count;
                        break;
                        default:
                        $count['cv'] = $count['cv'] + $r->count;
                    }
                }
            }

        }
        return view('count.stage-count', ['stageDropDownValue' => $stageDropDownValue,'count' => $count,'companies'=>$companies]);
    }
}
