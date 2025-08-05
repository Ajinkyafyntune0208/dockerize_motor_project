<?php 

namespace App\Quotes\FetchRenewalData\Car;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;


class liberty_videocon
{
    public static function getRenewalData($enquiryId, &$value)
    {
        include_once app_path() . '/Helpers/CarWebServiceHelper.php';
        $fetch_url = config('constants.IcConstants.liberty.MOTOR_FETCH_RENEWAL_URL');
    
        $tp_source_name = config('constants.IcConstants.liberty_videocon.TP_SOURCE_NAME_LIBERTY_VIDEOCON_MOTOR');
        
        $renewal_fetch_array =
        [
          "QuotationNumber"=> config('constants.IcConstants.liberty_videocon.BROKER_IDENTIFIER').time().substr(strtoupper(md5(mt_rand())), 0, 7),
          "RegNo1"=> "",
          "RegNo2"=> "",
          "RegNo3"=> "",
          "RegNo4"=> "",
          "EngineNumber"=> "",
          "ChassisNumber"=> "",
          "IMDNumber"=> "",
          "PreviousPolicyNumber"=> $value['previous_policy_number'],
          "TPSourceName"=> $tp_source_name
        ];
        $get_response = getWsData($fetch_url,$renewal_fetch_array, 'liberty_videocon', [
          'enquiryId'         => $enquiryId,
          'requestMethod'     => 'post',
          'productName'       => $value['product_name'] . " Renewal Data Migration",
          'company'           => 'liberty_videocon',
          'section'           => 'car',
          'method'            => 'Fetch Policy Details',
          'transaction_type'  => 'quote',
         
        ]);  
      $data = $get_response['response'];
      $response_data = json_decode($data);
        if (!empty($response_data)) 
        {
          if (!empty($response_data->KYCStage || !empty($response_data->ErrorText))) 
          {
            return false;
          }
          //  * assign response data to $value for policy data and vehicle detaisl and personal details
          $value['previous_policy_start_date'] = !empty($response_data->PreviousPolicyStartDate ?? null) ? date('d-m-Y', strtotime(str_replace('/','-',$response_data->PreviousPolicyStartDate))) : ($value['previous_policy_start_date'] ?? null);
          $value['previous_policy_end_date'] = !empty($response_data->PreviousPolicyEndDate ?? null) ? date('d-m-Y', strtotime(str_replace('/','-',$response_data->PreviousPolicyEndDate))) : ($value['previous_policy_end_date'] ?? null);
          if(!empty($response_data->NoOfClaims ?? null))
          {
            if($response_data->NoOfClaims == 'Yes')
            {
              $value['previous_claim_status'] = 'Y';
            }else
            {
              $value['previous_claim_status'] = 'N';
            }
          }
          $value['previous_ncb'] = isset($response_data->PreviousYearNCBPercentage) ? $response_data->PreviousYearNCBPercentage : ($value['previous_ncb'] ?? 0);
          $value['idv'] = !empty($response_data->VehicleIDV ?? null) ? $response_data->VehicleIDV : ($value['idv'] ?? null);
          // $value['vehicle_registration_number']= $response_data->;
          $value['registration_date'] = !empty($response_data->RegistrationDate ?? null) ? date('d-m-Y', strtotime(str_replace('/','-',$response_data->RegistrationDate))) : ($value['registration_date'] ?? null);
          $value['vehicle_manufacture_year'] = !empty($response_data->ManfYear ) ? $response_data->ManfYear : ($value['vehicle_manufacture_year'] ?? null);
          $value['chassis_no'] = !empty($response_data->ChassisNo ?? null) ? $response_data->ChassisNo : ($value['chassis_no'] ?? null);
          $value['engine_no'] = !empty($response_data->EngineNo ?? null) ? $response_data->EngineNo : ($value['engine_no'] ?? null);
          if(!empty($response_data->CustmerObj->FirstName))
          {
            $value['full_name'] = implode(" ",array_filter([$response_data->CustmerObj->FirstName,$response_data->CustmerObj->MiddleName,$response_data->CustmerObj->LastName]));
          }
          
          $value['dob'] = !empty($response_data->CustmerObj->DOB ?? null) ? date('d-m-Y', strtotime(str_replace('/','-', $response_data->CustmerObj->DOB))) : ($value['dob'] ?? null);
          $value['pan_card'] = !empty($response_data->CustmerObj->PanNo ?? null) ? $response_data->CustmerObj->PanNo :  null;
          // $value['gender']= $response_data->;
          $value['email_address'] = !empty($response_data->CustmerObj->EmailID ?? null) ? $response_data->CustmerObj->EmailID : ($value['email_address'] ?? null); 
          $value['mobile_no'] = !empty($response_data->CustmerObj->MobileNumber ??  null) ? $response_data->CustmerObj->MobileNumber : ($value['mobile_no'] ?? null);
          if(!empty($response_data->CustmerObj->CustomerType ?? null))
          {
            $value['owner_type'] = $response_data->CustmerObj->CustomerType == 'C' ? 'company' : 'individual';
          }
          else
          {
            $value['owner_type'] = null;
          }
          $value['communication_address'] = !empty($response_data->CustmerObj->AddressLine1 ?? null) ? $response_data->CustmerObj->AddressLine1 . " " . $response_data->CustmerObj->AddressLine2 . " " .$response_data->CustmerObj->AddressLine3 : ($value['communication_address'] ?? null);
          $value['communication_city'] = !empty($response_data->CustmerObj->CityDistrictName ?? null) ? $response_data->CustmerObj->CityDistrictName : ($value['communication_city'] ?? null);
          $value['communication_state'] = !empty($response_data->CustmerObj->StateName ?? null) ? $response_data->CustmerObj->StateName : ($value['communication_state'] ?? null);
          $value['communication_pincode'] = !empty($response_data->CustmerObj->PinCode ?? null) ? $response_data->CustmerObj->PinCode : ($value['communication_pincode'] ?? null);
          if (!empty($response_data->ManfYear) && !empty($response_data->ManfMonth)) 
          {
            $manufacture_dt = $response_data->ManfMonth . '-' . $response_data->ManfYear;
            $manu_dt = Carbon::createFromFormat('n-Y',$manufacture_dt);
            $manu_dt = Carbon::parse($manu_dt)->format('m-Y');
            $value['vehicle_manufacture_year'] = $manu_dt;
          }
          // $value['marital_status']  = !empty($response_data->CustmerObj->MaritalStatus ?? null) ? $response_data->CustmerObj->MaritalStatus : ($value['marital_status'] ?? null);

          //  * nominee data
          $value['nominee_name'] = implode(" ",array_filter([$response_data->NomineeFirstName, $response_data->NomineelastName]));
          // $value['nominee_dob']= $response_data->;
          $value['relationship_with_nominee'] =    $response_data->NomineeRelationship;
          $value['is_financed'] = $response_data->IsFinancierDetails == 'true' ? "Yes" : 'No';
          $value['financier_agreement_type'] =  $response_data->AgreementType;
          $value['financier_name'] =  $response_data->FinancierName;
          $value['hypothecation_city'] = $response_data->BuyerState;


          
          
          //  * assign response data to $value for addons
          $value['zero_dep'] = !empty((float)$response_data->DepreciationCoverPremium ?? null) ? $response_data->DepreciationCoverPremium : null;
          $value['rsa'] =  !empty((float)$response_data->RoadAssistPremium ?? null) ? $response_data->RoadAssistPremium : null;
          $value['engine_protector'] =  !empty((float)$response_data->EngineSafeCoverPremium ?? null) ? $response_data->EngineSafeCoverPremium : null;
          $value['return_to_invoice'] =  !empty((float)$response_data->GAPPremium ?? null) ? $response_data->GAPPremium : null;
          $value['consumable'] =  !empty((float)$response_data->ConsumablesCoverPremium) ? $response_data->ConsumablesCoverPremium : null;
          // $value['loss_of_personal_belonging'] =  $response_data->;
          $value['key_replacement'] = !empty((float)$response_data->KeyLossPremium ?? null) ? $response_data->KeyLossPremium : null;
          $value['cpa_amount'] =  !empty((float)$response_data->PatoOwnerDriverCoverPremium ?? null) ? $response_data->PatoOwnerDriverCoverPremium : null;

          
          //  * assign response data to $value accessories
          // $value['external_bifuel_cng_lpg'] =  $response_data->;
          // $value['external_bifuel_cng_lpg_si_amount'] =  $response_data->;
          if (!empty((float)$response_data->ElectricalAccessoriesPremium ?? null ) && !empty($response_data->ElectricalAccesorySumInsured ?? null)) {

            $value['electrical'] =  true;
            $value['electrical_si_amount'] =  $response_data->ElectricalAccesorySumInsured;
          }
          if (!empty($response_data->NonElectricalAccessoriesPremium ?? null) && !empty($response_data->NonElectricalAccessoriessumInsured ?? null)) {
            $value['non-electrical'] = true;
            $value['non-electrical_si_amount'] =  $response_data->NonElectricalAccessoriessumInsured;  
          }
          if(!empty($response_data->ExternalFuelKit) && $response_data->ExternalFuelKit == "Yes" && ($response_data->FuelSI ?? 0) > 0 && !empty($response_data->FuelType))
          {
            $value['external_bifuel_cng_lpg'] = true;
            $value['external_bifuel_cng_lpg_si_amount'] = $response_data->FuelSI;
          }


          //  * assign response data to $value for additional covers
          // $value['geogarphical_extension'] = "";
          // $value['geographical_extension_si_amount'] = "";

          $value['ll_paid_driver'] = !empty((float)$response_data->LLtoPaidDriverCoverPremium ) ? $response_data->LLtoPaidDriverCoverPremium : null;
          if(!empty($response_data->PAUnnnamed) && $response_data->PAUnnnamed == "Yes" && ($response_data->UnnamedPASI ?? 0) > 0)
          {
            $value['unnammed_passenger_pa_cover'] = true;
            $value['unnammed_passenger_pa_cover_si_amount'] = $response_data->UnnamedPASI ;
          }
          // $value['ll_paid_driver_si_amount'] = "";
          //  * create a json file with $value and return true
          $env = config('app.env');
          if ($env == 'local') {
              $envFolder = 'uat';
          } elseif ($env == 'test') {
              $envFolder = 'production';
          } elseif ($env == 'live') {
              $envFolder = 'production';
          }
      $product = "motor";
      $path = 'mmv_masters/' . $envFolder . '/';
      $path  = $path . $product . "_model_version.json";
      $mmvData_motor = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($path), true);
      foreach ($mmvData_motor as $mmv) {
        if (($mmv['mmv_liberty_videocon'] ?? '') == $response_data->ModelCode) {
          $value['version_id'] = $mmv['version_id'];
          break;
        }
      }
          if(empty($value['version_id']))
          {
            $path = 'mmv_masters/' . $envFolder . '/';
            $path  = $path  . "liberty_videocon_model_master.json";
            $mmvData = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($path), true);
            $isModelCodeFound = false;
            foreach ($mmvData as $key => $mmv) 
            {
              if (($mmv['vehicle_model_code'] ?? '') == $response_data->ModelCode && $mmv['manufacturer_code'] == $response_data->MakeCode) 
              {
                $response_data->ModelCode = $key;
                $isModelCodeFound = true;     //identifier
                break;
              }
            }
            if($isModelCodeFound)
            {
              foreach ($mmvData_motor as $mmv2) 
              {
                if (($mmv2['mmv_liberty_videocon'] ?? '') == $response_data->ModelCode ) 
                {
                  $value['version_id'] = $mmv2['version_id'];
                }
              }
            }
          }

          return true;
        }
        return false;
        
    }

  public static function getFyntuneVersionId(&$value, $code)
  {
    $env = config('app.env');
    if ($env == 'local') {
      $envFolder = 'uat';
    } elseif ($env == 'test') {
      $envFolder = 'production';
    } elseif ($env == 'live') {
      $envFolder = 'production';
    }
    $product = "motor";
    $path = 'mmv_masters/' . $envFolder . '/';
    $path  = $path . $product . "_model_version.json";
    $mmvData_motor = json_decode(Storage::disk(config('filesystems.driver.s3.status') == 'active' ? config('filesystems.default') : 'public')->get($path), true);
    foreach ($mmvData_motor as $mmv) {
      if (($mmv['mmv_liberty_videocon'] ?? '') == $code) {
        $value['version_id'] = $mmv['version_id'];
        break;
      }
    }
  }
}