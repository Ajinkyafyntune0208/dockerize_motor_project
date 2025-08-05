<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CommonController;
use App\Http\Controllers\Controller;
use App\Models\BrokerDetail;
use App\Models\MasterCompany;
use App\Models\MasterProductSubType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
include_once app_path() . '/Helpers/PersonalDataEncryptionHelper.php';


class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!auth()->user()->can('report.list')) {
            return abort(403, 'Unauthorized action.');
        }
        $data = [];
        if (!is_null($request->from) && !is_null($request->to)) {
            $url = is_null($request->broker_url) ? url('/api/proposalReports') : $request->broker_url . '/api/proposalReports';

            if(empty($request->broker_url)){
                if (config('ENABLE_NEW_POLICY_REPORT_LOGIC') == 'Y') {
                    $data = $this->getReportsData($request);
                    $excel_data = $data['excelData'];
                } else {
                    $request->merge(['skip_secret_token' => true]);
                    $data = \App\Http\Controllers\ProposalReportController::proposalReports($request)->getContent();
                    $data = json_decode($data, true);
                }
            }else{
                $data = httpRequestNormal($url, 'POST', $request->all(), [], [
                    'accept' => 'application/json'
                ], [], false)['response'];
            }

            if (isset($data['data']) && empty($data['data'])) {
                return redirect()->route('admin.report.index')->withInput();
            }
            if ($request->view == 'excel') {
                if (!isset($excel_data)) {
                    $excel_data[] = [
                        'Payment Date',
                        'Proposal Date',
                        'Enquiry Id',
                        //'Proposal Name',
                        //'Proposal Phone',
                        //'Proposal Email',
                        'Vehicle Registration No',
                        //'Vehicle Manufacture',
                        //'Policy Start Date',
                        //'Policy End Date',
                        'Policy No',
                        //'Insurance Company',
                        // 'Engine No',
                        // 'Chassis No',
                        'Business Type',
                        'Section',
                        'Premium Amount',
                    ];

                    if(!empty($data['data'])){
                        foreach ($data['data'] as $key => $value) {
                            $excel_data[] = [
                                $value['payment_time'],
                                $value['proposal_date'],
                                "'"  .$value['trace_id'],
                                //$value['proposer_name'],
                               // $value['proposer_mobile'],
                                //$value['proposer_emailid'],
                                $value['vehicle_registration_number'],
                               // $value['vehicle_make'] . ' ' . $value['vehicle_model'] . ' ' . $value['vehicle_version'],
                                //$value['policy_start_date'],
                                //$value['policy_end_date'],
                                $value['policy_no'],
                                //$value['company_name'],
                                //$value['engine_number'],
                                //$value['chassis_number'],
                                $value['business_type'],
                                $value['section'],
                                $value['premium_amount'],
                            ];
                        }
                    }
                }
                return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($excel_data), now() . ' Policy Report.xls');
            }
        }

        $filterMasterCompany = $request->input("master_company");
        $master_product_sub_types = MasterProductSubType::all();
        $motorLocalCompanies = MasterCompany::all();
        $borker_details = BrokerDetail::all();
        return view('report.index', [
            'reports' => !empty($data['data']) ?  $data['data'] : [],
            'master_product_sub_types' => $master_product_sub_types,
            'borker_details' => $borker_details,
            'motorLocalCompanies' => $motorLocalCompanies,
        ]);
    }

    public function getReportsData(Request $request)
    {

        $filterMasterCompany = $request->input("master_company");

        $motorLocalCompanies = MasterCompany::all();
        $isEncrypted = config('enquiry_id_encryption') == 'Y';
        $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'seller_type' => ['nullable', 'in:E,P,U,Partner,b2c'],
            'transaction_stage' => ['nullable', 'array'],
            'product_type' => ['nullable'],
            'enquiry_id' => ['nullable'],
            'policy_no' => ['nullable'],
        ]);

        $excelData[] = [
            'Payment Date',
            'Proposal Date',
            'Enquiry Id',
            'Proposal Name',
            'Proposal Phone',
            'Proposal Email',
            'Vehicle Registration No',
            'Vehicle Manufacture',
            'Policy Start Date',
            'Policy End Date',
            'Policy No',
            'Insurance Company',
            'Engine No',
            'Chassis No',
            'Business Type',
            'Section',
            'Premium Amount',
        ];
        $result = collect();
        if ($isEncrypted) {
            $selectStatement = "up.user_product_journey_id";
        } else {
            $selectStatement = "CONCAT(DATE_FORMAT(up.created_on,'%Y%m%d'),LPAD(up.user_product_journey_id,8,0))";
        }
        $selectStatement .= " AS `trace_id`,
        ql.premium_json,
        pro.vehicale_registration_number as pro_vehicale_registration_number,
        cvr.vehicle_registration_no as cvr_vehicle_registration_no,
    pro.ic_name AS company_name,
    pro.created_date AS sales_date,
    js.stage AS transaction_stage,
    pro.final_payable_amount as pro_final_payable_amount,
    ql.final_premium_amount as ql_final_premium_amount,
    pol.pdf_url AS policy_doc_path,
    pr.created_at AS payment_time,
    pro.proposal_date AS proposal_date,
    pro.first_name,
    pro.last_name,
    pro.mobile_number as pro_mobile_number,
    up.user_mobile as up_user_mobile,
    ql.quote_data,
    pro.email as pro_email,
    up.user_email as up_user_email,
    pro.policy_start_date AS policy_start_date,
    pro.policy_end_date as policy_end_date,
    pol.policy_number as pol_policy_number,
    pro.engine_number AS engine_number,
    pro.chassis_number AS chassis_number,
    pro.business_type as pro_business_type,
    cvr.business_type as cvr_business_type,
    pst.product_sub_type_code AS section,
    pro.proposal_no AS proposal_no";
        DB::table('user_product_journey as up')
        ->leftJoin('cv_journey_stages as js', 'js.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('payment_request_response as pr', 'pr.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('corporate_vehicles_quotes_request as cvr', 'cvr.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('cv_agent_mappings as am', 'am.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->join('master_product_sub_type as pst', 'cvr.product_id', '=', 'pst.product_sub_type_id')
        ->leftJoin('user_proposal as pro', 'pro.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->leftJoin('policy_details as pol', 'pol.proposal_id', '=', 'pro.user_proposal_id')
        ->leftJoin('quote_log as ql', 'ql.user_product_journey_id', '=', 'up.user_product_journey_id')
        ->whereBetween(
            'js.updated_at',
            [Carbon::parse($request->from)->startOfDay(), Carbon::parse($request->to)->endOfDay()]
        )
            ->when(!empty($request->master_company), function ($query) use ($filterMasterCompany) {
                $query->whereIn('ql.ic_id', $filterMasterCompany);
            })
            ->when(!empty($request->transaction_stage), function ($query) use ($request) {
                $query->whereIn('js.stage', $request->transaction_stage);
            })
            ->when(!empty($request->product_type), function ($query) use ($request) {
                $subTypeId = MasterProductSubType::whereIn('parent_product_sub_type_id', $request->product_type)->get()->pluck('product_sub_type_id')->toArray();
                $query->whereIn('cvr.product_id', $subTypeId);
            })
            ->when(!empty($request->seller_type), function ($query) use ($request) {
                if ($request->seller_type == 'b2c') {
                    $query->whereNull('am.seller_type')->whereNotNull('user_id');
                } else {
                    $query->where('am.seller_type', $request->seller_type);
                }
            })
            ->selectRaw($selectStatement)
        ->orderBy('pro.user_product_journey_id')
            ->chunk(2000, function($chunkResults) use(&$result, &$request, &$excelData, $isEncrypted) {
                $chunkResults = $chunkResults->map(function ($item) use($request,&$excelData, $isEncrypted) {
                    if ($isEncrypted) {
                        $item->trace_id = customEncrypt($item->trace_id);
                    }
                    $item =  self::userProposalCasts($item);
                    $item->vehicle_registration_number = !empty($item->pro_vehicale_registration_number) ? $item->pro_vehicale_registration_number : $item->cvr_vehicle_registration_no;
                    $item->premium_amount = !empty($item->pro_final_payable_amount) ? $item->pro_final_payable_amount : $item->premium_amount = $item->ql_final_premium_amount;
                    $item->proposer_name = $item->first_name.' '.$item->last_name;
                    $item->proposer_mobile = !empty($item->pro_mobile_number) ? $item->pro_mobile_number : $item->up_user_mobile;
                    $premiumJson = json_decode($item->premium_json);
                    $quoteData = json_decode($item->quote_data);
                    $item->product_name = $premiumJson->productName ?? null;
                    $item->policy_doc_path = file_url($item->policy_doc_path);
                    $item->business_type = !empty($item->pro_business_type) ? $item->pro_business_type : $item->cvr_business_type;
                    $item->policy_no = !empty($item->pol_policy_number) ? $item->pol_policy_number : '';
                    $item->vehicle_version = !empty($premiumJson->mmvDetail->versionName ?? '') ?
                    $premiumJson->mmvDetail->versionName : ($quoteData->version_name ?? '');
                    $item->proposer_emailid = !empty($item->pro_email) ? $item->pro_email : $item->up_user_email;
                    $item->vehicle_make = isset($premiumJson->mmvDetail->manfName) && !empty($premiumJson->mmvDetail->manfName) ? $premiumJson->mmvDetail->manfName : ($quoteData->manfacture_name ?? '');
                    $item->vehicle_model = isset($premiumJson->mmvDetail->modelName) && !empty($premiumJson->mmvDetail->modelName) ? $premiumJson->mmvDetail->modelName : ($quoteData->model_name ?? '');

                    if ($request->view == 'excel') {

                        $excelData[] = [
                            $item->payment_time,
                            $item->proposal_date,
                            "'"  .$item->trace_id,
                            $item->proposer_name,
                            $item->proposer_mobile,
                            $item->proposer_emailid,
                            $item->vehicle_registration_number,
                            $item->vehicle_make . ' ' . $item->vehicle_model . ' ' . $item->vehicle_version,
                            $item->policy_start_date,
                            $item->policy_end_date,
                            $item->policy_no,
                            $item->company_name,
                            $item->engine_number,
                            $item->chassis_number,
                            $item->business_type,
                            $item->section,
                            $item->premium_amount,
                        ];
                    }


                    unset($item->premium_json);
                    unset($item->cvr_vehicle_registration_no);
                    unset($item->pro_vehicale_registration_number);
                    unset($item->ql_final_premium_amount);
                    unset($item->pro_final_payable_amount);
                    unset($item->first_name);
                    unset($item->last_name);
                    unset($item->up_user_mobile);
                    unset($item->pro_mobile_number);
                    unset($item->cvr_business_type);
                    unset($item->pro_business_type);
                    unset($item->quote_data);
                    unset($item->pol_policy_number);
                    unset($item->pro_email);
                    unset($item->up_user_email);
                    return $item;
                });
                $result = $result->concat($chunkResults);
            });
        $result = $result->toArray();
        return ['data' => json_decode(json_encode($result), true), 'excelData' => $excelData];

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if (!auth()->user()->can('report_policy_upload.edit')) {
            return abort(403, 'Unauthorized action.');
        }
        try {
            $request->enquiryId = $request->enquiry_id;
            $requestData = [
                "enquiryId" => $request->enquiryId,
                "pdf_file" => base64_encode($request->file('policy_pdf')->getContent()),
                "policy_no" => $request->policy_no,
                "admin_id" => auth()->id(),
                "admin_name" => auth()->user()->name,
            ];
            
            $request = new Request($requestData);
            $common_controller = new CommonController();
            
            $response = $common_controller->policyPdfUpload($request);
            
         $response=json_decode($response->getContent());

        if ($response->status) {
             return redirect()->route('admin.report.index')->with([
                'status' => 'Policy uploaded Sucessfully with Policy No ' . $request->policy_no . '..!',
                'class' => 'success',
            ]);

        } else {
            return redirect()->back()->with([
                'status' => 'Failed to upload policy' . $response->msg . '...!',
                'class' => 'danger',
            ]);
        }
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public static function userProposalCasts($item)
    {
        $listKeys = [
            'first_name',
            'last_name',
            'pro_mobile_number',
            'up_user_mobile',
            'pro_email',
        ];
        foreach ($item as $key => $value) {
            if (in_array($key, $listKeys)) {
                $item->{$key} = decryptData($value);
            }
        }

        return $item;
    }
}


/*
$response = httpRequestNormal('https://apimotor.renewbuy.com/api/proposalReports', 'POST', [
    "from" => "2022-06-07",
    "to" => "2022-06-11",
    "transaction_stage" => [
        STAGE_NAMES['POLICY_ISSUED']
    ]
]);
if ($response['status'] == 200) {
    $records = $response['response']['data'];
    $headings = [
        "Enquiry ID",
        "POS ID ",
        "Policy Number",
        "Policy Type",
        "Insurer Name",
        "Business Type",
        "Product Type",
        "Make",
        "Model",
        "Variant",
        "Proposal ID",
        "Payment ID",
        "Payment Date and Time",
        "Payment Status",
        "Total Premium Payable",
        "Vehicle Reg Number",
    ];
    $data[] = $headings;
    foreach ($records as $key => $value) {
        $data[] = [
            $value['trace_id'],
            $value['seller_id'],
            $value['policy_no'],
            $value['policy_type'],
            $value['company_name'],
            $value['business_type'],
            $value['section'],
            $value['vehicle_make'],
            $value['vehicle_model'],
            $value['vehicle_version'],
            $value['proposal_no'],
            $value['payment_order_id'],
            $value['payment_time'],
            $value['payment_status'],
            $value['premium_amount'],
            $value['vehicle_registration_number'],
        ];
    }
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\DataExport($data), 'RenewBuy Policy Report.xls');
}*/
