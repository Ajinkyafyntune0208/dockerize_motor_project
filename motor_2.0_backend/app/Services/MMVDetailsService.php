<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use App\Models\MasterProductSubType;

class MMVDetailsService
{
    function get_mmv_details($productData, $version_id, $ic_name,$manufacture_year, $gcv_carrier_type=NULL)
    {
        if($ic_name == 'national'){
            return $this->get_mmv_details_by_variant_period($productData, $version_id, $ic_name,$manufacture_year);
        }

        ini_set('memory_limit', '1024M');
        $product_sub_type_id = MasterProductSubType::where('status', 'Active')->pluck('product_sub_type_id')->toArray();

        if (in_array($productData->product_sub_type_id, $product_sub_type_id)) {
            $env = config('app.env');
            if ($env == 'local') {
                $env_folder = 'uat';
            } else if ($env == 'test') {
                $env_folder = 'production';
            } else if ($env == 'live') {
                $env_folder = 'production';
            }

            $product = [
                1  => 'motor', 2  => 'bike', 5  => 'pcv', 6  => 'pcv', 7  => 'pcv',
                9  => 'gcv',   10 => 'pcv',  11 => 'pcv', 12 => 'pcv', 13 => 'gcv',
                14 => 'gcv',   15 => 'gcv',  16 => 'gcv', 17 => 'misc',18 => 'misc',
            ];

            $product = $product[$productData->product_sub_type_id];
            if (in_array($ic_name, explode(',', config(strtoupper($product) . '_ICS_USING_UAT_MMV')))) {
                $ic_name .= '_uat';
            }
            $path = 'mmv_masters/' . $env_folder . '/';
            $file_name  = $path . $product . '_model_version.json';

            $version_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($file_name), true);
            $mmv_code = '';
            $no_of_wheels = '0';
            $fyntune_version =[];
            $category = $gcv_carrier_type ? ($gcv_carrier_type == 'PUBLIC' ? 'a1' : 'a2') : '';

          if (!empty($version_data)) {
                foreach ($version_data as $version) {
                    if ($version['version_id'] == $version_id) {
                        if (in_array($ic_name, ['iffco_tokio', 'reliance', 'tata_aig_v2']) && $product == 'gcv') {
                        if ($gcv_carrier_type == 'PUBLIC') {
                            switch ($ic_name) {
                                case 'iffco_tokio' :
                                    if (isset($version['mmv_iffco_a1_public']) && $version['mmv_iffco_a1_public'] != 'null' && $version['mmv_iffco_a1_public'] != null) {

                                        $mmv_code = $version['mmv_iffco_a1_public'];

                                    } elseif(isset($version['mmv_iffco_a2_private']) && $version['mmv_iffco_a2_private'] != 'null' && $version['mmv_iffco_a2_private'] != null) {

                                        $mmv_code = $version['mmv_iffco_a2_private'];
    
                                    }
                                    break;
                                case 'reliance' :
                                    if (isset($version['mmv_reliance_a1_public']) && $version['mmv_reliance_a1_public'] != 'null' && $version['mmv_reliance_a1_public'] != null) {

                                        $mmv_code = $version['mmv_reliance_a1_public'];

                                    } elseif (isset($version['mmv_reliance_a3_public']) && $version['mmv_reliance_a3_public'] != 'null' && $version['mmv_reliance_a3_public'] != null) {

                                        $mmv_code = $version['mmv_reliance_a3_public'];

                                    }
                                    break;
                                case 'tata_aig_v2' :
                                    if (!empty($version['mmv_tata_aig_v2_a1_public'] ?? '') && $version['mmv_tata_aig_v2_a1_public'] != 'null') {

                                        $mmv_code = $version['mmv_tata_aig_v2_a1_public'];

                                    } elseif (!empty($version['mmv_tata_aig_v2_a3_public'] ?? '') && $version['mmv_tata_aig_v2_a3_public'] != 'null') {

                                        $mmv_code = $version['mmv_tata_aig_v2_a3_public'];

                                    }
                                    break;
                            }
                        } else {
                            switch ($ic_name) {
                                case 'iffco_tokio' :
                                    if(isset($version['mmv_iffco_a2_private']) && $version['mmv_iffco_a2_private'] != 'null' && $version['mmv_iffco_a2_private'] != null) {
                                        $mmv_code = $version['mmv_iffco_a2_private'];
                                    }
                                    break;
                                case 'reliance' :
                                    if (isset($version['mmv_reliance_a2_private']) && $version['mmv_reliance_a2_private'] != 'null' && $version['mmv_reliance_a2_private'] != null) {

                                        $mmv_code = $version['mmv_reliance_a2_private'];

                                    } elseif (isset($version['mmv_reliance_a4_private']) && $version['mmv_reliance_a4_private'] != 'null' && $version['mmv_reliance_a4_private'] != null) {

                                        $mmv_code = $version['mmv_reliance_a4_private'];

                                    }
                                    break;
                                case 'tata_aig' :

                                    if (!empty($version['mmv_tata_aig_v2_a2_private'] ?? '') && $version['mmv_tata_aig_v2_a2_private'] != 'null')
                                    {

                                        $mmv_code = $version['mmv_tata_aig_v2_a2_private'];

                                    }
                                    elseif (!empty($version['mmv_tata_aig_v2_a4_private'] ?? '') && $version['mmv_tata_aig_v2_a4_private'] != 'null')
                                    {

                                        $mmv_code = $version['mmv_tata_aig_v2_a4_private'];

                                    }
                                    break;
                            }
                        }
                        
                            $fyntune_version = $version;
                            $no_of_wheels = isset($version['no_of_wheels']) ? $version['no_of_wheels'] : '0';
    
                            break;
                        } elseif (isset($version['mmv_' . $ic_name])) {

                            $mmv_code = $version['mmv_' . $ic_name];
                            $fyntune_version = $version;
                            $no_of_wheels = isset($version['no_of_wheels']) ? $version['no_of_wheels'] : '0';
                            if($ic_name == 'renewbuy'){
                                return [
                                    'status' => true,
                                    'data' => $mmv_code
                                ];
                            }
                            break;
                        } else {
                            return  [
                                'status' => false,
                                'message' => (strtoupper($ic_name) == 'CHOLLA_MANDALAM' ? 'CHOLA_MANDALAM' : strtoupper($ic_name)).' mapping does not exist with IC master'
                            ];
                        }
                    }
                }
            }
            
            if ($mmv_code == '') {
                return  [
                    'status' => false,
                    'message' => 'Vehicle Not Mapped'
                ];
            } else if ($mmv_code == 'DNE') {
                return  [
                    'status' => false,
                    'message' => 'Vehicle Does Not Exists'
                ];
            } else if (strtolower($mmv_code) == 'declined') {
                return  [
                    'status' => false,
                    'message' => 'The following vehicle is Declined / Blacklisted'
                ];
            } else {
                $product = $product == 'motor' ? '' : '_' . $product;
                $ic_version_file_name  = $path . $ic_name . $product . '_model_master.json';
                $ic_version_data = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') :'public')->get($ic_version_file_name), true);

                if (isset($ic_version_data[$mmv_code])) {
                    $mmv_data = $ic_version_data[$mmv_code];
                    $mmv_data['ic_version_code'] = $mmv_code;
                    $mmv_data['no_of_wheels'] = $no_of_wheels;
                    $mmv_data['fyntune_version'] = $fyntune_version;
                    return  [
                        'status' => true,
                        'data' => $mmv_data
                    ];
                } else {
                    return  [
                        'status' => false,
                        'message' => (strtoupper($ic_name) == 'CHOLLA_MANDALAM' ? 'CHOLA_MANDALAM' : strtoupper($ic_name)).' Mapping Does Not Exists'
                    ];
                }
            }
        }
    }

    public function get_mmv_details_by_variant_period($productData, $version_id, $ic_name, $manufacture_year,)
    {
        ini_set('memory_limit', '1024M');

        $activeSubTypes = MasterProductSubType::where('status', 'Active')
            ->pluck('product_sub_type_id')
            ->toArray();
        if (!in_array($productData->product_sub_type_id, $activeSubTypes)) 
        {
            return [
                'status' => false,
                'message' => 'Invalid Product Sub Type'
            ];
        }

        $env = config('app.env');
        $env_folder = in_array($env, ['test', 'live']) ? 'production' : 'uat';
        $path = "mmv_masters/{$env_folder}/";

        $productMap = [
            1  => 'motor', 2  => 'bike', 5  => 'pcv', 6  => 'pcv', 7  => 'pcv',
            9  => 'gcv',   10 => 'pcv',  11 => 'pcv', 12 => 'pcv', 13 => 'gcv',
            14 => 'gcv',   15 => 'gcv',  16 => 'gcv', 17 => 'misc',18 => 'misc',
        ];

        $product = $productMap[$productData->product_sub_type_id] ?? 'motor';
        if (in_array($ic_name, explode(',', config(strtoupper($product) . '_ICS_USING_UAT_MMV')))) 
        {
            $ic_name .= '_uat';
        }

        if ($version_id === '') 
        {
            return ['status' => false, 'message' => 'Vehicle Not Mapped'];
        }

        if (strtolower($version_id) === 'dne') 
        {
            return [
                'status'  => false, 
                'message' => 'Vehicle Does Not Exist'
            ];
        }

        if (strtolower($version_id) === 'declined') 
        {
            return [
                'status'  => false, 
                'message' => 'The vehicle is Declined / Blacklisted'
            ];
        }

        $productSuffix = $product === 'motor' ? '' : "_{$product}";
        $icFile = $path . "{$ic_name}{$productSuffix}_model_master.json";

        $icData = json_decode(
            Storage::disk(config('filesystems.driver.s3.status') === 'active'
                ? config('filesystems.default')
                : 'public')->get($icFile),true
        );

        if (!isset($icData)) 
        {
            return [
                'status'  => false,
                'message' => strtoupper($ic_name) . ' Mapping Does Not Exist'
            ];
        }

        $mmv_arr = [];
        $mmv_details = [];
        $max_model_code_overlap_year = null;
        $model_code_overlap_year = null;

        foreach ($icData as $key => $val) 
        {
            if (!isset($val['FYNTUNE_VERSION_ID']) || $val['FYNTUNE_VERSION_ID'] != $version_id) 
            {
                continue;
            }

            $start_period = $val['MODEL_PERIOD_START'] ?? null;
            $end_period = $val['MODEL_PERIOD_END'] ?? null;

            if (!$start_period || !$end_period) 
            {
                continue;
            }

            $purchaseYear = $manufacture_year ?? now();
            array_push($mmv_arr, $val);

            if ($purchaseYear >= $start_period && $purchaseYear <= $end_period) 
            {
                if (!isset($max_model_code_overlap_year) || $val['SERIAL_NO'] > $max_model_code_overlap_year) 
                {
                    $max_model_code_overlap_year = $val['SERIAL_NO'];
                    $model_code_overlap_year = $val['NIC_VARIANT_CODE'];
                    $mmv_details = $val;
                }
            }
        }

        $mmv_data = [
            'nic_mmv_details'  => $mmv_details,
            'ic_version_code'  => $max_model_code_overlap_year,
            'NIC_VARIANT_CODE' => $model_code_overlap_year
        ];

        return [
            'status' => true,
            'data'   => $mmv_data
        ];
    }
}
