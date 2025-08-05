<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class VahanHelper
{
    public static function validateQuouteAndVahanMMv(Request $request, $vahanVersionId, $userProductJourneyId)
    {
        if (config('proposalPage.vehicleValidation.mmvValidationQuoteProposal') == 'Y') {

            $versionId = \App\Models\CorporateVehiclesQuotesRequest::where('user_product_journey_id', $userProductJourneyId)->pluck('version_id')->first();

            $sellerType = \App\Models\CvAgentMapping::where('user_product_journey_id', $userProductJourneyId)->pluck('seller_type')->first();

            $sellerType = empty($sellerType) ? 'B2C': strtoupper($sellerType);

            if (!self::isValidationEnabled($sellerType)) {
                return [
                    'status' => true,
                ];
            }
            
            $proposal_mmv_details = get_fyntune_mmv_details(\Illuminate\Support\Str::substr($vahanVersionId, 0, 3), $vahanVersionId);
            $quote_mmv_details = get_fyntune_mmv_details(\Illuminate\Support\Str::substr($versionId, 0, 3), $versionId);

            $quote_mmv_details = $quote_mmv_details['data']; // Quote MMV details Data
            $proposal_mmv_details = $proposal_mmv_details['data']; // Quote MMV details Data

            $quote_manufacturer = strtolower($quote_mmv_details['manufacturer']['manf_name']);
            $quote_model = strtolower($quote_mmv_details['model']['model_name']);
            $quote_fuel_type = strtolower($quote_mmv_details['version']['fuel_type']);

            $proposal_manufacturer = strtolower($proposal_mmv_details['manufacturer']['manf_name']);
            $proposal_model = strtolower($proposal_mmv_details['model']['model_name']);
            $proposal_fuel_type = strtolower($proposal_mmv_details['version']['fuel_type']);

            $enquiryId = config('enquiry_id_encryption') == 'Y' ? getDecryptedEnquiryId($request->enquiryId) : $request->enquiryId;

            if ($quote_manufacturer != $proposal_manufacturer && self::isValidationEnabled('Make')) {
                return [
                    'status' => false,
                    'overrideMsg' => "Trace ID - $enquiryId Vehicle Make mismatch with Vahan. Policy issuance is not allowed",
                    'data' => [
                        'status' => 101,
                        'showErrorMsg' => true,
                        'overrideMsg' => "Trace ID - $enquiryId Vehicle Make mismatch with Vahan. Policy issuance is not allowed",
                    ]
                ];
            }

            if ($quote_model != $proposal_model && self::isValidationEnabled('Model')) {
                return [
                    'status' => false,
                    'overrideMsg' => "Trace ID - $enquiryId Vehicle Model mismatch with Vahan. Policy issuance is not allowed",
                    'data' => [
                        'status' => 101,
                        'showErrorMsg' => true,
                        'overrideMsg' => "Trace ID - $enquiryId Vehicle Model mismatch with Vahan. Policy issuance is not allowed",
                    ],
                ];
            }

            if ($quote_fuel_type != $proposal_fuel_type && self::isValidationEnabled('Fuel')) {
                return [
                    'status' => false,
                    'overrideMsg' => "Trace ID - $enquiryId Vehicle Fuel Type mismatch with Vahan. Policy issuance is not allowed",
                    'data' => [
                        'status' => 101,
                        'showErrorMsg' => true,
                        'overrideMsg' => "Trace ID - $enquiryId Vehicle Fuel Type mismatch with Vahan. Policy issuance is not allowed",
                    ],
                ];
            }
        }
        return [
            'status' => true
        ];
    }

    public static function isValidationEnabled($type)
    {
        $value = \App\Models\Mmvproposaljourneyblocker::where('name', $type)
            ->pluck('value')
            ->first();
        return $value == 'Y';
    }
}
