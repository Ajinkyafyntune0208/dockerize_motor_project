<?php 

namespace App\Quotes\FetchRenewalData\Car;

use Illuminate\Support\Facades\Storage;
use Mtownsend\XmlToArray\XmlToArray;

class future_generali
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';

        $policy_data = [
            "PolicyNo" => $value['previous_policy_number'],
            "ExpiryDate" => '',
            "RegistrationNo" => '',
            "VendorCode" => config('constants.IcConstants.future_generali.FG_RENEWAL_VENDOR_CODE'),
        ];

        $url = config('constants.IcConstants.future_generali.FG_RENEWAL_CAR_FETCH_POLICY_DETAILS');
        $response = getWsData($url, $policy_data, 'future_generali', [
            'section' => "CAR",
            'method' => 'Renewal Fetch Policy Details',
            'requestMethod' => 'post',
            'enquiryId' => $enquiryId,
            'productName' => 'Car Insurance',
            'transaction_type' => 'quote',
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);

        $response = $response['response'];
        if (!empty($response)) {
            $quote_policy_output = XmlToArray::convert($response);
            if (($quote_policy_output['Policy']['Status'] ?? '') != 'Fail') {
                $quote_output = $quote_policy_output['PremiumBreakup']['NewDataSet']['Table'];
                foreach ($quote_output as $cover) {
                    $cover = array_map('trim', $cover);
                        $premium = $cover['BOValue'];
                        if (empty($cover) || empty($premium)) {
                            continue;
                        }
                    if (($cover['Code'] == 'VehicaleIDV') && ($cover['Type'] == 'OD')) {
                        $value['idv'] = $premium;
                    } elseif ((in_array($cover['Code'],['LLDE','LLDC'])) && ($cover['Type'] == 'TP')) {
                        $value['ll_paid_driver'] = $premium;
                    } elseif (($cover['Code'] == 'CPA') && ($cover['Type'] == 'TP')) {
                        $value['cpa_amount'] = $premium;
                    } elseif (($cover['Code'] == 'APA') && ($cover['Type'] == 'TP')) {
                        $value['unnammed_passenger_pa_cover'] = $premium;
                    } elseif (in_array($cover['Code'], ['CNG']) && ($cover['Type'] == 'OD')) {
                        $value['external_bifuel_cng_lpg'] = $premium;
                    } elseif (in_array($cover['Code'], ['CNG', 'CNGTP']) && ($cover['Type'] == 'TP')) {
                        $value['external_bifuel_cng_lpg'] = $premium;
                    } elseif (($cover['Code'] == 'EAV') && ($cover['Type'] == 'OD')) {
                        $value['electrical'] = $premium;
                    } elseif (($cover['Code'] == 'NEA') && ($cover['Type'] == 'OD')) {
                        $value['not-electrical'] = $premium;
                    }
                }
            }
        }

        //made status as failure, becuase IC is not sending required information in fetch response
        return false;
    }
}