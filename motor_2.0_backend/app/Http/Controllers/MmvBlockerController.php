<?php

namespace App\Http\Controllers;

use App\Models\MasterProduct;
use App\Models\MasterProductSubType;
use App\Models\MmvBlocker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;


class MmvBlockerController extends Controller
{
    public function index(Request $request)
    {
        //Get all segments
        $subTypes = MasterProductSubType::where('status', 'Active')->get()->toArray();
        $subTypes = array_column($subTypes, null, 'product_sub_type_id');

        //Seperate CV categories
        $vehicleCats = [];
        foreach ($subTypes as $key => $value) {
            if ($value['parent_id'] != 0) {
                $vehicleCats[$key]['productSubTypeId']   = $value['product_sub_type_id'];
                $vehicleCats[$key]['productSubTypeCode'] = $value['product_sub_type_code'];
                $vehicleCats[$key]['productSubTypeDesc'] = $value['product_sub_type_name'];
                $vehicleCats[$key]['productSubTypelogo'] = url(config('constants.motorConstant.vehicleCategory') . $value['logo']);
                $vehicleCats[$key]['productCategoryId']  = $subTypes[$value['parent_id']]['product_sub_type_id'];
                $vehicleCats[$key]['productCategoryName'] = $subTypes[$value['parent_id']]['product_sub_type_code'];
            }elseif($value['product_sub_type_code'] != 'PCV' && $value['product_sub_type_code'] != 'GCV' ) {
                // Logic for parent_id == 0
                $vehicleCats[$key]['productSubTypeId']   = $value['product_sub_type_id'];
                $vehicleCats[$key]['productSubTypeCode'] = $value['product_sub_type_code'];
                $vehicleCats[$key]['productSubTypeDesc'] = $value['product_sub_type_name'];
                $vehicleCats[$key]['productSubTypelogo'] = url(config('constants.motorConstant.vehicleCategory') . $value['logo']);
                $vehicleCats[$key]['productCategoryId']  = null; // No parent category
                $vehicleCats[$key]['productCategoryName'] = null; // No parent category name
            }
        }

        $sellerTypes = [
            'B2C' => [
                "EMPLOYEE" => "E",
                "PARTNER"   => "Partner",
                "USER_WITH_REGISTRATION" => "U",
                "USER_WITHOUT_REGISTRATION" => "B2C"
            ],

            'B2B' => [
                "POSP" => "P",
                "MISP" => "MISP"
            ]
        ];

        //read json files
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
        } else if ($env == 'test') {
            $env_folder = 'production';
        } else if ($env == 'live') {
            $env_folder = 'production';
        }

        // Store selected values in session for persistence
        $sellerType = session('sellerType', $request->input('sellerType', ''));
        $segment = session('segment', $request->input('segment', ''));

        //read mmv files
        $data = [];
        if ($request->has('segment') && !empty($request->segment)) {
            $product = strtolower($request->segment);
            if(in_array($product, ['taxi', 'auto-rickshaw', 'passenger-bus', 'school-bus', 'electric-rickshaw','tempo-traveller'])){
                $product = 'pcv';
            }else if(in_array($product, ['pick up/delivery/refrigerated van', 'dumper/tipper', 'truck', 'tractor', 'tanker/bulker'])){
                $product = 'gcv';
            }
            // dd($product);
            $product = $product == 'car' ? 'motor' : $product;
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name  = $path . $product . '_manufacturer.json';
            $mmv_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
        } else {
            return view('admin_lte.make_selector.index', compact('vehicleCats', 'sellerTypes', 'data'));
        }
        if ($mmv_data) {
            $mmv_1 = [];
            $mmv_2 = [];
            foreach ($mmv_data as $mmv) {
                unset($mmv['is_discontinued']);
                unset($mmv['is_active']);
                $manf_img = str_replace(" ", "_", strtolower(trim($mmv['manf_name'])));

                if (config('DEFAULT_MODEL_LOGO_ENABLE') == 'Y') {
                    $mmv['img'] = url('storage/' . config('constants.motorConstant.vehicleModels') . '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png');
                } else {
                    $mmv['img'] = /* Storage::url */ file_url((config('constants.motorConstant.vehicleModels')) . '/' . ($product == 'motor' ? 'car' : $product) . '/' . $manf_img . '.png');
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
            

            $product = strtolower($request->segment);
            // Fetch preselected manufacturers from the database so they appear checked on frontend
            $preselectedManufacturers = MmvBlocker::where('seller_type', $sellerType)
            ->where('segment', $product)
            ->pluck('manufacturer')
            ->toArray();

            //Segregating CV products so that duplicates don't appear when search is hit
            if($product != 'car' && $product != 'bike'){
                if (!empty($data)) {
                    switch ($product) {
                        case 'electric-rickshaw':
                            $product = 'e-rickshaw';
                        break;
                        case 'passenger-bus':
                            $product = 'bus';
                        break;
                        case 'auto-rickshaw':
                            $product = 'auto rickshaw';
                        break;
                        case 'school-bus':
                            $product = 'bus';
                        break;
                        default:
                        break;
                    }
                    $data = array_filter($data, function ($item) use ($preselectedManufacturers, $product) {
                        if (isset($item['cv_type'])) {
                            return strtolower($item['cv_type']) === $product;
                        }
                        return false;
                    });
                }
            }
         
            return view('admin_lte.make_selector.index', compact('vehicleCats', 'sellerTypes', 'data', 'sellerType', 'segment','preselectedManufacturers'));
        }
    }

    public function submitForm(Request $request)
    {
        $validatedData = $request->validate([
            'sellerType' => 'required|string',
            'segment' => 'required|string',
            'manf_names' => 'array',
            'manf_names.*' => 'string',
        ]);

        $product = strtolower($request->segment);

        $selectedManufacturers = $validatedData['manf_names'] ?? []; // Manufacturers currently selected
        $existingManufacturers = MmvBlocker::where('seller_type', $validatedData['sellerType'])
            ->where('segment', $product)
            ->pluck('manufacturer')
            ->toArray();

        // Find manufacturers to delete (those in DB but not in selected list)
        $manufacturersToDelete = array_diff($existingManufacturers, $selectedManufacturers);
        
        // Find manufacturers to add (those selected but not in MmvBlocker db)
        $manufacturersToAdd = array_diff($selectedManufacturers, $existingManufacturers);


        // Remove unselected manufacturers from the database also using each() so we can monitor the deletion of record in activity logs
        MmvBlocker::where('seller_type', $validatedData['sellerType'])
            ->where('segment', $product)
            ->whereIn('manufacturer', $manufacturersToDelete)
            ->get()
            ->each(function ($model) {
                $model->delete();
            });
        
        foreach ($manufacturersToAdd as $manufacturer) {
            // Create new record, not using insert at it doesn't trigger activity log
            MmvBlocker::create([
                'seller_type' => $validatedData['sellerType'],
                'product_sub_type_id' => $request->productSubTypeId,
                'segment' => $product,
                'manufacturer' => $manufacturer,
                'status' => 'Y',
            ]);
        }
        return $this->index($request);
    }
}