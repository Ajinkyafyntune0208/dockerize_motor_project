<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\UserProductJourney;
use App\Jobs\AbiblDataMigrationJob;
use App\Jobs\AbiblDataMigrationJobOld;
use App\Jobs\HyundaiDataUpload;
use App\Http\Controllers\Controller;
use App\Models\PremiumDetails;

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
        return view('dashboard');
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
        if(config('enquiry_id_encryption') == 'Y'){
            try {
                $enquiryId = null;
                if(strlen($request->enquiry_id) == 16 && config('enquiry_id_encryption') == 'Y' && (integer)$request->enquiry_id){
                    $new_enquiryId = \Illuminate\Support\Str::substr($request->enquiry_id, 8);
                    $enquiryId = customDecrypt(customEncrypt($new_enquiryId));
                } else if ($request->enquiry_id) {
                    $enquiryId = customDecrypt($request->enquiry_id);
                }
            } catch (\Throwable $th) {
                return redirect()->back()->withInput()->with('error', "Invalid enquiry id");
            }
        }
        else{
            if(is_numeric($request->enquiry_id)){
                $enquiryId = customDecrypt($request->enquiry_id);
            }
            elseif($request->enquiry_id){
                $enquiryId = enquiryIdDecryption($request->enquiry_id);
            }
        }
        


    try{
        $agent_details = $journey_data = $journey_stage = $addons = $user_proposal = $policy_details = $breakin_status = $payment_response = $quote_log = $corporate_vehicles_quote_request = $premium_details = [];
    if($request->has('enquiry_id')){
        $journey_data = \App\Models\UserProductJourney::find($enquiryId);
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
            $premium_details = self::checkAndLinks(PremiumDetails::where('user_product_journey_id', $user_product_journey_id)->get()?->toArray());

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
            ));
     } 
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
