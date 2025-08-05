<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexToTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('quote_log')) {
            Schema::table('quote_log', function (Blueprint $table) {
                $table->index(['user_product_journey_id', 'ic_id']);
            });
        }

        if (Schema::hasTable('acko_master_relationship')) {
            Schema::table('acko_master_relationship', function (Blueprint $table) {
                $table->index(['product_sub_type_id', 'status', 'ic_id']);
            });
        }

        if (Schema::hasTable('bajaj_allianz_motor_state_city_master')) {
            Schema::table('bajaj_allianz_motor_state_city_master', function (Blueprint $table) {
                $table->index(['pincode']);
            });
        }

        if (Schema::hasTable('bike_applicable_addons')) {
            Schema::table('bike_applicable_addons', function (Blueprint $table) {
                $table->index(['ic_id', 'ic_alias']);
            });
        }

        if (Schema::hasTable('bike_future_generali_cashless_garage')) {
            Schema::table('bike_future_generali_cashless_garage', function (Blueprint $table) {
                $table->string('Workshop_Name_Display_Name')->nullable()->change();
                $table->string('Workshop_name_Payee_name')->nullable()->change();
                $table->string('Status')->nullable()->change();
                $table->string('Workshop_Type')->nullable()->change();
                $table->string('Cashless_tie_up')->nullable()->change();
                $table->string('Vehicle_Category')->nullable()->change();
                $table->string('Vehicle_Make')->nullable()->change();
                $table->string('High_End_Vehicle')->nullable()->change();
                $table->string('Landmark')->nullable()->change();
                $table->string('City')->nullable()->change();
                $table->string('State')->nullable()->change();
                $table->string('Postcode')->nullable()->change()->index();
                $table->string('Collated_Address')->nullable()->change();
                $table->string('Contact_Person_Updated')->nullable()->change();
                $table->string('WorkTelephone')->nullable()->change();
                $table->string('HomeTelephone')->nullable()->change();
                $table->string('Emailid')->nullable()->change();
                $table->string('GSTIN')->nullable()->change();
                $table->string('PAN_Number')->nullable()->change();
                $table->string('Zone')->nullable()->change();
            });
        }

        if (Schema::hasTable('bike_godigit_cashless_garage')) {
            Schema::table('bike_godigit_cashless_garage', function (Blueprint $table) {
                $table->string('zone')->change()->nullable();
                $table->string('state')->change()->nullable();
                $table->string('city')->change()->nullable();
                $table->string('location')->change()->nullable();
                $table->string('dealer_name')->change()->nullable();
                $table->string('workshop_address')->change()->nullable();
                $table->string('pincode')->change()->nullable()->index();
                $table->string('make')->change()->nullable();
                $table->string('type')->change()->nullable();
            });
        }

        if (Schema::hasTable('bike_hdfc_ergo_cashless_garage')) {
            Schema::table('bike_hdfc_ergo_cashless_garage', function (Blueprint $table) {
                $table->string('WORKSHOP_NAME')->change();
                $table->mediumText('ADDRESS')->change();
                $table->string('PIN_CODE')->change()->index();
                $table->string('CITY')->change();
                $table->string('STATE')->change();
                $table->string('GARAGE_TYPE')->change();
                $table->string('MANUFACTURER')->change();
            });
        }

        if (Schema::hasTable('bike_iffco_tokio_cashless_garage')) {
            Schema::table('bike_iffco_tokio_cashless_garage', function (Blueprint $table) {
                $table->string('garage_name')->change()->nullable();
                $table->string('state')->change()->nullable();
                $table->string('city')->change()->nullable();
                $table->string('csc')->change()->nullable();
                $table->string('vehicle_type')->change()->nullable();
                $table->string('manufacturer')->change()->nullable();
                $table->string('address')->change()->nullable();
                $table->string('pincode')->change()->nullable()->index();
                $table->string('contact_person')->change()->nullable();
                $table->string('mobile')->change()->nullable();
            });
        }

        if (Schema::hasTable('bike_liberty_videocon_cashless_garage')) {
            Schema::table('bike_liberty_videocon_cashless_garage', function (Blueprint $table) {
                $table->string('Workshop_Code')->change()->nullable();
                $table->string('Workshop_Name')->change()->nullable();
                $table->string('Address')->change()->nullable();
                $table->string('City_District_Name')->change()->nullable();
                $table->string('Preferred_Garage')->change()->nullable();
                $table->string('Contact_Person_Name')->change()->nullable();
                $table->string('Mobile_No')->change()->nullable();
                $table->string('Business_Name')->change()->nullable();
                $table->string('Auth_to_Repair')->change()->nullable();
                $table->string('Zone')->change()->nullable();
                $table->string('State')->change()->nullable();
                $table->string('Location')->change()->nullable();
                $table->string('Pincode')->change()->nullable()->index();
                $table->string('Manufacturer')->change()->nullable();
                $table->string('Remarks')->change()->nullable();
            });
        }

        if (Schema::hasTable('car_future_generali_cashless_garage')) {
            Schema::table('car_future_generali_cashless_garage', function (Blueprint $table) {
                $table->string('Workshop_Name_Display_Name')->change()->nullable();
                $table->string('Workshop_name_Payee_name')->change()->nullable();
                $table->string('Status')->change()->nullable();
                $table->string('Workshop_Type')->change()->nullable();
                $table->string('Cashless_tie_up')->change()->nullable();
                $table->string('Vehicle_Category')->change()->nullable();
                $table->string('Vehicle_Make')->change()->nullable();
                $table->string('High_End_Vehicle')->change()->nullable();
                $table->string('Landmark')->change()->nullable();
                $table->string('City')->change()->nullable();
                $table->string('State')->change()->nullable();
                $table->string('Postcode')->change()->nullable()->index();
                $table->string('Collated_Address')->change()->nullable();
                $table->string('Contact_Person_Updated')->change()->nullable();
                $table->string('WorkTelephone')->change()->nullable();
                $table->string('HomeTelephone')->change()->nullable();
                $table->string('Emailid')->change()->nullable();
                $table->string('GSTIN')->change()->nullable();
                $table->string('PAN_Number')->change()->nullable();
                $table->string('Zone')->change()->nullable();
            });
        }

        if (Schema::hasTable('car_hdfc_ergo_cashless_garage')) {
            Schema::table('car_hdfc_ergo_cashless_garage', function (Blueprint $table) {
                $table->string('WORKSHOP_NAME')->change()->nullable();
                $table->mediumText('ADDRESS')->change()->nullable();
                $table->string('PIN_CODE')->change()->nullable()->nullable();
                $table->string('CITY')->change()->nullable();
                $table->string('STATE')->change()->nullable();
                $table->string('GARAGE_TYPE')->change()->nullable();
                $table->string('MANUFACTURER')->change()->nullable();
            });
        }

        if (Schema::hasTable('car_liberty_videocon_cashless_garage')) {
            Schema::table('car_liberty_videocon_cashless_garage', function (Blueprint $table) {
                $table->string('Workshop_Code')->change()->nullable();
                $table->string('Workshop_Name')->change()->nullable();
                $table->string('Address')->change()->nullable();
                $table->string('City_District_Name')->change()->nullable();
                $table->string('Preferred_Garage')->change()->nullable();
                $table->string('Contact_Person_Name')->change()->nullable();
                $table->string('Mobile_No')->change()->nullable();
                $table->string('Business_Name')->change()->nullable();
                $table->string('Auth_to_Repair')->change()->nullable();
                $table->string('Zone')->change()->nullable();
                $table->string('State')->change()->nullable();
                $table->string('Location')->change()->nullable();
                $table->string('Pincode')->change()->nullable()->index();
                $table->string('Manufacturer')->change()->nullable();
                $table->string('Remarks')->change()->nullable();
            });
        }

        if (Schema::hasTable('car_shriram_cashless_garage')) {
            Schema::table('car_shriram_cashless_garage', function (Blueprint $table) {
                $table->string('CodeNo')->change()->nullable();
                $table->string('Name')->change()->nullable();
                $table->string('CustEffFmDt')->change()->nullable();
                $table->string('CustEffToDt')->change()->nullable();
                $table->string('CustOffAddress')->change()->nullable();
                $table->string('EmailId1')->change()->nullable();
                $table->string('EmailId2')->change()->nullable();
                $table->string('State')->change()->nullable();
                $table->string('MobileNo')->change()->nullable();
                $table->string('OffCity')->change()->nullable();
                $table->string('PanNo')->change()->nullable();
                $table->string('Accno')->change()->nullable();
                $table->string('Bank')->change()->nullable();
                $table->string('AcntType')->change()->nullable();
                $table->string('Ifsccode')->change()->nullable();
                $table->string('MicrCode')->change()->nullable();
                $table->string('AcntHoldName')->change()->nullable();
                // $table->string('Fax	Garage')->change()->nullable();
                $table->string('DealingOff')->change()->nullable();
                $table->string('TypeOfGarage')->change()->nullable();
                $table->string('CashlessType')->change()->nullable();
                $table->string('Specialization')->change()->nullable();
                $table->string('TdsCode')->change()->nullable();
            });
        }

        if (Schema::hasTable('car_tata_aig_cashless_garage')) {
            Schema::table('car_tata_aig_cashless_garage', function (Blueprint $table) {
                $table->string('CashLGar_Id')->nullable()->change();
                $table->string('CashLGar_Cli_Id')->nullable()->change();
                $table->string('CashLGar_Ic')->nullable()->change();
                $table->string('CashLGar_VehicleTypeId')->nullable()->change();
                $table->string('CashLGar_WSName')->nullable()->change();
                $table->string('CashLGar_WSCode')->nullable()->change();
                $table->string('CashLGar_WSCntNumber')->nullable()->change();
                $table->string('CashLGar_WSAddress')->nullable()->change();
                $table->string('CashLGar_WSPincode')->nullable()->change();
                $table->string('CashLGar_WS_dlr_state_id')->nullable()->change();
                $table->string('CashLGar_WS_dlr_city_id')->nullable()->change();
                $table->string('CashLGar_WS_oem_id')->nullable()->change();
                $table->string('CashLGar_WSRating')->nullable()->change();
                $table->string('CashLGar_WSCashLess')->nullable()->change();
                $table->string('CashLGar_WSSmartCashLess')->nullable()->change();
                $table->string('CashLGar_InsUsrId')->nullable()->change();
                $table->string('CashLGar_ModUsrId')->nullable()->change();
                $table->string('CashLGar_InsDt')->nullable()->change();
                $table->string('CashLGar_ModDt')->nullable()->change();
                $table->string('CashLGar_InsIp')->nullable()->change();
                $table->string('CashLGar_ModIp')->nullable()->change();
                $table->string('CashLGar_Status')->nullable()->change();
            });
        }

        if (Schema::hasTable('cholla_mandalam_bike_rto_master')) {
            Schema::table('cholla_mandalam_bike_rto_master', function (Blueprint $table) {
                $table->index(['rto']);
            });
        }

        if (Schema::hasTable('cholla_mandalam_pincode_master')) {
            Schema::table('cholla_mandalam_pincode_master', function (Blueprint $table) {
                $table->index(['pincode']);
            });
        }

        if (Schema::hasTable('cholla_mandalam_rto_master')) {
            Schema::table('cholla_mandalam_rto_master', function (Blueprint $table) {
                $table->index(['rto']);
            });
        }

        if (Schema::hasTable('config_settings')) {
            Schema::table('config_settings', function (Blueprint $table) {
                $table->index(['key']);
            });
        }

        if (Schema::hasTable('corporate_vehicles_quotes_request')) {
            Schema::table('corporate_vehicles_quotes_request', function (Blueprint $table) {
                $table->string('gcv_carrier_type')->change()->nullable();
            });
        }

        if (Schema::hasTable('customer_payment_link')) {
            Schema::table('customer_payment_link', function (Blueprint $table) {
                $table->index(['proposal_id']);
            });
        }

        if (Schema::hasTable('cv_agent_mappings')) {
            Schema::table('cv_agent_mappings', function (Blueprint $table) {
                $table->index(['user_product_journey_id']);
            });
        }

        if (Schema::hasTable('cv_breakin_status')) {
            Schema::table('cv_breakin_status', function (Blueprint $table) {
                $table->index(['user_proposal_id']);
            });
        }

        if (Schema::hasTable('cv_hdfc_ergo_rto_location')) {
            Schema::table('cv_hdfc_ergo_rto_location', function (Blueprint $table) {
                $table->string('Num_Country_Code')->change()->nullable();
                $table->string('Num_State_Code')->change()->nullable();
                $table->string('Txt_Rto_Location_code')->change()->nullable();
                $table->string('Txt_Rto_Location_desc')->change()->nullable();
                $table->string('Num_Vehicle_Class_code')->change()->nullable();
                $table->string('Txt_Registration_zone')->change()->nullable();
                $table->string('Txt_Status')->change()->nullable();
                $table->string('Num_Vehicle_Subclass_Code')->change()->nullable();
                $table->string('EMG_COV')->change()->nullable();
                $table->string('IsActive')->change()->nullable();
                $table->string('IsActive_For_GCV')->change()->nullable();
            });
        }

        if (Schema::hasTable('edelweiss_finance_master')) {
            Schema::table('edelweiss_finance_master', function (Blueprint $table) {
                $table->string('name')->change()->nullable();
                $table->string('code')->change()->nullable();
            });
        }

        if (Schema::hasTable('edelweiss_pincode_master')) {
            Schema::table('edelweiss_pincode_master', function (Blueprint $table) {
                $table->string('cl')->nullable()->change();
                $table->string('pincode')->nullable()->change()->index();
                $table->string('region')->nullable()->change();
                $table->string('district')->nullable()->change();
                $table->string('city')->nullable()->change();
                $table->string('eq_zone')->nullable()->change();
                $table->string('eq_tarrif_rate')->nullable()->change();
                $table->string('creasta_zone')->nullable()->change();
                $table->string('rg')->nullable()->change();
            });
        }

        if (Schema::hasTable('edelweiss_previous_insurance_master')) {
            Schema::table('edelweiss_previous_insurance_master', function (Blueprint $table) {
                $table->string('Pre_Insurance_Company_Name')->nullable()->change();
            });
        }

        if (Schema::hasTable('edelweiss_rto_master')) {
            Schema::table('edelweiss_rto_master', function (Blueprint $table) {
                $table->index(['rto_code']);
            });
        }

        if (Schema::hasTable('fastlane_previous_ic_mapping')) {
            Schema::table('fastlane_previous_ic_mapping', function (Blueprint $table) {
                $table->string('company_alias')->nullable()->change();
            });
        }

        if (Schema::hasTable('fastlane_request_response')) {
            Schema::table('fastlane_request_response', function (Blueprint $table) {
                $table->index(['enquiry_id']);
            });
        }

        if (Schema::hasTable('feedback')) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->index(['user_product_journey_id']);
            });
        }

        if (Schema::hasTable('future_generali_cashless_garage')) {
            Schema::table('future_generali_cashless_garage', function (Blueprint $table) {
                $table->string('Workshop_Name_Display_Name')->change();
                $table->string('Workshop_name_Payee_name')->change();
                $table->string('Status')->change();
                $table->string('Workshop_Type')->change();
                $table->string('Cashless_tie_up')->change();
                $table->string('Vehicle_Category')->change();
                $table->string('Vehicle_Make')->change();
                $table->string('High_End_Vehicle')->change();
                $table->string('Landmark')->change();
                $table->string('City')->change();
                $table->string('State')->change();
                $table->string('Postcode')->change()->index();
                $table->string('Collated_Address')->change();
                $table->string('Contact_Person_Updated')->change();
                $table->string('WorkTelephone')->change();
                $table->string('HomeTelephone')->change();
                $table->string('Emailid')->change();
                $table->string('GSTIN')->change();
                $table->string('PAN_Number')->change();
                $table->string('Zone')->change();
            });
        }

        if (Schema::hasTable('future_generali_pincode_master')) {
            Schema::table('future_generali_pincode_master', function (Blueprint $table) {
                $table->index(['pincode']);
            });
        }

        if (Schema::hasTable('future_generali_prev_insurer')) {
            Schema::table('future_generali_prev_insurer', function (Blueprint $table) {
                $table->index(['id']);
            });
        }

        if (Schema::hasTable('future_generali_rto_master')) {
            Schema::table('future_generali_rto_master', function (Blueprint $table) {
                $table->string('shtdesc')->nullable()->change();
                $table->string('longdesc')->nullable()->change();
                $table->string('cltaddr01')->nullable()->change();
                $table->string('cltaddr02')->nullable()->change();
                $table->string('cltaddr03')->nullable()->change();
                $table->string('cltaddr04')->nullable()->change();
                $table->string('cltaddr05')->nullable()->change();
                $table->string('cltpcode')->nullable()->change();
                $table->string('type')->nullable()->change();
                $table->string('statecde')->nullable()->change();
                $table->string('zone')->nullable()->change();
                $table->index(['rta_code']);
            });
        }

        if (Schema::hasTable('godigit_pincode_state_city_master')) {
            Schema::table('godigit_pincode_state_city_master', function (Blueprint $table) {
                $table->string('pincode')->change()->nullable()->index();
                $table->string('state')->change()->nullable();
                $table->string('statecode')->change()->nullable();
                $table->string('statecodetext')->change()->nullable();
                $table->string('district')->change()->nullable();
                $table->string('city')->change()->nullable();
                $table->string('country')->change()->nullable();
            });
        }

        if (Schema::hasTable('gramcover_post_data_apis')) {
            Schema::table('gramcover_post_data_apis', function (Blueprint $table) {
                $table->index(['user_product_journey_id']);
            });
        }

        if (Schema::hasTable('hdfc_ergo_bike_rto_location')) {
            Schema::table('hdfc_ergo_bike_rto_location', function (Blueprint $table) {
                $table->index(['rto_code']);
            });
        }

        if (Schema::hasTable('hdfc_ergo_cashless_garage')) {
            Schema::table('hdfc_ergo_cashless_garage', function (Blueprint $table) {
                $table->string('WORKSHOP_NAME')->change();
                $table->mediumText('ADDRESS')->change();
                $table->string('PIN_CODE')->change()->index();
                $table->string('CITY')->change();
                $table->string('STATE')->change();
                $table->string('GARAGE_TYPE')->change();
                $table->string('MANUFACTURER')->change();
            });
        }

        if (Schema::hasTable('hdfc_ergo_financier_master')) {
            Schema::table('hdfc_ergo_financier_master', function (Blueprint $table) {
                $table->string('name')->change();
                $table->string('code')->change();
            });
        }

        if (Schema::hasTable('hdfc_ergo_motor_pincode_master')) {
            Schema::table('hdfc_ergo_motor_pincode_master', function (Blueprint $table) {
                $table->index(['num_pincode']);
                $table->string('txt_pincode_locality')->change();
            });
        }

        if (Schema::hasTable('hdfc_ergo_rto_location')) {
            Schema::table('hdfc_ergo_rto_location', function (Blueprint $table) {
                $table->index(['rto_code']);
            });
        }

        if (Schema::hasTable('icici_lombard_pincode_master')) {
            Schema::table('icici_lombard_pincode_master', function (Blueprint $table) {
                $table->index(['num_pincode']);
            });
        }

        if (Schema::hasTable('icici_rto_master')) {
            Schema::table('icici_rto_master', function (Blueprint $table) {
                $table->index(['rto_code']);
            });
        }

        if (Schema::hasTable('iffco_tokio_pincode_master')) {
            Schema::table('iffco_tokio_pincode_master', function (Blueprint $table) {
                $table->index(['pincode']);
            });
        }

        if (Schema::hasTable('insurer_address')) {
            Schema::table('insurer_address', function (Blueprint $table) {
                $table->index(['Company_Alias']);
            });
        }

        if (Schema::hasTable('kotak_bike_rto_location')) {
            Schema::table('kotak_bike_rto_location', function (Blueprint $table) {
                $table->string('NUM_STATE_CODE')->change();
                $table->string('TXT_RTO_LOCATION_CODE')->change();
                $table->string('NUM_REGISTRATION_CODE')->change();
                $table->string('TXT_RTO_LOCATION_DESC')->change();
                $table->string('TXT_REGISTRATION_ZONE')->change();
                $table->string('TXT_RTO_CLUSTER')->change();
                $table->string('rto_state_name')->change();
                $table->string('Intials')->change();
                $table->string('Pvt_UW')->change();
            });
        }

        if (Schema::hasTable('kotak_financier_master')) {
            Schema::table('kotak_financier_master', function (Blueprint $table) {
                $table->string('name')->change();
                $table->string('code')->change();
            });
        }

        if (Schema::hasTable('kotak_pincode_master')) {
            Schema::table('kotak_pincode_master', function (Blueprint $table) {
                $table->index(['NUM_PINCODE']);
            });
        }

        if (Schema::hasTable('kotak_rto_location')) {
            Schema::table('kotak_rto_location', function (Blueprint $table) {
                $table->string('NUM_COUNTRY_CODE')->change();
                $table->string('NUM_REGISTRATION_CODE')->index()->change();
                $table->string('TXT_RTO_LOCATION_CODE')->change();
                $table->string('TXT_RTO_CLUSTER')->change();
                $table->string('NUM_STATE_CODE')->change();
                $table->string('TXT_REGISTRATION_ZONE')->change();
                $table->string('TXT_RTO_LOCATION_DESC')->change();
                $table->string('rto_state_name')->change();
                $table->string('Intials')->change();
                $table->string('Pvt_UW')->change();
            });
        }

        if (Schema::hasTable('liberty_videocon_cashless_garage')) {
            Schema::table('liberty_videocon_cashless_garage', function (Blueprint $table) {
                $table->string('Workshop_Code')->change();
                $table->string('Workshop_Name')->change();
                $table->string('Address')->change();
                $table->string('City_District_Name')->change();
                $table->string('Preferred_Garage')->change();
                $table->string('Contact_Person_Name')->change();
                $table->string('Mobile_No')->change();
                $table->string('Business_Name')->change();
                $table->string('Auth_to_Repair')->change();
                $table->string('Zone')->change();
                $table->string('State')->change();
                $table->string('Location')->change();
                $table->string('Pincode')->index()->change();
                $table->string('Manufacturer')->change();
                $table->string('Remarks')->change();
            });
        }

        if (Schema::hasTable('liberty_videocon_pincode_master')) {
            Schema::table('liberty_videocon_pincode_master', function (Blueprint $table) {
                $table->string('area_name')->change();
                $table->index(['PinCode']);
            });
        }

        if (Schema::hasTable('liberty_videocon_pincode_state_city_master')) {
            Schema::table('liberty_videocon_pincode_state_city_master', function (Blueprint $table) {
                $table->string('pincode')->index()->change();
                $table->string('state')->change();
                $table->string('statecode')->change();
                $table->string('district')->change();
                $table->string('city')->change();
                $table->string('country')->change();
            });
        }

        if (Schema::hasTable('liberty_videocon_rto_master')) {
            Schema::table('liberty_videocon_rto_master', function (Blueprint $table) {
                $table->string('rtocode')->index()->change();
                $table->string('rta')->change();
                $table->string('statename')->change();
                $table->string('zone')->change();
            });
        }

        if (Schema::hasTable('magma_motor_pincode_master')) {
            Schema::table('magma_motor_pincode_master', function (Blueprint $table) {
                $table->index(['num_pincode']);
            });
        }

        if (Schema::hasTable('magma_rto_location')) {
            Schema::table('magma_rto_location', function (Blueprint $table) {
                $table->index(['rto_location_code']);
            });
        }

        if (Schema::hasTable('master_company')) {
            Schema::table('master_company', function (Blueprint $table) {
                $table->index(['company_alias']);
            });
        }

        if (Schema::hasTable('master_pincode_state_city')) {
            Schema::table('master_pincode_state_city', function (Blueprint $table) {
                $table->string('state_name')->change();
                $table->string('district_name')->change();
                $table->string('city_or_village_name')->change();
                $table->string('area_name')->change();
                $table->index(['area_name']);
            });
        }

        if (Schema::hasTable('new_india_motor_discount_grid')) {
            Schema::table('new_india_motor_discount_grid', function (Blueprint $table) {
                $table->string('section')->change();
                $table->string('discount_percent_without_addons_0_to_120_months')->change();
                $table->string('discount_percent_without_addons_121_to_178_months')->change();
                $table->string('discount_percent_with_addons_0_to_36_months')->change();
                $table->string('discount_percent_with_addons_37_to_58_months')->change();
            });
        }

        if (Schema::hasTable('new_india_pincode_master')) {
            Schema::table('new_india_pincode_master', function (Blueprint $table) {
                $table->string('geo_area_code')->change();
                $table->string('geo_area_name')->change();
                $table->string('geo_area_code_1')->change();
                $table->string('geo_area_name_1')->change();
                $table->string('pin_code')->index()->change();
            });
        }

        if (Schema::hasTable('nic_pincode_master')) {
            Schema::table('nic_pincode_master', function (Blueprint $table) {
                $table->index(['pin_cd']);
            });
        }

        if (Schema::hasTable('nic_previous_insurer_master')) {
            Schema::table('nic_previous_insurer_master', function (Blueprint $table) {
                $table->string('ic_master')->change();
                $table->string('tp_insurer')->change();
                $table->string('ic_ref')->change();
            });
        }

        if (Schema::hasTable('nic_rto_master')) {
            Schema::table('nic_rto_master', function (Blueprint $table) {
                $table->index(['rto_number']);
            });
        }

        if (Schema::hasTable('oriental_pinstatecity_master')) {
            Schema::table('oriental_pinstatecity_master', function (Blueprint $table) {
                $table->string('CITY_CODE')->change();
                $table->string('CITYNAME')->change();
                $table->string('PINCODE')->index()->change();
                $table->string('PIN_DESC')->change();
            });
        }

        if (Schema::hasTable('oriental_rto_master')) {
            Schema::table('oriental_rto_master', function (Blueprint $table) {
                $table->string('rto_code')->index()->change();
                $table->string('rto_description')->change();
            });
        }

        if (Schema::hasTable('oriental_state_city_pincode_master')) {
            Schema::table('oriental_state_city_pincode_master', function (Blueprint $table) {
                $table->string('pincode')->index()->change();
                $table->string('state_code')->change();
                $table->string('state')->change();
                $table->string('city')->change();
                $table->string('city_code')->change();
            });
        }

        if (Schema::hasTable('payment_response')) {
            Schema::table('payment_response', function (Blueprint $table) {
                $table->index(['company_alias', 'section']);
            });
        }

        if (Schema::hasTable('previous_insurer_lists')) {
            Schema::table('previous_insurer_lists', function (Blueprint $table) {
                $table->index(['company_alias']);
            });
        }

        if (Schema::hasTable('reliance_pincode_state_city_master')) {
            Schema::table('reliance_pincode_state_city_master', function (Blueprint $table) {
                $table->string('state_name')->change();
                $table->string('district_name')->change();
                $table->string('city_or_village_name')->change();
                $table->string('area_name')->change();
                $table->index(['pincode']);
            });
        }

        if (Schema::hasTable('reliance_rto_master')) {
            Schema::table('reliance_rto_master', function (Blueprint $table) {
                $table->string('region_name')->change();
                $table->string('region_code')->change();
                $table->string('state_name')->change();
                $table->string('state_id_fk')->change();
                $table->string('city_or_village_name')->change();
                $table->string('district_name')->change();
                $table->string('model_zone_name')->change();
            });
        }

        if (Schema::hasTable('royal_sundaram_motor_state_city_master')) {
            Schema::table('royal_sundaram_motor_state_city_master', function (Blueprint $table) {
                $table->string('city')->change();
                $table->string('city_code')->change();
                $table->string('state')->change();
                $table->string('state_code')->change();
                $table->string('zone')->change();
                $table->string('region')->change();
            });
        }

        if (Schema::hasTable('royal_sundaram_pincode_state_city_master')) {
            Schema::table('royal_sundaram_pincode_state_city_master', function (Blueprint $table) {
                $table->string('state_name')->change();
                $table->string('district_name')->change();
                $table->string('city_or_village_name')->change();
                $table->string('area_name')->change();
                $table->index(['pincode']);
            });
        }

        if (Schema::hasTable('royal_sundaram_prev_insurer')) {
            Schema::table('royal_sundaram_prev_insurer', function (Blueprint $table) {
                $table->string('name')->change();
            });
        }

        if (Schema::hasTable('royal_sundaram_rto_master')) {
            Schema::table('royal_sundaram_rto_master', function (Blueprint $table) {
                $table->index(['rto_no']);
            });
        }

        if (Schema::hasTable('sbi_bike_rto_location')) {
            Schema::table('sbi_bike_rto_location', function (Blueprint $table) {
                $table->index(['RTO_CODE']);
            });
        }

        if (Schema::hasTable('sbi_motor_city_master')) {
            Schema::table('sbi_motor_city_master', function (Blueprint $table) {
                $table->string('city_cd')->change();
                $table->string('city_nm')->change();
                $table->string('state_id')->change();
                $table->string('district_code')->change();
                $table->string('hra_code')->change();
                $table->string('cca_code')->change();
                $table->string('dha_code')->change();
            });
        }

        if (Schema::hasTable('sbi_motor_state_master')) {
            Schema::table('sbi_motor_state_master', function (Blueprint $table) {
                $table->bigInteger('state_id')->change();
                $table->string('state_name')->change();
                $table->string('state_capital')->change();
                $table->string('country_code')->change();
                $table->string('branch_gst_number')->change();
            });
        }

        // if (Schema::hasTable('sbi_pincode_state_city_master')) {
        //     Schema::table('sbi_pincode_state_city_master', function (Blueprint $table) {
        //         $table->index(['PIN_CD']);
        //         $table->timestamp('LAST_UPDATED_DATE')->useCurrent()->useCurrentOnUpdate();
        //     });
        // }

        if (Schema::hasTable('sbi_rto_location')) {
            Schema::table('sbi_rto_location', function (Blueprint $table) {
                $table->index(['rto_location_code']);
            });
        }

        if (Schema::hasTable('shriram_cashless_garage')) {
            Schema::table('shriram_cashless_garage', function (Blueprint $table) {
                $table->string('CodeNo')->change();
                $table->string('Name')->change();
                $table->string('CustEffFmDt')->change();
                $table->string('CustEffToDt')->change();
                $table->string('CustOffAddress')->change();
                $table->string('EmailId1')->change();
                $table->string('EmailId2')->change();
                $table->string('State')->change();
                $table->string('MobileNo')->change();
                $table->string('OffCity')->change();
                $table->string('PanNo')->change();
                $table->string('Accno')->change();
                $table->string('Bank')->change();
                $table->string('AcntType')->change();
                $table->string('Ifsccode')->change();
                $table->string('MicrCode')->change();
                $table->string('AcntHoldName')->change();
                // $table->stringFax('Garage')->change();
                $table->string('DealingOff')->change();
                $table->string('TypeOfGarage')->change();
                $table->string('CashlessType')->change();
                $table->string('Specialization')->change();
                $table->string('TdsCode')->change();
            });
        }

        if (Schema::hasTable('shriram_financier_master')) {
            Schema::table('shriram_financier_master', function (Blueprint $table) {
                $table->string('name')->change();
                $table->string('code')->change();
            });
        }

        if (Schema::hasTable('shriram_pin_city_state')) {
            Schema::table('shriram_pin_city_state', function (Blueprint $table) {
                $table->index(['pin_code']);
                $table->string('pin_desc')->change();
                $table->string('pc_short_desc')->change();
                $table->string('city')->change();
                $table->string('state')->change();
                $table->string('state_desc')->change();
            });
        }

        if (Schema::hasTable('shriram_rto_location')) {
            Schema::table('shriram_rto_location', function (Blueprint $table) {
                $table->string('rtocode')->change();
                $table->string('rtoname')->change();
            });
        }

        if (Schema::hasTable('tata_aig_cashless_garage')) {
            Schema::table('tata_aig_cashless_garage', function (Blueprint $table) {
                $table->string('CashLGar_Id')->change();
                $table->string('CashLGar_Cli_Id')->change();
                $table->string('CashLGar_Ic')->change();
                $table->string('CashLGar_VehicleTypeId')->change();
                $table->string('CashLGar_WSName')->change();
                $table->string('CashLGar_WSCode')->change();
                $table->string('CashLGar_WSCntNumber')->change();
                $table->string('CashLGar_WSAddress')->change();
                $table->string('CashLGar_WSPincode')->index()->change();
                $table->string('CashLGar_WS_dlr_state_id')->change();
                $table->string('CashLGar_WS_dlr_city_id')->change();
                $table->string('CashLGar_WS_oem_id')->change();
                $table->string('CashLGar_WSRating')->change();
                $table->string('CashLGar_WSCashLess')->change();
                $table->string('CashLGar_WSSmartCashLess')->change();
                $table->string('CashLGar_InsUsrId')->change();
                $table->string('CashLGar_ModUsrId')->change();
                $table->string('CashLGar_InsDt')->change();
                $table->string('CashLGar_ModDt')->change();
                $table->string('CashLGar_InsIp')->change();
                $table->string('CashLGar_ModIp')->change();
                $table->string('CashLGar_Status')->change();
            });
        }

        if (Schema::hasTable('tata_aig_pincode_master')) {
            Schema::table('tata_aig_pincode_master', function (Blueprint $table) {
                $table->string('num_state_cd')->change();
                $table->string('txt_state')->change();
                $table->string('num_citydistrict_cd')->change();
                $table->string('num_pincode')->change();
                $table->string('txt_pincode_locality')->change();
                $table->string('dat_start_dt')->change();
                $table->string('dat_end_dt')->change();
                $table->string('num_insert_trans_id')->change();
                $table->string('num_modify_trans_id')->change();
                $table->string('dat_insert_dt')->change();
                $table->string('dat_modify_dt')->change();
                $table->string('num_city_cd')->change();
                $table->string('txt_user_id')->change();
                $table->string('txt_ip_address')->change();
                $table->string('num_country_cd')->change();
                $table->string('txt_is_rural')->change();
                $table->string('num_tehsil_cd')->change();
            });
        }

        if (Schema::hasTable('tata_aig_vehicle_rto_location_master')) {
            Schema::table('tata_aig_vehicle_rto_location_master', function (Blueprint $table) {
                $table->index(['txt_rto_location_code']);
            });
        }

        if (Schema::hasTable('united_india_pincode_state_city_master')) {
            Schema::table('united_india_pincode_state_city_master', function (Blueprint $table) {
                $table->string('TXT_STATE')->change();
                $table->string('TXT_CITYDISTRICT')->change();
                $table->string('NUM_PINCODE')->index()->change();
            });
        }

        if (Schema::hasTable('united_india_previous_insurer_master')) {
            Schema::table('united_india_previous_insurer_master', function (Blueprint $table) {
                $table->string('tp_insurer')->change();
                $table->string('ic_master')->change();
                $table->string('ic_ref')->change();
            });
        }

        if (Schema::hasTable('united_india_rto_master')) {
            Schema::table('united_india_rto_master', function (Blueprint $table) {
                $table->string('TXT_RTO_LOCATION_CODE')->change();
                $table->string('TXT_RTA_CODE')->index()->change();
                $table->string('RTO_LOCATION_DESC')->change();
                $table->string('TXT_REGISTRATION_ZONE')->change();
            });
        }

        if (Schema::hasTable('universal_sompo_ex_showroom_price')) {
            Schema::table('universal_sompo_ex_showroom_price', function (Blueprint $table) {
                $table->string('make_code')->change();
                $table->string('model_code')->change();
                $table->string('ex_showroom_price')->change();
            });
        }

        if (Schema::hasTable('universal_sompo_pincode_master')) {
            Schema::table('universal_sompo_pincode_master', function (Blueprint $table) {
                $table->index(['Pincode']);
                $table->string('PincodeLoc')->change();
            });
        }

        if (Schema::hasTable('universal_sompo_rto_master')) {
            Schema::table('universal_sompo_rto_master', function (Blueprint $table) {
                $table->string('RTO_Location_Code')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
