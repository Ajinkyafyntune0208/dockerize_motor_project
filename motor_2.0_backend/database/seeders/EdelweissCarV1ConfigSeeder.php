<?php

namespace Database\Seeders;
use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class EdelweissCarV1ConfigSeeder extends Seeder
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
                'key' => 'IC.EDELWEISS.V1.CAR.ENABLE',
                'label' => 'IC.EDELWEISS.V1.CAR.ENABLE',
                'value' => '', //Y
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_TOKEN_GENERATION',
                'label' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_TOKEN_GENERATION',
                'value' => 'https://devapi.hizuno.com/oauth2/token',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.TOKEN_USER_NAME',
                'label' => 'IC.EDELWEISS.V1.CAR.TOKEN_USER_NAME',
                'value' => '',//tekq056u5hpf5p9ris13vtbk7
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.TOKEN_PASSWORD',
                'label' => 'IC.EDELWEISS.V1.CAR.TOKEN_PASSWORD',
                'value' => '', //ov93qgacj8g18tvp8k8d87rh2n53m8nu9benveo2f9ga8f1icgg
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.CONTRACT_COMMISION_ID',
                'label' => 'IC.EDELWEISS.V1.CAR.CONTRACT_COMMISION_ID',
                'value' => '', //1000012208
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.GRID',
                'label' => 'IC.EDELWEISS.V1.CAR.GRID',
                'value' => '', //Grid 1
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_QUOTE_GENERATION',
                'label' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_QUOTE_GENERATION',
                'value' => 'https://devapi.hizuno.com/motor/quote',
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.BRANCH',
                'label' => 'IC.EDELWEISS.V1.CAR.BRANCH',
                'value' => '', //Mumbai
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.AGENT_EMAIL',
                'label' => 'IC.EDELWEISS.V1.CAR.AGENT_EMAIL',
                'value' => '', //shivakumar.bale@qualitykiosk.com
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.SALES_MANAGER_CODE',
                'label' => 'IC.EDELWEISS.V1.CAR.SALES_MANAGER_CODE',
                'value' => '', // 26058
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.SALES_MANGER_NAME',
                'label' => 'IC.EDELWEISS.V1.CAR.SALES_MANGER_NAME',
                'value' => '', // Rahul B
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_PROPOSAL_GENERATION',
                'label' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_PROPOSAL_GENERATION',
                'value' => 'https://devapi.hizuno.com/motor/fullQuote', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.MOTOR_MERCHANT_ID',
                'label' => 'IC.EDELWEISS.V1.CAR.MOTOR_MERCHANT_ID',
                'value' => '',  //EDGENBKAGR
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.MOTOR_USER_ID',
                'label' => 'IC.EDELWEISS.V1.CAR.MOTOR_USER_ID',
                'value' => '',  //EDGENRENEW- NA
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.MOTOR_CHECKSUM_KEY',
                'label' => 'IC.EDELWEISS.V1.CAR.MOTOR_CHECKSUM_KEY',
                'value' => '',  //uatF5M7bP9j4
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_PAYMENT_GATEWAY',
                'label' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_PAYMENT_GATEWAY',
                'value' => '',  //https://uat.billdesk.com/pgidsk/PGIMerchantPayment
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_POLICY_GENERATION',
                'label' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_POLICY_GENERATION',
                'value' => 'https://devapi.hizuno.com/motor/issue-policy', 
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.MOTOR_BPID',
                'label' => 'IC.EDELWEISS.V1.CAR.MOTOR_BPID',
                'value' => '',  //1000012208
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_PAYMENT_REQUEST',
                'label' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_PAYMENT_REQUEST',
                'value' => 'https://devapi.hizuno.com/motor/online-payment-request',  
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_PDF_SERVICE',
                'label' => 'IC.EDELWEISS.V1.CAR.END_POINT_URL_PDF_SERVICE',
                'value' => 'https://devapi.hizuno.com/motor/pdf-generation',  
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.X_API_KEY',
                'label' => 'IC.EDELWEISS.V1.CAR.X_API_KEY',
                'value' => '',   //ZFwDqn2ieW85L5XIqt7su3bBPqLzhGK827u1F2kr
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.CAR.PAYMENT_LOT_CREATION',
                'label' => 'IC.EDELWEISS.V1.CAR.PAYMENT_LOT_CREATION',
                'value' => '', //Y  
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
