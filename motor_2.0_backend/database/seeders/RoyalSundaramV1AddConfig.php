<?php

namespace Database\Seeders;

use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class RoyalSundaramV1AddConfig extends Seeder
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
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.ENABLE',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.AGENTID',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.AGENTID',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.APIKEY',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.APIKEY',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.END_POINT_URL',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.END_POINT_URL',
                'value' => 'https://dtcdocstag.royalsundaram.in/Services/Product/PrivateCar/CalculatePremium',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.ENABLE_CONSUMABLE_AS_BUILT_IN',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.ENABLE_CONSUMABLE_AS_BUILT_IN',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.END_POINT_URL_ROYAL_SUNDARAM_MOTOR_PROPOSAL',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.END_POINT_URL_ROYAL_SUNDARAM_MOTOR_PROPOSAL',
                'value' => 'https://dtcdocstag.royalsundaram.in/Services/Product/PrivateCar/GProposalService',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.UPDATEVEHICLEDETAILS_END_POINT_URL',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.UPDATEVEHICLEDETAILS_END_POINT_URL',
                'value' => 'https://dtcdocstag.royalsundaram.in/Services/Product/PrivateCar/UpdateVehicleDetails',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.IS_POS_TESTING_MODE_ENABLE',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.IS_POS_TESTING_MODE_ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.ROYAL_SUNDARAM.V1.CAR.ENABLE_CKYC_ON_FIRST_CARD',
                'label' => 'IC.ROYAL_SUNDARAM.V1.CAR.ENABLE_CKYC_ON_FIRST_CARD',
                'value' => '',
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
