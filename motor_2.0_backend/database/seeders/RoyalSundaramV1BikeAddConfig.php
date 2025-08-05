<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class RoyalSundaramV1BikeAddConfig extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $configs = [
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.BIKE.ENABLE',
                'label' => 'IC.ROYAL_SUNDARAM.V1.BIKE.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL',
                'label' => 'IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL',
                'value' => 'https://dtcdocstag.royalsundaram.in/Services/Product/TwoWheeler/CalculatePremium',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.BIKE.AGENTID',
                'label' => 'IC.ROYAL_SUNDARAM.V1.BIKE.AGENTID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.BIKE.APIKEY',
                'label' => 'IC.ROYAL_SUNDARAM.V1.BIKE.APIKEY',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL_UPDATEVEHICLEDETAILS',
                'label' => 'IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL_UPDATEVEHICLEDETAILS',
                'value' => 'https://dtcdocstag.royalsundaram.in/Services/Product/TwoWheeler/UpdateVehicleDetails',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.BIKE.IS_POS_TESTING_MODE_ENABLE',
                'label' => 'IC.ROYAL_SUNDARAM.V1.BIKE.IS_POS_TESTING_MODE_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.BIKE.ENABLE_CKYC_ON_FIRST_CARD',
                'label' => 'IC.ROYAL_SUNDARAM.V1.BIKE.ENABLE_CKYC_ON_FIRST_CARD',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL_PREMIUM',
                'label' => 'IC.ROYAL_SUNDARAM.V1.BIKE.END_POINT_URL_PREMIUM',
                'value' => 'https://dtcdocstag.royalsundaram.in/Services/Product/TwoWheeler/CalculatePremium',
                'environment' => 'local'
            ]
        ];


        foreach ($configs as $config) {
            $checkConfig = ConfigSetting::where(['key' => $config['key']])->first();

            if($checkConfig == null){
                ConfigSetting::updateOrCreate($config);
            }
        }
    }
}
