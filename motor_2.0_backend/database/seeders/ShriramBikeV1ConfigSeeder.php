<?php

namespace Database\Seeders;
use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class ShriramBikeV1ConfigSeeder extends Seeder
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
                'key' => 'IC.SHRIRAM.V1.BIKE.ENABLE',
                'label' => 'IC.SHRIRAM.V1.BIKE.ENABLE',
                'value' => '', //Y
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.QUOTE_URL',
                'label' => 'IC.SHRIRAM.V1.BIKE.QUOTE_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/GetQuote',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.BIKE.USERNAME',
                'label' => 'IC.SHRIRAM.V1.BIKE.USERNAME',
                'value' => '', //LC331
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.PASSWORD',
                'label' => 'IC.SHRIRAM.V1.BIKE.PASSWORD',
                'value' => '', //shriram@2
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.RENEW_PROPOSAL_URL',
                'label' => 'IC.SHRIRAM.V1.BIKE.RENEW_PROPOSAL_URL',
                'value' => 'https://uatcollab.shriramgi.com/ShriramService/ShriramService.asmx', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.NEW_PROPOSAL_URL',
                'label' => 'IC.SHRIRAM.V1.BIKE.NEW_PROPOSAL_URL',
                'value' => 'https://uatcollab.shriramgi.com/ShriramService/ShriramService.asmx', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.PROPOSAL_URL',
                'label' => 'IC.SHRIRAM.V1.BIKE.PROPOSAL_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/GenerateProposal', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.PAYMENT_URL',
                'label' => 'IC.SHRIRAM.V1.BIKE.PAYMENT_URL',
                'value' => 'http://novaapiuat.shriramgi.com/uatnovapymt/MyDefaultCC.aspx', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.PAYMENT_URL_PAYMENTFROM',
                'label' => 'IC.SHRIRAM.V1.BIKE.PAYMENT_URL_PAYMENTFROM',
                'value' => '', //CCAVENUE
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.REQUEST_TYPE',
                'label' => 'IC.SHRIRAM.V1.BIKE.REQUEST_TYPE',
                'value' => '', //JSON
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.AUTH_NAME',
                'label' => 'IC.SHRIRAM.V1.BIKE.AUTH_NAME',
                'value' => '', //TEST
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.AUTH_PASS',
                'label' => 'IC.SHRIRAM.V1.BIKE.AUTH_PASS', 
                'value' => '', //TEST@123
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.POLICY_APPROVED_URL',
                'label' => 'IC.SHRIRAM.V1.BIKE.POLICY_APPROVED_URL', 
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/PolicyScheduleURL', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.BIKE.GENERATE_PDF',
                'label' => 'IC.SHRIRAM.V1.BIKE.GENERATE_PDF', 
                'value' => 'http://shriramgi.net.in/GenerateDigitalSign/CreateDS_PDF.aspx?', 
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
