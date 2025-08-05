<?php

namespace App\Http\Controllers\Admin\Configuration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FinsallConfiguration extends Controller
{
    /**
     * Show finsall config list.
     *
     * @return view('finsall.create')
     */
    function index()
    {
        $is_finsall_available = config('constants.finsall.FINSALL_ALLOWED_PRODUCTS');
        $data = $this->getConfigRows();
        return view('finsall.index', compact('is_finsall_available', 'data'));
    }

    /**
     * Show the form for creating a new entry for config.
     *
     * @return view('finsall.create')
     */
    public function create()
    {
        $sections = $this->getSectionMaster();
        $companies = \App\Models\MasterCompany::all();
        $premium_types = \App\Models\MasterPremiumType::all();

        return view('finsall.create', compact('sections', 'companies', 'premium_types'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validateData = $request->validate([
            'company' => 'required',
            'section' => 'required',
            'premium_type' => 'required',
            'active' => 'required',
        ]);

        try {
            $newConfig = $this->getNewConfig([
                'company' => $request->company,
                'section' => $request->section,
                'premium_type' => $request->premium_type,
                'active' => $request->active,
            ]);
            \App\Models\ConfigSetting::where([
                'key' => 'constants.finsall.FINSALL_ALLOWED_PRODUCTS'
            ])->update([
                'value' => json_encode($newConfig)
            ]);
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            return redirect()->route('admin.finsall-configuration.index')->with([
                'status' => 'Finsal entry added for ' . $request->company,
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    public function edit($request)
    {
        parse_str($request, $request);
        // return DB::table('master_policy')->get();
        $sections = $this->getSectionMaster();
        $companies = \App\Models\MasterCompany::all();
        $premium_types = \App\Models\MasterPremiumType::all();

        return view('finsall.edit', compact('sections', 'companies', 'premium_types', 'request'));
    }


    public function update($oldData, Request $request)
    {
        parse_str($oldData, $oldData);
        $newConfigArray = $this->deleteEntry($oldData);

        $newConfigArray[] = [
            'company' => $request->company,
            'section' => $request->section,
            'premium_type' => $request->premium_type,
            'active' => $request->active,
        ];

        $newConfig = $this->getNewConfig($newConfigArray, false);

        \App\Models\ConfigSetting::where([
            'key' => 'constants.finsall.FINSALL_ALLOWED_PRODUCTS'
        ])->update([
            'value' => json_encode($newConfig)
        ]);
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        return redirect()->route('admin.finsall-configuration.index')->with([
            'status' => 'Finsal entry added for ' . $request->company,
            'class' => 'success',
        ]);
    }


    public function show($request)
    {
        parse_str($request, $request);
        try {
            $newConfigArray = $this->deleteEntry([
                'company' => $request['company'],
                'section' => $request['section'],
                'premium_type' => $request['premium_type'],
                'active' => $request['active'],
            ]);

            $newConfig = $this->getNewConfig($newConfigArray, false);

            \App\Models\ConfigSetting::where([
                'key' => 'constants.finsall.FINSALL_ALLOWED_PRODUCTS'
            ])->update([
                'value' => json_encode($newConfig)
            ]);
            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            return redirect()->route('admin.finsall-configuration.index')->with([
                'status' => 'Finsal entry added for ' . $request['company'],
                'class' => 'success',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with([
                'status' => 'Something wents wrong ' . $e->getMessage() . '...!',
                'class' => 'danger',
            ]);
        }
    }

    /**
     * give list of finsall available products in associative array format
     *
     * @return array 
     */
    function getConfigRows()
    {
        $originalArray = json_decode(config('constants.finsall.FINSALL_ALLOWED_PRODUCTS'), 1);
        $resultArray = [];

        foreach ($originalArray as $company => $sections) {
            foreach ($sections as $section => $premiumTypes) {
                foreach($premiumTypes as $product => $config) {
                    $mode = $config['mode'] ?? [];
                    foreach ($mode as $type => $active) {
                        if ($active != 'Y') {
                            unset($mode[$type]);
                        }
                    }
                    $resultArray[] = [
                        "company" => $company,
                        "section" => $section,
                        "premium_type" => $product,
                        "active" => $config['enable'] ?? 'N',
                        "mode" => str_replace('_', ' ', implode(', ', array_keys($mode)))
                    ];
                }
            }
        }

        return $resultArray;
    }

    /**
     * add new entry in array or convert config rows in to one multidimentional array for storing in DB
     *
     * @return array 
     */
    function getNewConfig($newEntry, $action = true)
    {
        $oldConfig = $this->getConfigRows();
        if ($action) {
            $config = array_merge($oldConfig, [$newEntry]);
        } else {
            $config = $newEntry;
        }
        $newConfig = [];

        foreach ($config as $item) {
            $company = $item["company"];
            $section = $item["section"];
            $premiumType = $item["premium_type"];
            $active = $item["active"];

            if (!isset($newConfig[$company])) {
                $newConfig[$company] = [];
            }

            if (!isset($newConfig[$company][$section])) {
                $newConfig[$company][$section] = [];
            }

            $newConfig[$company][$section][$premiumType] = $active;
        }
        return $newConfig;
    }

    /**
     * give std class object of sections car, bike, cv
     *
     * @return object 
     */
    function getSectionMaster()
    {
        return (object)[
            (object)[
                'product_sub_type_code' => 'Car',
                'product_sub_type_id' => 'car'
            ],
            (object)[
                'product_sub_type_code' => 'Bike',
                'product_sub_type_id' => 'bike'
            ],
            (object)[
                'product_sub_type_code' => 'Cv',
                'product_sub_type_id' => 'cv'
            ],
        ];
        // $sections = \App\Models\MasterProductSubType::select('product_sub_type_id','product_sub_type_code')->whereNotIn('product_sub_type_id',[3,4,8])->get();
    }

    
    /**
     * remove matching row entry from config list and returns new config list
     *
     * @return array 
     */
    function deleteEntry($entry)
    {
        $config = $this->getConfigRows();

        foreach ($config as $key => $item) {
            if ($item == $entry) { // here item array matches entry mean entry exist in $key
                unset($config[$key]); // removing alement on $key 
                break;
            }
        }

        return $config;
    }
}
