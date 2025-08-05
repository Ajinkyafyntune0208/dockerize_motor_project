<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokerDetail;
use App\Models\EmbeddedScrubData;
use App\Models\MasterProductSubType;
use Illuminate\Http\Request;

class EmbeddedScrubController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('report.list'))
        {
            return abort(403, 'Unauthorized action.');
        }

        $data = $reports = [];

        if (!is_null($request->from) && !is_null($request->to))
        {
            $data = EmbeddedScrubData::where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($request->from)))
                ->where('created_at', '<', date('Y-m-d 23:59:59', strtotime($request->to)))
                ->get();

            if ($data)
            {
                $batch_ids = [];

                foreach ($data as $value)
                {
                    if ( ! in_array($value['batch_id'], $batch_ids))
                    {
                        array_push($batch_ids, $value['batch_id']);

                        $reports[$value['batch_id']] = [
                            'processed' => 0,
                            'processing' => 0,
                            'success' => 0,
                            'failure' => 0,
                            'pending' => 0,
                            'total' => 0
                        ];
                    }

                    if ($value['response'] != NULL && $value['is_processed'] == 1) {
                        $reports[$value['batch_id']]['processed']++;
                        $reports[$value['batch_id']]['success']++;
                    } elseif ($value['is_processed'] == 2) {
                        if ($value['attempts'] >= 3) {
                            $reports[$value['batch_id']]['processed']++;
                            $reports[$value['batch_id']]['failure']++;
                        } else {
                            $reports[$value['batch_id']]['processing']++;
                        }
                    } else {
                        if ($value['attempts'] >= 3) {
                            $reports[$value['batch_id']]['processed']++;
                            $reports[$value['batch_id']]['failure']++;
                        } else {
                            $reports[$value['batch_id']]['pending']++;
                        }
                    }

                    $reports[$value['batch_id']]['total']++;
                }
            }
        }

        $master_product_sub_types = MasterProductSubType::all();
        $borker_details = BrokerDetail::all();

        return view('embedded-scrub', [
            'reports' => !empty($reports) ?  $reports : [],
            'master_product_sub_types' => $master_product_sub_types,
            'borker_details' => $borker_details
        ]);
    }

    public function getEmbeddedScrubExcel(Request $request)
    {
        if (!auth()->user()->can('report.list'))
        {
            return abort(403, 'Unauthorized action.');
        }

        if ( ! is_null($request->from) && ! is_null($request->to) && ! is_null($request->batch_id))
        {
            $data = EmbeddedScrubData::where('created_at', '>=', date('Y-m-d 00:00:00', strtotime($request->from)))
                ->where('created_at', '<', date('Y-m-d 23:59:59', strtotime($request->to)))
                ->where('batch_id', '=', $request->batch_id)
                ->where('is_processed', '=', 1)
                ->get();

            if ($data)
            {
                $excel_data = [
                    [
                        'content' => [
                            'VAHAN Response' => [
                                'RC No.',
                                'Expiry Date',
                                'Make',
                                'Model',
                                'Varient',
                                'Vehicle Owner Name',
                                'OnGrid Success'
                            ],
                            '3 Month Policy' => [
                                'IC Name',
                                'Premium (Excl. GST)',
                                'Policy Type',
                                'Enquiry ID',
                                'Proposal Link',
                                'Quote PDF',
                                'CPA Premium (Excl. GST)'
                            ],
                            '6 Month Policy' => [
                                'IC Name',
                                'Premium (Excl. GST)',
                                'Policy Type',
                                'Enquiry ID',
                                'Proposal Link',
                                'Quote PDF',
                                'CPA Premium (Excl. GST)'
                            ],
                            '12 Month Policy' => [
                                'IC Name',
                                'Premium (Excl. GST)',
                                'Policy Type',
                                'Enquiry ID',
                                'Proposal Link',
                                'Quote PDF',
                                'CPA Premium (Excl. GST)'
                            ],
                            'Ongrid Failure Data' => [
                                'Enquiry ID',
                                'Proposal Link'
                            ]
                        ],
                        'additional_data' => [
                            'is_header' => TRUE
                        ]
                    ]
                ];

                foreach ($data as $key => $value) {
                    $response = $value['response'];

                    if ( ! empty($response) && isset($response['status']) && $response['status'])
                    {
                        $excel_data[] = [
                            'content' => [
                                'VAHAN Response' => [
                                    $response['data']['vahan_response']['rc_number'] ?? '',
                                    $response['data']['vahan_response']['expiry_date'] ?? '',
                                    $response['data']['vahan_response']['make'] ?? '',
                                    $response['data']['vahan_response']['model'] ?? '',
                                    $response['data']['vahan_response']['varient'] ?? '',
                                    $response['data']['vahan_response']['vehicle_owner_name'] ?? '',
                                    $response['data']['vahan_response']['ongrid_success'] ?? ''
                                ],
                                '3 Month Policy' => isset($response['data']['3_months_policy']) ? [
                                    $response['data']['3_months_policy']['ic_name'] ?? '',
                                    $response['data']['3_months_policy']['net_premium'] ?? '',
                                    $response['data']['3_months_policy']['policy_type'] ?? '',
                                    isset($response['data']['3_months_policy']['enquiry_id']) ? "'" . $response['data']['3_months_policy']['enquiry_id'] : '', // ' added for enquiry id as Excel considering enquiry id as an integer and rounding off
                                    $response['data']['3_months_policy']['proposal_link'] ?? '',
                                    $response['data']['3_months_policy']['quote_pdf'] ?? '',
                                    $response['data']['3_months_policy']['cpa_premium'] ?? ''
                                ] : NULL,
                                '6 Month Policy' => isset($response['data']['6_months_policy']) ? [
                                    $response['data']['6_months_policy']['ic_name'] ?? '',
                                    $response['data']['6_months_policy']['net_premium'] ?? '',
                                    $response['data']['6_months_policy']['policy_type'] ?? '',
                                    isset($response['data']['6_months_policy']['enquiry_id']) ? "'" . $response['data']['6_months_policy']['enquiry_id'] : '',
                                    $response['data']['6_months_policy']['proposal_link'] ?? '',
                                    $response['data']['6_months_policy']['quote_pdf'] ?? '',
                                    $response['data']['6_months_policy']['cpa_premium'] ?? ''
                                ] : NULL,
                                '12 Month Policy' => isset($response['data']['12_months_policy']) ? [
                                    $response['data']['12_months_policy']['ic_name'] ?? '',
                                    $response['data']['12_months_policy']['net_premium'] ?? '',
                                    $response['data']['12_months_policy']['policy_type'] ?? '',
                                    isset($response['data']['12_months_policy']['enquiry_id']) ? "'" . $response['data']['12_months_policy']['enquiry_id'] : '',
                                    $response['data']['12_months_policy']['proposal_link'] ?? '',
                                    $response['data']['12_months_policy']['quote_pdf'] ?? '',
                                    $response['data']['12_months_policy']['cpa_premium'] ?? ''
                                ] : NULL,
                                'ongrid_failure_data' => isset($response['data']['ongrid_failure_data']) ? [
                                    isset($response['data']['ongrid_failure_data']['enquiry_id']) ? "'" . $response['data']['ongrid_failure_data']['enquiry_id'] : '',
                                    $response['data']['ongrid_failure_data']['proposal_link'] ?? ''
                                ] : NULL
                            ]
                        ];
                    }
                }

                return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\EmbeddedScrubDataExports($excel_data), now() . ' Embedded Scrub Report.xls');
            }

            return;
        }
    }
}
