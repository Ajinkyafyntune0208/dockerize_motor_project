<?php

namespace App\Http\Controllers;

use App\Models\CkycLogsRequestResponse;
use App\Models\ErrorVisibilityReport;
use App\Models\FastlaneRequestResponse;
use App\Models\QuoteServiceRequestResponse;
use App\Models\QuoteVisibilityLogs;
use App\Models\UserProposal;
use App\Models\WebServiceRequestResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ErrorVisibilityController extends Controller
{
    public function getVisibilityReport(Request $request)
    {
        DB::disableQueryLog();
        if ($request->has('show_query')) {
            DB::enableQueryLog();
        }
        $validation = Validator::make($request->all(), [
            'from' => 'required|date_format:Y-m-d\TH:i:s\Z',
            'to' => 'required|date_format:Y-m-d\TH:i:s\Z|after_or_equal:from',
            'methods' => 'nullable|array',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validation->errors(),
            ]);
        }

        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $logs = [];

        try {
            // Quote Logs
            if (in_array('revisedquote', $request->methods)) {
                // $this->getQuoteLogs($from, $to, $logs);
                $this->getRevisedQuoteLogs($request, $from, $to, $logs);
            }
            
            if (in_array('quote', $request->methods)) {
                $this->getNewQuoteLogs($request, $from, $to, $logs);
            }

            // Proposal and Policy Logs
            if (in_array('proposal', $request->methods)) {
                $this->getProposalPolicyLogs($from, $to, $logs);
            }

            // CKYC logs
            if (in_array('ckyc', $request->methods)) {
                $this->getCkycLogs($from, $to, $logs);
            }

            // Vahan logs
            if (in_array('vahan', $request->methods)) {
                $this->getVahanLogs($from, $to, $logs);
            }

            // Currently Dashboard team is not using the average response time, succesrespo
            $this->calculateAverageResponse($logs);

            $status = count($logs) > 0;
            return response()->json([
                'status' => $status,
                'message' => $status ? 'Records found' : 'Record not found',
                'companies' => $logs,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::info($e);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching the details',
                'errorMsg' => $e->getMessage() . ' File : ' . basename($e->getFile()) . ' Line No. : ' . $e->getLine(),
                'companies' => $logs,
            ]);
        }
    }

    private function getQuoteLogs($from, $to, &$quoteLogs)
    {
        $quote_methods = config('webservicemethods.quote');
        QuoteServiceRequestResponse::query()
            ->select('enquiry_id', 'company', 'method_name', 'start_time', 'end_time', 'status')
            ->whereNotNull('status')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('method_name', $quote_methods)
            ->chunk(10000, function ($quote_logs) use (&$quoteLogs) {
                foreach ($quote_logs as $k => $v) {
                    $this->prepareData('quote', $v, $quoteLogs);
                }
            });

        return $quoteLogs;
    }

    private function getExistingReport(String $transaction_type, \DateTime $from, \Datetime $to): array
    {
        return ErrorVisibilityReport::
            selectRaw("SUM(total) as `total`,
                SUM(success) as `success`,
                SUM(failure) as `failure`,
                SUM(total_response_time) as `total_response_time`,
                SUM(success_response_time) as `success_response_time`,
                SUM(failure_response_time) as `failure_response_time`,
                `company`")
            ->where('transaction_type', $transaction_type)
            ->whereBetween('report_date', [$from, $to])
            ->groupBy('company')
            ->get()
            ->toArray();
    }

    private function getNewQuoteLogs(Request $request, $from, $to, &$quoteLogs)
    {
        $is_from_and_to_is_of_today = ($from->format('d-m-Y') == now()->format('d-m-Y')) && ($to->format('d-m-Y') == now()->format('d-m-Y'));
        // We don't want to log the current date records because day is still not in complete state.
        // We only store logs only for past whole day.
        $logsExist = ($request->with_time == 1 || $is_from_and_to_is_of_today) ? [] : $this->getExistingReport('quote', $from, $to);
        if (empty($logsExist)) {
            $quote_methods = config('webservicemethods.quote');
            $quoteCountData = QuoteServiceRequestResponse::query()
                ->selectRaw("COUNT(*) AS `total`,
                 SUM(IF(`status` = 'Success', 1, 0)) AS `success`,
                 SUM(IF(`status` = 'Failed', 1, 0)) AS `failure`,
                 SUM(`response_time`) AS `total_response_time`,
                 SUM(IF(`status` = 'Failed', `response_time`, 0)) AS `failure_response_time`,
                 SUM(IF(`status` = 'Success', `response_time`, 0)) AS `success_response_time`,
                 `company`")
                ->whereIn('status', ['Failed', 'Success'])
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('company')
                ->whereIn('method_name', $quote_methods)->get()->toArray();
        } else {
            $quoteCountData = $logsExist;
        }

        if (!empty($quoteCountData)) {
            foreach ($quoteCountData as $qk => $qv) {
                // If there are no records ($logsExist) then insert count into another table
                // Enter records if date difference is not more than 1 day
                if (empty($logsExist) && (date_diff($to, $from)->d == 0) && !$is_from_and_to_is_of_today) {
                    ErrorVisibilityReport::insert([
                        'total' => (int) $qv['total'],
                        'success' => (int) $qv['success'],
                        'failure' => (int) $qv['failure'],
                        'total_response_time' => (int) $qv['total_response_time'],
                        'success_response_time' => (int) $qv['success_response_time'],
                        'failure_response_time' => (int) $qv['failure_response_time'],
                        'company' => $qv['company'],
                        'transaction_type' => 'quote',
                        'report_date' => $from,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                if (!isset($quoteLogs[$qv['company']]) || !isset($quoteLogs[$qv['company']]['methods']['quote'])) {
                    $quoteLogs[$qv['company']]['methods']['quote']['success'] = 0;
                    $quoteLogs[$qv['company']]['methods']['quote']['failure'] = 0;
                    $quoteLogs[$qv['company']]['methods']['quote']['total'] = 0;

                    $quoteLogs[$qv['company']]['methods']['quote']['total_response_time'] = 0;
                    $quoteLogs[$qv['company']]['methods']['quote']['success_response_time'] = 0;
                    $quoteLogs[$qv['company']]['methods']['quote']['failure_response_time'] = 0;
                }

                $quoteLogs[$qv['company']]['methods']['quote']['success'] = (int) $qv['success'];
                $quoteLogs[$qv['company']]['methods']['quote']['failure'] = (int) $qv['failure'];
                $quoteLogs[$qv['company']]['methods']['quote']['total'] = (int) $qv['total'];

                $quoteLogs[$qv['company']]['methods']['quote']['total_response_time'] = (int) $qv['total_response_time'];
                $quoteLogs[$qv['company']]['methods']['quote']['success_response_time'] = (int) $qv['success_response_time'];
                $quoteLogs[$qv['company']]['methods']['quote']['failure_response_time'] = (int) $qv['failure_response_time'];
            }
        }
        return $quoteLogs;
    }

    private function getRevisedQuoteLogs(Request $request, $from, $to, &$quoteLogs)
    {
        $is_from_and_to_is_of_today = ($from->format('d-m-Y') == now()->format('d-m-Y')) && ($to->format('d-m-Y') == now()->format('d-m-Y'));
        // We don't want to log the current date records because day is still not in complete state.
        // We only store logs only for past whole day.
        $logsExist = ($request->with_time == 1 || $is_from_and_to_is_of_today) ? [] : $this->getExistingReport('revisedquote', $from, $to);
        if (empty($logsExist)) {
            $quote_methods = config('webservicemethods.quote');
            $quoteCountData = QuoteVisibilityLogs::query()
                ->selectRaw("COUNT(*) AS `total`,
                 SUM(IF(`status` = 'Success', 1, 0)) AS `success`,
                 SUM(IF(`status` = 'Failed', 1, 0)) AS `failure`,
                 SUM(`response_time`) AS `total_response_time`,
                 SUM(IF(`status` = 'Failed', `response_time`, 0)) AS `failure_response_time`,
                 SUM(IF(`status` = 'Success', `response_time`, 0)) AS `success_response_time`,
                 `company`")
                ->whereIn('status', ['Failed', 'Success'])
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('company')
                ->whereIn('method_name', $quote_methods)->get()->toArray();
        } else {
            $quoteCountData = $logsExist;
        }

        if (!empty($quoteCountData)) {
            foreach ($quoteCountData as $qk => $qv) {
                // If there are no records ($logsExist) then insert count into another table
                // Enter records if date difference is not more than 1 day
                if (empty($logsExist) && (date_diff($to, $from)->d == 0) && !$is_from_and_to_is_of_today) {
                    ErrorVisibilityReport::insert([
                        'total' => (int) $qv['total'],
                        'success' => (int) $qv['success'],
                        'failure' => (int) $qv['failure'],
                        'total_response_time' => (int) $qv['total_response_time'],
                        'success_response_time' => (int) $qv['success_response_time'],
                        'failure_response_time' => (int) $qv['failure_response_time'],
                        'company' => $qv['company'],
                        'transaction_type' => 'revisedquote',
                        'report_date' => $from,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                if (!isset($quoteLogs[$qv['company']]) || !isset($quoteLogs[$qv['company']]['methods']['quote'])) {
                    $quoteLogs[$qv['company']]['methods']['quote']['success'] = 0;
                    $quoteLogs[$qv['company']]['methods']['quote']['failure'] = 0;
                    $quoteLogs[$qv['company']]['methods']['quote']['total'] = 0;

                    $quoteLogs[$qv['company']]['methods']['quote']['total_response_time'] = 0;
                    $quoteLogs[$qv['company']]['methods']['quote']['success_response_time'] = 0;
                    $quoteLogs[$qv['company']]['methods']['quote']['failure_response_time'] = 0;
                }

                $quoteLogs[$qv['company']]['methods']['quote']['success'] = (int) $qv['success'];
                $quoteLogs[$qv['company']]['methods']['quote']['failure'] = (int) $qv['failure'];
                $quoteLogs[$qv['company']]['methods']['quote']['total'] = (int) $qv['total'];

                $quoteLogs[$qv['company']]['methods']['quote']['total_response_time'] = (int) $qv['total_response_time'];
                $quoteLogs[$qv['company']]['methods']['quote']['success_response_time'] = (int) $qv['success_response_time'];
                $quoteLogs[$qv['company']]['methods']['quote']['failure_response_time'] = (int) $qv['failure_response_time'];
            }
        }
        return $quoteLogs;
    }

    private function getProposalPolicyLogs($from, $to, &$proposalPolicyLogs)
    {
        $proposal_methods = array_values(config('webservicemethods.proposal'));
        // $policy_methods = array_values(config('webservicemethods.policy'));

        WebServiceRequestResponse::query()
            ->select('enquiry_id', 'company', 'method_name', 'start_time', 'end_time', 'status')
            ->whereBetween('created_at', [$from, $to])
        // Commenting below line as currently indexing is not done
        // Also "status is not null" condition is added which will satisfy the requirement
        //->where('transaction_type', 'proposal')
            ->whereIn('status', ['Failed', 'Success']) // Query was taking time, adding this quick fix resolved the issue
            ->whereIn('method_name', $proposal_methods)
            ->chunk(10000, function ($proposal_logs) use (&$proposalPolicyLogs) {
                foreach ($proposal_logs as $k => $v) {
                    $this->prepareData('proposal', $v, $proposalPolicyLogs);
                }
            });
        return $proposalPolicyLogs;
    }

    private function getCkycLogs($from, $to, &$ckycLogs)
    {
        CkycLogsRequestResponse::query()
            ->select('enquiry_id', DB::raw('company_alias as company'), 'start_time', 'end_time', 'status')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['Failed', 'Success'])
            ->chunk(10000, function ($ckyc_logs) use (&$ckycLogs) {
                foreach ($ckyc_logs as $k => $v) {
                    $this->prepareData('ckyc', $v, $ckycLogs);
                }
            });
        return $ckycLogs;
    }

    private function getVahanLogs($from, $to, &$quoteVahanLogs)
    {
        $quoteCountData = FastlaneRequestResponse::query()
            ->selectRaw("COUNT(*) AS `total`,
                SUM(IF(`status` = 'Success', 1, 0)) AS `success`,
                SUM(IF(`status` = 'Failed', 1, 0)) AS `failure`,
                SUM(TIME_TO_SEC(`response_time`)) AS `total_response_time`,
                SUM(IF(`status` = 'Failed', TIME_TO_SEC(`response_time`), 0)) AS `failure_response_time`,
                SUM(IF(`status` = 'Success', TIME_TO_SEC(`response_time`), 0)) AS `success_response_time`,
                `transaction_type`as company")
            ->whereIn('status', ['Failed', 'Success'])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('transaction_type')->get()->toArray();

        if (!empty($quoteCountData)) {
            foreach ($quoteCountData as $qk => $qv) {
                if (!isset($quoteVahanLogs[$qv['company']]) || !isset($quoteVahanLogs[$qv['company']]['methods']['vahan'])) {
                    $quoteVahanLogs[$qv['company']]['methods']['vahan']['success'] = 0;
                    $quoteVahanLogs[$qv['company']]['methods']['vahan']['failure'] = 0;
                    $quoteVahanLogs[$qv['company']]['methods']['vahan']['total'] = 0;

                    $quoteVahanLogs[$qv['company']]['methods']['vahan']['total_response_time'] = 0;
                    $quoteVahanLogs[$qv['company']]['methods']['vahan']['success_response_time'] = 0;
                    $quoteVahanLogs[$qv['company']]['methods']['vahan']['failure_response_time'] = 0;
                }

                $quoteVahanLogs[$qv['company']]['methods']['vahan']['success'] = (int) $qv['success'];
                $quoteVahanLogs[$qv['company']]['methods']['vahan']['failure'] = (int) $qv['failure'];
                $quoteVahanLogs[$qv['company']]['methods']['vahan']['total'] = (int) $qv['total'];

                $quoteVahanLogs[$qv['company']]['methods']['vahan']['total_response_time'] = (int) $qv['total_response_time'];
                $quoteVahanLogs[$qv['company']]['methods']['vahan']['success_response_time'] = (int) $qv['success_response_time'];
                $quoteVahanLogs[$qv['company']]['methods']['vahan']['failure_response_time'] = (int) $qv['failure_response_time'];
            }
        }

        return $quoteVahanLogs;
    }

    private function prepareData($methodName, $objValue, &$data)
    {
        // Initialize all the key tags
        if (!isset($data[$objValue->company]) || !isset($data[$objValue->company]['methods'][$methodName])) {
            $data[$objValue->company]['methods'][$methodName]['success'] = 0;
            $data[$objValue->company]['methods'][$methodName]['failure'] = 0;
            $data[$objValue->company]['methods'][$methodName]['total'] = 0;
            $data[$objValue->company]['methods'][$methodName]['success_response_time'] = 0;
            $data[$objValue->company]['methods'][$methodName]['failure_response_time'] = 0;
            $data[$objValue->company]['methods'][$methodName]['total_response_time'] = 0;
            // $data[$objValue->company]['all_methods']['success'] = 0;
            // $data[$objValue->company]['all_methods']['failure'] = 0;
            // $data[$objValue->company]['all_methods']['total'] = 0;
            // $data[$objValue->company]['all_methods']['total_response_time'] = 0;
            // $data[$objValue->company]['all_methods']['success_response_time'] = 0;
            // $data[$objValue->company]['all_methods']['failure_response_time'] = 0;
        }
        // Check the status
        $status = $objValue->status == 'Success' ? 'success' : 'failure';

        // Store the status count
        $data[$objValue->company]['methods'][$methodName][$status]++;

        // Calculate response time
        $start_time = Carbon::parse($objValue->start_time);
        $end_time = Carbon::parse($objValue->end_time);
        $response_time = $end_time->diffInSeconds($start_time);
        $data[$objValue->company]['methods'][$methodName][$status . '_response_time'] += $response_time;

        $data[$objValue->company]['methods'][$methodName]['total']++;
        $data[$objValue->company]['methods'][$methodName]['total_response_time'] += $response_time;
    }

    protected function calculateAverageResponse(&$companies)
    {
        foreach ($companies as $company => $value) {

            foreach ($value['methods'] as $type => $data) {
                try {
                    $companies[$company]['methods'][$type]['average_response_time'] = round(($data['total_response_time'] / $data['total']), 2);
                } catch (\DivisionByZeroError $th) {
                    $companies[$company]['methods'][$type]['average_response_time'] = 0;
                }

                try {
                    $companies[$company]['methods'][$type]['success_average_response_time'] = round(($data['success_response_time'] / $data['success']), 2);
                } catch (\DivisionByZeroError $th) {
                    $companies[$company]['methods'][$type]['success_average_response_time'] = 0;
                }

                try {
                    $companies[$company]['methods'][$type]['failure_average_response_time'] = round(($data['failure_response_time'] / $data['failure']), 2);
                } catch (\DivisionByZeroError $th) {
                    $companies[$company]['methods'][$type]['failure_average_response_time'] = 0;
                }

                // $companies[$company]['all_methods']['success'] += $data['success'];
                // $companies[$company]['all_methods']['failure'] += $data['failure'];
                // $companies[$company]['all_methods']['total'] += $data['total'];
                // $companies[$company]['all_methods']['total_response_time'] += $companies[$company]['methods'][$type]['total_response_time'];
                unset($companies[$company]['methods'][$type]['total_response_time']);

                // $companies[$company]['all_methods']['success_response_time'] += $companies[$company]['methods'][$type]['success_response_time'];
                unset($companies[$company]['methods'][$type]['success_response_time']);

                // $companies[$company]['all_methods']['failure_response_time'] += $companies[$company]['methods'][$type]['failure_response_time'];
                unset($companies[$company]['methods'][$type]['failure_response_time']);
            }

            /*
        try {
        $companies[$company]['all_methods']['average_response_time'] = round(($companies[$company]['all_methods']['total_response_time'] / $companies[$company]['all_methods']['total']), 2);
        } catch (\DivisionByZeroError $th) {
        $companies[$company]['all_methods']['average_response_time'] = 0;
        }
        unset($companies[$company]['all_methods']['total_response_time']);

        try {
        $companies[$company]['all_methods']['success_average_response_time'] = round(($companies[$company]['all_methods']['success_response_time'] / $companies[$company]['all_methods']['success']), 2);
        } catch (\DivisionByZeroError $th) {
        $companies[$company]['all_methods']['success_average_response_time'] = 0;
        }
        unset($companies[$company]['all_methods']['success_response_time']);

        try {
        $companies[$company]['all_methods']['failure_average_response_time'] = round(($companies[$company]['all_methods']['failure_response_time'] / $companies[$company]['all_methods']['failure']), 2);
        } catch (\DivisionByZeroError $th) {
        $companies[$company]['all_methods']['failure_average_response_time'] = 0;
        }
        unset($companies[$company]['all_methods']['failure_response_time']);
         */
        }
    }

    public function getVisibilityReportCount(Request $request)
    {
        $dummyResponse = [
            "data" => [
                "success" => 123123,
                "success_average_time" => 12,
                "failure" => 123123,
                "failure_average_time" => 10,
                "requests_above_expected_average_time" => 564,
                "companies" => [
                    "icici_lombard" => [
                        "success" => 123123,
                        "success_average_time" => 12,
                        "requests_above_average_time" => 2377,
                        "failure" => 123123,
                        "failure_average_time" => 10,
                        "actionable_at_ic" => 47,
                        "actionable_at_ft" => 50,
                    ],
                    "go_digit" => [
                        "success" => 123123,
                        "success_average_time" => 12,
                        "failure" => 123123,
                        "requests_above_expected_average_time" => 7623,
                        "failure_average_time" => 10,
                        "actionable_at_ic" => 47,
                        "actionable_at_ft" => 50,
                    ],
                ],
            ],
            "message" => "Data Fetched Successfully",
        ];

        return $dummyResponse;
    }

    public function getCkycCountSummary(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'from' => 'required|date_format:Y-m-d\TH:i:s\Z',
            'to' => 'required|date_format:Y-m-d\TH:i:s\Z|after_or_equal:from',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validation->errors(),
            ]);
        }

        $from = Carbon::parse($request->from, 'Asia/Calcutta');
        // $from = date('Y-m-d H:i:s', strtotime($request->from));
        // $to = date('Y-m-d H:i:s', strtotime($request->to));
        $to = Carbon::parse($request->to, 'Asia/Calcutta');

        $summary = CkycLogsRequestResponse::query()
            ->selectRaw("DENSE_RANK() OVER(PARTITION BY `enquiry_id` ORDER BY `id` DESC) AS `rank`, `enquiry_id`, `id`, `created_at`, `status`")
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('enquiry_id')
            ->orderBy('id')
            ->get();

        $unique_failed = $unique_success = collect();
        $result['total_records'] = $summary->count();
        $result['unique_records'] = $summary->unique('enquiry_id')->count();
        $result['unique_success_records'] = $result['unique_failure_records'] = 0;
        $result['failure_success_with_other_rc'] = 0;
        $summary->each(function ($value, $key) use (&$result, &$unique_failed, &$unique_success) {
            if ($value->rank == 1 && $value->status == 'Success') {
                $result['unique_success_records']++;
                $unique_success->push($value->enquiry_id);
            } else if ($value->rank == 1 && $value->status == 'Failed') {
                $result['unique_failure_records']++;
                $unique_failed->push($value->enquiry_id);
            }
        });
        return $result;

        // Check failed (unique) cases if the ckyc is success for other trace IDs on the basis of RC number
        if (!$from->isToday() && !$to->isToday() && $unique_failed->isNotEmpty()) {
            $result['failure_success_with_other_rc'] = 0;
            // Fetch RC numbers of failed cases
            $failed_cases_rc_number = UserProposal::select('user_product_journey_id', 'vehicale_registration_number')
                ->whereIn('user_product_journey_id', $unique_failed->all())
                ->whereNotNull('vehicale_registration_number')
                ->where('vehicale_registration_number', '!=', 'NEW')
                ->get();
            if ($failed_cases_rc_number->isNotEmpty()) {
                $count_of_failure_success_cases =
                DB::table('user_product_journey AS j')
                    ->selectRaw('COUNT(*) as `total_cases`, CONCAT(DATE_FORMAT(j.created_on, "%Y%m%d"), LPAD(j.user_product_journey_id, 8, 0)) AS `Trace ID`, p.vehicale_registration_number')
                    ->join('ckyc_logs_request_responses AS c', 'c.enquiry_id', '=', 'j.user_product_journey_id')
                    ->join('user_proposal AS p', 'p.user_product_journey_id', '=', 'j.user_product_journey_id')
                    ->whereIn('c.enquiry_id', $failed_cases_rc_number->pluck('user_product_journey_id'))
                    ->whereBetween('c.created_at', [$from->startOfDay()->toDateTimeString(), now()->endOfDay()->toDateTimeString()])
                    ->where('c.status', 'Success')
                    ->groupBy('c.enquiry_id')
                    ->orderBy('c.enquiry_id')
                    ->get();

                dd($count_of_failure_success_cases);
            }
        }

        return $result;
    }
}
