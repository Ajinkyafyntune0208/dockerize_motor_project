<?php


namespace Database\Seeders;
use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class ShriramGcvV1ConfigSeeder extends Seeder
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
                'key' => 'IC.SHRIRAM.V1.GCV.ENABLE',
                'label' => 'IC.SHRIRAM.V1.GCV.ENABLE',
                'value' => '',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.GCV.SHRIRAM_USERNAME',
                'label' => 'IC.SHRIRAM.V1.GCV.SHRIRAM_USERNAME',
                'value' => '', // NiveshIns
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.GCV.SHRIRAM_PASSWORD',
                'label' => 'IC.SHRIRAM.V1.GCV.SHRIRAM_PASSWORD',
                'value' => '', //shriram@1
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.QUOTE_URL',
                'label' => 'IC.SHRIRAM.V1.GCV.QUOTE_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/GetQuote',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.PROPOSAL_SUBMIT_URL',
                'label' => 'IC.SHRIRAM.V1.GCV.PROPOSAL_SUBMIT_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/GenerateGCCVProposal',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.PROPOSAL_SUBMIT_URL',
                'label' => 'IC.SHRIRAM.V1.GCV.PROPOSAL_SUBMIT_URL',
                'value' => 'https://novaapiuat.shriramgi.com/UATNOVADIGITAL/SVS_Services/PolicyGeneration.svc/RestService/GenerateGCCVProposal',
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.BREAKIN_BRANCH_PARTY_ID',
                'label' => 'IC.SHRIRAM.V1.GCV.BREAKIN_BRANCH_PARTY_ID',
                'value' => '', //127126
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.SHRIRAM_BREAKIN_SURVEYOR_PARTY_ID',
                'label' => 'IC.SHRIRAM.V1.GCV.SHRIRAM_BREAKIN_SURVEYOR_PARTY_ID',
                'value' => '', //1838197
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.BREAKIN_USER_PARTY_ID',
                'label' => 'IC.SHRIRAM.V1.GCV.BREAKIN_USER_PARTY_ID',
                'value' => '', //1974353
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.BREAKIN_SOURCE_FROM',
                'label' => 'IC.SHRIRAM.V1.GCV.BREAKIN_SOURCE_FROM',
                'value' => '', //M-Nova
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.BREAKIN_LOGIN_ID',
                'label' => 'IC.SHRIRAM.V1.GCV.BREAKIN_LOGIN_ID',
                'value' => '', //NC117
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.BREAKIN_ID_GENERATION_URL',
                'label' => 'IC.SHRIRAM.V1.GCV.BREAKIN_ID_GENERATION_URL',
                'value' => 'http://novaapiuat.shriramgi.com/UATShrigenAppService2.0/ShrigenServices/PreInspectionDetails.svc/RestService/PreInspectionCreation',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.GCV.REQUEST_TYPE',
                'label' => 'IC.SHRIRAM.V1.GCV.REQUEST_TYPE',
                'value' => '', //JSON
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.GCV.PAYMENT_URL',
                'label' => 'IC.SHRIRAM.V1.GCV.PAYMENT_URL',
                'value' => 'http://novaapiuat.shriramgi.com/uatnovapymt/MyDefaultCC.aspx', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.GCV.PAYMENT_BEFORE_URL_METHOD',
                'label' => 'IC.SHRIRAM.V1.GCV.PAYMENT_BEFORE_URL_METHOD',
                'value' => '', //POST
                'environment' => 'local'
            ],
            [
                'key' => 'IC.SHRIRAM.V1.GCV.APPLICATION_NAME',
                'label' => 'IC.SHRIRAM.V1.GCV.APPLICATION_NAME',
                'value' => '', //HeroIns
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.PAYMENT_TYPE',
                'label' => 'IC.SHRIRAM.V1.GCV.PAYMENT_TYPE',
                'value' => '', //CCAVENUE
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.POLICY_APPROVED_URL',
                'label' => 'IC.SHRIRAM.V1.GCV.POLICY_APPROVED_URL',
                'value' => 'http://119.226.131.2/ShriramService/ShriramService.asmx', 
                'environment' => 'local'
            ],

            [
                'key' => 'IC.SHRIRAM.V1.GCV.PAYMENT_STATUS_CHECK_URL',
                'label' => 'IC.SHRIRAM.V1.GCV.PAYMENT_STATUS_CHECK_URL',
                'value' => 'http://novaapiuat.shriramgi.com/UATNovaWS/novaservices/WebAggregator.svc/RestService/getPaymentStatus', 
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
