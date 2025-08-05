<?php

namespace App\Http\Controllers\Proposal\Services;
include_once app_path().'/Helpers/CvWebServiceHelper.php';

use App\Http\Controllers\SyncPremiumDetail\Services\LibertyVideoconPremiumDetailController;
use Carbon\Carbon;
use App\Models\UserProposal;
use App\Models\SelectedAddons;
use Illuminate\Support\Facades\DB;
use Mtownsend\XmlToArray\XmlToArray;
use Spatie\ArrayToXml\ArrayToXml;

class libertyVideoconSubmitProposal
{
    public static function submit($proposal, $request)
    {
        $quote = DB::table('quote_log')->where('user_product_journey_id', $proposal->user_product_journey_id)->first();
        $requestData = getQuotation($proposal->user_product_journey_id);
        $productData = getProductDataByIc($request['policyId']);
        $quote_data = json_decode($quote->premium_json,true);

        $inspection_no = '';
        $mmv = get_mmv_details($productData, $requestData->version_id, 'liberty_videocon');

        if ($mmv['status'] == 1) {
            $mmv = $mmv['data'];
        } else {
            return  [
                'premium_amount' => 0,
                'status' => false,
                'message' => $mmv['message']
            ];
        }

        $mmv = (object) array_change_key_case((array) $mmv, CASE_LOWER);

        if (empty($mmv->ic_version_code)) {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle Not Mapped',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle Not Mapped',
                ]
            ];
        } elseif ($mmv->ic_version_code == 'DNE') {
            return [
                'premium_amount' => 0,
                'status' => false,
                'message' => 'Vehicle code does not exist with Insurance company',
                'request' => [
                    'mmv' => $mmv,
                    'message' => 'Vehicle code does not exist with Insurance company',
                ]
            ];
        }

        $premium_type = DB::table('master_premium_type')
            ->where('id', $productData->premium_type_id)
            ->pluck('premium_type_code')
            ->first();

        $is_package     = (($premium_type == 'comprehensive' || $premium_type == 'breakin') ? true : false);
        $is_liability   = (($premium_type == 'third_party') ? true : false);
        $is_individual  = (($requestData->vehicle_owner_type == 'I') ? true : false);
        $is_new         = (($requestData->business_type == "rollover" || $requestData->business_type == "breakin") ? false : true);

        $is_breakin     = ((strpos($requestData->business_type, 'breakin') === false) ? false : true);

        $is_zero_dep    = (($productData->zero_dep == '0') ? true : false);

        $vehicle_invoice_date = new \DateTime($requestData->vehicle_invoice_date);
        $registration_date = new \DateTime($requestData->vehicle_register_date);

        $date1 = !empty($requestData->vehicle_invoice_date) ? $vehicle_invoice_date : $registration_date;
        $vehicleDate  = !empty($requestData->vehicle_invoice_date) ? $requestData->vehicle_invoice_date : $requestData->vehicle_register_date;
        $date2 = new \DateTime($requestData->previous_policy_expiry_date == 'New' ? date('Y-m-d') : $requestData->previous_policy_expiry_date);
        $interval = $date1->diff($date2);
        $age = (($interval->y * 12) + $interval->m) + 1;
        $vehicle_age = floor($age / 12);



        if ($is_new)
        {
            $BusinessType = 'New Business';
            $PreviousPolicyStartDate = '';
            $PreviousPolicyEndDate = '';

            $policy_start_date = date('Y-m-d');
            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime(date('Y-m-d'))));
        }
        elseif (!$is_new)
        {
            $BusinessType = 'Roll Over';
            $PreviousPolicyStartDate = date('d/m/Y', strtotime('-1 Year +1 dayr', strtotime($requestData->previous_policy_expiry_date)));
            $PreviousPolicyEndDate = date('d/m/Y', strtotime($requestData->previous_policy_expiry_date));

            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));

            if ($is_breakin) {
                $policy_start_date = date('Y-m-d');
            }

            $policy_end_date = date('Y-m-d', strtotime('+1 year -1 day', strtotime($policy_start_date)));
        }

        $policyTenure = '1';
        $isShortTermPolicy = false;
        $prevPolyStartDate = '';
        if (in_array($productData->premium_type_code, ['short_term_3'])) {
            $isShortTermPolicy = true;
            $policyTenure = '3';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $policy_end_date = Carbon::parse($policy_start_date)->addMonth(3)->subDay(1);
        } elseif (in_array($productData->premium_type_code, ['short_term_6'])) {
            $isShortTermPolicy = true;
            $policyTenure = '6';
            $policy_start_date = date('Y-m-d', strtotime('+1 day', strtotime($requestData->previous_policy_expiry_date)));
            $policy_end_date = Carbon::parse($policy_start_date)->addMonth(6)->subDay(1);
        }

        if (in_array($requestData->previous_policy_type, ['Comprehensive', 'Third-party', 'Own-damage'])) {
            $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subYear(1)->addDay(1)->format('d/m/Y');
        }

        if ($requestData->prev_short_term == "1") {
            $prevPolyStartDate = Carbon::parse($requestData->previous_policy_expiry_date)->subMonth(3)->addDay(1)->format('d/m/Y');
        }

        $vehicle_registration_no = explode('-', $proposal->vehicale_registration_number);

        if ($vehicle_registration_no[0] == 'DL') {
            $registration_no = RtoCodeWithOrWithoutZero($vehicle_registration_no[0].$vehicle_registration_no[1],true); 
            $vehicle_registration_no = $registration_no.'-'.$vehicle_registration_no[2].'-'.$vehicle_registration_no[3];
            $vehicle_registration_no = explode('-', $vehicle_registration_no);
        } else {
            $vehicle_registration_no = explode('-', $proposal->vehicale_registration_number);
        }

        if($is_new){
            $vehicle_registration_no = explode('-', $requestData->rto_code.'--');
        }

        if($vehicle_registration_no[0] == 'DL') {
            $vehicle_registration_no = explode('-',getRegisterNumberWithHyphen($vehicle_registration_no[0].$vehicle_registration_no[1].$vehicle_registration_no[2].$vehicle_registration_no[3]));
            $requestData->rto_code = $vehicle_registration_no[0].'-'.$vehicle_registration_no[1];
        }

        $additional = SelectedAddons::where('user_product_journey_id', $requestData->user_product_journey_id)
            ->select('compulsory_personal_accident','applicable_addons', 'accessories', 'additional_covers', 'voluntary_insurer_discounts', 'discounts')
            ->first();

        $cpa_selected = 'No';

        if (!empty($additional['compulsory_personal_accident'])) {
            foreach ($additional['compulsory_personal_accident'] as $key => $data)  {
                if (isset($data['name']) && $data['name'] == 'Compulsory Personal Accident')  {
                    $cpa_selected = 'Yes';
                }
            }
        }

        $electrical_accessories = 'No';
        $electrical_accessories_details = '';
        $non_electrical_accessories = 'No';
        $non_electrical_accessories_details = '';
        $external_fuel_kit = 'No';
        $fuel_type = $mmv->fuel_type;
        $external_fuel_kit_amount = '';

        if (!empty($additional['accessories'])) {
            foreach ($additional['accessories'] as $key => $data) {
                if ($data['name'] == 'External Bi-Fuel Kit CNG/LPG') {
                    $external_fuel_kit = 'Yes';
                    $fuel_type = 'CNG';
                    $external_fuel_kit_amount = $data['sumInsured'];
                }

                if ($data['name'] == 'Non-Electrical Accessories') {
                    $non_electrical_accessories = 'Yes';
                    $non_electrical_accessories_details = [
                        [
                            'Description'     => 'Other',
                            'Make'            => 'Other',
                            'Model'           => 'Other',
                            'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'SerialNo'        => '1001',
                            'SumInsured'      => $data['sumInsured'],
                        ]
                    ];
                }

                if ($data['name'] == 'Electrical Accessories') {
                    $electrical_accessories = 'Yes';
                    $electrical_accessories_details = [
                        [
                            'Description'     => 'Other',
                            'Make'            => 'Other',
                            'Model'           => 'Other',
                            'ManufactureYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
                            'SerialNo'        => '1001',
                            'SumInsured'      => $data['sumInsured'],
                        ]
                    ];
                }
            }
        }

        $road_side_assistance_selected = false;
        $engine_protection_selected = false;
        $return_to_invoice_selected = false;
        $consumables_selected = false;
        $key_and_lock_protection_selected = false;

        if (!empty($additional['applicable_addons'])) {
            foreach ($additional['applicable_addons'] as $key => $data) {
                if ($data['name'] == 'Road Side Assistance') {
                    $road_side_assistance_selected = true;
                }

                if ($data['name'] == 'Engine Protector') {
                    $engine_protection_selected = true;
                }

                if ($data['name'] == 'Return To Invoice') {
                    $return_to_invoice_selected = true;
                }

                if ($data['name'] == 'Consumable') {
                    $consumables_selected = true;
                }

                if ($data['name'] == 'Key Replacement') {
                    $key_and_lock_protection_selected = true;
                }
            }
        }

        $zero_depreciation_cover = 'No';
        $road_side_assistance_cover = 'No';
        $consumables_cover = 'No';
        $engine_protection_cover = 'No';
        $key_and_lock_protection_cover = 'No';
        $return_to_invoice_cover = 'No';

        $cover_pa_paid_driver = $cover_pa_unnamed_passenger = $cover_ll_paid_driver = 'No';
        $cover_pa_paid_driver_amt = $cover_pa_unnamed_passenger_amt = 0;
        $no_of_pa_unnamed_passenger = 1;

        if (!empty($additional['additional_covers'])) {
            foreach ($additional['additional_covers'] as $key => $data) {
                if ($data['name'] == 'PA cover for additional paid driver' && isset($data['sumInsured'])) {
                    $cover_pa_paid_driver = 'Yes';
                    $cover_pa_paid_driver_amt = $data['sumInsured'];
                }

                if ($data['name'] == 'Unnamed Passenger PA Cover' && isset($data['sumInsured']) && !empty($quote_data['coverUnnamedPassengerValue'])) {
                    $cover_pa_unnamed_passenger = 'Yes';
                    $cover_pa_unnamed_passenger_amt = $data['sumInsured'];
                    $no_of_pa_unnamed_passenger = $mmv->seating_capacity;
                }

                if ($data['name'] == 'LL paid driver') {
                    $cover_ll_paid_driver = 'Yes';
                }
            }
        }

        $is_anti_theft = 'No';
        $is_anti_theft_device_certified_by_arai = 'false';
        $is_voluntary_access = 'No';
        $voluntary_excess_amt = 0;

        if (!empty($additional['discounts'])) {
            foreach ($additional['discounts'] as $key => $data) {
                if ($data['name'] == 'anti-theft device') {
                    $is_anti_theft = 'Yes';
                    $is_anti_theft_device_certified_by_arai = 'true';
                }

                if ($data['name'] == 'voluntary_insurer_discounts' && isset($data['sumInsured'])) {
                    $is_voluntary_access = 'Yes';
                    $voluntary_excess_amt = $data['sumInsured'];
                }
            }
        }

        if ($requestData->vehicle_owner_type == "I") {
            if ($proposal->gender == "M") {
                $salutation = 'Mr';
            }
            else{
                if ($proposal->gender == "F" && $proposal->marital_status == "Single") {
                    $salutation = 'Mrs';
                } else {
                    $salutation = 'Miss';
                }
            }
        }
        else{
            $salutation = 'M/S';
        }

        $proposal_addtional_details = json_decode($proposal->additional_details, true);

        $is_pos     = config('constants.motorConstant.IS_POS_ENABLED');

        $pos_name   = '';
        $pos_type   = '';
        $pos_code   = '';
        $pos_aadhar = '';
        $pos_pan    = '';
        $pos_mobile = '';

        $pos_data = DB::table('cv_agent_mappings')
            ->where('user_product_journey_id', $requestData->user_product_journey_id)
            ->where('seller_type','P')
            ->first();

        if ($is_pos == 'Y' && isset($pos_data->seller_type) && $pos_data->seller_type == 'P') {
            if($pos_data) {
                $pos_name   = $pos_data->agent_name;
                $pos_type   = 'POSP';
                $pos_code   = $pos_data->pan_no;
                $pos_aadhar = $pos_data->aadhar_no;
                $pos_pan    = $pos_data->pan_no;
                $pos_mobile = $pos_data->agent_mobile;
            }
        }

        if(config('constants.motorConstant.IS_POS_TESTING_MODE_ENABLE_LIBERTY_VIDEOCON') == 'Y')
        {
            $pos_name   = 'Agent';
            $pos_type   = 'POSP';
            $pos_code   = 'ABGTY8890Z';
            $pos_aadhar = '569278616999';
            $pos_pan    = 'ABGTY8890Z';
            $pos_mobile = '8850386204';
        }

        if (isset($requestData->is_claim) && ($requestData->is_claim == 'Y')){
            $no_of_claims = '1';
            $claim_amount = '50000';
        }
        else{
            $no_of_claims = '';
            $claim_amount = '';
        }

        $productcode = '3153';//config('constants.IcConstants.liberty_videocon.PRODUCT_CODE_PACKAGE');

        $buyer_state = DB::table('liberty_videocon_state_master')->where('num_state_cd', $proposal->state_id)->first();

        $proposal_request = [
            'QuickQuoteNumber' => config('constants.IcConstants.liberty_videocon.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
            'IMDNumber' => config('constants.IcConstants.liberty_videocon.IMD_NUMBER_LIBERTY_VIDEOCON_CV'),
            'AgentCode' => '',
            'TPSourceName' => config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR'),
            'ProductCode' => '3153',
            'IsFullQuote' => 'true',// ($is_breakin ? 'false' : 'true'),
            'BusinessType' => (!$is_new || $is_breakin ? 'Roll Over' : 'New Business'),
            'MakeCode' => $mmv->manufacturer_code,
            'ModelCode' => $mmv->vehicle_model_code,
            'VehicleClass' => $mmv->vehicle_class_code,
            'VehicleSubClassCode' => "",
            'VehicleSegment' => $mmv->segment_type,
            'VehicleType' => $mmv->vehicle_class_desc,
            'ManfMonth' => date('m', strtotime('01-'.$requestData->manufacture_year)),
            'ManfYear' => date('Y', strtotime('01-'.$requestData->manufacture_year)),
            'RtoCode' => $requestData->rto_code,
            'RegNo1' => $vehicle_registration_no[0],
            'RegNo2' => $vehicle_registration_no[1],
            'RegNo3' => isset($vehicle_registration_no[3]) ? $vehicle_registration_no[2] : '',
            'RegNo4' => isset($vehicle_registration_no[3]) ? $vehicle_registration_no[3] : $vehicle_registration_no[2],
            'DeliveryDate' => date('d/m/Y', strtotime($vehicleDate)),
            'RegistrationDate' => date('d/m/Y', strtotime($requestData->vehicle_register_date)),
            'VehicleIDV' => $quote->idv,
            'PolicyStartDate' => date('d/m/Y', strtotime($policy_start_date)),
            'PolicyEndDate' => date('d/m/Y', strtotime($policy_end_date)),
            'PolicyTenure' => $policyTenure,
            'GeographicalExtn' => 'No',
            'GeographicalExtnList' => '',
            'DrivingTuition' => '',
            'VintageCar' => '',
            'LegalLiabilityPassenger' => 'Yes',//mandatory for pcv
            'LegalLiabilityToPaidDriver' => 'Yes',#$cover_ll_paid_driver,
            'NoOfPassengerForLLToPaidDriver' => '1',
            'LegalliabilityToEmployee' => '',
            'NoOfPassengerForLLToEmployee' => '',
            'PAUnnnamed' => $cover_pa_unnamed_passenger,
            'NoOfPerunnamed' => $no_of_pa_unnamed_passenger,
            'UnnamedPASI' => $cover_pa_unnamed_passenger_amt,
            'PAOwnerDriver' => $requestData->vehicle_owner_type == 'I' ? $cpa_selected : 'No',
            'PAOwnerDriverTenure' => $requestData->vehicle_owner_type == 'I' ? '1' : '0',
            'LimtedtoOwnPremise' => '',
            'CPAAlreadyAvailable' => $requestData->vehicle_owner_type == 'I' && $cpa_selected == 'No' ? 'true' : 'false',
            'ElectricalAccessories' => $electrical_accessories,
            'lstAccessories' => $electrical_accessories_details,
            'NonElectricalAccessories' => $non_electrical_accessories,
            'lstNonElecAccessories' => $non_electrical_accessories_details,
            'ExternalFuelKit' => $external_fuel_kit,
            'FuelType' => $fuel_type,
            'FuelSI' => $external_fuel_kit_amount,
            'PANamed' => 'No',
            'NoOfPernamed' => '0',
            'NamedPASI' => '0',
            'PAToPaidDriver' => $cover_pa_paid_driver,
            'NoOfPaidDriverPassenger' => '1',
            'PAToPaidDriverSI' => $cover_pa_paid_driver_amt,
            'AAIMembship' => 'No',
            'AAIMembshipNumber' => '',
            'AAIAssociationCode' => '',
            'AAIAssociationName' => '',
            'AAIMembshipExpiryDate' => '',
            'AntiTheftDevice' => $is_anti_theft,
            'IsAntiTheftDeviceCertifiedByARAI' => $is_anti_theft_device_certified_by_arai,
            'TPPDDiscount' => 'No',
            'ForeignEmbassy' => '',
            'VoluntaryExcess' => $is_voluntary_access,
            'VoluntaryExcessAmt' => $voluntary_excess_amt,
            'NoNomineeDetails' => $requestData->vehicle_owner_type == 'I' && $cpa_selected == 'Yes' ? 'false' : 'true',
            'NomineeFirstName' => $requestData->vehicle_owner_type == 'I' && $cpa_selected == 'Yes' ? $proposal->nominee_name : '',
            'NomineelastName' => '.',
            'NomineeRelationship' => $requestData->vehicle_owner_type == 'I' && $cpa_selected == 'Yes' ?  $proposal->nominee_relationship : '',
            'OtherRelation' => '',
            'IsMinor' => 'false',
            'RepFirstName' => '',
            'RepLastName' => '',
            'RepRelationWithMinor' => '',
            'RepOtherRelation' => '',
            'NoPreviousPolicyHistory'   => ($is_new ? 'No' : 'Yes'),
            'IsNilDepOptedInPrevPolicy' => ($is_new ? 'true' : 'false'),
            'PreviousPolicyInsurerName' => ($is_new ? '' : $proposal->previous_insurance_company),
            'PreviousPolicyType'        => ($is_new ? '' : 'PackagePolicy'),
            'PreviousPolicyStartDate'   => ($is_new ? '' : $prevPolyStartDate),
            'PreviousPolicyEndDate'     => ($is_new ? '' : date('d/m/Y', strtotime($requestData->previous_policy_expiry_date))),
            'PreviousPolicyNumber'      => ($is_new ? '' : $proposal->previous_policy_number),
            'PreviousYearNCBPercentage' => ($is_new ? '' : $requestData->previous_ncb),
            'ClaimAmount' => $claim_amount,
            'NoOfClaims' => $no_of_claims,
            'PreviousPolicyTenure' => $is_new ? '' : ($requestData->prev_short_term == "1" ? '3':'1'),
            'IsInspectionDone' => 'false',
            'InspectionDoneByWhom' => '',
            'ReportDate' => '',
            'InspectionDate' => '',
            'ConsumableCover' => $consumables_cover,
            'DepreciationCover' => $zero_depreciation_cover,
            'RoadSideAsstCover' => $road_side_assistance_cover,
            'GAPCover' => $return_to_invoice_cover,
            'GAPCoverSI' => '0',
            'EngineSafeCover' => $engine_protection_cover,
            'KeyLossCover' => $key_and_lock_protection_cover,
            'KeyLossCoverSI' => '0',
            'IsFinancierDetails' => $proposal->is_vehicle_finance ? 'true' : 'false',
            'AgreementType' => $proposal->is_vehicle_finance ? $proposal->financer_agreement_type : '',
            'FinancierName' => $proposal->is_vehicle_finance ? $proposal->name_of_financer : '',
            'FinancierAddress' => '',
            'PassengerAsstCover' => 'No',
            'EngineNo' => $proposal->engine_number,
            'ChassisNo' => $proposal->chassis_number,
            'BuyerState'        => ((!empty($proposal->gst_number)) ? strtoupper($buyer_state->buyer_state_name ?? '') : ($buyer_state->buyer_state_name ?? '')),
            'POSPName'          => $pos_name,
            'POSPType'          => $pos_type,
            'POSPCode'          => $pos_code,
            'POSPAadhar'        => $pos_aadhar,
            'POSPPAN'           => $pos_pan,
            'POSPMobileNumber'  => $pos_mobile,
            'IsShortTermPolicy' => $isShortTermPolicy,
            'CustmerObj' => [
                'TPSource' => 'TPService',
                'CustomerType' => $requestData->vehicle_owner_type,
                'Salutation' => $salutation,
                'FirstName' => str_replace(".", "", $proposal->first_name ?? ''),
                'LastName' => $proposal->last_name,
                'DOB' => date('d/m/Y', strtotime($proposal->dob)),
                'EmailId' => $proposal->email,
                'MobileNumber' => $proposal->mobile_number,
                'AddressLine1' => $proposal->address_line1,
                'AddressLine2' => $proposal->address_line2,
                'AddressLine3' => $proposal->address_line3,
                'PinCode' => $proposal->pincode,
                'StateCode' => $proposal->state_id,
                'StateName' => $proposal->state,
                'PinCodeLocality' => '',
                'PanNo' => $proposal->pan_number ?? '',
                'PermanentLocationSameAsMailLocation' => 'true',
                'MailingAddressLine1' => '',
                'MailingPinCode' => '',
                'MailingPinCodeLocality' => '',
                'IsEIAAvailable' => 'No',
                'EIAAccNo' => '',
                'IsEIAPolicy' => 'No',
                'EIAAccWith' => '',
                'EIAPanNo' => '',
                'EIAUIDNo' => '',
                'GSTIN' => $proposal->gst_number ?? ''
            ]
        ];

        if (config('constants.IS_CKYC_ENABLED') == 'Y') {
            $ckyc_meta_data = json_decode(($proposal->ckyc_meta_data ?? "[]"), true);

            $kyc_param = [
                'Aggregator_KYC_Req_No'    => ($ckyc_meta_data["Aggregator_KYC_Req_No"] ?? ''),
                'IC_KYC_No'    => ($ckyc_meta_data["IC_KYC_No"] ?? ''),
            ];

            $proposal_request = array_merge($proposal_request, $kyc_param);
        }

        if ($requestData->business_type == 'rollover') {
            $proposal_request['lstPreviousAddonDetails'] = [
                [
                    'IsConsumableOptedInPrevPolicy' => $consumables_cover == 'Yes' ? 'true' : 'false',
                    'IsEngineSafeOptedInPrevPolicy' => $engine_protection_cover == 'Yes' ? 'true' : 'false',
                    'IsGAPCoverOptedInPrevPolicy' => $return_to_invoice_cover == 'Yes' ? 'true' : 'false',
                    'IsKeyLossOptedInPrevPolicy' => $key_and_lock_protection_cover == 'Yes' ? 'true' : 'false',
                    'IsNilDepreicationOptedInPrevPolicy' => $zero_depreciation_cover == 'Yes' ? 'true' : 'false',
                    'IsPassengerAsstOptedInPrevPolicy' => 'false',
                    'IsRoadSideAsstOptedInPrevPolicy' => $road_side_assistance_cover == 'Yes' ? 'true' : 'false'
                ]
            ];
        }
        #echo json_encode($proposal_request,true);die;
        $get_response = getWsData(config('constants.IcConstants.liberty_videocon.END_POINT_URL_LIBERTY_VIDEOCON_PREMIUM_CALCULATION'), $proposal_request, 'liberty_videocon', [
            'enquiryId' => customDecrypt($request['enquiryId']),
            'requestMethod' =>'post',
            'productName'  => $productData->product_name,
            'company'  => 'liberty_videocon',
            'section' => $productData->product_sub_type_code,
            'method' =>'Proposal Submission',
            'transaction_type' => 'proposal',
        ]);
        $data = $get_response['response'];
        if ($data) {
            $proposal_response = json_decode($data, TRUE);
            if (trim($proposal_response['ErrorText']) == "") {
                $llpaiddriver_premium = round($proposal_response['LegalliabilityToPaidDriverValue']);
                $cover_pa_owner_driver_premium = round($proposal_response['PAToOwnerDrivervalue']);
                $cover_pa_paid_driver_premium = round($proposal_response['PatoPaidDrivervalue']);
                $cover_pa_unnamed_passenger_premium = round($proposal_response['PAToUnnmaedPassengerValue']);
                $voluntary_excess = round($proposal_response['VoluntaryExcessValue']);
                $anti_theft = round($proposal_response['AntiTheftDiscountValue']);
                $ic_vehicle_discount = round($proposal_response['Loading']) + round($proposal_response['Discount'] ?? 0);
                $ncb_discount = round($proposal_response['DisplayNCBDiscountvalue']);
                $od = round($proposal_response['BasicODPremium']);
                $tppd = round($proposal_response['BasicTPPremium']);
                $cng_lpg = round($proposal_response['FuelKitValueODpremium']);
                $cng_lpg_tp = round($proposal_response['FuelKitValueTPpremium']);
                $zero_depreciation = round($proposal_response['NilDepValue']);
                $road_side_assistance = round($proposal_response['RoadAssistCoverValue']);
                $engine_protection = round($proposal_response['EngineCoverValue']);
                $return_to_invoice = round($proposal_response['GAPCoverValue']);
                $consumables = round($proposal_response['ConsumableCoverValue']);
                $passenger_assist_cover = round($proposal_response['PassengerAssistCoverValue']);
                $electrical_accessories_amt = round($proposal_response['ElectricalAccessoriesValue']);
                $non_electrical_accessories_amt = round($proposal_response['NonElectricalAccessoriesValue']);

                $addon_premium = $zero_depreciation + $road_side_assistance + $engine_protection + $return_to_invoice + $consumables + $passenger_assist_cover;
                $final_od_premium = $od + $cng_lpg + $electrical_accessories_amt + $non_electrical_accessories_amt;
                $final_tp_premium = $tppd + $cng_lpg_tp + $llpaiddriver_premium +  $cover_pa_paid_driver_premium + $cover_pa_unnamed_passenger_premium + $cover_pa_owner_driver_premium;
                $final_total_discount = $ncb_discount + $voluntary_excess + $ic_vehicle_discount + $anti_theft;
                $final_net_premium = round($proposal_response['NetPremium']);
                $final_gst_amount = round($proposal_response['GST']);
                $final_payable_amount  = $proposal_response['TotalPremium'];

                $vehicleDetails = [
                    'manufacture_name' => $mmv->manufacturer,
                    'model_name' => $mmv->vehicle_model,
                    'version' => $mmv->variant,
                    'fuel_type' => $mmv->fuel_type,
                    'seating_capacity' => $mmv->seating_capacity,
                    'carrying_capacity' => $mmv->carrying_capacity,
                    'cubic_capacity' => $mmv->cubic_capacity,
                    'gross_vehicle_weight' => $mmv->gross_vehicle_weight,
                    'vehicle_type' => $mmv->vehicle_class_desc,
                ];

                LibertyVideoconPremiumDetailController::savePremiumDetails($get_response['webservice_id']);

                if($is_breakin){
                    $request_details['vehicle_registration_no'] = $vehicle_registration_no;
                    $request_details['enquiryId']       = $request['enquiryId'];
                    $request_details['is_individual']   = $is_individual;
                    $mmv='';
                    $lead_response = self::leadCreation($proposal, $mmv, $request_details, $productData, $requestData);

                    if(!$lead_response['status']){
                        return $lead_response;
                    }

                    $inspection_no = $lead_response['LeadID'];
                }

                // proposal_request

                $proposal_addtional_details['liberty']['proposal_request'] = $proposal_request;


                UserProposal::where('user_proposal_id' , $proposal->user_proposal_id)
                    ->update([
                    'od_premium' => $final_od_premium,
                    'tp_premium' => $final_tp_premium,
                    'ncb_discount' => $ncb_discount,
                    'total_discount' => $final_total_discount,
                    'addon_premium' => $addon_premium,
                    'total_premium' => $final_net_premium,
                    'service_tax_amount' => $final_gst_amount,
                    'final_payable_amount' => $final_payable_amount,
                    'cpa_premium' => $cover_pa_owner_driver_premium,
                    'proposal_no' => $proposal_response['PolicyID'],
                    'customer_id' => $proposal_response['CustomerID'],
                    'unique_proposal_id' => $proposal_response['QuotationNumber'],
                    'policy_start_date' => date('d-m-Y', strtotime($policy_start_date)),
                    'policy_end_date' => date('d-m-Y', strtotime($policy_end_date)),
                    'ic_vehicle_details' => $vehicleDetails,
                    'is_breakin_case' => ($is_breakin) ? 'Y' : 'N',
                    'additional_details' => $proposal_addtional_details,
                ]);

                updateJourneyStage([
                    'user_product_journey_id' => $proposal->user_product_journey_id,
                    'ic_id' => $productData->company_id,
                    'stage' => STAGE_NAMES['PROPOSAL_ACCEPTED'],
                    'proposal_id' => $proposal->user_proposal_id,
                ]);

                return [
                    'status' => true,
                    'msg' => 'Proposal submitted successfully',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'data' => [
                        'proposalId' => $proposal->user_proposal_id,
                        'proposalNo' => $proposal_response['PolicyID'],
                        'odPremium' => $final_od_premium,
                        'tpPremium' => $final_tp_premium,
                        'ncbDiscount' => $ncb_discount,
                        'totalPremium' => $final_net_premium,
                        'serviceTaxAmount' => $final_gst_amount,
                        'finalPayableAmount' => $final_payable_amount,
                        'isBreakinCase' => ($is_breakin) ? 'Y' : 'N',
                        'is_breakin'    =>($is_breakin) ? 'Y' : 'N',
                        'inspection_number' => $inspection_no
                    ]
                ];
            } else {
                return [
                    'status' => false,
                    'premium' => '0',
                    'webservice_id' => $get_response['webservice_id'],
                    'table' => $get_response['table'],
                    'message' => $proposal_response['ErrorText']
                ];
            }
        } else {
            return [
                'status' => false,
                'premium' => '0',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message' => 'Insurer not reachable'
            ];
        }
    }


    public static function leadCreation($proposal, $mmv, $request_details, $productData, $requestData){
        $lead_request = [
            "UserID"            => config('constants.IcConstants.liberty_videocon.breakin.PI_USER_ID'),
            "Passwd"            => config('constants.IcConstants.liberty_videocon.breakin.PI_PASSWORD'),
            "IntimatorName"     => config('constants.IcConstants.liberty_videocon.breakin.PI_USER_ID'),
            "IntimatorPhone"    => '',
            "IntimatorEmailId"  => config('constants.IcConstants.liberty_videocon.CAR_EMAIL'),
            "ProposalID"        => $proposal->user_proposal_id . time(),
            "AgencyID"          => 'tp123',//$proposal_details['agency_name'],
            "BranchID"          => '1199',//$proposal_details['branch_id'],

            "CustomerName"      => ($request_details['is_individual'] ? (str_replace(".", "", $proposal->first_name ?? '') . " " . $proposal->last_name) : $proposal->first_name),
            "CustomerPhone"     => $proposal->mobile_number,

            "VehicleInspectionAddress" => (
                $proposal->address_line1
                . "," . $proposal->address_line2
                . "," . $proposal->address_line3
                . "," . $proposal->pincode
            ),

            "ModelID"           => $mmv->vehicle_model_code,
            "MakeID"            => $mmv->manufacturer_code,

            "VehicleType"       => "PVT. CAR",
            "VehicleRegNo"      => $request_details['vehicle_registration_no'][0]
                . '' . $request_details['vehicle_registration_no'][1]
                . '' . $request_details['vehicle_registration_no'][2]
                . '' . $request_details['vehicle_registration_no'][3],
            "EngineNo"          => $proposal->engine_number,
            "ChassisNo"         => $proposal->chassis_number,
            "MfgYear"           => date('Y', strtotime('01-'.$requestData->manufacture_year)),

            "InspectionDate"    => date('Y-m-d'),
            "InspectionTime"    => date('H:i:s'),

            "RegistrationType"  => "Registered",
            "Purposeofinspection" => "Break-In",

            "ZipFileSize"       => "",
            "ChannelType"       => "",
            "FeestobeBornBy"    => "",
            "SalesManager"      => ''
        ];

        $lead_create_request = [
            $lead_request
        ];

        $container = '
            <Envelope
                xmlns="http://schemas.xmlsoap.org/soap/envelope/"
            >
                <Body>
                <CreateLeadJson xmlns="http://www.claimlook.com/">
                    <json>#replace</json>
                </CreateLeadJson>
                </Body>
            </Envelope>
        ';

        $additional_data = [
            'enquiryId'     => $proposal->user_product_journey_id,
            'requestMethod' => 'post',
            'productName'   => $productData->product_name,
            'company'       => 'liberty_videocon',
            'section'       => $productData->product_sub_type_code,
            'method'        => 'Lead Creation - Proposal',
            'transaction_type' => 'proposal',
            'root_tag'      => 'json',
            'soap_action'   => 'CreateLeadJson',
            'content_type'  => 'text/xml;',
            'container'     => $container,
        ];


        $get_response = getWsData(
            config('constants.IcConstants.liberty_videocon.breakin.END_POINT_URL_PI_LEAD_ID_CREATE'),
            $lead_request,
            'liberty_videocon',
            $additional_data
        );
        $lead_create_response = $get_response['response'];

        if(!$lead_create_response || $lead_create_response == ''){
            return [
                'status'    => false,
                'premium'   => '0',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'   => 'insurer Not Reachable',
                'LeadID'    => '',
                'data'      => []
            ];
        }

        $lead_response = html_entity_decode($lead_create_response);
        $lead_response = XmlToArray::convert($lead_response);
        $lead_response = json_decode($lead_response['soap:Body']['CreateLeadJsonResponse']['CreateLeadJsonResult'], true)[0];

        if($lead_response['Return'] != 'S'){
            return [
                'status'    => false,
                'premium'   => '0',
                'webservice_id' => $get_response['webservice_id'],
                'table' => $get_response['table'],
                'message'   => $lead_response['Error'],
                'LeadID'    => '',
                'data'      => []
            ];
        }


        $inspection_no = $lead_response['LeadID'];


        DB::table('cv_breakin_status')
        ->updateOrInsert(
            [
                'ic_id'             => $productData->company_id,
                'breakin_number'    => $lead_response['LeadID'],
                'breakin_id'        => $lead_response['LeadID'],
                'breakin_status'    => STAGE_NAMES['PENDING_FROM_IC'],
                'breakin_status_final' => STAGE_NAMES['PENDING_FROM_IC'],
                'breakin_response'  => $lead_create_response,
                'payment_end_date'  => Carbon::today()->addDay(3)->toDateString(),
                'created_at'        => Carbon::today()->toDateString()
            ],
            [
                'user_proposal_id'  => $proposal->user_proposal_id
            ]
        );

        updateJourneyStage([
            'user_product_journey_id'   => $proposal->user_product_journey_id,
            'ic_id'                     => $productData->company_id,
            'stage'                     => STAGE_NAMES['INSPECTION_PENDING'],
            'proposal_id'               => $proposal->user_proposal_id
        ]);

        return [
            'status'    => true,
            'premium'   => '0',
            'webservice_id' => $get_response['webservice_id'],
            'table' => $get_response['table'],
            'message'   => 'success',
            'LeadID'    => $lead_response['LeadID'],
            'data'      => $lead_response
        ];
    }



}
