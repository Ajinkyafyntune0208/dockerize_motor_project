<?php
use Carbon\Carbon;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;

function getRenewalQuote($enquiryId, $requestData, $productData)
{
    include_once app_path() . '/Quotes/Bike/' . $productData->company_alias . '.php';
    $quoteData = getQuote($enquiryId, $requestData, $productData);
    if(isset($quoteData['data']))
    {
        $quoteData['data']['isRenewal'] = 'Y';
    }
    return $quoteData;
}