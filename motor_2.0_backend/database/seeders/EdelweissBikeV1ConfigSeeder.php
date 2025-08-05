<?php

namespace Database\Seeders;
use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class EdelweissBikeV1ConfigSeeder extends Seeder
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
                'key' => 'IC.EDELWEISS.V1.BIKE.ENABLE',
                'label' => 'IC.EDELWEISS.V1.BIKE.ENABLE',
                'value' => '', //Y
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_TOKEN_GENERATION',
                'label' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_TOKEN_GENERATION',
                'value' => '', //https://devapi.edelweissinsurance.com/oauth2/token
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.TOKEN_USER_NAME',
                'label' => 'IC.EDELWEISS.V1.BIKE.TOKEN_USER_NAME',
                'value' => '', //6h1sf4cnka3fma5s5m9r9sgu1i
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.TOKEN_PASSWORD',
                'label' => 'IC.EDELWEISS.V1.BIKE.TOKEN_PASSWORD',
                'value' => '', //1vkkms4p18ghg0tq53igk25rt2q160rp2cblvc2isbqlbud6kudp
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.BRANCH',
                'label' => 'IC.EDELWEISS.V1.BIKE.BRANCH',
                'value' => '', //Mumbai
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_QUOTE_GENERATION',
                'label' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_QUOTE_GENERATION',
                'value' => '', //https://devapi.edelweissinsurance.com/motor-two-wheeler/rating
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.CONTRACT_COMMISION_ID',
                'label' => 'IC.EDELWEISS.V1.BIKE.CONTRACT_COMMISION_ID',
                'value' => '', //1000012208
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.AGENT_EMAIL',
                'label' => 'IC.EDELWEISS.V1.BIKE.AGENT_EMAIL',
                'value' => '',  //shivakumar.bale@qualitykiosk.com
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.SALES_MANAGER_CODE',
                'label' => 'IC.EDELWEISS.V1.BIKE.SALES_MANAGER_CODE',
                'value' => '',  //26058
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.SALES_MANGER_NAME',
                'label' => 'IC.EDELWEISS.V1.BIKE.SALES_MANGER_NAME',
                'value' => '',   //Rahul B
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_PROPOSAL_GENERATION',
                'label' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_PROPOSAL_GENERATION',
                'value' => '',  //https://devapi.edelweissinsurance.com/motor-two-wheeler/full-quote
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.MOTOR_USERNAME',
                'label' => 'IC.EDELWEISS.V1.BIKE.MOTOR_USERNAME',
                'value' => '',   //BAJCAP
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.MERCHANT_ID',
                'label' => 'IC.EDELWEISS.V1.BIKE.MERCHANT_ID',
                'value' => '',   //EDGENBKAGR
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.USER_ID',
                'label' => 'IC.EDELWEISS.V1.BIKE.USER_ID',
                'value' => '',    //EDGENRENEW- NA
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.MOTOR_CHECKSUM_KEY',
                'label' => 'IC.EDELWEISS.V1.BIKE.MOTOR_CHECKSUM_KEY',
                'value' => '',   // QBkzsMt99QHqoLvJWXS3iDjo8OYKUMFj
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_PAYMENT_GATEWAY',
                'label' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_PAYMENT_GATEWAY',
                'value' => '',   //https://uat.billdesk.com/pgidsk/PGIMerchantPayment
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_POLICY_GENERATION',
                'label' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_POLICY_GENERATION',
                'value' => '',   //https://devapi.edelweissinsurance.com/motor-two-wheeler/issue-policy
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.MOTOR_BPID',
                'label' => 'IC.EDELWEISS.V1.BIKE.MOTOR_BPID',
                'value' => '',   //1000012208
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_PAYMENT_REQUEST',
                'label' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_PAYMENT_REQUEST',
                'value' => '',   //https://devapi.edelweissinsurance.com/motor-two-wheeler/online-payment-request
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_PDF_SERVICE',
                'label' => 'IC.EDELWEISS.V1.BIKE.END_POINT_URL_PDF_SERVICE',
                'value' => '',   //https://devapi.edelweissinsurance.com/motor-two-wheeler/pdf-generation
                'environment' => 'local'
            ],
            [
                'key' => 'IC.EDELWEISS.V1.BIKE.ENABLE_PAYMENT_LOT_CREATION',
                'label' => 'IC.EDELWEISS.V1.BIKE.ENABLE_PAYMENT_LOT_CREATION',
                'value' => '',   //Y
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
