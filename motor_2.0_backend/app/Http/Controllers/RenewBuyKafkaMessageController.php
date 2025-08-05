<?php

namespace App\Http\Controllers;

use App\Jobs\KafkaCvDataPushJob;
use App\Jobs\KafkaDataPushJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RenewBuyKafkaMessageController extends Controller
{
    public function getMessages(Request $request)
    {

        DB::disableQueryLog();
        if ($request->has('show_query')) {
            DB::enableQueryLog();
        }

        $from_date = date("Y-m-d 00:00:00", strtotime($request->from_date));

        $to_date = date("Y-m-d 23:59:59", strtotime($request->to_date));

        $type = $request->type;

        $allowed_types = ['proposal', 'customer', 'policy', 'payment', 'inspection'];
        if (!in_array($type, $allowed_types)) {
            return response()->json([
                'message' => "Requested 'type' must be of one of the following : " . implode(", ", $allowed_types) . '.',
                'status' => false,
                'data' => [],
            ], 400);
        }
        $trace_ids = request()->trace_id;
        $user_journeys = DB::table("user_product_journey as u")
            ->when(is_array($trace_ids), function ($query) use ($trace_ids) {
                array_walk($trace_ids, fn(&$v, $k) => $v = (int) \Illuminate\Support\Str::substr($v, 8));
                return $query->whereIn('u.user_product_journey_id', $trace_ids);
            }, function ($query) use ($from_date, $to_date) {
                return $query->whereBetween("u.created_on", [$from_date, $to_date]);
            })
            ->when(request()->has('product_type') && strtolower(request()->product_type) == 'cv', function ($q) {
                return $q->whereNotIn('u.product_sub_type_id', [1, 2]);
            })
            ->when(request()->has('product_type') && strtolower(request()->product_type) == 'motor', function ($qq) {
                return $qq->whereIn('u.product_sub_type_id', [1, 2]);
            })
            ->join('cv_journey_stages as s', 's.user_product_journey_id', '=', 'u.user_product_journey_id')
            ->join('user_proposal as p', 'p.user_product_journey_id', '=', 'u.user_product_journey_id')
            ->whereIn('s.stage', [ STAGE_NAMES['PAYMENT_FAILED'], STAGE_NAMES['PAYMENT_INITIATED'], STAGE_NAMES['PAYMENT_SUCCESS'], STAGE_NAMES['POLICY_ISSUED'], STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'], STAGE_NAMES['INSPECTION_ACCEPTED'], STAGE_NAMES['INSPECTION_PENDING'], STAGE_NAMES['INSPECTION_REJECTED']])
            ->select("u.user_product_journey_id", "u.created_on", "u.product_sub_type_id")
            ->get();

        foreach ($user_journeys as $key => $v) {
            if (in_array($v->product_sub_type_id, [1, 2])) {
                $job = new KafkaDataPushJob($v->user_product_journey_id, $type);
            } else {
                $job = new KafkaCvDataPushJob($v->user_product_journey_id, $type);
            }
            $journey_id = \Carbon\Carbon::parse($v->created_on)->format('Ymd') . sprintf('%08d', $v->user_product_journey_id);
            try {
                $customMessage = $job->getCustomMessage();
                // We're returning false if record is not found, that case has to be handled as well. - 16-06-2022
                if (is_array($customMessage)) {
                    $data[$journey_id] = array_merge(['status_code' => 200], $customMessage);
                } else {
                    $data[$journey_id] = $customMessage;
                }
            } catch (\Exception$e) {
                $data[$journey_id] = [
                    'status_code' => 404,
                    'error_message' => $e->getMessage(),
                ];
            }
        }

        $filterdata = array_filter($data ?? []);

        //$filterdata = collect($filterdata)->values();

        return response()->json([
            'message' => count($filterdata) . " Record Found",
            'status' => count($filterdata) > 0 ? true : false,
            'data' => $filterdata,
        ], 200);
    }
}
