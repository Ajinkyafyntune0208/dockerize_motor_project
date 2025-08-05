<?php
use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
include_once app_path('Quotes/Renewal/Car/V2/tata_aig.php');

function getRenewalQuote($enquiryId, $requestData, $productData)
{
       
    if (config('IC.TATA.V2.CAR.RENEWAL.ENABLE') == 'Y')  return getTataRenewalQuote($enquiryId, $requestData, $productData);
    include_once app_path() . '/Quotes/Car/' . $productData->company_alias . '.php';
    $quoteData = getQuote($enquiryId, $requestData, $productData);
    if(isset($quoteData['data'])) 
    {
        $quoteData['data']['isRenewal'] = 'Y';
    }
    return $quoteData;
}