<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Models\PolicyDetails;

class AbiblDataMigrationDataJobOld implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);
        $file = Storage::allFiles('abibl-data-migration-data-old');
        if (count($file) == 0)
        return;
        $file = $file[0];
        $data = \Maatwebsite\Excel\Facades\Excel::toCollection(new \App\Imports\UspImport, $file)[0];
        //$title_array = ['Mr.','Ms.','Mrs.','M/s','Dr.'];
        foreach ($data as $key => $value) {
//            print_r($value);
//            die;
            //$title = '';
            //$full_name = trim($value['proposer_name']);
//            foreach ($title_array as $t) {
//                if (str_starts_with($value['proposer_name'], $t)) 
//                {
//                    $title = $t;
//                    $full_name = trim(str_replace($t,'',$value['proposer_name']));
//                    break;
//                }
//            }
            $transaction_date = \Carbon\Carbon::parse($value['businessdate'])->format('Y-m-d').' '.date("h:i:s");
            $policy_number = $value['policyno'];
            $check_data = PolicyDetails::where('policy_number', $policy_number)->first();
            if(!empty($check_data))
            {
                $data_status_data = [
                    'vehicle_reg_no'    => $value['vehicle_registration_number'],
                    'policy_no'         => $policy_number,
                    'source'            => 'ABIBL_MG_DATA',
                    'status'            => 0,
                    'policy_status'     => $value['transaction_stage'],
                    'comments'          => 'Duplicate Data',
                    'transaction_date'  => $transaction_date
                ];
                DB::table('migration_data_reports')->insert($data_status_data); 
            }
            else
            {
                $product_sub_type_id = null;
                if(trim($value['policytype']) == 'Two Wheeler')
                {
                   $product_sub_type_id = 2; 
                }
                else if(trim($value['policytype']) == 'Private Car')
                {
                   $product_sub_type_id = 1; 
                }
                $full_name = trim($value['insuredname']);
                $user_product_journey = UserProductJourney::create([
                    'user_fname'            => $full_name,
                    'user_lname'            => null,
                    'user_mname'            => null,
                    'user_email'            => Str::lower($value['proposer_emailid'] ?? null),
                    'user_mobile'           => $value['proposer_mobile'] ?? null,
                    'product_sub_type_id'   => $product_sub_type_id,
                    'created_on'            => $transaction_date,
                    'lead_source'           => 'ABIBL_OLD_DATA'
                ]);

                $rto_code = substr(trim($value['registrationno']), 0, 2).'-'.substr(trim($value['registrationno']), 2, 2);
                
                $rto_city = \Illuminate\Support\Facades\DB::table('master_rto')->where('rto_code', $rto_code)->first();
                $rto_city = $rto_city ? $rto_city->rto_name : null;
                $policy_type = $is_renewal = $business_type = null;

                $business_type = 'rollover';            
                if($value['policystatus'] == 'NEW')
                {
                   $business_type = 'newbusiness'; 
                }

                if($value["plantype"] == "1OD-0TP")
                $policy_type = "own_damage";
                else if($value["plantype"] == "1OD-1TP-0CPA" || $value["policy_type"] == "1OD-1TP-1CPA")
                $policy_type = "comprehensive";
                else if($value["plantype"] == "1OD-3TP")
                $policy_type = "comprehensive";
                else if($value["plantype"] == "3OD-3TP")
                $policy_type = "comprehensive";
                // $business_type = '';
                
                $version_id = 'CRPFYN2906213146';
                
                $company_details = DB::table('previous_insurer_lists as pil')
                    ->join('master_company as mc', 'mc.company_alias', '=', 'pil.code')
                    //->join('master_policy', 'master_policy.product_sub_type_id', '=', 'master_home_view.product_sub_type_id')
                    ->where('pil.name', $value['insurancecompany'])
                    ->select('mc.company_id','mc.company_name','mc.company_alias')
                    ->first();
                $company_alias = $company_details->company_alias;
                $company_name = $company_details->company_name;
                $company_id = $company_details->company_id;

                $previous_ncb = (int) $value['ncbpercent'];
                $applicable_ncb = (int) $value['ncbpercent'];
                $idv = (int) $value['totalidv'];
                
                $gender = '';
                $gender_name = '';
                if($value['clienttype'] == 'Individual')
                {
                   $vehicle_owner_type = 'I';
                }
                else
                {
                   $vehicle_owner_type = 'C';                    
                }
                $title = NULL;
                $vehicle_registration_date = NULL;
                $previous_policy_expiry_date = NULL;
                $previous_policy_type = NULL;
                $previous_insurer = NULL;
                $fuel_type = null;
                $manufacture_year = $value['yom'];
                $is_claim = "N";
                $version_name = NULL;
                
                $user_product_journey->corporate_vehicles_quote_request()->create([
                    'version_id'  => $version_id,
                    'business_type' => $business_type,
                    'policy_type' => $policy_type,
                    'vehicle_register_date' => $vehicle_registration_date,
                    'previous_policy_expiry_date' => $previous_policy_expiry_date,
                    'vehicle_registration_no' => $value['registrationno'],
                    'previous_policy_type' => $policy_type,
                    'previous_insurer' => $previous_insurer,
                    'fuel_type' => $fuel_type,
                    //'fuel_type' => ($value['vehicle_fuel_type'] != "" && $value['vehicle_fuel_type'] != "NULL") ? $value['vehicle_fuel_type'] : null,
                    'manufacture_year' => $manufacture_year,
                    'rto_code' => $rto_code,
                    'rto_city' => $rto_city,
                    //'vehicle_owner_type' => ($value['owner_type'] != "" && $value['owner_type'] != "NULL") ? Str::upper(Str::substr($value['owner_type'], 0, 1)) : null,
                    'vehicle_owner_type' => $vehicle_owner_type,
                    'is_claim' => $is_claim,
                    'previous_ncb' => $previous_ncb,
                    'applicable_ncb' => $applicable_ncb,
                    'version_name' => $value['vehicle_version'] ?? null,
                    'is_renewal'            => 'N',
                    'previous_insurer'      => $company_name,
                    'previous_insurer_code' => $company_alias,
                    'insurance_company_id'  => $company_id,
                    'product_id'            => $product_sub_type_id,
                    'created_on'            => $transaction_date
                ]);

                $addons_list = $value['selected_addons'] ?? NULL;
                $array_addons_list = explode('|',$addons_list);

                $selected_addons = [];
                foreach ($array_addons_list as $key => $addon) {
                    if(trim($addon) == 'NIL DEP')
                    {
                       $selected_addons[]['name'] = 'Zero Depreciation';
                    }
                    else if(trim($addon) == 'RTI')
                    {
                       $selected_addons[]['name'] = 'Return To Invoice'; 
                    }
                    else if(trim($addon) == 'RIM AND TYRE COVER')
                    {
                       $selected_addons[]['name'] = 'Tyre Secure'; 
                    }
                    else if(trim($addon) == 'ENGINE PROTECTOR')
                    {
                       $selected_addons[]['name'] = 'Engine Protector'; 
                    }
                    else if(trim($addon) == 'KEY LOSS COVER')
                    {
                       $selected_addons[]['name'] = 'Key Replacement'; 
                    }
                    else if(trim($addon) == 'LOSS OF PERSONAL BELONGINGS')
                    {
                       $selected_addons[]['name'] = 'Loss of Personal Belongings';  
                    }
                }

                $user_product_journey->addons()->create([
                    'user_product_journey_id'   => $product_sub_type_id,
                    'addons'                    => ($selected_addons),
                    'applicable_addons'         => ($selected_addons),
                    'created_at'                => $transaction_date,
                    'updated_at'                => $transaction_date
                ]);

                $user_product_journey->quote_log()->create([
                    'product_sub_type_id'   => $product_sub_type_id,
                    'ic_id'                 => $company_id,
                    'ic_alias'              => $company_name,
                    'od_premium'            => $value['od_premium'] ?? null,
                    'tp_premium'            => $value['tp_premium'] ?? null,
                    'service_tax'           => $value['tax_amount'] ?? null,
                    'addon_premium'         => $value['addon_premium'] ?? null,
                    'quote_data'            => "",
                    'idv'                   => $idv,
                    'updated_at'            => $transaction_date,
                    'searched_at'           => $transaction_date
                ]);
                $dob = NULL;
                $pan_number = NULL;
                $state = NULL;
                $engine_number = $value['engineno'];
                $chassis_number = $value['chassisno'];
                $is_vehicle_finance = NULL;             
                $financer_agreement_type = NULL;
                $name_of_financer = NULL;
                $previous_insurance_company = NULL;
                
                $address_line1 = trim(trim($value['insaddrln1']).' '.trim($value['insaddrln2']));
                $proposal = $user_product_journey->user_proposal()->create([
                    'title' => $title,
                    'first_name' => $full_name,
                    'email' => Str::lower($value['proposer_emailid'] ?? null),
                    'office_email' => Str::lower($value['proposer_emailid'] ?? null),
                    'mobile_number' => $value['proposer_mobile'] ?? null,
                    //'marital_status' => "Married",
                    'dob' => $dob,//($value["proposer_dob"] != 'NULL') ? Carbon::parse($value["proposer_dob"])->format('d-m-Y') : null,
                    'gender' => $gender,//($value['proposer_name'] != "" && $value["proposer_name"] != "") ? (explode('. ', $value['proposer_name'])[0] == "Mr" ? "MALE" : "FEMALE") : null,
                    'gender_name' => $gender_name,//($value['proposer_name'] != "" && $value["proposer_name"] != "") ? (explode('. ', $value['proposer_name'])[0] == "Mr" ? "Male" : "Female") : null,
                    //'occupation' => "Business / Professional Services",
                    //"occupation_name" => "Business / Professional Services",
                    "pan_number"    => $pan_number,//($value['pan_no'] != "" && $value['pan_no'] != "") ? $value['pan_no'] : null,
                    "address_line1" => $address_line1,//trim(trim($value['address_line_1']).' '.trim($value['address_line_1']).' '.trim($value['address_line_1'])),
                    //"address_line2" => ($value['address_line_2'] != "NULL" && $value['address_line_2'] != "") ? $value['address_line_2'] : null,
                    //"address_line3" => ($value['address_line_3'] != "NULL" && $value['address_line_3'] != "") ? $value['address_line_3'] : null,
                    "pincode"       => $value['pincode'],
                    "state"         => $state,//$value['state'],
                    "city"          => $value['cityname'],
                    "rto_location" => $rto_code,
                    "is_car_registration_address_same" => 1,
                    "car_registration_address1" => $address_line1,//($value['address_line_1'] != "NULL" && $value['address_line_1'] != "")? $value['address_line_1'] : null,
                    //"car_registration_address2" => ($value['address_line_2'] != "NULL" && $value['address_line_2'] != "")? $value['address_line_2'] : null,
                    //"car_registration_address3" => ($value['address_line_3'] != "NULL" && $value['address_line_3'] != "")? $value['address_line_3'] : null,
                    "car_registration_pincode" => $value['pincode'],//($value['pincode'] != "NULL" && $value['pincode'] != "")? $value['pincode'] : null,
                    "car_registration_state" => $state,//$value['state'],//($value['state'] != "NULL" && $value['state'] != "")? $value['state'] : null,
                    "car_registration_city" => $value['cityname'],
                    "vehicale_registration_number" => $value['registrationno'],
                    "vehicle_manf_year" => trim($manufacture_year),//($value['vehicle_registration_date'] != "" && $value['vehicle_registration_date'] != "NULL") ? Carbon::parse($value['vehicle_registration_date'])->format('m-') . $value['vehicle_manufacture_year'] : null,
                    "engine_number"     => $engine_number,
                    "chassis_number"    => $chassis_number,
                    "is_vehicle_finance" => $is_vehicle_finance,
                    "financer_agreement_type" => $financer_agreement_type,//$value['is_financed'] == "Yes" ? 'Hypothecation' : null, 
                    "name_of_financer" => $name_of_financer,//$value['is_financed'] == "Yes" ? $value['hypothecation_to'] : NULL,// != "NULL" && $value['hypothecation_to'] != "NULL") ? $value['hypothecation_to'] : null,
                    "previous_insurance_company" => $$previous_insurance_company,//$value["previous_insurer"],
                    "previous_policy_number" => ($value["previous_policy_number"] != "NULL" && $value["previous_policy_number"] != "NULL") ? $value["previous_policy_number"] : null,
                    "nominee_name" => $value["nominee_name"],
                    "nominee_age" => ($value["nominee_age"] != "NULL" && $value["nominee_age"] != "NULL") ? $value["nominee_age"] : null,
                    "nominee_relationship" => ($value["nominee_relationship"] != "NULL" && $value["nominee_relationship"] != "NULL") ? $value["nominee_relationship"] : null,
                    "proposal_date" => ($value["proposal_date"] != "NULL" && $value["proposal_date"] != "") ? Carbon::parse($value["proposal_date"])->format('Y-m-d H:i:s') : null,
                    "policy_start_date" => ($value["policy_start_date"] != "NULL" && $value["policy_start_date"] != "") ? Carbon::parse($value["policy_start_date"])->format('d-m-Y') : null,
                    "policy_end_date" => ($value["policy_end_date"] != "NULL" && $value["policy_end_date"] != "") ? Carbon::parse($value["policy_end_date"])->format('d-m-Y') : null,
                    "tp_start_date" => ($value["tp_start_date"] != "NULL" && $value["tp_start_date"] != "") ? Carbon::parse($value["tp_start_date"])->format('d-m-Y') : null,
                    "tp_end_date" => ($value["tp_end_date"] != "NULL" && $value["tp_end_date"] != "") ? Carbon::parse($value["tp_end_date"])->format('d-m-Y') : null,
                    "tp_insurance_company" => $value["tp_prev_company"],
                    "tp_insurance_number" => $value["tp_policy_number"],
                    "prev_policy_expiry_date" => ($value["previous_policy_expiry_date"] != "NULL" && $value["previous_policy_expiry_date"] != "") ? Carbon::parse($value["previous_policy_expiry_date"])->format('d-m-Y') : null,
                    "proposal_no" => ($value["proposal_no"] != "NULL" && $value["proposal_no"] != "") ? $value["proposal_no"] : null,
                    "od_premium" => ($value["od_premium"] != "NULL" && $value["od_premium"] != "") ? $value["od_premium"] : null,
                    "tp_premium" => ($value["tp_premium"] != "NULL" && $value["tp_premium"] != "") ? $value["tp_premium"] : null,
                    "total_premium" => ($value["base_premium"] != "NULL" && $value["base_premium"] != "") ? $value["base_premium"] : null,
                    "service_tax_amount" => ($value["tax_amount"] != "NULL" && $value["tax_amount"] != "") ? $value["tax_amount"] : null,
                    "final_payable_amount" => ($value["premium_amount"] != "NULL" && $value["premium_amount"] != "") ? $value["premium_amount"] : null,
                    "total_discount" => ($value["discount_amount"] != "NULL" && $value["discount_amount"] != "") ? $value["discount_amount"] : null,
                    "owner_type" => $vehicle_owner_type,//Str::upper(Str::substr(($value['owner_type'] != "NULL" && $value['owner_type'] != "" ) ? $value['owner_type'] : null, 0, 1)),
                    "nominee_dob" => ($value["nominee_dob"] != "" && $value["nominee_dob"] != "NULL") ? Carbon::parse($value["nominee_dob"])->format('Y-m-d') : null,
                    "insurance_company_name" => $value["previous_insurer"],
                    "ic_name" => $company_name,
                    'ic_id' => $company_id,
                    'idv'   => $idv,
                    "cpa_start_date" => ($value["cpa_policy_start_date"] != "" && $value["cpa_policy_start_date"] != "NULL") ? Carbon::parse($value["cpa_policy_start_date"])->format('d-m-Y') : null,
                    "cpa_end_date" => ($value["cpa_policy_end_date"] != "" && $value["cpa_policy_end_date"] != "NULL") ? Carbon::parse($value["cpa_policy_end_date"])->format('d-m-Y') : null,
                    "previous_ncb" => $previous_ncb,//($value["previous_ncb"] != "" && $value["previous_ncb"] != "NULL") ? $value["previous_ncb"] : null,
                    "applicable_ncb" => $applicable_ncb,//($value["ncb_percentage"] != "" && $value["ncb_percentage"] != "NULL") ? $value["ncb_percentage"] : null,
                    'created_date'          => $transaction_date,
                    'proposal_date'         => $transaction_date
                ]);

                //print_r($proposal->user_proposal_id);
                $user_product_journey->journey_stage()->create([
                    'ic_id'         => $company_id,
                    'proposal_id'   => $proposal->user_proposal_id,
                    'stage'         => $value['transaction_stage'],
                    'created_at'    => $transaction_date,
                    'updated_at'    => $transaction_date
                ]);

                $user_product_journey->payment_response()->create([
                    'ic_id'             => $company_id,
                    'user_proposal_id'  => $proposal->user_proposal_id,
                    'proposal_no'       => $value['proposal_no'],
                    'order_id'          => $value['proposal_no'],
                    'active'            => 1,
                    'created_at'        => $transaction_date,
                    'updated_at'        => $transaction_date
                ]);

                $proposal->policy_details()->create([
                    'policy_number' => $value['policy_no'],
                    'status'        => 'SUCCESS',
                    'created_on'    => $transaction_date
                ]);
                
                $data_status_data = [
                    'user_product_journey_id'   => $user_product_journey->user_product_journey_id,
                    'vehicle_reg_no'            => $value['vehicle_registration_number'],
                    'policy_no'                 => $policy_number,
                    'source'                    => 'ABIBL_MG_DATA',
                    'policy_status'             => $value['transaction_stage'],
                    'status'                    => 1,
                    'comments'                  => 'Data Inserted Sucessfully',
                    'transaction_date'          => $transaction_date
                ];
                DB::table('migration_data_reports')->insert($data_status_data);
                
            }
            
        }
        //echo $file;
        Storage::delete($file);
        return [];
    }
}
