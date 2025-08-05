<?php

namespace App\Http\Controllers\Lte\Admin;
use App\Http\Controllers\Controller;
use App\Models\MasterProductSubType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MasterProductTypeController extends Controller
{

    public function index(Request $request)
    {
        
        $productSubTypes = MasterProductSubType::all();
        $hierarchy = $productSubTypes->groupBy('parent_id');
    
        return view('admin_lte.master_product_type.index', compact('hierarchy'));
    }

    public function store(Request $request)
    {
        if ($request->input('reset_action') === 'reset') {
            $activeSubTypes = MasterProductSubType::where('status', 'Active')->get();
            return redirect()->route('admin.master_product_type.index')
                             ->with('success', 'Recently inactive items have been reset.');
        }
        $validator = Validator::make($request->all(), [
            'product_sub_type_code' => 'required|array|min:1',
            'product_sub_type_code.*' => 'exists:master_product_sub_type,product_sub_type_code',
        ], [
            'product_sub_type_code.required' => 'Please select at least one product type.',
            'product_sub_type_code.min' => 'Please select at least one product type.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                            ->withErrors($validator)
                            ->withInput(); 
        }

        if ($request->has('product_sub_type_code')) {
            $productSubTypeCode = $request->product_sub_type_code;
            $selectedSubTypes = MasterProductSubType::whereIn('product_sub_type_code', $productSubTypeCode)->get();
            $parentIds = $selectedSubTypes->pluck('parent_id')->unique();
    
            $hierarchy = MasterProductSubType::whereIn('product_sub_type_id', $parentIds)
                ->whereNotIn('product_sub_type_code', $productSubTypeCode)
                ->get();
    
            if ($hierarchy->isNotEmpty()) {
                return redirect()->back()->withErrors(['error' => 'Please select the Product for all selected SubProduct items.']);
            }
                $errors = [];

                $parentCodes = ['MISC', 'GCV', 'PCV']; 
                foreach ($parentCodes as $parentCode) {
                    if (in_array($parentCode, $productSubTypeCode)) {
                        $parentSubType = MasterProductSubType::where('product_sub_type_code', $parentCode)->first();
                        if ($parentSubType) {
                            $childSubTypes = MasterProductSubType::where('parent_id', $parentSubType->product_sub_type_id)->get();
                            $childCodes = $childSubTypes->pluck('product_sub_type_code')->toArray();
                            $selectedChildCodes = array_intersect($childCodes, $productSubTypeCode);
                
                            if (empty($selectedChildCodes)) {
                                $errors[] = "If you select {$parentCode}, you must select at least one child category.";
                            }
                        }
                    }
                }
              
                if (!empty($errors)) {
                    return redirect()->back()->withErrors($errors);
                }
    
            MasterProductSubType::whereIn('product_sub_type_code', $productSubTypeCode)
            ->each(function ($model) {
                $model->update(['status' => 'Active']);
            });
    
            MasterProductSubType::whereNotIn('product_sub_type_code', $productSubTypeCode)
            ->each(function ($model) {
                $model->update(['status' => 'Inactive']);
            });
        }
    
        return redirect()->route('admin.master_product_type.index')->with('success', 'Product types updated successfully.');
    }
    
    

}