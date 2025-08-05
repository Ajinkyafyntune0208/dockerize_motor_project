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
use App\Models\UserProposal;

ini_set('memory_limit', '-1');
class AbiblDataMigrationDataJob implements ShouldQueue
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
        
        $file = Storage::allFiles('abibl-data-migration-data');
        if (count($file) == 0)
        return;
        $file = $file[0];
        $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\UspImport, $file)[0];

        $title_array = ['Mr.','Ms.','Mrs.','M/s','Dr.'];
        foreach ($data as $key => $value) {
            $value = str_replace("'", "", $value);
//            print_r($value);
//            die;
            $value = array_map('trim', $value);
//            print_r($value);
//            die;
            $title = '';
            $full_name = trim($value['proposer_name']);
            foreach ($title_array as $t) {
                if (str_starts_with($value['proposer_name'], $t)) 
                {
                    $title = $t;
                    $full_name = trim(str_replace($t,'',$value['proposer_name']));
                    break;
                }
            }
            
            $policy_number = $value['policy_no'];
            $product_sub_type_id = trim($value['product_name']) == 'PCP' ? 1 : 8;
            if($value['vehicle_model'] == 'MG_ZS_EV')
            {
                $value['vehicle_model'] = 'MG ZS EV';
            }
            $value['vehicle_version'] = str_replace('  ', ' ', str_replace('  ', ' ', $value['vehicle_version']));
            $version_id = \Illuminate\Support\Facades\DB::table('abibl_vehicle_mapping')
                                    ->where('manf', trim($value['vehicle_make']))
                                    ->where('model_name', trim($value['vehicle_model']))
                                    ->where('variant_name', trim($value['vehicle_version']))
                                    ->first();
            $version_id = keysToLower($version_id);
            if(!isset($version_id->fts_code))
            {
                $version_id = \Illuminate\Support\Facades\DB::table('abibl_vehicle_mapping')
                    ->where('manf', trim($value['vehicle_make']))
                    ->where('model_name', trim($value['vehicle_model']))
                    ->where('variant_name','like','%'.trim($value['vehicle_version']).'%' )
                    ->first();
                $version_id = keysToLower($version_id);
            }
            
            if(isset($version_id->fts_code))
            {
               $version_id = $version_id->fts_code;
                $mmv_details = get_fyntune_mmv_details(1,$version_id);
                if(isset($mmv_details['status']) && $mmv_details['status'] == 1)
                {
                   $transaction_date = \Carbon\Carbon::parse($value['transaction_date'])->format('Y-m-d').' '.date("h:i:s");
                    $policy_number = $value['policy_no'];
                    $proposal_no = $value['proposal_no'];
                    $check_data = UserProposal::where('proposal_no', $proposal_no)->first();
                    if(!empty($check_data))
                    {
                        $user_proposal_id =  $check_data->user_proposal_id;
                        //Update Propodal Details 
                        $propsal_data = [
                            "engine_number"         => $value['engine_number'],
                            "chassis_number"        => $value['chassis_number'],
                            "tp_insurance_number"   => $value['tp_policy_number']
                        ];
                        
                        UserProposal::where('user_proposal_id',$user_proposal_id)
                                ->update($propsal_data);
                        //Update Policy Details
                        $policy_data = [
                            'policy_number' => $value['policy_no'],
                            'status'        => 'SUCCESS',
                            'created_on'    => $transaction_date
                        ];
                        PolicyDetails::where('proposal_id',$user_proposal_id)->update($policy_data); 
                        
                        $data_status_data = [
                            'vehicle_reg_no'    => $value['vehicle_registration_number'],
                            'policy_no'         => $policy_number,
                            'proposal_no'         => $proposal_no,
                            'source'            => 'ABIBL_MG_DATA',
                            'status'            => 0,
                            'policy_status'     => $value['transaction_stage'],
                            'comments'          => 'Data Updated',
                            'transaction_date'  => $transaction_date,
                            'file_name'         => $file
                        ];
                        DB::table('migration_data_reports')->insert($data_status_data); 
                    }
                    else
                    {                
                    $user_product_journey = UserProductJourney::create([
                        'user_fname'            => $full_name,
                        'user_lname'            => null,
                        'user_mname'            => null,
                        'user_email'            => Str::lower($value['proposer_emailid'] ?? null),
                        'user_mobile'           => $value['proposer_mobile'] ?? null,
                        'product_sub_type_id'   => $product_sub_type_id,
                        'created_on'            => $transaction_date,
                        'lead_source'           => 'ABIBL_MG_DATA'
                    ]);
                    //$value['vehicle_registration_number'] = 'DL-1d_5455';
                    $rto_code = null;
                    if($value['vehicle_registration_number'] != 'NULL' && $value['vehicle_registration_number'] != "" && $value['vehicle_registration_number'] != null)
                    {
                        if(strpos($value['vehicle_registration_number'],'DL-1') !== false)
                        {
                            $rto_code = 'DL-01'; 
                        } 
                        else if(strpos($value['vehicle_registration_number'],'DL-2') !== false)
                        {
                            $rto_code = 'DL-02'; 
                        } 
                        else if(strpos($value['vehicle_registration_number'],'DL-3') !== false)
                        {
                            $rto_code = 'DL-03'; 
                        } 
                        else if(strpos($value['vehicle_registration_number'],'DL-4') !== false)
                        {
                            $rto_code = 'DL-04'; 
                        } 
                        else if(strpos($value['vehicle_registration_number'],'DL-5') !== false)
                        {
                            $rto_code = 'DL-05'; 
                        } 
                        else if(strpos($value['vehicle_registration_number'],'DL-6') !== false)
                        {
                            $rto_code = 'DL-06'; 
                        } 
                        else if(strpos($value['vehicle_registration_number'],'DL-7') !== false)
                        {
                            $rto_code = 'DL-07'; 
                        } 
                        else if(strpos($value['vehicle_registration_number'],'DL-8') !== false)
                        {
                            $rto_code = 'DL-08'; 
                        } 
                        else if(strpos($value['vehicle_registration_number'],'DL-9') !== false)
                        {
                            $rto_code = 'DL-09'; 
                        } 
                        else
                        {
                            $rto_code = explode('-', $value['vehicle_registration_number'])[0] . '-' . explode('-', $value['vehicle_registration_number'])[1]; 
                        }                         
                    }
                    
                    if($rto_code == null)
                    {
                        $city = $value['city'];
                        if($city == 'NEW DELHI')
                        {
                          $city = 'DELHI';  
                        }
                        $rto_data = \Illuminate\Support\Facades\DB::table('master_rto_abibl')->where('rto_name', trim($city))->first();
                        $section = 1;
                        $rto_code = $rto_data->rto_code ?? null;
                    }
                    else
                    {
                        $rto_data = \Illuminate\Support\Facades\DB::table('master_rto_abibl')->where('rto_code', $rto_code)->first();
                        $section = 2;
                    }
                    
                    if(empty($rto_data))
                    {
                        $section = 3;
                        $rto_data = \Illuminate\Support\Facades\DB::table('rto_abibl')->where('city_name', $value['city'])->first();
                        $rto_code = $rto_data->rto_code ?? null;
                    }
                    if(empty($rto_data))
                    {
                        $section = 4;
                        //$value['pincode'] = '140417';
                        $city_by_pincode = \Illuminate\Support\Facades\DB::table('mg_abibl_edelweiss_pincode_master')->where('pincode', $value['pincode'])->get();
                        foreach ($city_by_pincode as $key => $city_list) {
                            $rto_data = \Illuminate\Support\Facades\DB::table('master_rto_abibl')->where('rto_name', $city_list->city)->first();
                            if(empty($rto_data))
                            {
                                $rto_data = \Illuminate\Support\Facades\DB::table('master_rto_abibl')->where('rto_name', $city_list->district)->first();
                            }
                            else
                            {
                                break;
                            }
                            $rto_code = $rto_data->rto_code ?? null;
                        }                        
                    }
                    
                    $rto_city = $rto_data->rto_name ?? $value['city'];
                    if(empty($rto_data))
                    {
                        $section = 5;
                        $rto_data = \Illuminate\Support\Facades\DB::table('master_default_rto_abibl')->where('state_name', $value['state'])->first();
                        $rto_code = $rto_data->rto_code ?? null;
                        $rto_not_found_data = [
                            'reg_number'    => $value['vehicle_registration_number'],
                            'city'          => $value['city'],
                            'state'         => $value['state'],
                            'pincode'       => $value['pincode'],
                            'section'      => $section,
                            'source'        => 'ABIBL_MG_DATA',                            
                            'created_at'    => date('Y-m-d H:i:s')
                        ];
                        DB::table('rto_not_found')->insert($rto_not_found_data);
                    }
                    
                    if(empty($rto_data))
                    {
                        $rto_not_found_data = [
                            'reg_number'    => $value['vehicle_registration_number'],
                            'city'          => $value['city'],
                            'state'         => $value['state'],
                            'pincode'       => $value['pincode'],
                            'section'      => $section,
                            'source'        => 'ABIBL_MG_DATA',                            
                            'created_at'    => date('Y-m-d H:i:s')
                        ];
                        DB::table('rto_not_found')->insert($rto_not_found_data);                        
                    }
                    
                    $policy_type = $is_renewal = $business_type = null;

                    $business_type = 'rollover';           
                    if(isset($value['business_type']) && $value['business_type'] == 'New')
                    {
                       $business_type = 'newbusiness'; 
                    }

                    if($value["policy_type"] == "1OD-0TP")
                    $policy_type = "own_damage";
                    else if($value["policy_type"] == "1OD-1TP")
                    $policy_type = "comprehensive";
                    else if($value["policy_type"] == "1OD-3TP")
                    $policy_type = "comprehensive";
                    else if($value["policy_type"] == "3OD-3TP")
                    $policy_type = "comprehensive";
                    // $business_type = '';

                    //$version_id = 'CRPFYN2906213146';

                    $company_details = DB::table('previous_insurer_lists as pil')
                        ->join('master_company as mc', 'mc.company_alias', '=', 'pil.code')
                        //->join('master_policy', 'master_policy.product_sub_type_id', '=', 'master_home_view.product_sub_type_id')
                        ->where('pil.name', trim($value['company_alias']))
                        ->where('pil.company_alias', 'ABIBL_MG_DATA')
                        ->select('mc.company_id','mc.company_name','mc.company_alias')
                        ->first();
                    $company_alias = $company_details->company_alias;
                    $company_name = $company_details->company_name;
                    $company_id = $company_details->company_id;

                    $previous_ncb = (int) $value['previous_ncb'];
                    $applicable_ncb = (int) $value['ncb_percentage'];
                    $idv = (int) $value['sum_assured'];

                    if($value['owner_type'] == 'INDIVIDUAL')
                    {
                       $vehicle_owner_type = 'I';
                       $gender = $value['gender_name'] == 'M' ? 'Male' : 'Female';
                       $gender_name = $value['gender_name'] == 'M' ? 'Male' : 'Female';
                       $dob = ($value["proposer_dob"] != 'NULL') ? Carbon::parse($value["proposer_dob"])->format('d-m-Y') : null;
                       $nominee_name = $value["nominee_name"];
                       $nominee_age = ($value["nominee_age"] != "NULL" && $value["nominee_age"] != "NULL") ? $value["nominee_age"] : null;
                       $nominee_relationship = ($value["nominee_relationship"] != "NULL" && $value["nominee_relationship"] != "NULL") ? $value["nominee_relationship"] : null;
                       $nominee_dob = ($value["nominee_dob"] != "" && $value["nominee_dob"] != "NULL") ? Carbon::parse($value["nominee_dob"])->format('Y-m-d') : null;
                    }
                    else
                    {
                       $vehicle_owner_type = 'C'; 
                       $gender = NULL;
                       $gender_name = NULL;
                       $dob = null;
                       $nominee_name = null;
                       $nominee_age = null;
                       $nominee_relationship = null;
                       $nominee_dob = null;
                    }
                    

                    $vehicle_register_date = ($value['vehicle_registration_date'] != '' && $value['vehicle_registration_date'] != 'NULL') ? $value['vehicle_registration_date'] : $value['invoice_date'];
    //                echo \Carbon\Carbon::parse($vehicle_register_date)->format('d-m-Y');
    //                die;
                    $user_product_journey->corporate_vehicles_quote_request()->create([
                        'version_id'                    => $version_id,
                        'business_type'                 => $business_type,
                        'policy_type'                   => $policy_type,
                        'vehicle_register_date'         => \Carbon\Carbon::parse($vehicle_register_date)->format('d-m-Y'),
                        'previous_policy_expiry_date'   => ($value['previous_policy_expiry_date'] != "NULL" && $value['previous_policy_expiry_date'] != "") ? \Carbon\Carbon::parse($value['previous_policy_expiry_date'])->format('d-m-Y') : null,
                        'vehicle_registration_no'       => ($value['vehicle_registration_number'] != "" && $value['vehicle_registration_number'] != "NULL") ? $value['vehicle_registration_number'] : null,
                        'previous_policy_type'          => 'Comprehensive',//($value['prev_policy_type'] != "" && $value['prev_policy_type'] != "NULL") ? $value['prev_policy_type'] : null,
                        'previous_insurer'              => ($value['previous_insurer'] != "" && $value['previous_insurer'] != "NULL") ? $value['previous_insurer'] : null,
                        'fuel_type'                     => $mmv_details['data']['version']['fuel_type'],
                        //'fuel_type' => ($value['vehicle_fuel_type'] != "" && $value['vehicle_fuel_type'] != "NULL") ? $value['vehicle_fuel_type'] : null,
                        'manufacture_year'              => trim($value['vehicle_manufacture_year']),
                        'rto_code'                      => $rto_code ?? null,
                        'rto_city'                      => $rto_city ?? null,
                        //'vehicle_owner_type' => ($value['owner_type'] != "" && $value['owner_type'] != "NULL") ? Str::upper(Str::substr($value['owner_type'], 0, 1)) : null,
                        'vehicle_owner_type'            => $vehicle_owner_type,
                        'is_claim'                      => $value['ncb_claim'] ?? null,
                        'previous_ncb'                  => $previous_ncb,
                        'applicable_ncb'                => $applicable_ncb,
                        'version_name'                  => $mmv_details['data']['version']['version_name'],
                        'is_renewal'                    => 'N',
                        'previous_insurer'              => $company_name,
                        'previous_insurer_code'         => $company_alias,
                        'insurance_company_id'          => $company_id,
                        'product_id'                    => $product_sub_type_id,
                        'created_on'                    => $transaction_date
                    ]);

                    $addons_list = $value['selected_addons'];
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
                    
                    if((int) $value['cpa_amount'] > 0)
                    {
                        $cpa['name'] = 'Compulsory Personal Accident';                        
                    }
                    else
                    {
                        $cpa['reason'] = 'I have another motor policy with PA owner driver cover in my name';
                    }
                    
                    $user_product_journey->addons()->create([
                        'user_product_journey_id'       => $product_sub_type_id,
                        'addons'                        => ($selected_addons),
                        'applicable_addons'             => ($selected_addons),
                        'compulsory_personal_accident'  => ([$cpa]),
                        'created_at'                    => $transaction_date,
                        'updated_at'                    => $transaction_date
                    ]);

                    $user_product_journey->quote_log()->create([
                        'product_sub_type_id'   => $product_sub_type_id,
                        'ic_id'                 => $company_id,
                        'ic_alias'              => $company_name,
                        'od_premium'            => (int) $value['od_premium'] ?? 0,
                        'tp_premium'            => (int) $value['tp_premium'] ?? 0,
                        'service_tax'           => (int) $value['tax_amount'] ?? 0,
                        'addon_premium'         => (int) $value['addon_premium'] ?? 0,
                        'quote_data'            => "",
                        'idv'                   => $idv,
                        'updated_at'            => $transaction_date,
                        'searched_at'           => $transaction_date
                    ]);
                    $address_line1 = trim(trim($value['address_line_1']).' '.trim($value['address_line_2']).' '.trim($value['address_line_3']));
                    
                    $propsal_data = [
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
                        "pan_number"    => ($value['pan_no'] != "" && $value['pan_no'] != "") ? $value['pan_no'] : null,
                        "address_line1" => $address_line1,//trim(trim($value['address_line_1']).' '.trim($value['address_line_1']).' '.trim($value['address_line_1'])),
                        //"address_line2" => ($value['address_line_2'] != "NULL" && $value['address_line_2'] != "") ? $value['address_line_2'] : null,
                        //"address_line3" => ($value['address_line_3'] != "NULL" && $value['address_line_3'] != "") ? $value['address_line_3'] : null,
                        "pincode"       => $value['pincode'],
                        "state"         => $value['state'],
                        "city"          => $value['city'],
                        "rto_location" => $rto_code,
                        "is_car_registration_address_same" => 1,
                        "car_registration_address1" => $address_line1,//($value['address_line_1'] != "NULL" && $value['address_line_1'] != "")? $value['address_line_1'] : null,
                        //"car_registration_address2" => ($value['address_line_2'] != "NULL" && $value['address_line_2'] != "")? $value['address_line_2'] : null,
                        //"car_registration_address3" => ($value['address_line_3'] != "NULL" && $value['address_line_3'] != "")? $value['address_line_3'] : null,
                        "car_registration_pincode" => $value['pincode'],//($value['pincode'] != "NULL" && $value['pincode'] != "")? $value['pincode'] : null,
                        "car_registration_state" => $value['state'],//($value['state'] != "NULL" && $value['state'] != "")? $value['state'] : null,
                        "car_registration_city" => $value['city'],
                        "vehicale_registration_number" => $value['vehicle_registration_number'],
                        "vehicle_manf_year" => trim($value['vehicle_manufacture_year']),//($value['vehicle_registration_date'] != "" && $value['vehicle_registration_date'] != "NULL") ? Carbon::parse($value['vehicle_registration_date'])->format('m-') . $value['vehicle_manufacture_year'] : null,
                        "engine_number"     => $value['engine_number'],
                        "chassis_number"    => $value['chassis_number'],
                        "is_vehicle_finance" => $value['is_financed'] == "Yes" ? 1 : 0, 
                        "financer_agreement_type" => $value['is_financed'] == "Yes" ? 'Hypothecation' : null, 
                        "name_of_financer" => $value['is_financed'] == "Yes" ? $value['hypothecation_to'] : NULL,// != "NULL" && $value['hypothecation_to'] != "NULL") ? $value['hypothecation_to'] : null,
                        "previous_insurance_company" => $value["previous_insurer"],
                        "previous_policy_number" => ($value["previous_policy_number"] != "NULL" && $value["previous_policy_number"] != "NULL") ? $value["previous_policy_number"] : null,
                        "nominee_name" => $value["nominee_name"],
                        "nominee_age" => ($value["nominee_age"] != "NULL" && $value["nominee_age"] != "NULL") ? $value["nominee_age"] : null,
                        "nominee_relationship" => ($value["nominee_relationship"] != "NULL" && $value["nominee_relationship"] != "NULL") ? $value["nominee_relationship"] : null,
                        "nominee_dob" => ($value["nominee_dob"] != "" && $value["nominee_dob"] != "NULL") ? Carbon::parse($value["nominee_dob"])->format('Y-m-d') : null,
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
                    ];
                    
                    if($vehicle_owner_type == 'I')
                    {
                        $nominee = [
                            'nomineeName'             => $nominee_name,                       
                            'nomineeAge'              => $nominee_age,                      
                            'nomineeRelationship'     => $nominee_relationship,                    
                            'relationship_with_owner' => $nominee_relationship
                        ];
                    }
                    else
                    {                        
                        $nominee = [];                        
                    }
                    
                    if($value['vehicle_registration_number'] != 'NULL' && $value['vehicle_registration_number'] != '')
                    {
                        $full_reg_number = $value['vehicle_registration_number'];
                        $reg = explode('-',$value['vehicle_registration_number']);
                        $reg1 = $reg[0].'-'.$reg[1];
                        $reg2 = $reg[2] ?? NULL;
                        $reg3 = $reg[3] ?? NULL;                        
                    }
                    else
                    {
                        $reg1 = $rto_code;
                        $reg2 = null;
                        $reg3 = null;
                    }
                    
                    $additinal_details = [
                        'owner'     => [
                            "occupation"        => NUll,
                            "maritalStatus"     => NULL,
                            "lastName"          => NULL,
                            "firstName"         => $full_name,
                            "fullName"          => $full_name,
                            "gender"            => $gender,
                            "dob"               => $dob,
                            "mobileNumber"      => $value['proposer_mobile'],
                            "email"             => Str::lower($value['proposer_emailid']),
                            "panNumber"         =>  null,
                            "gstNumber"         =>  null,
                            "stateId"           =>  $value['state'],
                            "cityId"            =>  $value['city'],
                            "state"             =>  $value['state'],
                            "city"              =>  $value['city'],
                            "addressLine1"      =>  $address_line1,
                            "pincode"           =>  $value['pincode'],
                            "officeEmail"       =>  Str::lower($value['proposer_emailid']),
                            "prevOwnerType"     =>  $vehicle_owner_type,
                            "address"           =>  $address_line1,
                            "genderName"        =>  $gender_name,
                            "occupationName"    =>  null                         
                        ],
                        'nominee'   => $nominee,
                        'vehicle'   => [
                            "regNo1"                        => $reg1,                            
                            "regNo2"                        => $reg2,
                            "regNo3"                        => $reg3,
                            "chassisNumber"                 => $value['chassis_number'],
                            "vehicleManfYear"               => trim($value['vehicle_manufacture_year']),
                            "engineNumber"                  => $value['engine_number'],
                            "vehicaleRegistrationNumber"    => $full_reg_number ?? $reg1,
                            "vehicleColor"                  => null,
                            "isValidPuc"                    => true,
                            "isVehicleFinance"              => $value['is_financed'] == "Yes" ? true : false,
                            "isCarRegistrationAddressSame"  => true,
                            "rtoLocation"                   => $reg1,
                            "registrationDate"              => $vehicle_register_date
                            
                        ],
                        'prepolicy' => [                            
                            "previousInsuranceCompany"  => $value["previous_insurer"],
                            "InsuranceCompanyName"      => $value["previous_insurer"],
                            "previousPolicyExpiryDate"  => ($value["previous_policy_expiry_date"] != "NULL" && $value["previous_policy_expiry_date"] != "") ? Carbon::parse($value["previous_policy_expiry_date"])->format('d-m-Y') : null, 
                            "previousPolicyNumber"      => $value["previous_policy_number"],
                            "isClaim"                   => $value['ncb_claim'] ?? "N",
                            "applicableNcb"             => $applicable_ncb,
                            "previousNcb"               => $previous_ncb,
                            "prevPolicyExpiryDate"      => ($value["previous_policy_expiry_date"] != "NULL" && $value["previous_policy_expiry_date"] != "") ? Carbon::parse($value["previous_policy_expiry_date"])->format('d-m-Y') : null,                           
                        ]                       
                    ];
                    $propsal_data['additional_details'] = json_encode($additinal_details);
                    
                    $proposal = $user_product_journey->user_proposal()->create($propsal_data);
                    
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
                        'proposal_no'         => $proposal_no,
                        'policy_no'                 => $policy_number,
                        'source'                    => 'ABIBL_MG_DATA',
                        'policy_status'             => $value['transaction_stage'],
                        'status'                    => 1,
                        'comments'                  => 'Data Inserted Sucessfully',
                        'transaction_date'          => $transaction_date,
                        'file_name'         => $file
                    ];
                    DB::table('migration_data_reports')->insert($data_status_data);
                } 
                }
                else
                {
                   $data_status_data = [
                        'vehicle_reg_no'    => $value['vehicle_registration_number'],
                        'vehicle_code'      => $version_id,
                        'policy_no'         => $policy_number,
                        'source'            => 'ABIBL_MG_DATA',
                        'manf'              => $value['vehicle_make'],
                        'model'             => $value['vehicle_model'],
                        'version'           => $value['vehicle_version'],
                        'comments'          => 'Vehicle Code Not Found In Fyntune Version Master',
                        'created_at'      => date('Y-m-d H:i:s')
                    ];
                    DB::table('not_mapped_vehicle')->insert($data_status_data);
                }                
            }
            else
            {
               $data_status_data = [
                    'vehicle_reg_no'    => $value['vehicle_registration_number'],
                    'policy_no'         => $policy_number,
                    'source'            => 'ABIBL_MG_DATA',
                    'manf'              => $value['vehicle_make'],
                    'model'             => $value['vehicle_model'],
                    'version'           => $value['vehicle_version'],
                    'comments'          => 'Vehicle Not Found In MG Mapping',
                    'created_at'      => date('Y-m-d H:i:s')
                ];
                DB::table('not_mapped_vehicle')->insert($data_status_data);
            }
            
        }
        Storage::delete($file);
        return [];
    }
}
