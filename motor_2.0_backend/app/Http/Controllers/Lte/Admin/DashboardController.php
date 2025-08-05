<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Models\UserProductJourney;
use App\Jobs\AbiblDataMigrationJob;
use App\Jobs\AbiblDataMigrationJobOld;
use App\Jobs\HyundaiDataUpload;
use App\Http\Controllers\Controller;
use App\Models\PremiumDetails;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PremiumDetailController;
use App\Models\UserProposal;
use Illuminate\Support\Facades\Log;

ini_set('memory_limit', '-1');
set_time_limit(0);

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $role_id = auth()->user()->roles->first()->id;
        $customQuickLink =  $permissions = DB::table('role_has_quick_link')->where('role_id', $role_id)->exists();
        $get_quick_link_data = DB::table('menu_master')
        ->select('menu_name', 'menu_url')
        ->join('role_has_quick_link', 'menu_master.menu_id', '=', 'role_has_quick_link.menu_id')
        ->where('role_has_quick_link.role_id', $role_id)
        ->orderBy('menu_name', 'asc')
        ->get()
        ->toArray();
        return view('admin_lte.dashboard', compact('customQuickLink', 'get_quick_link_data'));
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
        //
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
    
    function checkAndLinks($data) {
        if ($data !== null) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $data[$key] =$this->checkAndLinks($value);
                }
            } elseif (is_string($data) && filter_var($data, FILTER_VALIDATE_URL)) {
                $data = "<a target='_blank' href='$data'>$data</a>";
            }
        }
        return $data;
    }
    public function getJourneyData(Request $request)
    {
        if (!auth()->user()->can('journey_data.list')) {
            response('unauthorized action', 401);
        }

    try{
        $agent_details = $journey_data = $journey_stage = $addons = $user_proposal = $policy_details = $breakin_status = $payment_response = $brokerCommission = $quote_log = $corporate_vehicles_quote_request = $premium_details = [];
    if($request->has('enquiry_id')){
        $enquiryId  = acceptBothEncryptDecryptTraceId($request->enquiry_id);
        $journey_data = \App\Models\UserProductJourney::find(ltrim($enquiryId,'0'));
            $journey_stage = self::checkAndLinks(!empty($journey_data->journey_stage) ? $journey_data->journey_stage->toArray(): []);
            $agent_details = self::checkAndLinks(!empty($journey_data->agent_details) ? $journey_data->agent_details->toArray():[]);
            $addons = self::checkAndLinks(!empty($journey_data->addons)? $journey_data->addons->toArray() : []);
            $user_proposal = self::checkAndLinks(!empty($journey_data->user_proposal) ? $journey_data->user_proposal->toArray(): []);
            $policy_details = $user_proposal ? self::checkAndLinks(!empty($journey_data->user_proposal->policy_details) ? $journey_data->user_proposal->policy_details->toArray() : []) : null;
            $breakin_status = $user_proposal ? self::checkAndLinks(!empty($journey_data->user_proposal->breakin_status) ? $journey_data->user_proposal->breakin_status->toArray() : []) : null;
            $payment_response = self::checkAndLinks($journey_data->payment_response_all->toArray());
            $quote_log = self::checkAndLinks(!empty($journey_data->quote_log) ? $journey_data->quote_log->toArray(): []);
            $corporate_vehicles_quote_request = self::checkAndLinks(!empty($journey_data->corporate_vehicles_quote_request) ? $journey_data->corporate_vehicles_quote_request->toArray():[]);
            $journey_data->smsOtps;
            $user_product_journey_id = $journey_data->user_product_journey_id;
            $premium_details = self::checkAndLinks(PremiumDetails::where('user_product_journey_id', $user_product_journey_id)->first()?->toArray());
            $brokerCommission = self::calculateBrokerCommission($premium_details,$journey_stage);
            
            if (!empty($premium_details)) {
                unset($premium_details['commission_conf_id']);
                unset($premium_details['commission_details']);
            }
        }
    }catch(\Exception $e){
            return redirect()->back()->with('error', $e->getMessage());
    }

     if(strstr(auth()->user()->email,'@fyntune.com') ){

        return view('journey_data', compact(
            "journey_data",
            "journey_stage",
            "addons",
            "user_proposal",
            "policy_details",
            "breakin_status",
            "payment_response",
            "quote_log",
            "corporate_vehicles_quote_request",
            "agent_details",
            "premium_details",
            "brokerCommission",
        ));
     }
     else{
         return view('journey_data2', compact(
                "journey_data",
                "journey_stage",
                "addons",
                "user_proposal",
                "policy_details",
                "breakin_status",
                "payment_response",
                "quote_log",
                "corporate_vehicles_quote_request",
                "agent_details",
                "premium_details",
                "brokerCommission",
            ));
     } 
    }

    public function calculateBrokerCommission($premium_details, $journey_stage)
    {
        try {
            if (empty($premium_details) || empty($journey_stage)) {
                return null;
            }
            $condition = [
                STAGE_NAMES['POLICY_ISSUED'],
                STAGE_NAMES['POLICY_ISSUED_BUT_PDF_NOT_GENERATED'],
                STAGE_NAMES['PAYMENT_SUCCESS'],
                STAGE_NAMES['PAYMENT_FAILED'],
                STAGE_NAMES['PAYMENT_INITIATED'],
            ];

            $condition = array_map(function ($v) {
                return strtoupper($v);
            }, $condition);

            if (!empty($journey_stage['stage']) && in_array(strtoupper($journey_stage['stage']), $condition)) {
                $temp = [];
                $premium_details = (object) $premium_details;
                if (!empty($premium_details)) {
                    $premiumDetails = $premium_details->details;
                    $commissionDetails = $premium_details->commission_details;
                    $verifyDetails = PremiumDetailController::verifyPremiumDetails($premium_details->user_product_journey_id);
                    if ($verifyDetails['status'] ?? false) {
                        $totalA = PremiumDetailController::getTotalAPremium($premiumDetails);
                        $totalB = PremiumDetailController::getTotalBPremium($premiumDetails);
                        $totalC = PremiumDetailController::getTotalCPremium($premiumDetails);
                        $totalD = PremiumDetailController::getTotalDPremium($premiumDetails);

                        $temp['od_premium'] = $totalA - $totalC;
                        $temp['tp_premium'] = $totalB;
                        $temp['od_net_premium'] = $totalA - $totalC + $totalD;
                        $temp['addon_premium'] = $totalD;
                        $temp['base_premium'] = $premiumDetails['net_premium'];
                        $temp['tax_amount'] = $premiumDetails['service_tax_amount'];
                        $temp['premium_amount'] = $premiumDetails['final_payable_amount'];
                    } else {
                        $temp = UserProposal::select('user_product_journey_id', 'od_premium', 'tp_premium', 'addon_premium', 'total_premium', 'service_tax_amount', 'final_payable_amount')->where('user_product_journey_id', $premium_details->user_product_journey_id)->first()->toArray();
                        $temp['base_premium'] = $temp['total_premium'];
                        $temp['tax_amount'] = $temp['service_tax_amount'];
                        $temp['premium_amount'] = $temp['final_payable_amount'];
                    }
                    if (!empty($commissionDetails['brokerage'])) {
                        $premiumList = [
                            'odPremium' => $temp['od_premium'],
                            'totalOdPayable' => $temp['od_net_premium'],
                            'tpPremium' => $temp['tp_premium'],
                            'netPremium' => $temp['base_premium'],
                            'grosspremium' => $temp['premium_amount'],
                            'basePremium' => $temp['base_premium'],
                            'addOnpremium' => $temp['addon_premium'],
                            'totalTax' => $temp['tax_amount'],
                            'taxAmount' => $temp['tax_amount'],
                            'totalAmount' => $temp['premium_amount'],
                        ];
                        $commission = \App\Http\Controllers\BrokerCommissionController::calculateTotalCommission(
                            $premiumList,
                            $commissionDetails
                        );
                        $temp['brokerage'] = [
                            'commissionAmount' => $commission,
                            'commission_conf_id' => $premium_details->commission_conf_id,
                            'commission_details' => $premium_details->commission_details
                        ];
                        return $temp['brokerage'];
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }

        return null;
    }

    public function abiblDataMigration(Request $request)
    {
        if (!auth()->user()->can('abibl_mg_data.list')) {
            abort(403, 'Unauthorized action.');
        }
        return view('abibl_data_migration.index');
    }
    public function abiblDataMigrationOld(Request $request)
    {
        if (!auth()->user()->can('abibl_old_data.list')) {
            abort(403, 'Unauthorized action.');
        }
        return view('abibl_data_migration.old');
    }

    public function abiblDataMigrationStore(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);
        // $data = \Maatwebsite\Excel\Facades\Excel::toCollection(new \App\Imports\UspImport, $request->file('file'));
        set_time_limit(0);
        $request->file->store('abil-data-migration-uploads');
        AbiblDataMigrationJob::dispatch();
        return redirect()->back()->with([
            'status' => 'File Uploaded Sucessfully..! Proccessing will start on some time',
            'class' => 'success'
        ]);
    }
    public function abiblDataMigrationOldStore(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);
        // $data = \Maatwebsite\Excel\Facades\Excel::toCollection(new \App\Imports\UspImport, $request->file('file'));
        set_time_limit(0);
        $request->file->store('abil-data-migration-uploads-old');
        AbiblDataMigrationJobOld::dispatch();
        return redirect()->back()->with([
            'status' => 'File Uploaded Sucessfully..! Proccessing will start on some time',
            'class' => 'success'
        ]);
    }
    public function abiblDataMigrationHyundai(Request $request)
    {
        if (!auth()->user()->can('abibl_hyundai_data.list')) {
            abort(403, 'Unauthorized action.');
        }
        return view('abibl_data_migration.hyundai');
    }
    public function abiblDataMigrationHyundaiStore(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv'
        ]);
        // $data = \Maatwebsite\Excel\Facades\Excel::toCollection(new \App\Imports\UspImport, $request->file('file'));
        set_time_limit(0);
        $request->file->store('hyundai_data_upload');
        HyundaiDataUpload::dispatch();
        return redirect()->back()->with([
            'status' => 'File Uploaded Sucessfully..! Proccessing will start on some time',
            'class' => 'success'
        ]);
    }
}
