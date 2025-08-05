<?php

namespace Database\Seeders;
use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class ShriramPcvV1ConfigSeeder extends Seeder
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
                'key' => 'IC.SHRIRAM.V1.PCV.ENABLE',
                'label' => 'IC.SHRIRAM.V1.PCV.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.SHRIRAM_USERNAME',
                'label' => 'IC.SHRIRAM.V1.PCV.SHRIRAM_USERNAME',
                'value' => '', // NiveshIns
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.SHRIRAM_PASSWORD',
                'label' => 'IC.SHRIRAM.V1.PCV.SHRIRAM_PASSWORD',
                'value' => '', // shriram@1
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.QUOTE_URL',
                'label' => 'IC.SHRIRAM.V1.PCV.QUOTE_URL',
                'value' => 'https://novauat.shriramgi.com/UATWebAggrNAPI/PolicyGeneration.svc/RestService/GetQuote', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.PROPOSAL_SUBMIT_URL',
                'label' => 'IC.SHRIRAM.V1.PCV.PROPOSAL_SUBMIT_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/GeneratePCCVProposal', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.BREAKIN_BRANCH_PARTY_ID',
                'label' => 'IC.SHRIRAM.V1.PCV.BREAKIN_BRANCH_PARTY_ID',
                'value' => '',  //127126
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.SHRIRAM_BREAKIN_SURVEYOR_PARTY_ID',
                'label' => 'IC.SHRIRAM.V1.PCV.SHRIRAM_BREAKIN_SURVEYOR_PARTY_ID',
                'value' => '',  //1838197
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.BREAKIN_USER_PARTY_ID',
                'label' => 'IC.SHRIRAM.V1.PCV.BREAKIN_USER_PARTY_ID',
                'value' => '',  //1974353
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.BREAKIN_SOURCE_FROM',
                'label' => 'IC.SHRIRAM.V1.PCV.BREAKIN_SOURCE_FROM',
                'value' => 'M-Nova', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.BREAKIN_LOGIN_ID',
                'label' => 'IC.SHRIRAM.V1.PCV.BREAKIN_LOGIN_ID',
                'value' => '',  //NC117
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.BREAKIN_ID_GENERATION_URL',
                'label' => 'IC.SHRIRAM.V1.PCV.BREAKIN_ID_GENERATION_URL',
                'value' => 'http://novaapiuat.shriramgi.com/UATShrigenAppService2.0/ShrigenServices/PreInspectionDetails.svc/RestService/PreInspectionCreation', 
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.PCV.REQUEST_TYPE',
                'label' => 'IC.SHRIRAM.V1.PCV.REQUEST_TYPE',
                'value' => '', //JSON
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.PCV.PAYMENT_URL',
                'label' => 'IC.SHRIRAM.V1.PCV.PAYMENT_URL',
                'value' => 'http://novaapiuat.shriramgi.com/uatnovapymt/MyDefaultCC.aspx', 
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.PCV.PAYMENT_BEFORE_URL_METHOD',
                'label' => 'IC.SHRIRAM.V1.PCV.PAYMENT_BEFORE_URL_METHOD',
                'value' => 'POST', 
                'environment' => 'local'
            ],

            
            [
                'key' => 'IC.SHRIRAM.V1.PCV.PAYMENT_TYPE',
                'label' => 'IC.SHRIRAM.V1.PCV.PAYMENT_TYPE',
                'value' => '', // CCAVENUE
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.PCV.PAYMENT_STATUS_CHECK_URL',
                'label' => 'IC.SHRIRAM.V1.PCV.PAYMENT_STATUS_CHECK_URL',
                'value' => 'http://novaapiuat.shriramgi.com/UATNovaWS/novaservices/WebAggregator.svc/RestService/getPaymentStatus',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.PCV.POLICY_APPROVED_URL',
                'label' => 'IC.SHRIRAM.V1.PCV.POLICY_APPROVED_URL',
                'value' => 'http://119.226.131.2/ShriramService/ShriramService.asmx',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.PCV.APPLICATION_NAME',
                'label' => 'IC.SHRIRAM.V1.PCV.APPLICATION_NAME',
                'value' => '', //HeroIns
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
