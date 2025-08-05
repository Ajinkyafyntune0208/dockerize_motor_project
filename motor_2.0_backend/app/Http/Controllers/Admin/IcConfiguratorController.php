<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrokerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\MasterCompany;
use App\Models\MasterProductSubType;
use App\Models\ConfigSetting;
use App\Models\MasterPolicy;
use App\Models\IcProduct;
use App\Models\MasterPremiumType;
use App\Models\MasterProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use App\Exports\PolicyReportsExport;
use App\Models\DefaultApplicableCover;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;


class IcConfiguratorController extends Controller
{


    public function index()
    {

        if (!auth()->user()->can('ic_configurator.view')) {
            abort(403, 'Unauthorized action.');
        }

        $alias = MasterCompany::select('master_company.company_alias', 'master_company.company_name')
            ->where('status', 'Active')
            ->whereNotNull('company_alias')
            ->orderBy('company_alias')
            ->get();
        return view('IC_Configurator.index', ['company' => $alias]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function fetching(Request $request)
    {
        $companyAlias = $request->input('company_alias');


        $company = MasterCompany::select('master_company.company_alias', 'ic_credentials.config_name', 'ic_credentials.config_key', 'config_settings.value', 'ic_credentials.section', 'ic_credentials.default_value')
        ->orderBy('master_company.company_alias')
        ->whereNotNull('master_company.company_alias')
        ->join('ic_credentials', 'ic_credentials.company_alias', '=', 'master_company.company_alias')
        ->leftjoin('config_settings', 'ic_credentials.config_key', '=', 'config_settings.key')
        ->where('master_company.company_alias', $companyAlias)
        ->whereRaw("FIND_IN_SET('{$request->section}', ic_credentials.section)")
        ->get();
        $response_htm = '';
        if(config('security.compliance.enable') == 'Y') 
        {
            $sr= 1;
            foreach ($company as $key => $value) {
              $config_name = $value['config_name'];
              $config_key = $value['config_key'];
              $configValue = $value['value'];
              $default_value = $value['default_value'];
              $section = $value['section'];
              $checked = ($configValue == '') ? 'checked' : '';
              $response_htm .= " <tr style='border-bottom: 2px solid black;'> 
              <td class='table-cell'> $sr </td> 
              <td class='table-cell'> $config_name
              </td><input type='hidden' name='config_name[]' value='$config_name '/> 
              <td class='table-cell'> $config_key </td>
              <input type='hidden' name='config_key[]' value='$config_key '/>  </td>
              <td class='table-cell'><input type='text' name='config_value[]' class='form-control' value=' $configValue' style='width: 400px;'></td> 
              <td class='table-cell'> $default_value <span hidden id=' $sr '> $default_value  </span><i  class='mdi mdi-eye-off eye-icon' onclick='eyeButton( $sr ,event)'></i> 
              </td><input type='hidden' name='default_value[]' value=' $default_value '/> 
              <td class='table-cell'><input type='checkbox' class='clear-checkbox' $checked ></td> 
              </tr>";
              $sr++;
            }
            return response()->json([
                'status' => true,
                'data' => $response_htm,
                'data_flow' => 'Y'
            ]);
        }
        else
        {
            if ($company->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'data' => $company,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'data' => [],
                    'msg' => 'No records found'
                ]);
            }
        }
    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function storeOrUpdate(Request $request)
    {
        $configurations = [];

        // Extract data from the request
        $configNames = $request->input('config_name');
        $configKeys = $request->input('config_key');
        $configValues = $request->input('config_value');

        // Combine the data into an array of configurations
        foreach ($configNames as $index => $configName) {
            $configurations[] = [
                'label' => $configName,
                'key' => $configKeys[$index],
                'value' => $configValues[$index]
            ];
        }
        // Insert or update each configuration
        foreach ($configurations as $config) {
            // If record exists, update it
            $resul = ConfigSetting::updateOrCreate(['key' => $config['key']], $config);
        }
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        return redirect()->back();
    }


    public function fetchingProduct()
    {
        $businessType = IcProduct::select('business_type')
        ->distinct('business_type')
        ->get()
        ->pluck('business_type');
        $alias = MasterCompany::select('master_company.company_alias', 'master_company.company_name', 'master_company.company_id as insurance_company_id')
            ->where('status', 'Active')
            ->whereNotNull('company_alias')
            ->orderBy('company_alias')
            ->get();

            $product_dropdown = MasterProductSubType::select('master_product_sub_type.product_sub_type_code', 'master_product_sub_type.product_sub_type_name', 'master_product_sub_type.product_sub_type_id')
            ->whereNotIn('product_sub_type_id', [3, 4, 8])
                    ->where('status', 'Active')
                    ->orderBy('product_sub_type_code')
                    ->get();

        $company = MasterPremiumType::select('master_premium_type.premium_type', 'master_premium_type.id')
            ->orderBy('premium_type_code')
            ->get();


        return view('IC_Configurator.product', ['businessType' => $businessType, 'product_dropdown' => $product_dropdown, 'alias' => $alias, 'company' => $company]);
    }
    public function fetchingPremium(Request $request)
    {

        $company = MasterPremiumType::select('master_premium_type.premium_type', 'master_premium_type.premium_type_code')
        ->orderBy('premium_type_code')
        ->get();
        return response()->json($company);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function ProductList(Request $request)
    {
        $where = [
            'ic_product.product_sub_type_id' => $request->product_sub_type_id,
            'ic_product.insurance_company_id' => $request->insurance_company_id,
        ];

        if (!empty($request->businessType)) {
            $where['ic_product.business_type'] = $request->businessType;
        }

        $companyQuery = IcProduct::select(
            'master_policy.*', 
            // 'master_policy.master_policy_id as policy_ids', 
            'ic_product.product_sub_type_id as product_sub_type_ids', 
            'ic_product.insurance_company_id as insurance_company_ids', 
            'ic_product.premium_type_id as premium_type_ids', 
            'master_premium_type.premium_type as premium_type',
            // DB::raw('COALESCE(master_policy.policy_id, ic_product.ic_policy_id) AS policy_ids'), 
            DB::raw('COALESCE(master_policy.product_name, ic_product.product_name) AS product_names'), 
            DB::raw('COALESCE(master_policy.product_unique_name, ic_product.product_unique_name) AS product_unique_names'), 
            DB::raw('COALESCE(master_policy.product_identifier, ic_product.product_identifier) AS product_identifiers'), 
            DB::raw('COALESCE(master_policy.business_type, ic_product.business_type) AS business_types'), 
            DB::raw('COALESCE(master_policy.pos_flag, ic_product.pos_flag) AS pos_flags'), 
            DB::raw('COALESCE(master_policy.product_key, ic_product.product_key) AS product'), 
            DB::raw('COALESCE(master_policy.zero_dep, ic_product.zero_dep) AS zero'), 
            DB::raw('COALESCE(master_policy.default_discount, ic_product.default_discount) AS default_discounts'), 
            DB::raw('COALESCE(master_policy.gcv_carrier_type, ic_product.gcv_carrier_type) AS gcv_carrier_types'), 
            DB::raw('COALESCE(master_policy.owner_type, ic_product.owner_type) AS owner'),
            DB::raw('COALESCE(master_policy.consider_for_visibility_report, ic_product.consider_for_visibility_report) AS consider_for_visibility_report')
        )
        ->leftJoin('master_policy', 'ic_product.product_key', '=', 'master_policy.product_key')
        ->join('master_premium_type', 'master_premium_type.id', '=', 'ic_product.premium_type_id')
        ->where($where);
    
        $company = $companyQuery->get();
        $content = '' ;
         $selectIdarr = $select_arr = [];
        // dd($company);
        if(config('security.compliance.enable') == 'Y')
        {
            foreach($company as $key=>$value)
            {
                array_push($selectIdarr, $key);
                $selectId = 'pos_flag_' . $key;
                $selectName = 'pos_flag[' . $key . '][]';
                $select = 'owner' . $key;
                $selectowner = 'owner['  .$key . '][]';
                $checkboxChecked = $value['status'] === 'Active' ? 'checked' : '';
                $product = $value['product'];
                $bussiness_types = $value['business_types'] ?? '';
                $premium_type = $value['premium_type'] ?? '';
                $product_names = $value['product_names'] ?? '';
                $product_identifier = $value['product_identifiers'] ?? '';
                $default_discounts = $value['default_discounts'] ?? '';
                $pos_flags = $value['pos_flags'] ?? '';
                $pos_sel = $nonpos_sel = $driver_sel = $ESSONE_sel = $EV_sel = $E_sel = '';
                $owner = $value['owner'] ?? '';
                $zero = $value['zero'] ;
                $policy_id = $value['policy_id'] ?? '';
                $plan_nm = $visibilityCheckBox =  '';
                $product_sub_type_ids = $value['product_sub_type_ids'] ?? '';
                $insurance_company_ids = $value['insurance_company_ids'] ?? '';
                $premium_type_ids = $value['premium_type_ids'] ?? '';
                $consider_for_visibility_report = $value['consider_for_visibility_report'] ?? '';
                $gcv_carrier_types = $value['gcv_carrier_types'] ?? '';
                $product  = $value['product'] ?? '';
                $status = $value['status'] ?? '';
                if($zero === '0' )
                {
                    $plan_nm = 'Zero Dept';
                }
                elseif($zero === '1' )
                {
                    $plan_nm = 'Base Plan';
                }
                else
                {
                    $plan_nm = '';
                }

                switch ($pos_flags) {
                    case 'P':
                        $pos_sel = 'selected';
                        break;
                    case 'P,N':
                        $nonpos_sel = 'selected';
                        break;
                    case 'D':
                        $driver_sel = 'selected';
                        break;
                    case 'A':
                        $ESSONE_sel = 'selected';
                        break;
                    case 'EV':
                        $EV_sel = 'selected';
                        break;
                    case 'E':
                        $E_sel = 'selected';
                        break;

                    default:
                    $pos_sel = $nonpos_sel = $driver_sel = $ESSONE_sel = $EV_sel = $E_sel = '';
                    break;
                }
                 $visibilityReportChecked = $consider_for_visibility_report;
                if($visibilityReportChecked){
                     $visibilityCheckBox = '<input type="checkbox" name="visibilityCheckBox[]" checked onchange="visibilityChange(this)"><input type="hidden" name="visibilityCheckBox1[]" value =1>';
                }else{
                    $visibilityCheckBox = '<input type="checkbox" name="visibilityCheckBox[]" onchange="visibilityChange(this)"><input type="hidden" name="visibilityCheckBox1[]" value =0>';
                }
                $indvidual_sel = $corporate_sel = '';
                if (Str::contains($owner, 'I')) {
                    $indvidual_sel = 'selected';
                }
                if (Str::contains($owner, 'C')) {
                    $corporate_sel = 'selected';
                }
                $checkboxChecked = $status === 'Active' ?'checked' : '';
                $statusCheckbox ="<input type='checkbox' name='status1[]' onchange='statuschange(this)' value='Active'   $checkboxChecked  ><br> <input type='hidden'  name='status[]'  value='Active' $checkboxChecked  ><br>";
                if (!$checkboxChecked) 
                {
                    $statusCheckbox =
                        "<input type='checkbox' onchange='statuschange(this)' name='status1[]' value='Inactive'> <input type='hidden'  name='status[]'  value='Inactive'  
                        $checkboxChecked  ><br>";
                }
                $gcvCarrierTypeDropdown = "<td> 
                <select class='form-control gcv-carrier-type-dropdown' name='gcv_carrier_type[]'> 
                <option value='Public'  ($gcv_carrier_types === Public ?  selected : )  >Public</option> 
                <option value='Private'  ($gcv_carrier_types === Private ?  selected : )  >Private</option> 
                </select> 
                </td>";
                
                $content .= "<tr> <td>  <span> $product  </span> 
                <input type='text' hidden name='product_key[]' value=' $product'></td> 
                <td> 
                <input type='hidden' name='business_types[]' value=' $bussiness_types '> 
                <span> $bussiness_types  </span> 
                </td> 
                <td>  $premium_type 
                <input type='text' hidden name='premium_type[]' value='$premium_type'></td> 
                <td><input type='hidden' name='product_name[]' value='$product_names'  pattern='[A-Za-z0-9\s]]' title='Please enter only alphanumeric characters'> 
                $product_names  </td>  
                <td><input type='hidden' name='product_identifier[]' value= '$product_identifier' pattern='[A-Za-z0-9\s]' title='Please enter only alphanumeric characters'> 
                $product_identifier </td> 
                <td><input class='form-control' type='text' name='default_discount[]' value= '$default_discounts' pattern='[0-9]*' title='Please enter only numeric characters' max='100'></td> 
                <td> 
                <select name='$selectName' id= '$selectId' data-style='btn-cus-v2' data-live-search='true' data-actions-box='true' multiple class='selectpicker w-100' required> 
                <option value='P'   $pos_sel >POS</option> 
                <option value='N'  $nonpos_sel >NONPOS</option> 
                <option value='D'   $driver_sel >DRIVER APP</option> 
                <option value='A'   $ESSONE_sel >ESSONE</option> 
                <option value='EV'  $EV_sel>ELECTRIC VEHICLE</option>
                <option value='E'  $E_sel>EMPLOYEE</option> 
                </select> </td> <td> 
                <select name= '$selectowner' id='$select ' data-style='btn-cus-v2' data-live-search='true' data-actions-box='true' multiple class='selectpicker w-100' required>
                <option value=I  $indvidual_sel>Individual</option> 
                <option value='C' $corporate_sel >Company</option>
                </select> 
                </td> $gcvCarrierTypeDropdown <td> 
                <input type='hidden' name='zero_dep[]' value='$zero'> 
                <span>  $plan_nm  </span>  </td>  <td> 
                $statusCheckbox 
               
                    <input type='hidden' name='policy_id[]' value=' $policy_id '>
                <input type='hidden' name='product_sub_type_id[]' value='$product_sub_type_ids' >
                <input type='hidden' name='insurance_company_id[]' value='$insurance_company_ids'>
                <input type='hidden' name='premium_type_id[]' value='$premium_type_ids'></td><td>  
                $visibilityCheckBox
                </td> </tr>
                
              
                ";
            }
            return response()->json([
                'status' => true,
                'data' => $content,
                'msg' => 'Records Found',
                'flow' => 1,
                'selectId' => $selectIdarr,
            ]);
        }
        else
        {
    
        if ($company) {
            return response()->json([
                'status' => true,   
                'data' => $company,
                'msg' => 'Records Found',
            ]);
        } else {
            return response()->json([
                'status' => false,   
                'data' => [],
                'msg' => 'No records found'
            ]);
        }
        }
    }


    public function downloadExcel(Request $request)
    {
        if(isset($request->product_sub_type_id) && isset($request->insurance_company_id)){
            $object = $this->ProductList($request);
            $object_data = json_decode(json_encode($object), true);
            foreach ($object_data['original']['data'] as $key => $data_value) {
                foreach ($data_value as $key_data => $value_data) {
                    if($value_data == null){
                        unset($object_data['original']['data'][$key][$key_data]);
                    }
                }
            }
            return Excel::download(new PolicyReportsExport($object_data['original']['data']), 'policy_report.xls');            
        } else {
            return redirect()->back();
        }
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function productUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'default_discount.*' => 'nullable|integer|max:100',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $configurations = [];
        $product = []; 

        $product_key = $request->input('product_key');
        $business_type = $request->input('business_types');
        // $product_unique_name = $request->input('product_unique_name');
        $product_name = $request->input('product_name');
        $default_discount = $request->input('default_discount');
        $product_identifier = $request->input('product_identifier');
        $pos_flag = $request->input('pos_flag');
        $zero_dep = $request->input('zero_dep');
        $status = $request->input('status');
        $consider_for_visibility_report = $request->input('visibilityCheckBox1');
        $owner_type = $request->input('owner');
        $gcv = $request->input('gcv_carrier_type');
        $policy_id = $request->input('policy_id');
        $product_sub_type_id = $request->input('product_sub_type_id') ?? [];
        $insurance_company_id = $request->input('insurance_company_id') ?? [];
        $premium_type_id = $request->input('premium_type_id') ?? [];
        if (empty($policy_id)) {
            return redirect()->back()->withErrors([
                'msg' => 'No data received for product update.',
            ]);
        }
        foreach ($product_name as $index => $configName) {

            $posFlagValue = !empty($pos_flag[$index] ?? null) ? implode(',', $pos_flag[$index]) : '';
            $ownertypeValue = !empty($owner_type[$index] ?? null) ? implode(',', $owner_type[$index]) : '';
            // $bussinesValue = !empty($business_type[$index] ?? null) ? implode(',', $business_type[$index]) : '';
            $configurations[$index] = [
                'product_key' => $product_key[$index],
                'business_type' =>  $business_type[$index],
                // 'product_unique_name' => $product_unique_name[$index],
                'product_name' =>  $configName,
                'default_discount' => $default_discount[$index],
                'product_identifier' => $product_identifier[$index],
                'pos_flag' =>  $posFlagValue,
                'owner_type' => $ownertypeValue,
                'gcv_carrier_type' => $gcv[$index] ?? null,
                'zero_dep' => $zero_dep[$index],
                'status' => $status[$index],
                'consider_for_visibility_report' => $consider_for_visibility_report[$index],
                'product_sub_type_id' => $product_sub_type_id[$index],
                'insurance_company_id' => $insurance_company_id[$index],
                'premium_type_id' => $premium_type_id[$index],
                'is_premium_online' => 'Yes', 
                'is_proposal_online' => 'Yes',
                'is_payment_online' => 'Yes', 
                // 'created_at' => now(),
            ];
            $product[$index] = [
                'product_name' =>  $configName,
                'product_identifier' => $product_identifier[$index],
                'master_policy_id' => $policy_id[$index],
                // 'created_at' => now(),
            ];
        }
        foreach ($configurations as $k => $config) {
            $resul = MasterPolicy::updateOrCreate(['product_key' => $config['product_key']], $config);
            $product[$k]['master_policy_id']=$resul['policy_id'];
            MasterProduct::updateOrCreate(['master_policy_id' => $resul['policy_id']], $product[$k]);
        }
        if ($request->has('prev')) {
            return redirect($request->prev)->with([
                'status' => 'Product Updated Successfully..!',

            ]);
        } else {
            return redirect()->back()->withInput()->with([
                'status' => 'Product Updated Successfully..!',
                'class' => 'success',
            ]);
        }
    }

    public function miscellaneous()
    {
        $alias = MasterCompany::select('master_company.company_alias', 'master_company.company_name')
            ->whereNotNull('company_alias')
            ->where('status', 'Active')
            ->orderBy('company_alias')
            ->get();
            $product_dropdown = MasterProductSubType::select('master_product_sub_type.product_sub_type_code', 'master_product_sub_type.product_sub_type_name', 'master_product_sub_type.product_sub_type_id')
            ->where('status', 'Active')
            ->orderBy('product_sub_type_code')
            ->get();

        $company = MasterPremiumType::select('master_premium_type.premium_type', 'master_premium_type.id')
            ->orderBy('premium_type_code')
            ->get();
        return view('IC_Configurator.Miscellaneous', ['product_dropdown' => $product_dropdown, 'alias' => $alias, 'company' => $company]);
    }
    public function enable_cover(Request $request)
    {

        $selectedCovers = array_column(DefaultApplicableCover::selectRaw('LOWER(section) as section')->where('status', 'Y')->get()->toArray(),'section');
        return view('admin_lte.enable_default_applicable_cover.index', compact('selectedCovers'));
    }


    public function cover_store(Request $request)
    {
        $selectedCovers = $request->input('covers', []);

        foreach ($selectedCovers as $cover) {
            $data = [
                'status' => 'Y',
                'cover_type' => 'additional_covers',
                'cover_name' => 'LL paid driver',
                'sum_insured' => '100000',
            ];

            DefaultApplicableCover::updateOrCreate(
                ['section' => $cover],
                $data
            );
        }

        DefaultApplicableCover::whereNotIn('section', $selectedCovers)->update(['status' => 'N']);

        $defaultCover = DefaultApplicableCover::where('status', 'Y')->get();

        return view('admin_lte.enable_default_applicable_cover.index', compact("defaultCover", "selectedCovers"));
    }
}
