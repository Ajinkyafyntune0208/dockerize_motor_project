<?php

namespace App\Traits;

use App\Models\CkycLogsRequestResponse;
use App\Models\QuoteServiceRequestResponse;
use App\Models\WebServiceRequestResponse;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait LogSummary
{
    protected $metrics = [];

    public $expected_average_response_time = 3;

    protected $method_names = [
        'quote' => [
            'token' => 'Token Generation',
            'idv_service' => 'IDV Service',
            'premium_calculation' => 'Premium Calculation',
            'premium_re_calculation' => 'Premium Re-Calculation',
        ],
        'proposal' => [
            'token' => 'Token Generation',
            'premium_calculation' => 'Premium Calculation',
            'premium_re_calculation' => 'Premium Re-Calculation',
            'proposal_submit' => 'Proposal Submit',
        ],
        'ckyc' => [
            'ckyc' => 'ckyc',
        ],
    ];

    protected function getLogAndMethodName(String $methodString): array | Exception
    {
        // method type will be eg. quote_token, proposal_token, etc....
        list($log_type, $method_key) = explode('_', $methodString, 2);

        if (!isset($this->method_names[$log_type][$method_key])) {
            throw new Exception("Invalid method key.");
        } else {
            return [
                $log_type,
                $this->method_names[$log_type][$method_key],
            ];
        }
    }

    protected function updateMetrics($log)
    {
        $company = $log->company;
        if (!isset($this->metrics['companies'][$company])) {
            $this->metrics['companies'][$company] = [
                'total' => 0,
                "success" => 0,
                "requests_above_average_time" => 0,
                "failure" => 0,
                "actionable_at_ic" => 0,
                "actionable_at_ft" => 0,
                "success_total_response_time" => 0,
                "failure_total_response_time" => 0,
            ];
        }

        if ($log->status == 'Success') {
            $this->metrics['companies'][$company]['success']++;
            $this->metrics['success']++;
            $this->metrics['companies'][$company]['success_total_response_time'] += (int) $log->response_time;
        } else if ($log->status == 'Failed') {
            $this->metrics['companies'][$company]['failure']++;
            $this->metrics['failure']++;
            $this->metrics['companies'][$company]['failure_total_response_time'] += (int) $log->response_time;

            if ($log->transaction_type == "Internal Service Error") {
                $this->metrics['companies'][$company]['actionable_at_ft']++;
            } else {
                $this->metrics['companies'][$company]['actionable_at_ic']++;
            }
        }

        if ($log->response_time > $this->expected_average_response_time) {
            $this->metrics['companies'][$company]['requests_above_average_time']++;
        }

        $this->metrics['companies'][$company]['total']++;
        $this->metrics['total']++;
    }

    private function getQuoteCount($from, $to, $method_name)
    {
        $quoteCountData = QuoteServiceRequestResponse::query()
        // $quoteCountData = DB::table('quote_webservice_request_response_data_temp')
            ->selectRaw("COUNT(*) AS `total`, SUM(IF(`status` = 'Success', 1, 0)) AS `success`, SUM(IF(`status` = 'Failed', 1, 0)) AS `failure`, SUM(IF(`status` = 'Failed', `response_time`, 0)) AS `failure_response_time`, SUM(IF(`status` = 'Success', `response_time`, 0)) AS `success_response_time`, SUM(IF(`transaction_type` = 'Internal Service Error' AND `status` = 'Failed', 1, 0)) AS `actionable_at_ft`, SUM(IF(`transaction_type` != 'Internal Service Error' AND `status` = 'Failed', 1, 0)) AS `actionable_at_ic`, SUM(IF(`response_time` > " . $this->expected_average_response_time . ", 1, 0)) AS `requests_above_average_time`, `company`")
            ->whereIn('status', ['Failed', 'Success'])
            ->whereBetween('created_at', [Carbon::parse($from)->toDateTimeString(), Carbon::parse($to)->toDateTimeString()])
            ->where('method_name', $method_name)
            ->groupBy('company')
            ->get()->toArray();

        foreach ($quoteCountData as $qk => $qv) {
            $company = $qv['company'];
            if (!isset($this->metrics['companies'][$company])) {
                $this->metrics['companies'][$company] = [
                    'total' => 0,
                    "success" => 0,
                    "requests_above_average_time" => 0,
                    "failure" => 0,
                    "actionable_at_ic" => 0,
                    "actionable_at_ft" => 0,
                    "success_total_response_time" => 0,
                    "failure_total_response_time" => 0,
                ];
            }

            $this->metrics['companies'][$company]['success'] = (int) $qv['success'];
            $this->metrics['companies'][$company]['failure'] = (int) $qv['failure'];
            $this->metrics['companies'][$company]['total'] = $qv['total'];
            $this->metrics['companies'][$company]['actionable_at_ic'] = (int) $qv['actionable_at_ic'];
            $this->metrics['companies'][$company]['actionable_at_ft'] = (int) $qv['actionable_at_ft'];
            $this->metrics['companies'][$company]['requests_above_average_time'] = (int) $qv['requests_above_average_time'];

            // $this->metrics['companies'][$company]['total_response_time'] = (int) $qv['total_response_time'];
            $this->metrics['companies'][$company]['success_total_response_time'] = $qv['success_response_time'];
            $this->metrics['companies'][$company]['failure_total_response_time'] = $qv['failure_response_time'];

            $this->metrics['total'] += $qv['total'];
            $this->metrics['success'] += (int) $qv['success'];
            $this->metrics['failure'] += (int) $qv['failure'];
        }
    }

    private function getProposalCount($from, $to, $method_name)
    {
        WebServiceRequestResponse::query()
        // DB::table('webservice_request_response_data_temp')
            ->select('id', 'company', 'status', 'response_time', 'transaction_type')
            ->whereBetween('created_at', [Carbon::parse($from)->toDateTimeString(), Carbon::parse($to)->toDateTimeString()])
            ->whereIn('status', ['Failed', 'Success'])
            ->where('method_name', $method_name)
            ->orderBy('id')
            ->chunk(10000, function ($proposal_logs) {
                foreach ($proposal_logs as $k => $v) {
                    $this->updateMetrics($v);
                }
            });
    }

    private function getCkycCount($from, $to, &$ckycLogs)
    {
        CkycLogsRequestResponse::query()
            ->select(DB::raw('company_alias as company'), 'status', 'response_time', DB::raw('"IC" AS `transaction_type`'))
            ->whereBetween('created_at', [Carbon::parse($from)->toDateTimeString(), Carbon::parse($to)->toDateTimeString()])
            ->whereIn('status', ['Failed', 'Success'])
            ->chunk(1000, function ($ckyc_logs) {
                foreach ($ckyc_logs as $k => $v) {
                    $this->updateMetrics($v);
                }
            });
        return $ckycLogs;
    }

    protected function calculateMetrics($from, $to, $log_type, $method_name)
    {
        switch ($log_type) {
            case 'quote':
                $this->getQuoteCount($from, $to, $method_name);
                break;

            case 'proposal':
                $this->getProposalCount($from, $to, $method_name);
                break;

            case 'ckyc':
                $this->getCkycCount($from, $to, $method_name);
                break;

            default:

                break;
        }

        foreach (($this->metrics['companies'] ?? []) as $alias => $metrics) {
            $success_total_response_time = round($metrics['success_total_response_time'], 2);
            $this->metrics['companies'][$alias]['success_total_response_time'] = $success_total_response_time;
            $success_count = $metrics['success'];
            try {
                $this->metrics['companies'][$alias]['success_average_time'] = round($success_total_response_time / $success_count, 2);
            } catch (\Throwable $th) {
                $this->metrics['companies'][$alias]['success_average_time'] = 0;
            }

            $failure_total_response_time = round($metrics['failure_total_response_time'], 2);
            $this->metrics['companies'][$alias]['failure_total_response_time'] = $failure_total_response_time;
            $failure_count = $metrics['failure'];
            try {
                $this->metrics['companies'][$alias]['failure_average_time'] = round($failure_total_response_time / $failure_count, 2);
            } catch (\Throwable $th) {
                $this->metrics['companies'][$alias]['failure_average_time'] = 0;
            }

            $this->metrics['requests_above_average_time'] += $metrics['requests_above_average_time'];

            $this->metrics['success_total_response_time'] += $success_total_response_time;
            $this->metrics['failure_total_response_time'] += $failure_total_response_time;

            $this->metrics['actionable_at_ic'] += $metrics['actionable_at_ic'];
            $this->metrics['actionable_at_ft'] += $metrics['actionable_at_ft'];
        }

        $this->metrics['success_total_response_time'] = round($this->metrics['success_total_response_time'], 2);
        $this->metrics['failure_total_response_time'] = round($this->metrics['failure_total_response_time'], 2);
        try {
            $this->metrics['success_average_time'] = round($this->metrics['success_total_response_time'] / $this->metrics['success'], 2);
        } catch (\Throwable $th) {
            $this->metrics['success_average_time'] = 0;
        }

        try {
            $this->metrics['failure_average_time'] = round($this->metrics['failure_total_response_time'] / $this->metrics['failure'], 2);
        } catch (\Throwable $th) {
            $this->metrics['failure_average_time'] = 0;
        }
    }

    protected function getMetrics()
    {
        return $this->metrics;
    }

    protected function initializeMetrics()
    {
        $this->metrics = [
            'total' => 0,
            "success" => 0,
            "failure" => 0,
            "requests_above_average_time" => 0,
            "success_total_response_time" => 0,
            "failure_total_response_time" => 0,
            "success_average_time" => 0,
            "failure_average_time" => 0,
            "actionable_at_ic" => 0,
            "actionable_at_ft" => 0,
        ];
    }

    protected function convert_to_indian_timestamp(Carbon $dateObject)
    {
        return $dateObject->subHours(5)->subMinutes(30);
    }
}
