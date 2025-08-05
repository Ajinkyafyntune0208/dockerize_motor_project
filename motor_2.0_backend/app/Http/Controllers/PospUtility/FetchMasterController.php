<?php

namespace App\Http\Controllers\PospUtility;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Http\Controllers\CommonController;
use App\Models\MasterState;
use App\Models\MasterCity;
use App\Models\MasterProductSubType;
use App\Models\MasterCompany;
use App\Models\MasterRto;
use App\Models\MasterPremiumType;
use Illuminate\Support\Facades\DB;

use function PHPSTORM_META\type;

class FetchMasterController extends Controller
{

    public function fetchMaster(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'masterType' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ]);
        }
        $functionName = 'fetch' . Str::studly($request->masterType);
        if (method_exists(__CLASS__, $functionName)) {
            try {
                return $this->{$functionName}($request);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error($e);
                return response()->json([
                    'status' => false,
                    'message' => 'An error occured : ' . implode('. ', [
                        $e->getMessage(),
                        'Line No.',
                        $e->getLine(),
                    ]),
                ]);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Master type is not a valid master type.',
            ]);
        }
    }

    public function fetchSegmentType(Request $request)
    {
        $segments = MasterProductSubType::where('status', 'Active')->orderBy('product_sub_type_id', 'desc')->get();
        if (count($segments) > 0) {
            return response()->json([
                'status' => true,
                'data' => $segments,
                'message' => 'Data found.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No data found.'
            ]);
        }
    }

    public function fetchInsuranceCompanies(Request $request)
    {
        $ic = MasterCompany::select('company_id', 'company_name')->where('status', 'Active')->orderBy('company_id', 'desc')->get();
        if (count($ic) > 0) {
            foreach ($ic as $insurance) {
                $arr[$insurance->company_id] = $insurance->company_name;
                if (config('constants.IcConstants.godigit.IS_GDD_ENABLED') == 'Y' && strtolower($insurance->company_name) == strtolower('Godigit General Insurance')) {
                    $arr[$insurance->company_id . '|gdd'] = $insurance->company_name . '-GDD';
                }
            }
            return response()->json([
                'status' => true,
                'data' => $arr,
                'message' => 'Data found.'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No data found.'
            ]);
        }
    }

    public function fetchRto(Request $request)
    {
        $data = MasterRto::join('master_state', 'master_state.state_id', 'master_rto.state_id')->select('rto_id', 'rto_name', 'rto_number', 'rto_code', 'master_state.state_name', 'master_rto.state_id', 'master_rto.status')->where('master_rto.status', 'active');
        if ($request->has('state_id')) {
            if (is_array($request->state_id) == true) {
                $data = $data->whereIn('master_rto.state_id', $request->state_id);
            } else {
                $data = $data->where('master_rto.state_id', $request->state_id);
            }
        }
        if ($data->count() > 0) {
            return response()->json([
                'status' => true,
                'message' => 'data found',
                'data' => $data->get()
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No data found',
            ]);
        }
    }

    public function fetchManufacturer(Request $request)
    {
        $validator = validator::make($request->all(), [
            'productSubTypeId' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $response_arr = [];
        $manufacturer = new CommonController();
        if (is_array($request->productSubTypeId) == true) {
            foreach ($request->productSubTypeId as $key => $value) {
                $subRequest = new Request(['productSubTypeId' => $value]);
                $response = json_decode(json_encode($manufacturer->getManufacturer($subRequest)), true);
                if (isset($response['original']['data']) && count($response['original']['data']) > 0) {
                    array_push($response_arr, $response['original']['data']);
                }
            }
            $arr = [];
            if (count($response_arr) > 0) {
                for ($i = 0; $i < count($response_arr); $i++) {
                    if (!isset($request->productSubTypeId[$i])) {
                        // Skip this iteration if productSubTypeId[$i] is not set
                        continue;
                    }
                    for ($j = 0; $j < count($response_arr[$i]); $j++) {
                        if (isset($response_arr[$i][$j])) {
                            $response_arr[$i][$j]['productSubTypeId'] = $request->productSubTypeId[$i];
                            array_push($arr, $response_arr[$i][$j]);
                        }
                    }
                }
            }
            if (count($arr) > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'data found',
                    'data' => array_values($arr)
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found',
                ]);
            }
        } else {
            $response = json_decode(json_encode($manufacturer->getManufacturer($request)), true);
            if (isset($response['original']['data']) && count($response['original']['data']) > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'data found',
                    'data' => $response['original']['data']
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found',
                ]);
            }
        }
    }

    public function fetchModel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'manfId' => 'required',
            'productSubTypeId' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $model = new CommonController();
        if (is_array($request->productSubTypeId) == true && is_array($request->manfId) == true) {
            $response_arr = [];
            if (count($request->productSubTypeId) > count($request->manfId)) {
                for ($i = 0; $i < count($request->manfId); $i++) {
                    $arr = $request->productSubTypeId;
                    $arr[$i] = $request->productSubTypeId[0];
                    $request->productSubTypeId = $arr;
                }
            } elseif (count($request->productSubTypeId) < count($request->manfId)) {
                for ($i = 0; $i < count($request->productSubTypeId); $i++) {
                    $arr = $request->manfId;
                    $arr[$i] = $request->manfId[0];
                    $request->manfId = $arr;
                }
            }
            foreach ($request->productSubTypeId as $key => $value) {
                foreach ($request->manfId as $key => $manfId) {
                    $subRequest = new Request(['productSubTypeId' => $value, 'manfId' => $manfId]);
                    $response = json_decode(json_encode($model->getModel($subRequest)), true);
                    if (isset($response['original']['data']) && count($response['original']['data']) > 0) {
                        array_push($response_arr, $response['original']['data']);
                    }
                }
            }
            $arr = [];
            if (count($response_arr) > 0) {
                for ($i = 0; $i < count($response_arr); $i++) {
                    if (!isset($request->productSubTypeId[$i])) {
                        // Skip this iteration if productSubTypeId[$i] is not set
                        continue;
                    }
                    for ($j = 0; $j < count($response_arr[$i]); $j++) {
                        if (isset($response_arr[$i][$j])) {
                            $response_arr[$i][$j]['productSubTypeId'] = $request->productSubTypeId[$i];
                            array_push($arr, $response_arr[$i][$j]);
                        }
                    }
                }
            }
            if (count($arr) > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'data found',
                    'data' => $arr
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found',
                ]);
            }
        } else {
            $response = json_decode(json_encode($model->getModel($request)), true);
            if (isset($response['original']['data']) && count($response['original']['data']) > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'data found',
                    'data' => $response['original']['data']
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found',
                ]);
            }
        }
    }

    public function fetchFuel(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'productSubTypeId' => 'required',
            'modelId' => 'required'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'Message' => $validate->errors()
            ]);
        }


        $fuel = new CommonController;

        if (is_array($request->productSubTypeId) == true && is_array($request->modelId) == true) {
            $response_arr = [];
            if (count($request->productSubTypeId) > count($request->modelId)) {
                for ($i = 0; $i < count($request->modelId); $i++) {
                    $arr = $request->productSubTypeId;
                    $arr[$i] = $request->productSubTypeId[0];
                    $request->productSubTypeId = $arr;
                }
            } elseif (count($request->productSubTypeId) < count($request->modelId)) {
                for ($i = 0; $i < count($request->productSubTypeId); $i++) {
                    $arr = $request->modelId;
                    $arr[$i] = $request->modelId[0];
                    $request->modelId = $arr;
                }
            }
            foreach ($request->productSubTypeId as $key => $value) {
                foreach ($request->modelId as $key => $modelId) {
                    $subRequest = new Request(['productSubTypeId' => $value, 'modelId' => $modelId]);
                    $response = json_decode(json_encode($fuel->getFuelType($subRequest)), true);
                    if (isset($response['original']['data']) && count($response['original']['data']) > 0) {
                        array_push($response_arr, $response['original']['data']);
                    }
                }
            }
            $arr = [];
            if (count($response_arr) > 0) {
                for ($i = 0; $i < count($response_arr); $i++) {
                    if (!isset($request->productSubTypeId[$i])) {
                        // Skip this iteration if productSubTypeId[$i] is not set
                        continue;
                    }
                    for ($j = 0; $j < count($response_arr[$i]); $j++) {
                        if (isset($response_arr[$i][$j])) {
                            $response_arr[$i][$j]['productSubTypeId'] = $request->productSubTypeId[$i];
                            array_push($arr, $response_arr[$i][$j]);
                        }
                    }
                }
            }
            if (count($arr) > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'data found',
                    'data' => $arr
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found',
                ]);
            }
        } else {
            $response = json_decode(json_encode($fuel->getFuelType($request)), true);
            if (isset($response['original']['data']) && count($response['original']['data']) > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'data found',
                    'data' => $response['original']['data']
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found',
                ]);
            }
        }
    }

    public function fetchVariant(Request $request)
    {
        $validate = validator::make($request->all(), [
            'modelId' => 'required',
            'fuelType' => 'required'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validate->errors()
            ]);
        }

        $variant = new CommonController;

        if (is_array($request->fuelType) == true && is_array($request->modelId) == true) {
            $response_arr = [];
            if (count($request->fuelType) > count($request->modelId)) {
                for ($i = 0; $i < count($request->modelId); $i++) {
                    $arr = $request->fuelType;
                    $arr[$i] = $request->fuelType[0];
                    $request->fuelType = $arr;
                }
            } elseif (count($request->fuelType) < count($request->modelId)) {
                for ($i = 0; $i < count($request->fuelType); $i++) {
                    $arr = $request->modelId;
                    $arr[$i] = $request->modelId[0];
                    $request->modelId = $arr;
                }
            }

            foreach ($request->fuelType as $key => $value) {
                foreach ($request->modelId as $key => $manfId) {
                    $subRequest = new Request(['fuelType' => $value, 'modelId' => $manfId]);
                    $response = json_decode(json_encode($variant->getModelVersion($subRequest)), true);
                    if (isset($response['original']['data']) && count($response['original']['data']) > 0) {
                        array_push($response_arr, $response['original']['data']);
                    }
                }
            }
            $arr = [];
            if (count($response_arr) > 0) {
                for ($i = 0; $i < count($response_arr); $i++) {
                    if (!isset($request->productSubTypeId[$i])) {
                        // Skip this iteration if productSubTypeId[$i] is not set
                        continue;
                    }
                    for ($j = 0; $j < count($response_arr[$i]); $j++) {
                        if (isset($response_arr[$i][$j])) {
                            $response_arr[$i][$j]['productSubTypeId'] = $request->productSubTypeId[$i];
                            array_push($arr, $response_arr[$i][$j]);
                        }
                    }
                }
            }
            if (count($arr) > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'data found',
                    'data' => $arr
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found',
                ]);
            }
        } else {
            $response = json_decode(json_encode($variant->getModelVersion($request)), true);
            if (isset($response['original']['data']) && count($response['original']['data']) > 0) {
                return response()->json([
                    'status' => true,
                    'message' => 'data found',
                    'data' => $response['original']['data']
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'No data found',
                ]);
            }
        }
    }

    public function fetchState(Request $request)
    {
        $state = MasterState::all();
        if (count($state)) {
            return response()->json([
                'status' => true,
                'message' => 'Record Found',
                'data' => $state
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'No data found',
        ]);
    }

    public function fetchCity(Request $request)
    {
        $cities = new MasterCity();
        if ($request->has('state_id')) {
            $cities = $cities->where('state_id', $request->state_id);
        } else {
            $cities = $cities->limit(100);
        }
        $cities = $cities->get();
        if (count($cities)) {
            return response()->json([
                'status' => true,
                'message' => 'Record Found',
                'data' => $cities
            ]);
        }
        return response()->json([
            'status' => false,
            'message' => 'No data found',
        ]);
    }

    public function fetchPremiumType(Request $request)
    {
        $data = MasterPremiumType::where('status', 'Y');
        if ($data->count() > 0) {
            return response()->json([
                'status' => true,
                'message' => 'data found',
                'data' => $data->get()
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No data found',
            ]);
        }
    }
    public function getSegmentList(Request $request)
    {
        if ($request->product_sub_type_id) {
            $data = MasterProductSubType::select('product_sub_type_id', 'product_sub_type_name')->where('product_sub_type_id', $request->product_sub_type_id)->first();
        } else {
            $data = MasterProductSubType::select('product_sub_type_id', 'product_sub_type_name')->get();
        }
        if ($data->count()) {
            return response()->json([
                'status' => true,
                'message' => 'Data Found',
                'data' => $data
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found',
            ]);
        }
    }
    public function getIcbySegment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_sub_type_id' => 'required|exists:ic_product,product_sub_type_id'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error.',
                'errors' => $validator->errors()
            ]);
        }
        $unique_companies = MasterCompany::select("master_company.company_name", 'master_company.company_alias')
            ->join('ic_product', 'ic_product.insurance_company_id', 'master_company.company_id')
            ->where('ic_product.product_sub_type_id', $request->product_sub_type_id)
            ->distinct('master_company.company_name')
            ->get()->toArray();
        $gdd_companies = MasterCompany::select(DB::raw("CONCAT(master_company.company_name,' - GDD') as company_name"), DB::raw("CONCAT(master_company.company_alias,'.gdd') as company_alias"))
            ->join('ic_product', 'ic_product.insurance_company_id', 'master_company.company_id')
            ->where('ic_product.product_sub_type_id', $request->product_sub_type_id)
            ->where('ic_product.good_driver_discount', 'Yes')
            ->distinct('master_company.company_name')
            ->get()->toArray();

        $short_term_companies = MasterCompany::select(
            DB::raw("IF(ic_product.premium_type_id = 5, CONCAT(master_company.company_name,' - Short Term 3 months'), IF(ic_product.premium_type_id = 8, CONCAT(master_company.company_name,' - Short Term 6 months'), master_company.company_name)) as company_name"),
            DB::raw("IF(ic_product.premium_type_id = 5, CONCAT(master_company.company_alias,'.short_term_3'), IF(ic_product.premium_type_id = 8, CONCAT(master_company.company_alias,'.short_term_6'), master_company.company_alias)) as company_alias")
        )
            ->join('ic_product', 'ic_product.insurance_company_id', 'master_company.company_id')
            ->where('ic_product.product_sub_type_id', 6)
            ->whereIn('ic_product.premium_type_id', [5, 8])
            ->get()
            ->toArray();

        $result =  array_merge($unique_companies, $gdd_companies, $short_term_companies);

        usort($result, function ($a, $b) {
            return strcmp($a['company_name'], $b['company_name']);
        });
        $unique_aliases = [];
        $unique_short_term_companies = [];

        foreach ($result as $company) {
            $alias = $company['company_alias'];
            if (!in_array($alias, $unique_aliases)) {
                $unique_aliases[] = $alias;
                $unique_short_term_companies[] = $company;
            }
        }

        $result = $unique_short_term_companies;
        if (count($result)) {
            return response()->json([
                'status' => true,
                'message' => 'Data Found',
                'data' => $result
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No Data Found',
            ]);
        }
    }
}
