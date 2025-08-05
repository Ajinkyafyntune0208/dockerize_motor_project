<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterProductSubType;
use App\Models\MasterCompany;
use App\Models\MasterPolicy;
use App\Models\MasterProduct;
use App\Models\MasterPremiumType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon;

class MasterPolicyController extends Controller
{
    protected static $blockedMessage = 'We\'re not allowing to add / update products from this page, instead use IC configurator for that.';
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        DB::enableQueryLog();
        if (!auth()->user()->can('master_policy.list')) {
            return response('unauthorized action', 401);
        }

        $mcomp = MasterCompany::where('status','Active')->select('company_id','company_name')->get(); //company id
        $mpremiums = MasterPremiumType::select('id','premium_type')->get();
        $pstype = MasterProductSubType::select('product_sub_type_id','product_sub_type_code')->whereNotIn('product_sub_type_id',[3,4,8])->get();
        
        // dd($pstype,$mcomp,$mpremiums);
        $bidMappings = [
            1 => 'newbusiness',
            2 => 'rollover',
            3 => 'breakin',
        ]; 
        $bidval = $bidMappings[$request->bid] ?? null;
        $company_id = $request->company_id;
        $premium_type = $request->premium_type;
        $product_sub_type = $request->product_sub_type;
        $pstatus =$request->pstatus;

        // dd($bidval,$company_id,$premium_type,$product_sub_type);

        if ( $request->bid != null || //bussiness type  
            $request->company_id != null || //company name 
            $request->premium_type != null || //premium type
            $request->product_sub_type != null || // product sub type car or something
            $request->pstatus != null  || // policy status
            $request->gdd != null // good driver discount status
        ){
            $master_policies = MasterPolicy::with('master_product')
            ->with('premium_type')
            ->join('master_company', function ($join) use ($request) {
                $join->on('master_company.company_id', '=', 'insurance_company_id')
                     ->when($request->company_id != null, function ($query) use ($request) {
                        return $query->whereIn('company_id', $request->company_id);
                     });
            })
            ->join('master_product_sub_type', function ($join) use ($request) {
                $join->on('master_product_sub_type.product_sub_type_id', '=', 'master_policy.product_sub_type_id')
                    ->when($request->product_sub_type != null, function ($query) use ($request) {
                        return $query->where('master_product_sub_type.product_sub_type_id', '=', $request->product_sub_type);
                    });
            })
            ->select('*', 'master_policy.status as master_policy_status')
            ->when($request->bid != null, function ($query) use ($bidval) {
                return $query->whereRaw("FIND_IN_SET('$bidval',business_type)");
            })
            ->when($request->gdd != null, function ($query) use ($request) {
                return $query->where('good_driver_discount', [$request->gdd]);
            })
            ->when($request->premium_type != null, function ($query) use ($request) {
                return $query->where('premium_type_id', '=', $request->premium_type);
            })
            ->when($request->pstatus != null, function ($query) use ($request) {
                return $query->where('master_policy.status', '=', $request->pstatus == 1 ? 'Active' : 'Inactive');
            })
            ->orderby('master_policy.policy_id', 'desc')
            ->paginate($request->per_page ?? 25)->withQueryString();
        
        } else {
            $master_policies = MasterPolicy::with('master_product') //insurance_company_id
                ->join('master_company', 'master_company.company_id', '=', 'insurance_company_id')
                ->join('master_product_sub_type', 'master_product_sub_type.product_sub_type_id', '=', 'master_policy.product_sub_type_id')
                ->select('*', 'master_policy.status as master_policy_status')
                ->orderby('master_policy.policy_id', 'desc')
                ->paginate($request->per_page ?? 25);
            
        }
        // dd($master_policies[0]['premium_type']);
        
        return view('master_policy.index', compact('master_policies','pstype', 'mcomp', 'mpremiums',));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return redirect()->back();
        // if (!auth()->user()->can('master_policy.create')) {
        //     return response('unauthorized action', 401);
        // }
        // return DB::table('master_policy')->get();
        // $master_product_sub_types = MasterProductSubType::select('product_sub_type_id','product_sub_type_code')->whereNotIn('product_sub_type_id',[3,4,8])->get();
        // $master_companies = MasterCompany::all();
        // $master_premiums = MasterPremiumType::all();
        // $previous_url = urldecode($request->previous_url) ?? null;

        // return view('master_policy.create', compact('master_product_sub_types', 'master_companies', 'master_premiums','previous_url'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        // return back()->with([
        //     'status' => self::$blockedMessage,
        //     'class' => 'danger',
        // ]);
        $validator = Validator::make($request->all(), [
            'product_sub_type' => 'required',
            'company_name' => 'required',
            'premium_type' => 'required',
            'business_type' => 'required',
            'policy_type' => 'required',
            'premium_online' => 'required',
            'proposal_online' => 'required',
            'payment_online' => 'required',
            'pos_flag' => 'required',
            'owner_type' => 'required',
            'gcv_carrier_type' => 'nullable',
            'zero_dep' => 'required',
            'product_name' => 'required',
            'product_identifier' => 'required',
            'default_discount' => 'nullable|integer|between:0,100',
            'driver_discount' => 'required',
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        try {

            DB::beginTransaction();
            $master_policy = MasterPolicy::create([
                'product_sub_type_id' => $request->product_sub_type,
                'insurance_company_id' => $request->company_name,
                'premium_type_id' => $request->premium_type,
                'business_type' => implode(',', $request->business_type),
                'default_discount' => $request->default_discount,
                'status' => $request->status,
                'policy_type' => $request->policy_type,
                'is_premium_online' => $request->premium_online,
                'is_proposal_online' => $request->proposal_online,
                'is_payment_online' => $request->payment_online,
                'pos_flag' => implode(',', $request->pos_flag),
                'owner_type' => implode(',', $request->owner_type),
                'gcv_carrier_type' => $request->gcv_carrier_type,
                'zero_dep' => $request->zero_dep,
                'good_driver_discount' => $request->driver_discount,
                'created_date' => now(),
            ]);
            $master_policy->master_product()->create([
                'product_name' => $request->product_name,
                'product_identifier' => $request->product_identifier,
                'ic_id' => $request->company_name,
                'status' => $request->status,
            ]);
            DB::commit();

            if ($request->has('prev')) {
                return redirect($request->prev)->with([
                    'status' => 'Product Added Successfully ',
                    'class' => 'success',
                ]);

            } else {
                return redirect()->route('admin.master_policy.index')->with([
                    'status' => 'Product Added Successfully ',
                    'class' => 'success',
                ]);
            }

        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with([
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
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request,$policy_id)
    {
        // return back()->with([
        //     'status' => self::$blockedMessage,
        //     'class' => 'danger',
        // ]);
        $master_product_sub_types = MasterProductSubType::select('product_sub_type_id','product_sub_type_code')->whereNotIn('product_sub_type_id',[3,4,8])->get();
        $master_companies = MasterCompany::all();
        $master_premiums = MasterPremiumType::all();
        $master_policy = MasterPolicy::find($policy_id);
        $master_product = DB::table('master_product')->where('master_policy_id', $policy_id)->first();
        $previous_url = urldecode($request->previous_url) ?? null;

        return view('master_policy.edit', compact('master_product', 'master_policy', 'master_product_sub_types', 'master_companies', 'master_premiums','previous_url'));
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
        // return back()->with([
        //     'status' => self::$blockedMessage,
        //     'class' => 'danger',
        // ]);
        if (!auth()->user()->can('master_policy.list')) {
            return abort(403, 'Unauthorized action.');
        }    
        $validator = Validator::make($request->all(), [
            'product_sub_type' => 'required',
            'company_name' => 'required',
            'premium_type' => 'required',
            'business_type' => 'required',
            'policy_type' => 'required',
            'premium_online' => 'required',
            'proposal_online' => 'required',
            'payment_online' => 'required',
            'pos_flag' => 'required',
            'owner_type' => 'required',
            'gcv_carrier_type' => 'nullable',
            'zero_dep' => 'required',
            'product_name' => 'required',
            'product_identifier' => 'required',
            'default_discount' => 'nullable|integer|between:0,100',
            'driver_discount' => 'required',
            'status' => 'required',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }
        try {
            if (!auth()->user()->can('master_policy.edit')) {
                return abort(403, 'Unauthorized action.');
            }
            $master_policy = MasterPolicy::where('policy_id',$id)->first();
            DB::beginTransaction();
            $master_policy->update([
                    'insurance_company_id' => $request->company_name,
                    'premium_type_id' => $request->premium_type,
                    'business_type' => implode(',', $request->business_type),
                    'default_discount' => $request->default_discount,
                    'status' => $request->status,
                    'policy_type' => $request->policy_type,
                    'is_premium_online' => $request->premium_online,
                    'is_proposal_online' => $request->proposal_online,
                    'is_payment_online' => $request->payment_online,
                    'pos_flag' => implode(',', $request->pos_flag),
                    'owner_type' => implode(',', $request->owner_type),
                    'gcv_carrier_type' => $request->gcv_carrier_type,
                    'zero_dep' => $request->zero_dep,
                    'good_driver_discount' => $request->driver_discount,
                    'updated_date' => now(),
                ]);
            $master_policy->master_product()->update([
                'product_name' => $request->product_name,
                'product_identifier' => $request->product_identifier,
                'ic_id' => $request->company_name,
                'status' => $request->status,
            ]);
            DB::commit();

            if ($request->has('prev'))

                return redirect($request->prev)->with([
                    'status' => 'Product Updated Successfully..!',
                    'class' => 'success',
                ]);

            else {

                return redirect()->route('admin.master-product.index')->with([
                    'status' => 'Product Updated Successfully..!',
                    'class' => 'success',
                ]);
            }

        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update1(Request $request, $id)
    {

        // return back()->with([
        //     'status' => self::$blockedMessage,
        //     'class' => 'danger',
        // ]);
        if (!auth()->user()->can('master_policy.list')) {
            return abort(403, 'Unauthorized action.');
        }
        try {
            if (!auth()->user()->can('master_policy.edit')) {
                return abort(403, 'Unauthorized action.');
            }
            MasterPolicy::where('policy_id', $id)
                ->update([
                    'status' => $request->status,
                    'business_type' => implode(',', $request->business_type),
                ]);
            return redirect()->route('admin.master-product.index')->with([
                'status' => 'Policy status Updated Successfully with Policy ID ' . $id . '..!',
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    public function statusUpdate (Request $request){
   
        // return response()->json([
        //     'status' => false,
        //     'message' => self::$blockedMessage,
        // ]);
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'policy_id' => 'required',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $id = $request->policy_id;
        $status = $request->status;

        try {
            if (!auth()->user()->can('master_policy.edit')) {
                return abort(403, 'Unauthorized action.');
            }
            $master_policy = MasterPolicy::where('policy_id',$id)->first();
            DB::beginTransaction();
            $master_policy->update([
                    'status' => $status,
                    'updated_date' => now(),
                ]);
            $master_policy->master_product()->update([
                'status' => $status,
            ]);
            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Product Updated Successfully..!',
            ]);

        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Something wents wrong ' . $e->getMessage() . '...!',
            ]);
    
        }

    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // return back()->with([
        //     'status' => self::$blockedMessage,
        //     'class' => 'danger',
        // ]);
        MasterPolicy::find($id)->delete();
        MasterProduct::find($id)->delete();
        
        return redirect()->route('admin.master-product.index')->with([
            'status' => 'Product Deleted Successfully..!',
            'class' => 'success',
        ]);
    }
}
