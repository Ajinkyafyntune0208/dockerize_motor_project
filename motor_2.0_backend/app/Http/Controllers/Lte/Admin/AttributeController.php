<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Models\PremCalcAttributes;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AttributeController extends Controller
{
    public function viewAttribute(Request $request)
    {

        if (!auth()->user()->can('view_attributes.list')) {
            return response('unauthorized action', 401);
        }
        $query = PremCalcAttributes::query();


        $alias = PremCalcAttributes::select('ic_alias')->distinct()->get();

        if ($request->ic_alias) {
            $query->where('ic_alias', $request->ic_alias);
        }
        if ($request->integration_type) {
            $query->where('integration_type', $request->integration_type);
        }
        if ($request->segment) {
            $query->where('segment', $request->segment);
        }
        if ($request->business_type) {
            $query->where('business_type', $request->business_type);
        }

        $attributes = $query->get();

        if ($request->ajax()) {
            return response()->json(['attributes' => $attributes]);
        }

        return view('admin_lte.ics.view_attribute', compact('attributes', 'alias'));
    }
}
