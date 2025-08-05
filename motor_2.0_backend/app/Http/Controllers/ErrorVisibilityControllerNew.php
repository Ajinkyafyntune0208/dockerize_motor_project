<?php

namespace App\Http\Controllers;

use App\Models\VisibilityReportLogSummary;
use App\Traits\LogSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ErrorVisibilityControllerNew extends Controller
{
    use LogSummary;

    public function __invoke(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'from' => 'required|date_format:Y-m-d\TH:i:s\Z',
            'to' => 'required|date_format:Y-m-d\TH:i:s\Z|after_or_equal:from',
            'method_type' => 'required',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validation->errors(),
            ]);
        }

        list($log_type, $method_name) = $this->getLogAndMethodName($request->method_type);

        $need_logs_from = (Carbon::parse($request->from));
        $need_logs_till = (Carbon::parse($request->to));

        $timeRangesToCheckForCache = generateHourlyDateRanges($need_logs_from, $need_logs_till);

        $summary_query = VisibilityReportLogSummary::query();

        $timeRangesToCheckForCache->each(function ($range) use (&$summary_query, $request) {
            $summary_query->orWhere(function ($query) use ($range, $request) {
                $query->where('from', $range['from']->getTimestamp())
                    ->where('to', $range['to']->getTimestamp())
                    ->where('method_type', $request->method_type);
            });
        });

        $metrics = [];

        $cached_metrics = $summary_query->get();

        foreach ($cached_metrics as $metric) {
            mergeArrays($metrics, $metric['data']);
        }

        $cacheEndStartTime = $timeRangesToCheckForCache->last()['from'];

        if ($need_logs_till > now()) {
            $this->initializeMetrics();

            $realTimeCalculationStartTime = $cacheEndStartTime->addHour();

            $realtime_from = clone $realTimeCalculationStartTime;
            $realtime_to = clone $realTimeCalculationStartTime;

            $realtime_from = $realtime_from->startOfHour();
            $realtime_to = $realtime_to->endOfHour();

            $this->calculateMetrics($realtime_from->getTimestamp(), $realtime_to->getTimestamp(), $log_type, $method_name);

            $current_day_metrics = $this->getMetrics();

            mergeArrays($metrics, $current_day_metrics);
        }

        $response = [
            'data' => $metrics,
        ];

        if (isset($realtime_from)) {
            $response['realtime_calculation'] = [
                'from' => $realtime_from->getTimestamp(),
                'to' => $realtime_to->getTimestamp(),
                'from_date' => $realtime_from->toDateTimeString(),
                'to_date' => $realtime_to->toDateTimeString(),
            ];
        }

        return $response;

    }
}
