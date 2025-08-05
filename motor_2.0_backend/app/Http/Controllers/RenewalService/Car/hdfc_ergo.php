<?php
namespace App\Http\Controllers\RenewalService\Car;
use App\Models\SelectedAddons;

class hdfc_ergo
{
    protected $section;
    public function __construct()
    {   
        $this->section = 'CAR';
    }
    
    public function IcServicefetchData($renwalData)
    { 
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        $policy_data = [
            'ConfigurationParam' => [
                'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE'),
            ],
            'PreviousPolicyNumber' =>  $renwalData['previous_policy_number'],
            'VehicleRegistrationNumber' => $renwalData['vehicale_registration_number']
        ];
        // $policy_data = [
        //    'ConfigurationParam' => [
        //        'AgentCode' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_AGENT_CODE'),
        //    ],
        //    'PreviousPolicyNumber' =>  '2302201189699800000',
        //    'VehicleRegistrationNumber' => 'MH-01-DR-1009'
        // ];
        $url = config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_FETCH_POLICY_DETAILS');
        $get_response = getWsData($url, $policy_data, 'hdfc_ergo', [
            'section' => $this->section,
            'method' => 'Fetch Policy Details',
            'requestMethod' => 'post',
            'enquiryId' => $renwalData['user_product_journey_id'],
            'productName' => "Car Fetch Policy Details",
            'transaction_type' => 'quote',
            'headers' => [
                'MerchantKey' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_MERCHANT_KEY'),
                'SecretToken' => config('constants.IcConstants.hdfc_ergo.HDFC_ERGO_V2_SECRET_TOKEN'),
                'Content-Type' => 'application/json',                
                'User-Agent' => $_SERVER['HTTP_USER_AGENT']
            ]
        ]);
        return $get_response['response'] ?? NULL; 
    }
    //send second param as TRUE if want to update addons else FALSE
    public function prepareFetchData($policy_data_response,$db_save = true)
    {
        $applicable_addons = [];
        $enquiryId = $policy_data_response['user_product_journey_id'];
        $policy_data_ic_response = json_decode($policy_data_response['policy_data_response'],true);
        if($policy_data_ic_response['Status'] == 200)
        {
            $all_data = $policy_data_ic_response['Data'];
            $AddOnsOptedLastYear = explode(',',$all_data['AddOnsOptedLastYear']);
            $PrivateCarRenewalPremiumList = $all_data['PrivateCarRenewalPremiumList'][0];
            $AddOnCovers = $PrivateCarRenewalPremiumList['AddOnCovers'] ?? '';

            $zeroDepreciation           = 0;
            $engineProtect              = 0;
            $keyProtect                 = 0;
            $tyreProtect                = 0;
            $returnToInvoice            = 0;
            $lossOfPersonalBelongings   = 0;
            $roadSideAssistance         = 0;
            $consumables                = 0;
            $ncb_protection             = 0;

            $ElectricalAccessoriesIdv    = $all_data['ElectricalAccessoriesIdv'];
            $NonelectricalAccessoriesIdv = $all_data['NonelectricalAccessoriesIdv'];
            $LpgCngKitODPremium          = $all_data['LpgCngKitIdv'];

            $LLPaidDriversPremium    = $PrivateCarRenewalPremiumList['LLPaidDriversPremium'];
            $UnnamedPassengerPremium = $PrivateCarRenewalPremiumList['UnnamedPassengerPremium'];
            $PAPaidDriverPremium     = $PrivateCarRenewalPremiumList['PAPaidDriverPremium'];

           // $policy_data_response['user_product_journey_id'] = 47071;
            $SelectedAddons = SelectedAddons::where('user_product_journey_id', $policy_data_response['user_product_journey_id'])
            ->select('compulsory_personal_accident', 'applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();
            $additional_covers = [];
            $accessories       = [];
            $UnnamedPassengerPACover_found = false;

            if (!empty($SelectedAddons['additional_covers']) && false)//set false for reset all addons as per ic
            {
                $additional_covers = $SelectedAddons['additional_covers'];
                foreach ($additional_covers as $key => $data) 
                {
                    if ($data['name'] == 'Unnamed Passenger PA Cover')
                    {
                        $UnnamedPassengerPACover_found = true;
                        if($PrivateCarRenewalPremiumList['UnnamedPassengerPremium'] > 0)
                        {
                            $additional_covers[$key]['sumInsured'] = $all_data['UnnamedPassengerSI'];
                        }
                        else
                        {
                            unset($additional_covers[$key]);
                        }
                    }
                }
                if(!$UnnamedPassengerPACover_found && $PrivateCarRenewalPremiumList['UnnamedPassengerPremium'] > 0)
                {
                    $UnnamedPassengerPACover = [
                        'name'       => 'Unnamed Passenger PA Cover',
                        'sumInsured' => $all_data['UnnamedPassengerSI']
                    ];
                    $additional_covers[] = $UnnamedPassengerPACover;
                }
            }
            else
            {
                $applicable_addons = [
                    'Electrical Accessories',
                    'Non-Electrical Accessories',
                    'External Bi-Fuel Kit CNG/LPG',
                    'LL paid driver',
                    'Unnamed Passenger PA Cover',
                    'PA cover for additional paid driver'
                ];
                //Start - Accessories
                if($ElectricalAccessoriesIdv > 0)
                {
                    $accessories[] = [
                        'name'       => 'Electrical Accessories',
                        'sumInsured' => (int) $ElectricalAccessoriesIdv
                    ];
                    array_splice($applicable_addons, array_search('Electrical Accessories', $applicable_addons), 1);
                }
                if($NonelectricalAccessoriesIdv > 0)
                {
                    $accessories[] = [
                        'name'       => 'Non-Electrical Accessories',
                        'sumInsured' => (int) $NonelectricalAccessoriesIdv
                    ];
                    array_splice($applicable_addons, array_search('Non-Electrical Accessories', $applicable_addons), 1);
                }
                if($LpgCngKitODPremium > 0)
                {
                    $accessories[] = [
                        'name'       => 'External Bi-Fuel Kit CNG/LPG',
                        'sumInsured' => (int) $LpgCngKitODPremium
                    ];
                    array_splice($applicable_addons, array_search('External Bi-Fuel Kit CNG/LPG', $applicable_addons), 1);
                }
                //End - Accessories

                //Start - additional_covers
                if($LLPaidDriversPremium > 0)
                {
                    $additional_covers[] = [
                        'name'       => 'LL paid driver',
                        'sumInsured' => '100000'
                    ];
                    array_splice($applicable_addons, array_search('LL paid driver', $applicable_addons), 1);
                }

                if($UnnamedPassengerPremium > 0)
                {
                    $additional_covers[] = [
                        'name'       => 'Unnamed Passenger PA Cover',
                        'sumInsured' => (int) $all_data['UnnamedPassengerSI']
                    ];
                    array_splice($applicable_addons, array_search('Unnamed Passenger PA Cover', $applicable_addons), 1);
                }

                if($PAPaidDriverPremium > 0)
                {
                    $additional_covers[] = [
                        'name'       => 'PA cover for additional paid driver',
                        'sumInsured' => (int) $all_data['PAPaidDriverSI']
                    ];
                    array_splice($applicable_addons, array_search('PA cover for additional paid driver', $applicable_addons), 1);
                }
            }

            if($db_save)
            {
                selectedAddons::where(['user_product_journey_id' => $enquiryId])
                ->update(
                [
                        'additional_covers' => array_values($additional_covers),
                        'accessories'       => array_values($accessories)
                ]);
            }
        }

        return
        [
            'removal_addons' => $applicable_addons
        ];
    }
}