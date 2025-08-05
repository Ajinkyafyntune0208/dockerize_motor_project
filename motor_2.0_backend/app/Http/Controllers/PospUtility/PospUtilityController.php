<?php

namespace App\Http\Controllers\PospUtility;

use App\Http\Controllers\Controller;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\PospUtility;
use App\Models\PospUtilityIcParameter;
use App\Models\PospUtilityRto;
use App\Models\PospUtilityMmv;
use App\Models\CvAgentMapping;
use App\Models\MasterRto;
use App\Models\PospUtilityImd;
use App\Models\PospUtilityImdMapping;
use App\Models\PospUtilityImdSellerMapping;

class PospUtilityController extends Controller
{
    /**
     * All the form save logic should be written in below function.
     */
    public function addIcParameters(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'seller_type' => 'required|string',
            'source' => 'required|in:DASHBOARD,MOTOR',
            'segment_id' => 'required|integer',
            'matrix' => 'required',
            'source_user_id' => 'required|integer',
            'ic_integration_type' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }
        $validatedData = $validator->validated();
        $pospKeys = array_keys($validatedData['matrix']);
        $commonData = [
            'seller_type' => $validatedData['seller_type'],
            'source' => $validatedData['source'],
            'segment_id' => $validatedData['segment_id'],
            'ic_integration_type' => $validatedData['ic_integration_type'],
            'created_by' => $validatedData['source_user_id'],
            'created_source' => $validatedData['source'],
            'created_at' => now()
        ];
        if (isset($commonData['seller_type']) && $commonData['seller_type'] === 'B2C') {
            if (count($validatedData['matrix']) !== 1) {
                return $validator->errors()->add('matrix', 'For seller type B2C, matrix should have only one object.');
            }
        }
        foreach ($pospKeys as $posp) {
            if ($request->input('seller_type') == "B2C") {
                $posp = 0;
            }
            $exists = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->exists();
            if (!$exists) {
                $posp_data = new PospUtility();
                $posp_data->seller_user_id = $posp;
                $posp_data->seller_type = $request->input('seller_type');
                $posp_data->created_at = now();
                $posp_data = $posp_data->save();
            }
            $posp_data = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->first();
            $utility_id = $posp_data->utility_id;
            $matrixData = $validatedData['matrix'][$posp];
            $imd_id = $matrixData['parameters']['imd_id'];
            $exists_utility = PospUtilityIcParameter::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->exists();
            if ($exists_utility) {

                $ic_param = PospUtilityIcParameter::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->first();
                $ic_param->matrix = $matrixData['products'];
                $ic_param->imd_id = $imd_id;
                $ic_param->updated_by = $commonData['created_by'];
                $ic_param->updated_source = $commonData['created_source'];
                $ic_param->updated_at = now();
                $ic_param = $ic_param->save();
            } else {
                $ic_param = new PospUtilityIcParameter();
                $ic_param->utility_id = $utility_id;
                $ic_param->segment_id = $validatedData['segment_id'];
                $ic_param->ic_integration_type = $validatedData['ic_integration_type'];
                $ic_param->matrix = $matrixData['products'];
                $ic_param->imd_id = $imd_id;
                $ic_param->created_by = $commonData['created_by'];
                $ic_param->created_source = $commonData['created_source'];
                $ic_param->created_at = now();
                $ic_param = $ic_param->save();
            }
        }
        return response()->json(['message' => 'Records processed successfully'], 201);
    }

    public function addMmvUtility(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'seller_type' => 'required|string',
            'source' => 'required|in:DASHBOARD,MOTOR',
            'segment_id' => 'required|integer',
            'matrix' => 'required',
            'source_user_id' => 'required|integer',
            'ic_name' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }
        $validatedData = $validator->validated();
        $pospKeys = array_keys($validatedData['matrix']);

        $commonData = [
            'seller_type' => $validatedData['seller_type'],
            'source' => $validatedData['source'],
            'segment_id' => $validatedData['segment_id'],
            'ic_integration_type' => $validatedData['ic_name'],
            'created_by' => $validatedData['source_user_id'],
            'created_source' => $validatedData['source'],
            'created_at' => now()
        ];
        if (isset($commonData['seller_type']) && $commonData['seller_type'] === 'B2C') {
            if (count($validatedData['matrix']) !== 1) {
                return $validator->errors()->add('matrix', 'For seller type B2C, matrix should have only one object.');
            }
        }
        foreach ($pospKeys as $posp) {
            if ($request->input('seller_type') == "B2C") {
                $posp = 0;
            }
            $exists = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->exists();
            if (!$exists) {
                $posp_data = new PospUtility();
                $posp_data->seller_user_id = $posp;
                $posp_data->seller_type = $request->input('seller_type');
                $posp_data->created_at = now();
                $posp_data = $posp_data->save();
            }
            $posp_data = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->first();
            $utility_id = $posp_data->utility_id;
            $matrixData = $validatedData['matrix'][$posp];

            $exists_utility = PospUtilityMmv::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_name'])->exists();
            if ($exists_utility) {

                $ic_param = PospUtilityMmv::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_name'])->first();
                $ic_param->matrix = $matrixData;
                $ic_param->updated_by = $commonData['created_by'];
                $ic_param->updated_source = $commonData['created_source'];
                $ic_param->updated_at = now();
                $ic_param = $ic_param->save();
            } else {
                $ic_param = new PospUtilityMmv();
                $ic_param->utility_id = $utility_id;
                $ic_param->segment_id = $validatedData['segment_id'];
                $ic_param->ic_integration_type = $validatedData['ic_name'];
                $ic_param->matrix = $matrixData;
                $ic_param->created_by = $commonData['created_by'];
                $ic_param->created_source = $commonData['created_source'];
                $ic_param->created_at = now();
                $ic_param = $ic_param->save();
            }
        }
        return response()->json(['message' => 'Records processed successfully'], 201);
    }

    public function addRtoUtility(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'seller_type' => 'required|string',
            'source' => 'required|in:DASHBOARD,MOTOR',
            'segment_id' => 'required|integer',
            'matrix' => 'required',
            'source_user_id' => 'required|integer',
            'ic_name' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }
        $validatedData = $validator->validated();
        $pospKeys = array_keys($validatedData['matrix']);

        $commonData = [
            'seller_type' => $validatedData['seller_type'],
            'source' => $validatedData['source'],
            'segment_id' => $validatedData['segment_id'],
            'ic_integration_type' => $validatedData['ic_name'],
            'created_by' => $validatedData['source_user_id'],
            'created_source' => $validatedData['source'],
            'created_at' => now()
        ];
        if (isset($commonData['seller_type']) && $commonData['seller_type'] === 'B2C') {
            if (count($validatedData['matrix']) !== 1) {
                return $validator->errors()->add('matrix', 'For seller type B2C, matrix should have only one object.');
            }
        }
        foreach ($pospKeys as $posp) {
            if ($request->input('seller_type') == "B2C") {
                $posp = 0;
            }
            $exists = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->exists();
            if (!$exists) {
                $posp_data = new PospUtility();
                $posp_data->seller_user_id = $posp;
                $posp_data->seller_type = $request->input('seller_type');
                $posp_data->created_at = now();
                $posp_data = $posp_data->save();
            }
            $posp_data = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->first();
            $utility_id = $posp_data->utility_id;
            $matrixData = $validatedData['matrix'][$posp];

            $exists_utility = PospUtilityRto::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_name'])->exists();
            if ($exists_utility) {

                $ic_param = PospUtilityRto::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_name'])->first();
                $ic_param->matrix = $matrixData;
                $ic_param->updated_by = $commonData['created_by'];
                $ic_param->updated_source = $commonData['created_source'];
                $ic_param->updated_at = now();
                $ic_param = $ic_param->save();
            } else {
                $ic_param = new PospUtilityRto();
                $ic_param->utility_id = $utility_id;
                $ic_param->segment_id = $validatedData['segment_id'];
                $ic_param->ic_integration_type = $validatedData['ic_name'];
                $ic_param->matrix = $matrixData;
                $ic_param->created_by = $commonData['created_by'];
                $ic_param->created_source = $commonData['created_source'];
                $ic_param->created_at = now();
                $ic_param = $ic_param->save();
            }
        }
        return response()->json(['message' => 'Records processed successfully'], 201);
    }

    public function getPospImdMapping(Request $request)
    {
        $request['company_slug'] = explode('.', $request['company_alias'])[0];
        $rules = [
            'product_sub_type_id' => 'required|exists:master_product_sub_type,product_sub_type_id,status,Active',
            'company_slug' => 'required|exists:master_company,company_alias',
            'seller_type' => 'required|in:POSP,MISP,B2C,EMPLOYEES,PARTNER',
        ];
        if (is_array($request->seller_user_id)) {
            $rules = array_merge($rules, [
                'seller_user_id' => 'required|array',
                'seller_user_id.*' => 'required|integer',
            ]);
        } else {
            $rules = array_merge($rules, [
                'seller_user_id' => 'required|in:all',
            ]);
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors()
            ]);
        }
        if ($request->seller_user_id === 'all') {
            $request['seller_user_id'] = PospUtility::pluck('seller_user_id');
        }
        $all_data = PospUtilityIcParameter::select('posp_utility_ic_parameter.ic_param_id', 'posp_utility_ic_parameter.imd_id', 'posp_utility_imd.imd_code', 'posp_utility_ic_parameter.utility_id', 'posp_utility_ic_parameter.segment_id as product_sub_type_id', 'posp_utility_ic_parameter.ic_integration_type', 'posp_utility_ic_parameter.created_by', 'posp_utility_ic_parameter.updated_by', 'posp_utility_ic_parameter.created_at', 'posp_utility_ic_parameter.updated_at', 'posp_utility_ic_parameter.deleted_at', 'posp_utility_ic_parameter.created_source', 'posp_utility_ic_parameter.updated_source', 'posp_utility.seller_user_id', 'posp_utility.seller_type', 'posp_utility_ic_parameter.matrix')
        ->join('posp_utility_imd', 'posp_utility_imd.imd_id', 'posp_utility_ic_parameter.imd_id')
        ->join('posp_utility', 'posp_utility.utility_id', 'posp_utility_ic_parameter.utility_id')
        ->where('segment_id', $request->product_sub_type_id)
        ->where('posp_utility_ic_parameter.ic_integration_type', $request->company_alias)
        ->where('posp_utility.seller_type', $request->seller_type)
        ->whereIn('posp_utility.seller_user_id', $request->seller_user_id)
        ->get();
        if ($all_data->count()) {
            return response()->json([
                'status' => true,
                'message' => 'All Record Found.',
                'data' => $all_data
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'No Record Found.',
            'data' => []
        ], 500);
    }
    public function getMmvMapping(Request $request)
    {
        $request['company_slug'] = explode('.', $request['company_alias'])[0];
        $rules = [
            'product_sub_type_id' => 'required|exists:master_product_sub_type,product_sub_type_id,status,Active',
            'company_slug' => 'required|exists:master_company,company_alias',
            'seller_type' => 'required|in:POSP,MISP,B2C,EMPLOYEES,PARTNER',
            'make_id' => 'nullable|integer',
            'model_id' => 'nullable|integer',
            'variant' => 'nullable|string',
            'fuel' => 'nullable|string',
        ];
        if (is_array($request->seller_user_id)) {
            $rules = array_merge($rules, [
                'seller_user_id' => 'required|array',
                'seller_user_id.*' => 'required|integer',
            ]);
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors()
            ]);
        }
        $all_data = PospUtilityMmv::select('posp_utility_mmv.utility_mmv_id', 'posp_utility_mmv.utility_id', 'posp_utility_mmv.segment_id as product_sub_type_id', 'posp_utility_mmv.ic_integration_type', 'posp_utility_mmv.created_by', 'posp_utility_mmv.updated_by', 'posp_utility_mmv.created_at', 'posp_utility_mmv.updated_at', 'posp_utility_mmv.deleted_at', 'posp_utility_mmv.created_source', 'posp_utility_mmv.updated_source', 'posp_utility.seller_user_id', 'posp_utility.seller_type', 'posp_utility_mmv.matrix')
            ->join('posp_utility', 'posp_utility.utility_id', 'posp_utility_mmv.utility_id')
            ->where('posp_utility_mmv.segment_id', $request->product_sub_type_id)
            ->where('posp_utility_mmv.ic_integration_type', $request->company_alias)
            ->where('posp_utility.seller_type', $request->seller_type)
            ->whereIn('posp_utility.seller_user_id', $request->seller_user_id);
        $all_data = $all_data->get();
        $filteredResults = [];
        foreach ($all_data as $result) {
            if ($result->matrix['allowed'] !== null) {
                foreach ($result->matrix['allowed'] as $allowed) {
                    $makeIdMatch = !isset($request->make_id) || $allowed['make_id'] === $request->make_id;
                    $modelIdMatch = !isset($request->model_id) || $allowed['model_id'] === $request->model_id;
                    $variantMatch = !isset($request->variant) || (isset($allowed['variant']) && in_array($request->variant, $allowed['variant']));
                    $fuelMatch = !isset($request->fuel) || (isset($allowed['fuel']) && in_array($request->fuel, $allowed['fuel']));

                    if ($makeIdMatch && $modelIdMatch && $variantMatch && $fuelMatch) {
                        $filteredResults[] = $result;
                        break;
                    }
                }
            }

            if ($result->matrix['denied'] !== null) {
                foreach ($result->matrix['denied'] as $denied) {
                    $makeIdMatch = !isset($request->make_id) || $denied['make_id'] === $request->make_id;
                    $modelIdMatch = !isset($request->model_id) || $denied['model_id'] === $request->model_id;
                    $variantMatch = !isset($request->variant) || (isset($denied['variant']) && in_array($request->variant, $denied['variant']));
                    $fuelMatch = !isset($request->fuel) || (isset($denied['fuel']) && in_array($request->fuel, $denied['fuel']));

                    if ($makeIdMatch && $modelIdMatch && $variantMatch && $fuelMatch) {
                        $filteredResults[] = $result;
                        break;
                    }
                }
            }
        }

        if (count($filteredResults)) {
            $filteredResults = array_values(array_map("unserialize", array_unique(array_map("serialize", $filteredResults))));
            return response()->json([
                'status' => true,
                'message' => 'All Record Found.',
                'data' => $filteredResults
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'No Record Found.',
            'data' => []
        ], 500);
    }
    public function getRtoMapping(Request $request)
    {
        $request['company_slug'] = explode('.', $request['company_alias'])[0];
        $rules = [
            'product_sub_type_id' => 'required|exists:master_product_sub_type,product_sub_type_id,status,Active',
            'company_slug' => 'required|exists:master_company,company_alias',
            'seller_type' => 'required|in:POSP,MISP,B2C,EMPLOYEES,PARTNER',
            'seller_user_id' => 'required|array',
            'seller_user_id.*' => 'required|integer',
            'state_id' => 'required|integer',
            'rto_id' => 'required|array',
            'rto_id.*' => 'required|integer',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors()
            ]);
        }
        $all_data = PospUtilityRto::select('posp_utility_rto.utility_rto_id', 'posp_utility_rto.utility_id', 'posp_utility_rto.segment_id as product_sub_type_id', 'posp_utility_rto.ic_integration_type', 'posp_utility_rto.created_by', 'posp_utility_rto.updated_by', 'posp_utility_rto.created_at', 'posp_utility_rto.updated_at', 'posp_utility_rto.deleted_at', 'posp_utility_rto.created_source', 'posp_utility_rto.updated_source', 'posp_utility.seller_user_id', 'posp_utility.seller_type', 'posp_utility_rto.matrix')
            ->join('posp_utility', 'posp_utility.utility_id', 'posp_utility_rto.utility_id')
            ->where('posp_utility_rto.segment_id', $request->product_sub_type_id)
            ->where('posp_utility_rto.ic_integration_type', $request->company_alias)
            ->where('posp_utility.seller_type', $request->seller_type)
            ->whereIn('posp_utility.seller_user_id', $request->seller_user_id)
            ->get();
        $filteredResults = [];
        foreach ($all_data as $result) {
            if ($result->matrix['allowed'] !== null) {
                foreach ($result->matrix['allowed'] as $allowed) {
                    $stateMatch = !isset($request->state_id) || $allowed['state_id'] === $request->state_id;
                    $rtoMatch = !isset($request->rto_id) || count(array_intersect($allowed['rto_id'] ?? [], $request->rto_id)) > 0;

                    if ($stateMatch && $rtoMatch) {
                        $filteredResults[] = $result;
                        break;
                    }
                }
            }

            if ($result->matrix['denied'] !== null) {
                foreach ($result->matrix['denied'] as $denied) {
                    $stateMatch = !isset($request->state_id) || $denied['state_id'] === $request->state_id;
                    $rtoMatch = !isset($request->rto_id) || count(array_intersect($denied['rto_id'] ?? [], $request->rto_id)) > 0;

                    if ($stateMatch && $rtoMatch) {
                        $filteredResults[] = $result;
                        break;
                    }
                }
            }
        }
        if (count($filteredResults)) {
            return response()->json([
                'status' => true,
                'message' => 'All Record Found.',
                'data' => $filteredResults
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'No Record Found.',
            'data' => []
        ], 500);
    }

    public function updatePospUtility(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'seller_type' => 'required|string',
            'source' => 'required|in:DASHBOARD,MOTOR',
            'segment_id' => 'required|integer',
            'source_user_id' => 'required|integer',
            'ic_integration_type' => 'required|string',
            'module_name' => 'required|in:MMV,RTO,IC-PARAMETER',
            "operation" => 'required|in:delete,recover',
            'seller_type_id' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }
        $validatedData = $validator->validated();
        $pospKeys = $validatedData['seller_type_id'];
        if ($validatedData['module_name'] == "MMV") {
            foreach ($pospKeys as $posp) {
                $exists = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->exists();
                if ($exists) {
                    $posp_data = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->first();
                    $utility_id = $posp_data->utility_id;
                }
                if ($validatedData['operation'] == 'delete') {
                    $exists_utility = PospUtilityMmv::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->exists();
                    if ($exists_utility) {
                        $ic_param = PospUtilityMmv::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->first();
                        $ic_param = $ic_param->delete();
                    }
                } else {
                    $exists_utility = PospUtilityMmv::onlyTrashed()->where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->exists();
                    if ($exists_utility) {
                        $ic_param = PospUtilityMmv::onlyTrashed()->where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->first();
                        $ic_param = $ic_param->restore();
                    }
                }
            }
        } elseif ($validatedData['module_name'] == "RTO") {
            foreach ($pospKeys as $posp) {
                $exists = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->exists();
                if ($exists) {
                    $posp_data = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->first();
                    $utility_id = $posp_data->utility_id;
                }
                if ($validatedData['operation'] == 'delete') {
                    $exists_utility = PospUtilityRto::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->exists();
                    if ($exists_utility) {
                        $ic_param = PospUtilityRto::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->first();

                        $ic_param = $ic_param->delete();
                    }
                } else {
                    $exists_utility = PospUtilityRto::onlyTrashed()->where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->exists();
                    if ($exists_utility) {
                        $ic_param = PospUtilityRto::onlyTrashed()->where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->first();
                        $ic_param = $ic_param->restore();
                    }
                }
            }
        } elseif ($validatedData['module_name'] == "IC-PARAMETER") {
            foreach ($pospKeys as $posp) {
                $exists = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->exists();
                if ($exists) {
                    $posp_data = PospUtility::where('seller_user_id', '=', $posp)->where('seller_type', '=', $request->input('seller_type'))->first();
                    $utility_id = $posp_data->utility_id;
                }
                if ($validatedData['operation'] == 'delete') {
                    $exists_utility = PospUtilityIcParameter::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->exists();
                    if ($exists_utility) {
                        $ic_param = PospUtilityIcParameter::where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->first();
                        $ic_param = $ic_param->delete();
                    }
                } else {
                    $exists_utility = PospUtilityIcParameter::onlyTrashed()->where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->exists();
                    if ($exists_utility) {
                        $ic_param = PospUtilityIcParameter::onlyTrashed()->where('utility_id', '=', $utility_id)->where('segment_id', '=', $validatedData['segment_id'])->where('ic_integration_type', '=', $validatedData['ic_integration_type'])->first();
                        $ic_param = $ic_param->restore();
                    }
                }
            }
        }

        return response()->json(['message' => 'Records processed successfully'], 201);
    }

    public function addImd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imd_code' => 'required',
            'seller_type' => 'required',
            'segment_id' => 'required|integer',
            'imd_fields' => 'required',
            'ic_integration_type' => 'required|string',
            'source' => 'required|in:DASHBOARD,MOTOR',
            'source_user_id' => 'required|integer'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }
       
        
        
        // Check if the mapping already exists
        $seller_types = $request->input('seller_type');
        $seller_types = is_array($seller_types) ? $seller_types : [$seller_types];
        $imd_mapping_exist = PospUtilityImdMapping::where('segment_id', '=', $request->input('segment_id'))
            ->where('ic_integration_type', '=', $request->input('ic_integration_type'))
            ->whereIn('seller_type', $seller_types)
            ->exists();
        
        if ($imd_mapping_exist) {
            return response()->json([
                'status' => false,
                'message' => "Imd code already inserted for this IC and Segment please update for seller type"
            ]);
        } else {
            $imd_exists = PospUtilityImd::where('imd_code', '=', $request->input('imd_code'))->exists();

            if (!$imd_exists) {
                // Create a new IMD record
                $imd_data = new PospUtilityImd();
                $imd_data->imd_code = $request->input('imd_code');
                $imd_data->imd_fields_data = $request->input('imd_fields');
                $imd_data->created_by = $request->input('source_user_id');
                $imd_data->created_source = $request->input('source');
                $imd_data->created_at = now();
                $imd_data->save();
                $imd_data = PospUtilityImd::where('imd_code', '=', $request->input('imd_code'))->first();
                $imd_id = $imd_data->imd_id;
            } else {
                $imd_data = PospUtilityImd::where('imd_code', '=', $request->input('imd_code'))->first();
                $imd_id = $imd_data->imd_id;
            }
            
            // Create a new IMD mapping record
            foreach ($seller_types as $seller_type) {
                $imd_mapping_data = new PospUtilityImdMapping();
                $imd_mapping_data->segment_id = $request->input('segment_id');
                $imd_mapping_data->ic_integration_type = $request->input('ic_integration_type');
                $imd_mapping_data->imd_id = $imd_id;
                $imd_mapping_data->seller_type = $seller_type;
                $imd_mapping_data->created_by = $request->input('source_user_id');
                $imd_mapping_data->save();
            }
        
            return response()->json([
                'status' => true,
                'message' => "Record inserted successfully"
            ]);
        }
    }

    public function updateImd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imd_id' =>  'required|integer',
            'imd_code' => 'string',
            'source' => 'required|in:DASHBOARD,MOTOR',
            'source_user_id' => 'required|integer'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }
        $imd_exists = PospUtilityImd::where('imd_id', '=', $request->input('imd_id'))->exists();
        if (!$imd_exists) {
            return response()->json([
                'status' => false,
                'message' => "Imd id doesn't exists"
            ]);
        } else {
                $imd_data = PospUtilityImd::where('imd_id', '=', $request->input('imd_id'))->first();
                if ($request->has('imd_code')) {
                    $imd_data->imd_code = $request->input('imd_code');
                }
                if ($request->has('imd_fields')) {
                    $imd_data->imd_fields_data = $request->input('imd_fields');
                }
                $imd_data->updated_source = $request->source;
                $imd_data->updated_at = now();
                $imd_data->save();

                if ($request->has('seller_type')) {
                    $validator = Validator::make($request->all(), [
                        'segment_id' =>  'required|integer',
                        'ic_integration_type' => 'required|string'
                    ]);
                    if ($validator->fails()) {
                        return response()->json([
                            'status' => false,
                            'message' => $validator->errors(),
                        ]);
                    }
                    $seller_types = $request->input('seller_type');
                    $seller_types = is_array($seller_types) ? $seller_types : [$seller_types];
                    foreach ($seller_types as $seller_type) {
                        $imd_mapping_exist = PospUtilityImdMapping::where('imd_id', '=', $request->input('imd_id'))->where('segment_id', '=', $request->input('segment_id'))
                        ->where('ic_integration_type', '=', $request->input('ic_integration_type'))
                        ->where('seller_type', $seller_type)
                        ->exists();
                        if(!$imd_mapping_exist){
                            $imd_mapping_data = new PospUtilityImdMapping();
                            $imd_mapping_data->segment_id = $request->input('segment_id');
                            $imd_mapping_data->ic_integration_type = $request->input('ic_integration_type');
                            $imd_mapping_data->imd_id = $request->input('imd_id');
                            $imd_mapping_data->seller_type = $seller_type;
                            $imd_mapping_data->updated_by = $request->input('source_user_id');
                            $imd_mapping_data->save();
                        }
                    }
                }
                return response()->json([
                    'status' => true,
                    'message' => "Record updated successfully"
                ]);
        }
    }

    public function deleteImd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imd_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }
        $imd_exists = PospUtilityImd::where('imd_id', '=', $request->input('imd_id'))->exists();
        if (!$imd_exists) {
            return response()->json([
                'status' => false,
                'message' => "Imd id doesn't exists"
            ]);
        } else {
            try {
                DB::transaction(function () use ($request) {
                    $imd_id =  $request->input('imd_id');
                    $isImdIdUsed = PospUtilityIcParameter::where('imd_id', $imd_id)->exists();
                    if ($isImdIdUsed) {
                        throw new \Exception("Imd is used in posp utility and cannot be deleted.");
                    }
                
                    // Delete related records from PospUtilityImdMapping
                    PospUtilityImdMapping::where('imd_id', '=', $imd_id)->delete();
                
                    // Delete the record from PospUtilityImd
                    PospUtilityImd::where('imd_id', '=', $imd_id)->delete();
                });
                
                return response()->json([
                    'status' => true,
                    'message' => 'Record and related data deleted successfully'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage()
                ]);
            }
        }
    }

    public function listImd(Request $request)
    {
        if ($request->has('imd_id')) {
            $imd_data = PospUtilityImd::where('imd_id', '=', $request->imd_id)->first();
                if ($imd_data) {
                    return response()->json([
                        'status' => true,
                        'message' => 'All Record Found.',
                        'imd_data' => $imd_data
                    ]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'No Record Found.',
                        'data' => []
                    ], 500);
                }
        } else {
            $validator = Validator::make($request->all(), [
                'segment_id' => 'required|integer',
                'ic_integration_type' => 'required|string',
                'seller_type' => 'required'
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors(),
                ]);
            }
            $imd_mapping_exist = PospUtilityImdMapping::where('segment_id', '=', $request->input('segment_id'))
            ->where('ic_integration_type', '=', $request->input('ic_integration_type'))
            ->exists();
            if(!$imd_mapping_exist){
                return response()->json([
                    'status' => false,
                    'message' => "No record found for this IC and Segment"
                ]);
            } else {
                $seller_type = $request->input('seller_type');
                $imd_mapping_query = PospUtilityImdMapping::where('segment_id', $request->input('segment_id'))
                    ->where('ic_integration_type', $request->input('ic_integration_type'));

                if (is_array($seller_type)) {
                    $imd_mapping_query->whereIn('seller_type', $seller_type);
                } else {
                    $imd_mapping_query->where('seller_type', $seller_type);
                }

                $imd_mapping_data = $imd_mapping_query->first();
                if ($imd_mapping_data) {
                    $imd_id = $imd_mapping_data->imd_id;
                    $imd_data = PospUtilityImd::where('imd_id', $imd_id)->first();
        
                    if ($imd_data) {
                        return response()->json([
                            'status' => true,
                            'message' => 'All Record Found.',
                            'imd_data' => $imd_data
                        ]);
                    } else {
                        return response()->json([
                            'status' => false,
                            'message' => 'No Record Found.',
                            'data' => []
                        ], 500);
                    }
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'No Record Found.',
                        'data' => []
                    ], 500);
                }
            }
        }
    }

    public static function filterIcParam($returnArray, $enquiryId)
    {
       if(config('ENABLE_POSP_UTILITY_BUSINESSTYPE') == 'Y'){
            $agentDetails = CvAgentMapping::select('agent_id', 'seller_type')->where('user_product_journey_id', customDecrypt($enquiryId))->first();

            if (empty($agentDetails)) {
                $agentId = 0;
                $seller_type ="B2C";
            }elseif(!empty($agentDetails) && $agentDetails->seller_type == 'P'){
                $agentId = $agentDetails->agent_id;
                $seller_type = 'POSP';
            }elseif(!empty($agentDetails) && $agentDetails->seller_type == 'E'){
                $agentId = $agentDetails->agent_id;
                $seller_type = 'EMPLOYEE';
            } else{
                $agentId = $agentDetails->agent_id;
                $seller_type = \Illuminate\Support\Str::upper($agentDetails->seller_type);
            }

            $pospData = PospUtility::where('seller_user_id', $agentId)->where('seller_type', $seller_type)->first();
            if (empty($pospData)) {
                return $returnArray;
            }
            $get_data = PospUtilityIcParameter::select('matrix', 'ic_integration_type')->where('utility_id', $pospData['utility_id'])->pluck('matrix', 'ic_integration_type');

            if ($get_data->isEmpty()) {
                return $returnArray;
            }

            $data = CorporateVehiclesQuotesRequest::select('business_type', 'policy_type', 'is_renewal', 'rollover_renewal')->where('user_product_journey_id', customDecrypt($enquiryId))->first();
     
            if($data['is_renewal'] == "Y" && $data['rollover_renewal'] == "N"){
                $data['business_type'] = "renewal";
            }
 
            foreach($get_data as $company => $matrix){
                foreach ($returnArray as $productKey => $productType) {
                    $returnArray[$productKey] =  array_values(array_map(function($value) use ($productKey, $data, $company, $matrix) {
                            $company_alias = explode(".", $company)[0];
                            $matrixBusinessType = $matrix;

                            $comprehensive_policy = ['own_damage', 'own_damage_breakin', 'comprehensive', 'breakin'];
                            $thirdParty_policy = ['third_party', 'third_party_breakin'];
                            $shortTerm_policy = ['short_term_3', 'short_term_3_breakin', 'short_term_6', 'short_term_6_breakin'];
                    
                            if($productKey == 'comprehensive' && $value['companyAlias'] == $company_alias && in_array($value['premiumTypeCode'], $comprehensive_policy) ){
                                if(!in_array($data['policy_type'], $matrixBusinessType[$data->business_type])){
                                $value['isBlocked'] = 1;
                                $value['blockStatusCode'] = 'businessType:'.$data->business_type.'.'.$productKey;
                                $value['blockedMessage'] = 'The quotation for this Insurance Company is blocked.';
                                return $value;
                                // return false;
                                }elseif((!in_array($productKey, $matrixBusinessType[$data->business_type]) && !str_contains($value['premiumTypeCode'], $data['policy_type']))){
                                $value['isBlocked'] = 1;
                                $value['blockStatusCode'] = 'businessType:'.$data->business_type.'.'.$productKey;
                                $value['blockedMessage'] = 'The quotation for this Insurance Company is blocked.';
                                return $value;
                                // return false;
                                }
                            }elseif($productKey == 'third_party' && $value['companyAlias'] == $company_alias && in_array($value['premiumTypeCode'], $thirdParty_policy) ){
                                if((!in_array($productKey, $matrixBusinessType[$data->business_type]))){
                                $value['isBlocked'] = 1;
                                $value['blockStatusCode'] = 'businessType:'.$data->business_type.'.'.$productKey;
                                $value['blockedMessage'] = 'The quotation for this Insurance Company is blocked.';
                                return $value;
                                // return false;
                                }
                            }elseif($productKey == 'short_term' && $value['companyAlias'] == $company_alias && in_array($value['premiumTypeCode'], $shortTerm_policy) ){
                                if((!in_array($productKey, $matrixBusinessType[$data->business_type]))){
                                $value['isBlocked'] = 1;
                                $value['blockStatusCode'] = 'businessType:'.$data->business_type.'.'.$productKey;
                                $value['blockedMessage'] = 'The quotation for this Insurance Company is blocked.';
                                return $value;
                                // return false;
                                }elseif(!in_array($data['policy_type'], $matrixBusinessType[$data->business_type])){
                                $value['isBlocked'] = 1;
                                $value['blockStatusCode'] = 'businessType:'.$data->business_type.'.'.$data['policy_type'];
                                $value['blockedMessage'] = 'The quotation if this Insurance Company is blocked.';
                                return $value;
                                // return false;
                                }
                            }
                        return $value;
                        // return true;
                    }, $productType));
                }
            }
        }
        return $returnArray;
    }

    public static function filterRtoPospUtility($newarray, $enquiryId)
    {
        if (config("POSPUTILITY_RTO_ENABLE") == 'Y') {
            //customDecrypt($enquiryId)//3166
            $id = CvAgentMapping::select('agent_id', 'seller_type')
                ->where('user_product_journey_id', customDecrypt($enquiryId))
                ->first();
            if (empty($id)) {
                $agent_id = 0;
                $seller_type = 'B2C';
            } elseif (!empty($id) && $id->seller_type == 'P') {
                $agent_id = $id->agent_id;
                $seller_type = 'POSP';
            } elseif (!empty($id) && $id->seller_type == 'E') {
                $agent_id = $id->agent_id;
                $seller_type = 'EMPLOYEE';
            } else {
                $agent_id = $id->agent_id;
                $seller_type = \Illuminate\Support\Str::upper($id->seller_type);
            }

            $get_data = PospUtility::from('posp_utility as u')
                ->join('posp_utility_rto as p', 'u.utility_id', '=', 'p.utility_id')
                ->select('u.utility_id', 'p.ic_integration_type', 'p.matrix')
                ->where('u.seller_user_id', $agent_id)
                ->Where('u.seller_type', $seller_type)
                ->get();

            if ($get_data->isEmpty()) {
                return $newarray;
            }
            $get_rto_code = CorporateVehiclesQuotesRequest::select('rto_code')->where('user_product_journey_id', customDecrypt($enquiryId))->first();
            if (!empty($get_rto_code)) {
                $get_id = MasterRto::select('rto_id', 'state_id')->where('rto_code', $get_rto_code->rto_code)->first();
                $getId = $get_id->rto_id;
                $state_id = $get_id->state_id;
            }

            $get_data = $get_data->pluck('matrix', 'ic_integration_type');
            foreach ($newarray as $productKey => $productType) {
                $newarray[$productKey] = array_values(array_map(function ($value) use ($get_data, $productType, $getId, $state_id) {
                    $ic_type = $value['companyAlias'] . '.' . match(true) {
                        $value['goodDriverDiscount'] == 'Y' => 'gdd',
                        $value['premiumTypeCode'] == 'short_term_3' => 'short_term_3',
                        $value['premiumTypeCode'] == 'short_term_6' => 'short_term_6',
                        default => 'basic',
                    };

                    if ($ic_type == $value['companyAlias'].'.basic') {
                        $ic_type = $value['companyAlias'];
                    }

                    if (isset($get_data[$ic_type])) {
                        // check allowed and denied condition
                        $matrix = json_decode($get_data[$ic_type]);
                        $denied = $matrix->denied;
                        $allowed = $matrix->allowed;
                        if (!is_null($denied)) {
                            foreach ($denied as $d_rto) {
                                if ($state_id == $d_rto->state_id && is_null($d_rto->rto_id)) {
                                    $value['isBlocked'] = 1;
                                    $value['blockStatusCode'] = 'rto:state';
                                    $value['blockedMessage'] = 'This RTO is blocked';
                                    return $value;
                                    // return false;
                                } elseif ($state_id == $d_rto->state_id && !is_null($d_rto->rto_id) && in_array($getId, $d_rto->rto_id)) {
                                    // return false;
                                    $value['isBlocked'] = 1;
                                    $value['blockStatusCode'] = 'rto:rto_code';
                                    $value['blockedMessage'] = 'This RTO is blocked';
                                    return $value;
                                }
                            }
                        }
                        if (!is_null($allowed)) {
                            foreach ($allowed as $a_rto) {
                                if ($state_id == $a_rto->state_id && is_null($a_rto->rto_id)) {
                                    return $value;
                                } elseif ($state_id == $a_rto->state_id && !is_null($a_rto->rto_id) && in_array($getId, $a_rto->rto_id)) {
                                    return $value;
                                }
                            }
                        }
                        //If both allowed and denied are null than every things allowed.
                        return $value;
                    } else {
                        return $value;
                    }
                }, $productType));
            }
            // return $newarray;
        }
        return $newarray;
    }
    static public function filterProducts($singleRecord, $mmv)
    {
        if (!isset($singleRecord['matrix']['denied']) && !isset($singleRecord['matrix']['allowed'])) {
            return true; // make it false if you dont want to show qoutes in this case
        } else {
            $blockType = [
                'allType' => []
            ];
            $return_val = true;
            // denied check
            if ($return_val == true && isset($singleRecord['matrix']['denied'])) {
                foreach ($singleRecord['matrix']['denied'] as $denied_rule) {
                    $denied_return_val = false;
                    foreach ($denied_rule as $key => $value) {
                        if ($value == null || $value == 'all') {
                            if ($value == 'all') {
                                array_push($blockType['allType'], $key);
                            }
                            unset($denied_rule[$key]);
                            $denied_return_val = false;
                        }
                    }
                    foreach ($mmv as $key => $value) {
                        if (array_key_exists($key, $denied_rule) && !in_array(strtolower(trim($value)), $denied_rule[$key]) && $denied_rule[$key][0] !== 'all') {
                            $denied_return_val = true;
                        } else {
                            if (array_key_exists($key, $denied_rule)) {
                                if (($denied_rule[$key][0] ?? '') == 'all') {
                                    array_push($blockType['allType'], $key);
                                } elseif (in_array(strtolower(trim($value)), $denied_rule[$key])) {
                                    $blockType['blocked_by'] = $key;
                                }
                            }
                        }
                    }
                    if ($denied_return_val == false) {
                        $return_val = false;
                        break;
                    }
                }
            }
            // allowed check
            if ($return_val == true && isset($singleRecord['matrix']['allowed'])) {
                foreach ($singleRecord['matrix']['allowed'] as $allowed_rule) {
                    $allowed_return_val = true;
                    foreach ($allowed_rule as $key => $value) {
                        if ($value == null || $value == 'all') {
                            unset($allowed_rule[$key]);
                            $allowed_return_val = true;
                        }
                    }
                    foreach ($mmv as $key => $value) {
                        if (array_key_exists($key, $allowed_rule) && !in_array(strtolower($value), $allowed_rule[$key]) && $allowed_rule[$key][0] !== 'all') {
                            $allowed_return_val = false;
                        }
                    }
                    if ($allowed_return_val == true) {
                        $return_val = true;
                        break;
                    } else {
                        $return_val = false;
                    }
                }
            }

            // /false
            return [
                'status' => $return_val,
                'data' => $blockType
            ];
        }
    }
    static public function filterQuoteByMmv(Request $request, $returnArray)
    {
        $decryptedEnquiryId = customDecrypt($request->enquiryId);
        $agent_id = CvAgentMapping::where('user_product_journey_id', $decryptedEnquiryId)->first()?->agent_id;
        if (!$agent_id) {
            $agent_id = 0;
        }
        $utility_id = PospUtility::select('utility_id')->where('seller_user_id', $agent_id)->first()?->utility_id;
        if (empty($utility_id)) {
            return $returnArray;
        }
        $mmvUtility = PospUtilityMmv::where('utility_id', $utility_id)->where('segment_id', $request->productSubTypeId)->get();
        if (empty($mmvUtility)) {
            return $returnArray;
        }
        foreach ($mmvUtility as $singleRecord) {
            // Access the 'matrix' attribute of the model directly as an array
            $matrix = $singleRecord->matrix;

            // Iterate through each rule type ('denied', 'allowed', etc.)
            foreach ($matrix as $ruleType => &$rules) {
                // Iterate through each rule within the rule type
                if (isset($rules)) {
                    foreach ($rules as &$rule) {
                        // Iterate through each key-value pair within the rule
                        foreach ($rule as $key => &$value) {
                            // Check if the value is not already an array and is set
                            if (!is_array($value) && isset($value)) {
                                // Convert the value to an array
                                $value = [$value];
                            } elseif (is_array($value)) {
                                // Convert all elements of the array to lowercase
                                $value = array_map('strtolower', $value);
                            }
                        }
                    }
                }
            }

            // Update the 'matrix' attribute of the model
            $singleRecord->matrix = $matrix;

            // Save the updated model instance
            $singleRecord->save();
        }

        $qoute_request = CorporateVehiclesQuotesRequest::select('version_id')->where('user_product_journey_id', $decryptedEnquiryId)->first();
        $mmv = get_fyntune_mmv_details($request->productSubTypeId, $qoute_request->version_id);
        $userMmv = [
            'make_id' => $mmv['data']['manufacturer']['manf_id'],
            'model_id' => $mmv['data']['version']['model_id'],
            'variant' => $mmv['data']['version']['version_id'],
            'fuel' => $mmv['data']['version']['fuel_type'],
        ];
        foreach ($returnArray as $planType => &$plans) {
            foreach ($plans as $index => &$plan) {
                foreach ($mmvUtility as $singleRecord) {
                    $ic_alias = explode('.', $singleRecord['ic_integration_type'])[0];
                    if ($plan['companyAlias'] === $ic_alias) {
                        $filterResult = PospUtilityController::filterProducts($singleRecord, $userMmv);
                        if (!$filterResult['status']) {
                            // Remove product
                            // unset($plans[$index]);
                            $plans[$index]['isBlocked'] = 1;
                            $plans[$index]['blockedMessage'] = 'This MMV is blocked';
                            $blockedBy = $filterResult['data']['blocked_by'] ?? null;
                            $listPreference = [
                                'make_id' => 'make',
                                'model_id' => 'model',
                                'fuel' => 'fuel',
                                'variant' => 'variant',
                            ];
                            $blockedBy = $listPreference[$blockedBy] ?? $blockedBy;
                            if (!empty($filterResult['data']['allType'])) {
                                foreach ($listPreference as $lKey => $lValue) {
                                    if (in_array($lKey, $filterResult['data']['allType'])) {
                                        $blockedBy = $lValue;
                                        break;
                                    }
                                }
                            }
                            if (!empty($blockedBy)) {
                                $plans[$index]['blockStatusCode'] = 'mmv:'.$blockedBy;
                            }
                        }
                    }
                }
            }
            $plans = array_values($plans);
        }
        return $returnArray;
    }
}
