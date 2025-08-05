<?php

namespace App\Http\Controllers\Lte\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ConfigSetting;
use App\Models\MasterCompany;
use App\Models\MasterPremiumType;
use App\Models\MasterProductSubType;
use App\Models\userActivityLog;
use App\Models\VahanService;
use App\Models\VahanServiceCredentials;
use App\Models\VahanServicePriorityList;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VahanServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth()->user()->can('vahan_service.list')) {
            abort(403, 'Unauthorized action.');
        }
        $cred = 'N';
        $vahan_Services = VahanService::all();
        return view('admin_lte.vahan_service_new.index', compact('vahan_Services','cred'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin_lte.vahan_service_new.create');
    }
    public function dash()
    {
        return view('admin_lte.vahan_service_new.dash');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'service_name' => 'required|unique:vahan_service_list,vahan_service_name',
            'service_code' => 'required|unique:vahan_service_list,vahan_service_name_code',
            'status' => 'required',
        ]);
        if($validation->fails()) {
            return redirect()->back()->withInput()->with([
                'status' => $validation->errors(),
                'class' => 'danger',
            ]);
        }
        try {
            DB::beginTransaction();
            VahanService::create([
                'vahan_service_name' => $request->service_name,
                'vahan_service_name_code' => $request->service_code,
                'status' => $request->status,
                'created_at' => now(),
            ]);
            DB::commit();

            return redirect()->route('admin.vahan_service.index')->with([
                'status' => 'Product Added Successfully ',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with([
                'status' => 'Something went wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    public function prioritySave(Request $request, $v_type)
    {
        $vahan_Services = VahanService::where('status', 'Active')->get();
        if ($request->intergration == 'priority') {
            foreach ($vahan_Services as $key => $val) {
                if (($request->{'prio_no_' . $val->id}) == null) {
                    return back()->withErrors(['message' => 'select priority for each services']);
                }
            }
        }

        if ($request->j_stage == 'Input page') {
            $j_val = 'input_page';
        } else {
            $j_val = 'proposal_page';
        }
        $Companies = MasterCompany::select('company_id', 'company_shortname')->where('status', 'Active')->whereNotNull('company_shortname')->get();
        $pos_items = $pos_block_items = $emp_items = $emp_block_items = $partner_items = $partner_block_items = $b2c_items = $b2c_block_items = array();
        foreach ($Companies as $key => $value) {
            if ($request->has('pos_' . $value->company_id)) {
                $pos_items[] = ($request->{'pos_' . $value->company_id});
                $pos_array_string = implode(",", $pos_items);
            }
            if ($request->has('pos_block' . $value->company_id)) {
                $pos_block_items[] = ($request->{'pos_block' . $value->company_id});
                $pos_block_array_string = implode(",", $pos_block_items);
            }
            if ($request->has('emp_' . $value->company_id)) {
                $emp_items[] = ($request->{'emp_' . $value->company_id});
                $emp_array_string = implode(",", $emp_items);
            }
            if ($request->has('emp_block' . $value->company_id)) {
                $emp_block_items[] = ($request->{'emp_block' . $value->company_id});
                $emp_block_array_string = implode(",", $emp_block_items);
            }
            if ($request->has('partner_' . $value->company_id)) {
                $partner_items[] = ($request->{'partner_' . $value->company_id});
                $partner_array_string = implode(",", $partner_items);
            }
            if ($request->has('partner_block' . $value->company_id)) {
                $partner_block_items[] = ($request->{'partner_block' . $value->company_id});
                $partner_block_array_string = implode(",", $partner_block_items);
            }
            if ($request->has('b2c_' . $value->company_id)) {
                $b2c_items[] = ($request->{'b2c_' . $value->company_id});
                $b2c_array_string = implode(",", $b2c_items);
            }
            if ($request->has('b2c_block' . $value->company_id)) {
                $b2c_block_items[] = ($request->{'b2c_block' . $value->company_id});
                $b2c_block_array_string = implode(",", $b2c_block_items);
            }
        }
        $B2B_employee_decision = [
            "allowedICs" => $emp_array_string ?? '',
            "blockFailure" => $emp_block_array_string ?? '',
        ];
        $B2B_pos_decision = [
            "allowedICs" => $pos_array_string ?? '',
            "blockFailure" => $pos_block_array_string ?? '',
        ];
        $B2B_partner_decision = [
            "allowedICs" => $partner_array_string ?? '',
            "blockFailure" => $partner_block_array_string ?? '',
        ];
        $B2C_decision = [
            "allowedICs" => $b2c_array_string ?? '',
            "blockFailure" => $b2c_block_array_string ?? '',
        ];

        $matchThese = ['journey_type' => $j_val, 'vehicle_type' => $v_type];
        $prio_list = VahanServicePriorityList::where($matchThese)->first();
        try {
            if (sizeof($vahan_Services) == 0) {
                return redirect()->route('admin.vahan-service-stage.stageIndex')->with([
                    'status' => 'Services is empty',
                    'class' => 'danger',
                ]);
            } else {
                if ($prio_list) {
                    $vahanServicePriorityList = VahanServicePriorityList::where($matchThese)->get();
                    foreach ($vahanServicePriorityList as $v) {
                        VahanServicePriorityList::find($v->id)->delete();
                    }
                }
                if ($request->intergration == 'single') {
                    $newUser = VahanServicePriorityList::Create([
                        'vehicle_type'     => $v_type,
                        'journey_type'     =>  $j_val,
                        'vahan_service_id' => $request->single_service,
                        'priority_no'    => 1,
                        'integration_process'   => $request->intergration,
                        'B2B_employee_decision'       => ($request->j_stage == 'Input page') ? ($request->has('emp_check') ? 'true' : 'false') : (json_encode($B2B_employee_decision)),
                        'B2B_pos_decision'       => ($request->j_stage == 'Input page') ? ($request->has('pos_check') ? 'true' : 'false') : (json_encode($B2B_pos_decision)),
                        'B2B_partner_decision'       => ($request->j_stage == 'Input page') ? ($request->has('partner_check') ? 'true' : 'false') : (json_encode($B2B_partner_decision)),
                        'B2C_decision'   => ($request->j_stage == 'Input page') ? ($request->has('b2c_check') ? 'true' : 'false') : (json_encode($B2C_decision)),
                        ($prio_list ? 'updated_at' : 'created_at') => now(),
                    ]);
                } else {
                    foreach ($vahan_Services as $key => $vahan_Service) {
                        $newUser = VahanServicePriorityList::Create([
                            'vehicle_type'     => $v_type,
                            'journey_type'     =>  $j_val,
                            'vahan_service_id' => $vahan_Service->id,
                            'priority_no'    => ($request->intergration == 'parallel') ? '' : (($request->{'prio_no_' .  $vahan_Service->id}) ?? ''),
                            'integration_process'   => $request->intergration,
                            'B2B_employee_decision'       => ($request->j_stage == 'Input page') ? ($request->has('emp_check') ? 'true' : 'false') : (json_encode($B2B_employee_decision)),
                            'B2B_pos_decision'       => ($request->j_stage == 'Input page') ? ($request->has('pos_check') ? 'true' : 'false') : (json_encode($B2B_pos_decision)),
                            'B2B_partner_decision'       => ($request->j_stage == 'Input page') ? ($request->has('partner_check') ? 'true' : 'false') : (json_encode($B2B_partner_decision)),
                            'B2C_decision'   => ($request->j_stage == 'Input page') ? ($request->has('b2c_check') ? 'true' : 'false') : (json_encode($B2C_decision)),
                            ($prio_list ? 'updated_at' : 'created_at') => now(),
                        ]);
                    }
                }
                if ($newUser) {
                    return redirect()->route('admin.vahan-service-stage.stageIndex')->with([
                        'status' => 'Success..!',
                        'class' => 'success',
                    ]);
                } else {
                    return redirect()->route('admin.vahan-service-stage.stageIndex')->with([
                        'status' => 'Something went wrong..!',
                        'class' => 'danger',
                    ]);
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something went wrong ' . $e->getMessage() . '...!',
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
    public function edit($id)
    {

        $vahan_service = DB::table('vahan_service_list')->where('id', $id)->first();
        return view('admin_lte.vahan_service_new.edit', compact('vahan_service', 'id'));
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
       $rules= [
            'service_name' => 'required|unique:vahan_service_list,vahan_service_name,' . $id,
            'service_code' => 'required|unique:vahan_service_list,vahan_service_name_code,' . $id,
            'status' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }else{
            try {
                $vahan_service = VahanService::find($id);
                $vahan_service->update([
                    'vahan_service_name' => $request->service_name,
                    'vahan_service_name_code' => $request->service_code,
                    'status' => $request->status,
                    'updated_at' => now(),
                ]);
                // Reason for adding forach is to trigger the updated/deleted event in the Observers
                if ($request->status == 'Active') {
                    $vahanServiceCredentials = VahanServiceCredentials::where('vahan_service_id', $id)->get();
                    foreach ($vahanServiceCredentials as $v) {
                        VahanServiceCredentials::find($v->id)->update(['status' => 'Active']);
                    }
                }
                if ($request->status == 'Inactive') {
                    $vahanServiceCredentials = VahanServiceCredentials::where('vahan_service_id', $id)->get();
                    foreach ($vahanServiceCredentials as $v) {
                        VahanServiceCredentials::find($v->id)->update(['status' => 'Inactive']);
                    }

                    $vahanServicePriorityList = VahanServicePriorityList::where('vahan_service_id', $id)->get();
                    foreach ($vahanServicePriorityList as $v) {
                        VahanServicePriorityList::find($v->id)->delete();
                    }
                }
                return redirect()->route('admin.vahan_service.index')->with([
                    'status' => 'Updated Successfully..!',
                    'class' => 'success',
                ]);
            } catch (\Exception $e) {
                return redirect()->back()->with([
                    'status' => 'Something went wrong ' . $e->getMessage() . '...!',
                    'class' => 'danger',
                ]);
            }
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $url = 'admin.vahan-service-credentials.index';
        if ($request->cred == 'N') {
            $url = 'admin.vahan_service.index';
            VahanService::find($request->id)->delete();

            // Reason for adding foor loop is, to trigger the deleted event in the Observer
            $vahanServiceCredentials=VahanServiceCredentials::where('vahan_service_id', $request->id)->get();

            foreach ($vahanServiceCredentials as $v) {
                VahanServiceCredentials::find($v->id)->delete();
            }

            $vahanServicePriorityList = VahanServicePriorityList::where('vahan_service_id', $request->id)->get();

            foreach ($vahanServicePriorityList as $v) {
                VahanServicePriorityList::find($v->id)->delete();
            }
        }
        return redirect()->route($url)->with([
            'status' => 'Deleted Successfully..!',
            'class' => 'success',
        ]);
    }
    public function credDelete(Request $request)
    {
        $cred = VahanServiceCredentials::find($request->id);
        try {
            if ($cred) {
                $cred->status = 'Inactive';
                $cred->save();
                return response()->json([
                    "status" => true,
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something went wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }
    public function credData()
    {
        $cred = 'Y';
        $vahan_Services = VahanService::all();
        return view('admin_lte.vahan_service_new.index', compact('vahan_Services', 'cred'));
    }
    public function credCrudPage(Request $request, $id)
    {
        $servic = VahanService::find($id);
        $service = $servic->vahan_service_name;
        $matchThese = ['vahan_service_id' => $id, 'status' => 'Active'];
        $vahan_services = DB::table('vahan_service_credentials')->where($matchThese)->get();
        if ($servic->status == 'Inactive') {
            return redirect()->route('admin.vahan-service-credentials.index')->with([
                'status' => 'Service is inactive',
                'class' => 'danger',
            ]);
        } else {
            return view('admin_lte.vahan_credentials.read', compact('vahan_services', 'service', 'id'));
        }
    }
    public function credKeyCheck(Request $request, $row_id = 0)
    {
        $matchThese = ['vahan_service_id' => $request->v_id, 'key' => $request->key_value, 'status' => 'Active'];
        if ($row_id == 0) {
            return response()->json([
                "status" => VahanServiceCredentials::where($matchThese)->exists() ? true : false,
            ]);
        } else {
            return response()->json([
                "status" => VahanServiceCredentials::where($matchThese)->where('id', '!=', $row_id)->exists() ? true : false,
            ]);
        }
    }
    public function credInsert(Request $request)
    {
        $service = VahanService::find($request->id);
        $matchThese = ['vahan_service_id' => $request->id, 'key' => $request->new_key, 'status' => 'Active'];
        $exist = VahanServiceCredentials::where($matchThese)->exists() ? true : false;
        if ($service->status == 'Active' && !$exist) {
            $vahan_service = VahanServiceCredentials::Create([
                'vahan_service_id'     => $request->id,
                'label'     =>  $request->new_label,
                'key'     => $request->new_key,
                'value'     => $request->new_value,
                'updated_at' => now(),
            ]);

            return response()->json([
                "status" => $vahan_service ? true : false,
            ]);
        } else {
            return response()->json([
                "status" => $exist ? 'exist' : 'inactive',
            ]);
        }
    }
    public function credUpdate(Request $request)
    {
        $service = VahanService::find($request->v_id);
        $matchThese = ['vahan_service_id' => $request->v_id, 'key' => $request->key, 'status' => 'Active'];
        if ($request->id == 0) {
            $exist = VahanServiceCredentials::where($matchThese)->exists() ? true : false;
        } else {
            $exist = VahanServiceCredentials::where($matchThese)->where('id', '!=', $request->id)->exists() ? true : false;
        }
        $credId = DB::table('vahan_service_credentials')->where('id', $request->id)->first();
        if ($service->status == 'Active' && !$exist) {
            $vahan_service = VahanServiceCredentials::updateOrCreate([
                'id'   => $request->id,
            ], [
                'label'     => $request->label,
                'key'     => $request->key,
                'value'     => $request->val,
                ($credId ? 'updated_at' : 'created_at') => now(),
            ]);
            return response()->json([
                "status" => $vahan_service ? true : false,
            ]);
        } else {
            return response()->json([
                "status" => $exist ? 'exist' : 'inactive',
            ]);
        }
    }
    public function stageIndex()
    {
        if (!auth()->user()->can('vahan_service_configurator.list')) {
            abort(403, 'Unauthorized action.');
        }
        $vahan_Services = VahanService::where('status', 'Active')->get();
        $J_stages = ['input_page' => 'Input Page', 'Proposal_page' => 'Proposal Page'];
        return view('admin_lte.vahan_service_stage.index', compact('vahan_Services', 'J_stages'));
    }
    public function stageEdit($key, $v_type)
    {
        if ($key == 'input_page') {
            $j_val = 'Input page';
        } else {
            $j_val = 'Proposal page';
        }
        $matchThese = ['journey_type' => $key, 'vehicle_type' => $v_type];
        $vahan_Services = VahanService::where('status', 'Active')->get();
        $vahan_configs = VahanServicePriorityList::where($matchThese)->get();
        $prio = [];
        foreach ($vahan_Services as $key => $ser) {
            $prio[$key]['id'] = $ser->id;
        }
        $find = '';
        foreach ($prio as $key => $val) {
           $find = $vahan_configs->where('vahan_service_id', $val['id'])->first();
            $prio[$key]['priority_no'] = $find->priority_no ?? '';
        }
        $collection = collect($prio);
        $service_prio_lists = DB::table(DB::raw('vahan_service_list l'))
            ->select('l.id', 'p.priority_no', 'l.vahan_service_name')
            ->leftJoin(DB::raw('vahan_service_priority_list p'), 'p.vahan_service_id', '=', 'l.id')
            ->where('l.status', '=', 'Active')->orWhere($matchThese)
            ->get()->toArray();
        if (sizeof($vahan_configs) == 0) {
            $b2b_lists_show = '';
        } else {
            $b2b_lists_show = array(
                0 => $vahan_configs[0]->B2B_employee_decision,
                1 => $vahan_configs[0]->B2B_pos_decision,
                2 => $vahan_configs[0]->B2B_partner_decision
            );
        }
        $b2b_lists = array(
            0 => 'Employee Decision',
            1 => 'Pos Decision',
            2 => 'Partner Decision',
        );
        $items = [];
        for ($i = 1; $i <= sizeof($vahan_Services); $i++) {
            $items[] = $i;
        }
        $array_ids = [];
        foreach ($vahan_Services as $key => $service) {
            $array_ids[] = $service->id;
        }
        $string_ids = implode(",", $array_ids);
        $Companies = DB::table('master_policy')
            ->selectRaw('master_company.company_id,master_company.company_shortname')
            ->join('master_company', 'master_company.company_id', '=', 'master_policy.insurance_company_id')
            ->where('master_company.status', '=', 'Active')->where('master_policy.status', '=', 'Active')->where('master_company.company_shortname', '<>', '')
            ->groupBy('master_company.company_id', 'master_company.company_shortname')
            ->get();
        $Ic_ids = [];
        foreach ($Companies as $key => $Company) {
            $Ic_ids[] = $Company->company_id;
        }
        $ic_ids = implode(",", $Ic_ids);
        if (sizeof($vahan_Services) == 0) {
            return redirect()->route('admin.vahan-service-stage.stageIndex')->with([
                'status' => 'Services is Empty / Inactive',
                'class' => 'danger',
            ]);
        } else {
            return view('admin_lte.vahan_service_stage.edit_new', compact('vahan_Services', 'collection', 'vahan_configs', 'Companies', 'v_type', 'j_val', 'key', 'items', 'string_ids', 'ic_ids', 'service_prio_lists'));
        }
    }


    public function VahanConfigurator(Request $request)
    {
        // dd($request->config);
        foreach( $request->config as $config)
        {
         ConfigSetting::updateOrCreate([
            'key' => $config['key']
        ],
        [
            'value' => $config['value'] ?? 'N',
            'label' => $config['label']
        ]
        );
        }
        return redirect()->back();
    }
}
