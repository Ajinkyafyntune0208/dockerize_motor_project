<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\PolicyStartandEndDateLogs;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PolicyStartAndEndDateUpdate extends Controller
{
    public function updatePolicyDates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'checksum' => 'required|in:HGFDF@#$%^&878454545#@%$#@GJG$#$%^%$#45454544544^%%#$#%$^$$4HGG@#$%^&(*&^%'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        //Main query to check policy start date or end date are empty in user proposal or not.
        $data = DB::table('cv_journey_stages as cv')
            ->join('user_proposal as up', 'up.user_product_journey_id', '=', 'cv.user_product_journey_id')
            ->join('user_product_journey as upj', 'upj.user_product_journey_id', '=', 'cv.user_product_journey_id')
            ->join('master_product_sub_type as mpst', 'mpst.product_sub_type_id', '=', 'upj.product_sub_type_id')
            ->join('quote_log as ql', 'ql.user_product_journey_id', '=', 'cv.user_product_journey_id')
            ->join('master_company as mc', 'mc.company_id', '=', 'ql.ic_id')
            ->select(
                'mc.company_alias',
                'mc.company_id as ic_id',
                'cv.user_product_journey_id',
                'mpst.product_sub_type_code as section_code',
                'ql.master_policy_id',
                'up.policy_start_date',
                'up.policy_end_date',
                'up.tp_start_date',
                'up.tp_end_date',
                DB::raw("JSON_VALUE(ql.premium_json, '$.policyType') as policy_type")
            )
            ->whereIn('cv.stage', [
                STAGE_NAMES['POLICY_ISSUED'],
                STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED']
            ])
            ->where(function ($query) {
                $query->where('upj.lead_source', '!=', 'RENEWAL_DATA_UPLOAD')
                    ->orWhereNull('upj.lead_source');
            })
            ->whereBetween('upj.created_on', [
                Carbon::parse($request->from)->startOfDay(),
                Carbon::parse($request->to)->endOfDay()
            ])
            ->where(function ($query) {
                $query->whereRaw("JSON_VALUE(ql.premium_json, '$.policyType') != 'Own Damage'")
                ->where(function ($query) {
                    $query->whereNull('up.tp_start_date')
                    ->orWhere('up.tp_start_date', '')
                    ->whereNull('up.tp_end_date')
                    ->orWhere('up.tp_end_date', '')
                    ->whereNull('up.policy_start_date')
                    ->orWhere('up.policy_start_date', '')
                    ->orWhere('up.policy_end_date', '')
                    ->orWhereNull('up.policy_end_date');
                })
                ->orWhere(function ($query) {
                    $query->whereNull('up.policy_start_date')
                    ->orWhereNull('up.policy_end_date', '')
                    ->orWhere('up.policy_start_date', '')
                    ->orWhere('up.policy_end_date', '');
                });
            })
            ->get();

        //Create data to be inserted in the new table
        $newData = $data->map(function ($item) {
            return [
                'enquiry_id' => $item->user_product_journey_id,
                'ic_id' => $item->ic_id,
                'segment' => $item->section_code,
                'proceed' => 'N',
                'comments' => 'WIP',
                'status' => 'Pending',
                'policy_type' => $item->policy_type,
                'ic_name' => $item->company_alias,
            ];
        });

        //Insert data into the table
        $newData->each(function ($item) {
            PolicyStartandEndDateLogs::updateOrCreate(
                [
                    'enquiry_id' => $item['enquiry_id'], // Condition to check for duplicates
                ],
                [
                    'ic_id' => $item['ic_id'],
                    'segment' => $item['segment'],
                    'proceed' => $item['proceed'],
                    'comments' => $item['comments'],
                    'status' => $item['status'],
                    // 'policy_id' => $item['master_policy_id'],
                    'ic_name' => $item['ic_name'],
                    'policy_type' => $item['policy_type']
                ]
            );
        });

        $returnData = [];

        //Check for logs now
        foreach ($data as $value) {

            $oldData = [
                'policy_start_date' => $value->policy_start_date,
                'policy_end_date' => $value->policy_end_date,
                'tp_start_date' => $value->tp_start_date,
                'tp_end_date' => $value->tp_end_date
            ];

            $className = "\\App\\Http\\Controllers\\PolicyDateSync\\" . ucfirst(Str::camel($value->company_alias)) . "PolicyDateSync";

            $message = 'Integration Pending';
            
            if (class_exists($className) && method_exists($className, 'syncDetails')) {
                $controller = new $className();
                $response = $controller->syncDetails($value->user_product_journey_id, $value);
                if ($response['status'] ?? false) {

                    $message = 'Logs found and details updated';

                    PolicyStartandEndDateLogs::where('enquiry_id', $value->user_product_journey_id)->update([
                        'policy_start_date' => $response['data']['start_date'],
                        'policy_end_date' => $response['data']['end_date'],
                        'proceed' => 'Y',
                        'old_data' => $oldData,
                        'status' => 'Success',
                        'comments' => $message
                    ]);

                    if ($value->policy_type == 'Own Damage') {
                        $updateData = [
                            'policy_start_date' => $response['data']['start_date'],
                            'policy_end_date' => $response['data']['end_date'],
                        ];
                    } else {
                        $updateData = [
                            'policy_start_date' => $response['data']['start_date'],
                            'policy_end_date' => $response['data']['end_date'],
                            'tp_start_date' => $response['data']['tp_start_date'],
                            'tp_end_date' => $response['data']['tp_end_date'],
                        ];
                    }

                    DB::table('user_proposal')->where('user_product_journey_id', $value->user_product_journey_id)
                    ->update($updateData);

                    $returnData[$value->user_product_journey_id] = [
                        'message' => $message,
                        'data' => [
                            'response' => $response['data'],
                            'oldData' => $oldData,
                            'companyAlias' => $value->company_alias,
                            'segment' => $value->section_code,
                            'policyType' => $value->policy_type
                        ]
                    ];

                    continue;
                }
                $message = $response['message'] ?? 'Something went wrong';
            }

            $returnData[$value->user_product_journey_id] = [
                'message' => $message,
                'data' => [
                    'response' => null,
                    'oldData' => $oldData,
                    'companyAlias' => $value->company_alias,
                    'segment' => $value->section_code,
                    'policyType' => $value->policy_type
                ]
            ];

            PolicyStartandEndDateLogs::where('enquiry_id', $value->user_product_journey_id)->update([
                'proceed' => 'N',
                'status' => 'Failure',
                'comments' => $message
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $returnData,
        ], 200);
    }
}
