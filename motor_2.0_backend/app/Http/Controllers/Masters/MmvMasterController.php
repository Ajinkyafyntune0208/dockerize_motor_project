<?php

namespace App\Http\Controllers\Masters;

use App\Models\MotorModel;
use Illuminate\Http\Request;
use App\Models\CvAgentMapping;
use App\Models\MotorModelVersion;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MasterProductSubType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MmvMasterController extends Controller
{
    public static function getManufacturer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productSubTypeId' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
        //      $product_sub_type_id = [1, 2, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16,17,18];

        //        if($request->productSubTypeId == 18)
        //        {
        //            return response()->json([
        //                'status' => true,
        //                'data' => camelCase([
        //                    [
        //                        "cvType" => "MISC",
        //                        "img" => "http://motor_2.0_backend.test?pN3=hCN3zA34t%2FA4ty3tLbbfoUvuT4TVIdhLJtxMYrCFX3LPz12hs0yrBqxEMlph19e18vj%2BgMClVIpPC6rsQ7O9Jg%3D%3D",
        //                        "manfId" => "366",
        //                        "manfName" => "EICHER TRACTOR",
        //                        "priority" => "1"
        //                    ]
        //                ])
        //            ]);
        //        }

        if (in_array($request->productSubTypeId, $product_sub_type_id)) {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }
            $product = strtolower(get_parent_code($request->productSubTypeId));
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name  = $path . $product . '_manufacturer.json';

            // Cache
            $mmv_cache_key = '_manufacturer.json '.$request->productSubTypeId;
            $mmv_data = self::getDataFromFile($mmv_cache_key, $file_name);

            //$mmv_data = json_decode(file_get_contents($file_name), true);
            $data = [];
            if ($mmv_data) {
                $mmv_1 = [];
                $mmv_2 = [];
                foreach ($mmv_data as $mmv) {
                    unset($mmv['is_discontinued']);
                    unset($mmv['is_active']);
                    $manf_img = str_replace(" ", "_", strtolower(trim($mmv['manf_name'])));
                    if ($manf_img == "maruti_suzuki") {
                        $manf_img = "maruti";
                    }
                    //$mmv['img'] = /* Storage::url */file_url((config('constants.motorConstant.vehicleModels')) . '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png');
                    // $mmv['img'] = Storage::url(config('constants.motorConstant.vehicleModels')) . '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png';
                    if(config('DEFAULT_MODEL_LOGO_ENABLE') == 'Y')
                    {                        
                        $mmv['img'] = url('storage/'.config('constants.motorConstant.vehicleModels'). '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png');
                    }
                    else
                    {
                        $mmv['img'] = /* Storage::url */file_url((config('constants.motorConstant.vehicleModels')) . '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png');
                    }
                    
                    if ($mmv['priority'] != '' && $mmv['priority'] != 0) {
                        $mmv_1[] = $mmv;
                    } else {
                        $mmv_2[] = $mmv;
                    }
                }
                array_multisort(array_column($mmv_1, 'priority'), SORT_ASC, $mmv_1);
                array_multisort(array_column($mmv_2, 'manf_name'), SORT_ASC, $mmv_2);
                $data = array_merge($mmv_1, $mmv_2);
                // dd($data);
                if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy') {
                    $data = array_filter($data, function($item) {
                        return strtolower($item['manf_name']) != 'morris garages';
                    });
                    $data = array_values($data);
                }
                else if(isset($request->enquiryId) && config('constants.motorConstant.SMS_FOLDER') == 'tmibasl')
                {
                    $lead_source = UserProductJourney::where('user_product_journey_id',customDecrypt($request->enquiryId))->pluck('lead_source')->first();
                    if($lead_source == 'TML')
                    {
                        $data = array_filter($data, function($item) {
                            return in_array(strtoupper($item['manf_name']),['TATA','TATA MOTORS']);
                        });
                        $data = array_values($data);
                        if(isset($data[0]['priority']))
                        {
                            $data[0]['priority'] = "1";
                        }
                    }
                }
                if ($product == 'gcv') {
                    $gcv_data = [];
                    foreach ($data as $key => $value) {
                        if ($value['cv_type'] == 'PICK UP/DELIVERY/REFRIGERATED VAN' && $request->productSubTypeId == 9) {
                            $gcv_data[] =  $value;
                        } else if ($value['cv_type'] == 'DUMPER/TIPPER' && $request->productSubTypeId == 13) {
                            $gcv_data[] =  $value;
                        } else if ($value['cv_type'] == 'TRUCK' && $request->productSubTypeId == 14) {
                            $gcv_data[] =  $value;
                        } else if ($value['cv_type'] == 'TRACTOR' && $request->productSubTypeId == 15) {
                            $gcv_data[] =  $value;
                        } else if ($value['cv_type'] == 'TANKER/BULKER' && $request->productSubTypeId == 16) {
                            $gcv_data[] =  $value;
                        }
                    }
                    unset($data);
                    $data = $gcv_data;
                } elseif ($product == 'pcv') {
                    $pcv_data = [];
                    foreach ($data as $key => $value) {
                        if ($value['cv_type'] == 'AUTO RICKSHAW' && $request->productSubTypeId == 5) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'TAXI' && $request->productSubTypeId == 6) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'PASSENGER-BUS' && $request->productSubTypeId == 7) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'SCHOOL-BUS' && $request->productSubTypeId == 10) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'E-RICKSHAW' && $request->productSubTypeId == 11) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'TEMPO-TRAVELLER' && $request->productSubTypeId == 12) {
                            $pcv_data[] =  $value;
                        }
                        elseif ($value['cv_type'] == 'BUS' && in_array($request->productSubTypeId,[7,10])) {
                            $pcv_data[] =  $value;
                        }
                    }

                    unset($data);
                    $data = $pcv_data;
                }
            }

            if(isset($request->userProductJourneyId)){
                $enquiryId = customDecrypt($request->userProductJourneyId);
                $agent_details = \App\Models\CvAgentMapping::where('user_product_journey_id', $enquiryId)->first();
                if(isset($agent_details->seller_type) && $agent_details->seller_type = 'Dealer'){
                    $dealer = \Illuminate\Support\Facades\DB::table('original_equipment_manufacturer_dealers')->where('id', $agent_details->agent_id)->first();
                    $oem = \Illuminate\Support\Facades\DB::table('original_equipment_manufacturers')->where('id', $dealer->oem_id)->first();
                    $manf_data = collect($data)->where('manf_id', $oem->for)->first();
                    $data = [json_decode(json_encode($manf_data), true)];
                }
            }

            try {

                if (!empty($request->enquiryId)) {

                    $EV_Category = config('POS_CATEGORY_IDENTIFIER');
                    $agent_details = false;
                    if($EV_Category !== NULL)
                    {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'P')
                            ->where('category', $EV_Category)
                            ->exists();                        
                    }
                    if ($agent_details) {

                        $manfucturer_ids = self::getManufacturerByFuelType($request);

                        //return $manfucturer_ids;

                        if ($manfucturer_ids instanceof \Illuminate\Http\JsonResponse) {
                            $manfucturer_ids = json_decode($manfucturer_ids->getContent(), true)['data'] ?? [];
                        } else {
                            $manfucturer_ids = [];
                        }

                        $data = array_values(collect($data)->whereIn("manf_id", $manfucturer_ids)->toArray());

                        // Temporary Condition added for KMD's Bharat Benz Demo, please check with Dipraj before removing the if condition
                        if( config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR' && config('BHARAT_BENZ_DEMO') == 'Y')
                        {
                            return response()->json([
                                'status' => true,
                                'msg'    => $env_folder,
                                'data'   => [ [ "manfId" => "14", "manfName" => "BHARAT BENZ", "priority" => "5", "cvType" => "TRUCK", "img" => "https://motor-uat.s3.ap-south-1.amazonaws.com/uploads/vehicleModels/gcv/bharat_benz.png#" ] ]
                            ]); exit;
                        }

                        return response()->json([
                            'status' => true,
                            'msg'    => $env_folder,
                            'data' => camelCase($data)
                        ]);
                    }
                }

            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }

            // Temporary Condition added for KMD's Bharat Benz Demo, please check with Dipraj before removing the if condition
            if( config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR' && config('BHARAT_BENZ_DEMO') == 'Y')
            {
                return response()->json([
                    'status' => true,
                    'msg'    => $env_folder,
                    'data'   => [ [ "manfId" => "14", "manfName" => "BHARAT BENZ", "priority" => "5", "cvType" => "TRUCK", "img" => "https://motor-uat.s3.ap-south-1.amazonaws.com/uploads/vehicleModels/gcv/bharat_benz.png#" ] ]
                ]); exit;
            }
            // dd($data);
            return response()->json([
                'status' => true,
                'msg'    => $env_folder,
                'data' => camelCase($data)
            ]);
        }


        $searchString = $request->searchString;
        if (!empty($searchString)) {
            $priority = 0;
        } else {
            $priority = 1;
        }

        $showActive = isset($request->showActive) ? $priority : 0;

        if ($priority == 1 && $showActive == 0) {
            $manufacturerData = DB::table('motor_manufacturer')
                ->where('product_sub_type_id', $request->productSubTypeId)
                ->where('status', 'Active')
                ->select('manf_id', 'manf_name', 'img')
                ->get();
        } elseif (!empty($searchString)) {
            $manufacturerData = DB::table('motor_manufacturer')
                ->where('product_sub_type_id', $request->productSubTypeId)
                ->where('status', 'Active')
                ->orWhere('manf_name', 'LIKE', "%{$searchString}%")
                ->select('manf_id', 'manf_name', 'img')
                ->get();
        } elseif ($priority == 1 && $showActive == 1) {
            $manufacturerData = DB::table('motor_manufacturer')
                ->where('product_sub_type_id', $request->productSubTypeId)
                ->where('status', 'Active')
                ->select('manf_id', 'manf_name', 'img')
                ->get();
        }

        if (!$manufacturerData->isEmpty()) {
            foreach ($manufacturerData as $key => $value) {
                $manufacturerData[$key]->img =  url(config('constants.motorConstant.vehicleModels') . $value->img);
            }

            // Temporary Condition added for KMD's Bharat Benz Demo, please check with Dipraj before removing the if condition
            if( config('constants.motorConstant.SMS_FOLDER') == 'KMDASTUR' && config('BHARAT_BENZ_DEMO') == 'Y')
            {
                return response()->json([
                    'status' => true,
                    'msg'    => $env_folder,
                    'data'   => [ [ "manfId" => "14", "manfName" => "BHARAT BENZ", "priority" => "5", "cvType" => "TRUCK", "img" => "https://motor-uat.s3.ap-south-1.amazonaws.com/uploads/vehicleModels/gcv/bharat_benz.png#" ] ]
                ]); exit;
            }
            // dd($manufacturerData);
            return response()->json([
                'status' => true,
                'data' => camelCase($manufacturerData)
            ]);
        } else {
            return response()->json([
                'status' => false,
                'msg' => 'Some Error Occurred during Found'
            ]);
        }
    }

    public function getManufacturerByFuelType(Request $request)
    {
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();

        if (in_array($request->productSubTypeId, $product_sub_type_id)) {

            $path = self::getMmvPathDetails($request);

            /* Get Manufacturer Details */
            $model_file_name  = $path . '_model.json';

            // Cache
            $manufacturer_data_by_fuel_cache_key = '_model.json' . $request->productSubTypeId;
            $model_data = self::getDataFromFile($manufacturer_data_by_fuel_cache_key, $model_file_name);

            $data = self::getModelIdByFuelType($request);

            if ($data instanceof \Illuminate\Http\JsonResponse) {
                $data = json_decode($data->getContent(), true)['data'] ?? [];
            }else{
                $data = [];
            }

            $model_data = collect($model_data)->whereIn('model_id', $data)->pluck('manf_id')->unique()->values();

            return response()->json([
                'status' => true,
                'data' => $model_data
            ]);
        }
      
    }

    public static function getMmvPathDetails($request): string
    {
        $env = config('app.env');

        if ($env == 'local') {
            $env_folder = 'uat';
        } else if ($env == 'test') {
            $env_folder = 'production';
        } else if ($env == 'live') {
            $env_folder = 'production';
        }

        $product = strtolower(get_parent_code($request->productSubTypeId));
        $product = ($product == 'car') ? 'motor' : $product;

        return 'mmv_masters/' . $env_folder . '/' . $product;
    }

    public function getModelIdByFuelType(Request $request)
    {

        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
        
        if (in_array($request->productSubTypeId, $product_sub_type_id)) {

            $path = self::getMmvPathDetails($request);

            /* Get Model Id Details */
            $file_name  = $path . '_model_version.json';

            // Cache
            $model_data_by_fuel_cache_key = '_model_version.json' . $request->productSubTypeId;
            $data = self::getDataFromFile($model_data_by_fuel_cache_key, $file_name);

            if(in_array(get_parent_code($request->productSubTypeId), ["PCV", "GCV"])){
                $data = collect($data)->filter(function (array $value, mixed $key): bool {
                    return $value['fuel_type'] === 'ELECTRIC' && (substr($value['version_id'], 0, 5) == 'GCV3W' || substr($value['version_id'], 0, 5) == 'PCV3W');
                })->pluck('model_id')->unique()->values();
            }else{
                $data = collect($data)->filter(function (array $value, mixed $key): bool {
                    return $value['fuel_type'] === 'ELECTRIC';
                })->pluck('model_id')->unique()->values();
            }

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        }
      
    }

    public static function getModel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'manfId' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
        //        if($request->productSubTypeId == 18)
        //        {
        //            return response()->json([
        //                'status' => true,
        //                'data' => camelCase([
        //                    [
        //                        "manfId" => "366",
        //                        "modelId" => "6338",
        //                        "modelName" => "EICHER 241",
        //                        "modelPriority" => "1"
        //                    ]
        //                ])
        //            ]);
        //        }

        if (in_array($request->productSubTypeId, $product_sub_type_id))
        //if($request->productSubTypeId == 6)
        {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }

            $product = strtolower(get_parent_code($request->productSubTypeId));
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            //$file_name  = $path.'pcv_model.json';
            $file_name  = $path . $product . '_model.json';

            // Cache
            $model_cache_key = '_model.json' . $request->productSubTypeId;
            $data = self::getDataFromFile($model_cache_key, $file_name);

            //$data = json_decode(file_get_contents($file_name), true);
            $result = $mmv_1 = $mmv_2 = [];
            if ($data) {
                foreach ($data as $value) {
                    if ($value['manf_id'] == $request->manfId) {
                        unset($value['is_discontinued']);
                        unset($value['is_active']);
                        if ($value['model_priority'] != '' && $value['model_priority'] != 0) {
                            $mmv_1[] = $value;
                        } else {
                            $mmv_2[] = $value;
                        }
                    }
                }
            }
            array_multisort(array_column($mmv_1, 'model_priority'), SORT_ASC, $mmv_1);
            array_multisort(array_column($mmv_2, 'model_name'), SORT_ASC, $mmv_2);
            $result = array_merge($mmv_1, $mmv_2);

            try {

                if (!empty($request->enquiryId)) {

                    $EV_Category = config('POS_CATEGORY_IDENTIFIER');
                    $agent_details = false;                    
                    if($EV_Category !== NULL)
                    {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'P')
                            ->where('category', $EV_Category)
                            ->exists();
                    }                    

                    if ($agent_details) {

                        $model_ids = self::getModelIdByFuelType($request);

                        if ($model_ids instanceof \Illuminate\Http\JsonResponse) {
                            $model_ids = json_decode($model_ids->getContent(), true)['data'] ?? [];
                        } else {
                            $model_ids = [];
                        }

                        $result = array_values(collect($result)->whereIn("model_id", $model_ids)->toArray());

                        return response()->json([
                            'status' => true,
                            'msg'    => $env_folder,
                            'data' => camelCase($result)
                        ]);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }

                return response()->json([
                    'status' => true,
                    'msg'    => $env_folder,
                    'data' => camelCase($result)
                ]);
        }
        $models = MotorModel::select('model_id', 'manf_id', 'vehicle_id', 'model_name')
            ->where('status', 'Active')
            ->where('manf_id', $request->manfId)
            ->get()
            ->toarray();
        list($status, $msg, $data) = $models
            ? [true, 'Models List Fetched Successfully...!', camelCase($models)]
            : [false, 'Something went wrong !', null];
        return response()->json([
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    public static function getFuelType(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'productSubTypeId' => 'required',
            'modelId' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        } else {
            $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();

            try {
                if (!empty($request->enquiryId)) {

                    $EV_Category = config('POS_CATEGORY_IDENTIFIER');
                    $agent_details = false;

                    if ($EV_Category !== NULL) {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'P')
                            ->where('category', $EV_Category)
                            ->exists();
                    }

                    if ($agent_details) {
                        return response()->json([
                            'status' => true,
                            'data' => camelCase([
                               "ELECTRIC"
                            ])
                        ]);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }

            if($request->productSubTypeId == 18)
            {
                return response()->json([
                    'status' => true,
                    'data' => camelCase([
                        "Petrol",
                        "DIESEL",
                        "CNG"
                    ])
                ]);
            }

            if (in_array($request->productSubTypeId, $product_sub_type_id))
            //if($request->productSubTypeId == 6)
            {
                $env = config('app.env');
                if ($env == 'local') {
                    $env_folder = 'uat';
                } else if ($env == 'test') {
                    $env_folder = 'production';
                } else if ($env == 'live') {
                    $env_folder = 'production';
                }

                $product = strtolower(get_parent_code($request->productSubTypeId));
                $product = $product == 'car' ? 'motor' : $product;
                $path = 'mmv_masters/' . $env_folder . '/';
                //$file_name  = $path.'pcv_model.json';
                $file_name  = $path . $product . '_model_version.json';

                // Cache
                $fuel_type_cache_key = '_model_version.json' . $request->productSubTypeId;
                $data = self::getDataFromFile($fuel_type_cache_key, $file_name);

                //$data = json_decode(file_get_contents($file_name), true);
                $result = [];
                if ($data) {
                    foreach ($data as $value) {
                        if ($value['model_id'] == $request->modelId) {
                            if (!(in_array($value['fuel_type'], $result))) {
                                $result[] =  $value['fuel_type'];
                            }
                        }
                    }
                }

                $result = array_map(function ($res) {
                    if (in_array($res, ['PETROL+CNG', 'PETROL+LPG', 'DIESEL+CNG', 'DIESEL+LPG'])) {
                        return 'CNG';
                    }

                    return $res;
                }, $result);

                rsort($result);
                return response()->json([
                    'status' => true,
                    'msg'    => $env_folder,
                    'data' => camelCase($result)
                ]);
            } else {
                $fuel_types = DB::table('motor_model_version')
                    ->where([
                        'status' => 'Active',
                        'model_id' => $request->modelId,
                    ])
                    ->groupBy('fuel_type')
                    ->pluck('fuel_type')
                    ->toArray();

                if (empty($fuel_types)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Fuel Types Not Found',
                    ]);
                } else {
                    return response()->json([
                        'status' => true,
                        'data' => $fuel_types,
                    ]);
                }
            }
        }
    }

    public static function getModelVersion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'modelId' => 'required',
            'fuelType' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validator->errors(),
            ]);
        }
        //$product_sub_type_id = [1, 2, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16];
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();
        //        if($request->productSubTypeId == 18)
        //        {
        //            return response()->json([
        //                'status' => true,
        //                'data' => camelCase([
        //                    [
        //                        "carryingCapicity" => "1",
        //                        "cubicCapacity" => "",
        //                        "fuelFype" => "PETROL",
        //                        "grosssVehicleWeight" => "",
        //                        "kw" => "null",
        //                        "modelId" => "6338",
        //                        "seatingCapacity" => "1",
        //                        "vehicleBuiltUp" => "",
        //                        "versionId" => "MISC4C3010211543",
        //                        "versionName" => "EICHER 241"
        //                    ]
        //                ])
        //            ]);
        //        }
        if (in_array($request->productSubTypeId, $product_sub_type_id)) {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }
            $is_3_wheeler_blocked = config('IS_3_WHEELER_BLOCKED') == 'Y';
            $is_PCV_greater_7_seater_blocked = config('IS_PCV_GREATER_THAN_7_SEATER_BLOCKED') == 'Y';
            $product = strtolower(get_parent_code($request->productSubTypeId));
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name  = $path . $product . '_model_version.json';

            // Cache
            $model_version_cache_key = '_model_version.json' . $request->productSubTypeId;
            $data = self::getDataFromFile($model_version_cache_key, $file_name);

            //$file_name  = $path.'pcv_model_version.json';
            //$data = json_decode(file_get_contents($file_name), true);
            $result = [];
            if ($data) {
                foreach ($data as $value) {
                    $value1['carrying_capicity'] = $value['carrying_capacity'] ?? $value['seating_capacity'];
                    $value1['cubic_capacity'] = $value['cubic_capacity'] ?? '';
                    $value1['fuel_fype'] = $value['fuel_type'];
                    $value1['grosss_vehicle_weight'] = $value['gvw'] ?? '';
                    $value1['kw'] = $value['kw'] ?? '';
                    $value1['model_id'] = $value['model_id'];
                    $value1['seating_capacity'] = $value['seating_capacity'];
                    $value1['version_id'] = $value['version_id'];
                    //$value1['version_id'] = $value['sequence_id'];
                    $value1['version_name'] = $value['version_name'];
                    $value1['vehicle_built_up'] = $value['vehicle_built_up'] ?? '';
                    $value1['no_of_wheels'] = $value['no_of_wheels'] ?? '';
                    if (!empty($value1['fuel_fype'])) {
                        $value1['fuel_fype'] = strtoupper($value1['fuel_fype']);
                    };
                    if ($value['model_id'] == $request->modelId) 
                    {
                        if($request->fuelType == 'NULL')
                        {
                            if(!in_array($request->productSubTypeId,[1,2]) && isset($value['seating_capacity']) && $value['seating_capacity'] > 7 && $is_PCV_greater_7_seater_blocked)
                            {
                               //as per git id https://github.com/Fyntune/motor_2.0_backend/issues/10832
                              //not included seating capecity greater than 7
                            }else if(isset($value['no_of_wheels']) && $value['no_of_wheels'] == 3 && $is_3_wheeler_blocked) 
                            {
                                // if 3 wheeler variants are blocked then don't show variants
                            }
                            else
                            {
                                $result[] = $value1;
                            }
                           
                        }
                        else
                        {
                            if ($product == 'bike') 
                            {
                                if ($value['fuel_type'] == $request->fuelType) 
                                {
                                    $result[] = $value1;
                                }
                            } 
                            else 
                            {                                
                                if(!in_array($request->productSubTypeId,[1,2]) && isset($value['seating_capacity']) && $value['seating_capacity'] > 7 && $is_PCV_greater_7_seater_blocked)
                                {
                                  //as per git id https://github.com/Fyntune/motor_2.0_backend/issues/10832
                                  //not included seating capecity greater than 7
                                }
                                else if(isset($value['no_of_wheels']) && $value['no_of_wheels'] == 3 && $is_3_wheeler_blocked) 
                                {
                                    // if 3 wheeler variants are blocked then don't show variants
                                } 
                                else 
                                {
                                    if (in_array($value['fuel_type'], ['CNG', 'LPG', 'PETROL+CNG', 'PETROL+LPG', 'DIESEL+CNG', 'DIESEL+LPG']) && in_array($request->fuelType, ['CNG', 'LPG'])) 
                                    {
                                        $result[] = $value1;
                                    } 
                                    else if (strtolower($request->fuelType) == strtolower($value['fuel_type'])) 
                                    {
                                        $result[] = $value1;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            try {

                if (!empty($request->enquiryId)) {

                    $EV_Category = config('POS_CATEGORY_IDENTIFIER');
                    $agent_details = false;
                    if($EV_Category !== NULL)
                    {
                        $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($request->enquiryId))
                            ->where('seller_type', 'P')
                            ->where('category', $EV_Category)
                            ->exists();                        
                    }
                    
                    if ($agent_details) {

                        $result = array_values(collect($result)->whereIn("fuel_fype", "ELECTRIC")->toArray());
                        return response()->json([
                            'status' => true,
                            'msg'    => $env_folder,
                            'data' => camelCase($result)
                        ]);
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'msg'    => $env_folder,
                'data' => camelCase($result)
            ]);
        }
        $motor_model_version = MotorModelVersion::select('version_id', 'version_name', 'fuel_type', 'model_id', 'cubic_capacity', 'carrying_capicity', 'seating_capacity', 'grosss_vehicle_weight')
            ->where('model_id', $request->modelId)
            ->when($request->fuelType == 'CNG', function ($query) {
                if (isset(request()->LpgCngKitValue) && request()->LpgCngKitValue > 0) {
                    return $query->where('fuel_type', 'PETROL');
                } else {
                    return $query->where('fuel_type', 'CNG');
                }
            }, function ($query) {
                return $query->where('fuel_type', request()->fuelType);
            })
            ->get()
            ->toArray();

        list($status, $msg, $data)  = $motor_model_version
            ? [true, 'Model Version List Fetched Successfully...!', camelCase($motor_model_version)]
            : [false, 'No data found', null];

        return response()->json([
            'status' => $status,
            'msg' => $msg,
            'data' => $data
        ]);
    }

    public static function getDataFromFile($cache_key, $file_name)
    {
        $cache = Cache::get($cache_key);
    
        if (empty($cache)) {
            $get_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active'? config('filesystems.default') : 'public')->get($file_name), true);
            $data = Cache::rememberForever($cache_key, function () use ($get_data) {
                return $get_data;
            });
        } else {
            $data = $cache;
        }
    
        return $data;
    }

}
