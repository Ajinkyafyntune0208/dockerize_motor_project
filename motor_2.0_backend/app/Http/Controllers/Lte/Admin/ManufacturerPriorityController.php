<?php

namespace App\Http\Controllers\Lte\Admin;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Models\ManufacturerPriority;
use App\Models\MasterProductSubType;

class ManufacturerPriorityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $company = self::getCompnayName('car', 1);
        $Exixtdata = [];
        $seller_type = ['B2B', 'B2C'];
        return view('admin_lte.manufecturer_priority.index', compact('company', 'seller_type', 'Exixtdata'));
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
    public function fetchinsurers(Request $request)
    {
        $vehicle = $request->vehicle_type == 'cv' ?  $request->gcvSubType : $request->vehicle_type;
        $filter = [];

        $subtype = MasterProductSubType::where('product_sub_type_code', $vehicle)->first();
        if ($request->business_type != null) {
            $filter[] = ['sellerType', '=', $request->business_type];
        }
        if ($request->vehicle_type != null) {
            $filter[] = ['vehicleType', '=', $subtype->product_sub_type_id];
        }
        if ($request->cv_type != null) {
            $filter[] = ['cv_type', '=', $request->cv_type];
        }
        $insurers = ManufacturerPriority::where($filter)->pluck('insurer')->toArray();
        $vehicle =  $subtype->parent_product_sub_type_id == 4 ? 'gcv' : ($subtype->parent_product_sub_type_id == 8 ? 'pcv' : $vehicle);
        $menufacturer = self::getCompnayName($vehicle, $subtype->product_sub_type_id);

        return response()->json([
            'data' => $insurers,
            'menufacturer' => $menufacturer,
        ]);
    }
    public static function getCompnayName($product, $product_sub_type_id = null)
    {
        $env = config('app.env');
        if ($env == 'local') {
            $env_folder = 'uat';
        } else if ($env == 'test') {
            $env_folder = 'production';
        } else if ($env == 'live') {
            $env_folder = 'production';
        }
        $product = $product == 'car' ? 'motor' : $product;
        $path = 'mmv_masters/' . $env_folder . '/';
        $file_name  = $path . $product . '_manufacturer.json';
        $mmv_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($file_name), true);
        $data = [];
        foreach ($mmv_data as $key => $value) {
            $data[] = $value;
        }


        if ($product == 'gcv') {
            $gcv_data = [];
            foreach ($data as $key => $value) {
                if ($value['cv_type'] == 'PICK UP/DELIVERY/REFRIGERATED VAN' && $product_sub_type_id == 9) {
                    $gcv_data[] =  $value['manf_name'];
                } else if ($value['cv_type'] == 'DUMPER/TIPPER' && $product_sub_type_id == 13) {
                    $gcv_data[] =  $value['manf_name'];
                } else if ($value['cv_type'] == 'TRUCK' && $product_sub_type_id == 14) {
                    $gcv_data[] =  $value['manf_name'];
                } else if ($value['cv_type'] == 'TRACTOR' && $product_sub_type_id == 15) {
                    $gcv_data[] =  $value['manf_name'];
                } else if ($value['cv_type'] == 'TANKER/BULKER' && $product_sub_type_id == 16) {
                    $gcv_data[] =  $value['manf_name'];
                }
            }
            unset($data);
            $data = $gcv_data;
        } elseif ($product == 'pcv') {
            $pcv_data = [];
            foreach ($data as $key => $value) {
                if ($value['cv_type'] == 'AUTO RICKSHAW' && $product_sub_type_id == 5) {
                    $pcv_data[] =  $value['manf_name'];
                } elseif ($value['cv_type'] == 'TAXI' && $product_sub_type_id == 6) {
                    $pcv_data[] =  $value['manf_name'];
                } elseif ($value['cv_type'] == 'PASSENGER-BUS' && $product_sub_type_id == 7) {
                    $pcv_data[] =  $value['manf_name'];
                } elseif ($value['cv_type'] == 'SCHOOL-BUS' && $product_sub_type_id == 10) {
                    $pcv_data[] =  $value['manf_name'];
                } elseif ($value['cv_type'] == 'E-RICKSHAW' && $product_sub_type_id == 11) {
                    $pcv_data[] =  $value['manf_name'];
                } elseif ($value['cv_type'] == 'TEMPO-TRAVELLER' && $product_sub_type_id == 12) {
                    $pcv_data[] =  $value['manf_name'];
                } elseif ($value['cv_type'] == 'BUS' && in_array($product_sub_type_id, [7, 10])) {
                    $pcv_data[] =  $value['manf_name'];
                }
            }
            unset($data);
            $data = $pcv_data;
        } else {
            $carbike = [];
            foreach ($data as $key => $value) {
                $carbike[] = $value['manf_name'];
            }
            unset($data);
            $data = $carbike;
        }
        return $data;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $vehicle = $request->vehicle_type == 'cv' ?  $request->gcvSubType : $request->vehicle_type;
        $subtype = MasterProductSubType::where('product_sub_type_code', $vehicle)->first();;
        $insertData = [];
        $data['sellerType'] = $request->businessType;
        $data['created_at'] = now();
        $data['vehicleType'] = $subtype->product_sub_type_id;
        $data['cv_type'] = empty($request->cv_type) ? null : $request->cv_type;
        $allIC = $request->insurers;
        $priority = 1;
        foreach ($allIC as $key => $value) {
            $data['insurer'] = $value;
            $data['priority'] = $priority;
            $priority++;
            $insertData[] = $data;
        }
        $delete = [];
        if ($request->businessType != null) {
            $delete[] = ['sellerType', '=', $request->businessType];
        }
        if ($request->vehicle_type != null) {
            $delete[] = ['vehicleType', '=', $subtype->product_sub_type_id];
        }
        if ($request->cv_type != null) {
            $delete[] = ['cv_type', '=', $request->cv_type];
        }
        ManufacturerPriority::where($delete)->delete();
        ManufacturerPriority::insert($insertData);
        return redirect()->back()->with('success', 'Priority added successfully.');
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
}