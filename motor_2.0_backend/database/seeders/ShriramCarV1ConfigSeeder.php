<?php

namespace Database\Seeders;
use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class ShriramCarV1ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $configs = [
            [
                'key' => 'IC.SHRIRAM.V1.CAR.ENABLE',
                'label' => 'IC.SHRIRAM.V1.CAR.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.CAR.QUOTE_URL',
                'label' => 'IC.SHRIRAM.V1.CAR.QUOTE_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/GetQuote',
                'environment' => 'local'
            ],
           
            [
                'key' => 'IC.SHRIRAM.V1.CAR.USERNAME',
                'label' => 'IC.SHRIRAM.V1.CAR.USERNAME',
                'value' => '',
                'environment' => 'local'
            ],
            
            [
                'key' => 'IC.SHRIRAM.V1.CAR.PASSWORD',
                'label' => 'IC.SHRIRAM.V1.CAR.PASSWORD',
                'value' => '',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.CAR.PROPOSAL_URL',
                'label' => 'IC.SHRIRAM.V1.CAR.PROPOSAL_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/GenerateProposal',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.CAR.PAYMENT_URL',
                'label' => 'IC.SHRIRAM.V1.CAR.PAYMENT_URL',
                'value' => 'http://novaapiuat.shriramgi.com/uatnovapymt/MyDefaultCC.aspx',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.CAR.PAYMENT_URL_PAYMENTFROM',
                'label' => 'IC.SHRIRAM.V1.CAR.PAYMENT_URL_PAYMENTFROM',
                'value' => '',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.CAR.GENERATE_PDF',
                'label' => 'IC.SHRIRAM.V1.CAR.GENERATE_PDF',
                'value' => 'http://shriramgi.net.in/GenerateDigitalSign/CreateDS_PDF.aspx?',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.CAR.POLICY_APPROVED_URL',
                'label' => 'IC.SHRIRAM.V1.CAR.POLICY_APPROVED_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/PolicyScheduleURL',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.CAR.AUTH_NAME_MOTOR',
                'label' => 'IC.SHRIRAM.V1.CAR.AUTH_NAME_MOTOR',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/PolicyScheduleURL',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.CAR.AUTH_PASS_MOTOR',
                'label' => 'IC.SHRIRAM.V1.CAR.AUTH_PASS_MOTOR',
                'value' => '',
                'environment' => 'local'
            ],


           
         

           
        ];

        foreach ($configs as $config) {

            $checkConfig = ConfigSetting::where(['key' => $config['key']])->first();

            if($checkConfig == null){
                ConfigSetting::updateOrCreate($config);
            }
        }
    }
}
