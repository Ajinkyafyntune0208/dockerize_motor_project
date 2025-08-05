<?php
namespace App\Http\Controllers\RenewalService\Bike;
use App\Models\SelectedAddons;

class hdfc_ergo
{
    protected $section;
    public function __construct()
    {   
        $this->section = 'BIKE';
    }
    
    public function IcServicefetchData($renwalData)
    { 
        include_once app_path() . '/Helpers/BikeWebServiceHelper.php';
        $policy_data = [
            'ConfigurationParam' => [
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_AGENT_CODE'),
            ],
            'PreviousPolicyNumber' =>  $renwalData['previous_policy_number'],
            'VehicleRegistrationNumber' => $renwalData['vehicale_registration_number']
        ];
//        $policy_data = [
//            'ConfigurationParam' => [
//                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_AGENT_CODE'),
//            ],
//            'PreviousPolicyNumber' =>  '2311201189006000000',
//            'VehicleRegistrationNumber' => 'MH-01-MW-1025'
//        ];
   
        $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_FETCH_POLICY_DETAILS');
        $get_response = getWsData($url, $policy_data, 'hdfc_ergo', [
            'section'       => $this->section,
            'method'        => 'Fetch Policy Details',
            'requestMethod' => 'post',
            'enquiryId'     => $renwalData['user_product_journey_id'],
            'productName'   => "Bike Fetch Policy Details",
            'transaction_type' => 'quote',
            'headers' => [
                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_MERCHANT_KEY'),//'RENEWBUY',//
                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_BIKE_SECRET_TOKEN'),//'vaHspz4yj6ixSaTFS4uEVw==',//
                'Content-Type' => 'application/json',
                //'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36',
                'User-Agent' => $_SERVER['HTTP_USER_AGENT']
                //'Accept-Language' => 'en-US,en;q=0.5'
            ]
        ]);
        return $get_response['response'] ?? NULL; 
    }
    public function prepareFetchData($policy_data_response)
    {
        $enquiryId = $policy_data_response['user_product_journey_id'];
        $policy_data_ic_response = json_decode($policy_data_response['policy_data_response'],true);
        if($policy_data_ic_response['Status'] == 200)
        {
            $all_data = $policy_data_ic_response['Data'];
            $zeroDepreciation           = 0;
            $engineProtect              = 0;
            $keyProtect                 = 0;
            $tyreProtect                = 0;
            $returnToInvoice            = 0;
            $lossOfPersonalBelongings   = 0;
            $roadSideAssistance         = 0;
            $consumables                = 0;
            $ncb_protection             = 0;
            //$policy_data_response['user_product_journey_id'] = 47071;
            $SelectedAddons = SelectedAddons::where('user_product_journey_id', $policy_data_response['user_product_journey_id'])
            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
            $save = false;
            $additional_covers = [];         
            if (!empty($SelectedAddons['additional_covers'])) 
            {
                $additional_covers = $SelectedAddons['additional_covers'];
                foreach ($additional_covers as $key => $data) 
                {
                    if ($data['name'] == 'Unnamed Passenger PA Cover' && $all_data['UNNamedpaLastyear'] == 'YES') 
                    {
                        $save = true;
                        $additional_covers[$key]['sumInsured'] = $all_data['UnnamedPersonSI'];
                    }
                }
            }
            else
            {
                if($all_data['UNNamedpaLastyear'] == 'YES')
                {
                    $save = true;
                    $UnnamedPassengerPACover = [
                        'name'       => 'Unnamed Passenger PA Cover',
                        'sumInsured' => $all_data['UnnamedPersonSI']
                    ];
                    $additional_covers[] = $UnnamedPassengerPACover;
                }
            }
            if($save)
            {
                selectedAddons::where(['user_product_journey_id' => $enquiryId])
                ->update(
                    [                 
                        'additional_covers' => $additional_covers
                ]);                
            }
        }    
        return true;
    }
}