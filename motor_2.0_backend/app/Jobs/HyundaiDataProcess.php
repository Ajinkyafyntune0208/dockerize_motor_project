<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Models\UserProductJourney;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\PolicyDetails;
use App\Models\UserProposal;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 1800);

class HyundaiDataProcess implements ShouldQueue
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
        # $allfiles = Storage::allFiles('hyundai_data_process');
        $allfiles = scandir('/mnt/hyundai_data_process');

        if (count($allfiles) == 0)
        return;
	$fileProcessedCount = 0;
	foreach($allfiles as $file)
	{
		$fileProcessedCount++;

		if(  $fileProcessedCount > 25 )
		{
			return;
		}

	    if( $file == "." || $file == ".." )
	    {
		continue;
	    }
	    $onlyFileName = $file;
	    $file = "/mnt/hyundai_data_process/".$file;
	    $movedFileName =  "/mnt/hyundai_data_upload/".$onlyFileName;
	
	    if( !file_exists(  $file ) )
	    {
		    continue;
	    }
            # $data = json_decode(Storage::disk()->get($file), true); 

	    try
	    {
	    	$data = json_decode( file_get_contents( $file ), true ); 
	    }
	    catch( Exception $e )
	    {
		    continue;
	    }
            # print_r( $data  ); die;

            $title_array = ['Mr.','Ms.','Mrs.','M/s','Dr.','MR.','MS.','MRS.','M/S','DR.'];
            $source = 'HYUNDAI';
            foreach ($data as $key => $value) 
            {
                $value = array_change_key_case_recursive($value);
                $value = str_replace("`", "", $value);
                $value = array_map('trim', $value);
                $title = '';
                $DEALER_CODE_LIST = explode(',',config('REMOVE_DATA_OF_DEALER_CODE_LIST'));
                if(in_array($value['dealercode'], $DEALER_CODE_LIST))
                {
                    continue;
                }
                $full_name = trim($value['insuredname']);
                foreach ($title_array as $t) 
                {
                    if (str_starts_with($value['insuredname'], $t)) 
                    {
                        $title = $t;
                        $full_name = trim(str_replace($t,'',$value['insuredname']));
                        break;
                    }
                }            
                $proposal_no = $policy_number = $value['policyno'];
                $product_sub_type_id = 1;
                $version_id = \Illuminate\Support\Facades\DB::table('hyundai_vehicle_mapping')
                                        ->where('VARIANT_ID', trim($value['variantcode']))
                                        ->get()
                                        ->toArray();
                if(count($version_id) == 0)
                {
                    $data_status_data = [
                        'vehicle_reg_no'    => $value['registrationno'],
                        'policy_no'         => $policy_number,
                        'source'            => $source,
                        'manf'              => $value['make'],
                        'model'             => $value['model'],
                        'version'           => $value['vehicle_version'] ?? NULL,
                        'version_code'      => $value['variantcode'],
                        'comments'          => 'Vehicle Not Found In Hyundai Mapping',
                        'created_at'        => date('Y-m-d H:i:s')
                    ];
                    DB::table('hyundai_not_mapped_vehicle')->insert($data_status_data);
                }

                if(isset($version_id))
                {
                    $version_id = json_decode(json_encode($version_id),true);
                    $version_id = $version_id[0]['FTS_CODES'] ?? NULL;
                    $mmv_details = get_fyntune_mmv_details(1,$version_id);
                    if($mmv_details)
                    {
                        if(!$mmv_details['status'])
                        {
                            $data_status_data = [
                                'vehicle_reg_no'    => $value['registrationno'],
                                'vehicle_code'      => $version_id,
                                'policy_no'         => $policy_number,
                                'source'            => $source,
                                'manf'              => $value['make'],
                                'model'             => $value['model'],
                                'version'           => $value['vehicle_version'] ?? NULL,
                                'version_code'      => $value['variantcode'],
                                'comments'          => 'Vehicle Code Not Found In Fyntune Version Master',
                                'created_at'        => date('Y-m-d H:i:s')
                            ];
                            DB::table('hyundai_not_mapped_vehicle')->insert($data_status_data);
                        }
                        $transaction_date = \Carbon\Carbon::parse($value['businessdate'])->format('Y-m-d H:i:s');                    
                        $check_data = UserProposal::where('proposal_no', $proposal_no)->count();
                        if($check_data == 0)			
                        {                
                            $user_product_journey = UserProductJourney::create([
                                'user_fname'            => $full_name,
                                'user_lname'            => null,
                                'user_mname'            => null,
                                'user_email'            => Str::lower($value['clientconemail']),
                                'user_mobile'           => $value['clientconmobile'],
                                'product_sub_type_id'   => $product_sub_type_id,
                                'created_on'            => $transaction_date,
                                'lead_source'           => $source
                            ]);
                            //dd($user_product_journey);
                            //$value['registrationno'] = 'DL-1d_5455';
                            $rto_code = null;
                            if($value['registrationno'] != null)
                            {
                                $value['registrationno'] = getRegisterNumberWithHyphen(str_replace('-', '', $value['registrationno']));
                              
                                if(strpos($value['registrationno'],'DL-1') !== false)
                                {
                                    $rto_code = 'DL-01'; 
                                } 
                                else if(strpos($value['registrationno'],'DL-2') !== false)
                                {
                                    $rto_code = 'DL-02'; 
                                } 
                                else if(strpos($value['registrationno'],'DL-3') !== false)
                                {
                                    $rto_code = 'DL-03'; 
                                } 
                                else if(strpos($value['registrationno'],'DL-4') !== false)
                                {
                                    $rto_code = 'DL-04'; 
                                } 
                                else if(strpos($value['registrationno'],'DL-5') !== false)
                                {
                                    $rto_code = 'DL-05'; 
                                } 
                                else if(strpos($value['registrationno'],'DL-6') !== false)
                                {
                                    $rto_code = 'DL-06'; 
                                } 
                                else if(strpos($value['registrationno'],'DL-7') !== false)
                                {
                                    $rto_code = 'DL-07'; 
                                } 
                                else if(strpos($value['registrationno'],'DL-8') !== false)
                                {
                                    $rto_code = 'DL-08'; 
                                } 
                                else if(strpos($value['registrationno'],'DL-9') !== false)
                                {
                                    $rto_code = 'DL-09'; 
                                } 
                                else
                                {
                                $rto_code = explode('-', $value['registrationno'])[0] . '-' . explode('-', $value['registrationno'])[1]; 
                            }                         
                            }
			    $city = $value['cityname'];
                            if($rto_code == null)
                            {
                                $city = $value['cityname'];

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
                                $rto_data = \Illuminate\Support\Facades\DB::table('rto_abibl')->where('city_name', $city)->first();
                                $rto_code = $rto_data->rto_code ?? null;
                            }
                            if(empty($rto_data))
                            {
                                $section = 4;
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
                            $rto_city = $rto_data->rto_name ?? $city;
                            if(empty($rto_data))
                            {
                                $section = 5;
                                $rto_data = \Illuminate\Support\Facades\DB::table('master_default_rto_abibl')->where('state_name', $value['statecode'])->first();
                                $rto_code = $rto_data->rto_code ?? null;
                                $rto_not_found_data = [
                                    'reg_number'    => $value['registrationno'],
                                    'city'          => $city,
                                    'state'         => $value['statecode'],
                                    'pincode'       => $value['pincode'],
                                    'section'       => $section,
                                    'source'        => $source,                            
                                    'created_at'    => date('Y-m-d H:i:s')
                                ];
                                DB::table('hyundai_rto_not_found')->insert($rto_not_found_data);
                            }
                            if(empty($rto_data))
                            {
                                $rto_not_found_data = [
                                    'reg_number'    => $value['registrationno'],
                                    'city'          => $city,
                                    'state'         => $value['statecode'],
                                    'pincode'       => $value['pincode'],
                                    'section'       => $section,
                                    'source'        => $source,                            
                                    'created_at'    => date('Y-m-d H:i:s')
                                ];
                                DB::table('hyundai_rto_not_found')->insert($rto_not_found_data);                        
                            }                    
                            $policy_type = $is_renewal = $business_type = null;

                            $business_type = 'rollover';           
                            if($value['policystatus'] == 'NEW')
                            {
                               $business_type = 'newbusiness';
                            }

                            if($value["plantype"] == "1OD-0TP")
                            $policy_type = "own_damage";
                            else if($value["plantype"] == "1OD-1TP")
                            $policy_type = "comprehensive";
                            else if($value["plantype"] == "1OD-3TP")
                            $policy_type = "comprehensive";
                            else if($value["plantype"] == "3OD-3TP")
                            $policy_type = "comprehensive";
                            else if($value["plantype"] == "0OD-1TP")
                            $policy_type = "comprehensive";

                            $company_details = DB::table('previous_insurer_lists as pil')
                                ->join('master_company as mc', 'mc.company_alias', '=', 'pil.code')
                                //->join('master_policy', 'master_policy.product_sub_type_id', '=', 'master_home_view.product_sub_type_id')
                                ->where('pil.name', trim($value['currentyearicname']))
                                ->where('pil.company_alias', $source)
                                ->select('mc.company_id','mc.company_name','mc.company_alias')
                                ->first();
                            $company_alias = $company_details->company_alias;
                            $company_name = $company_details->company_name;
                            $company_id = $company_details->company_id;

                            //previous
                            //$value['previnsurcompanyname'] = 'Bajaj Allianz General Insurance Co. Ltd.';                    
                            $previous_company_details = DB::table('previous_insurer_lists as pil')
                                ->join('master_company as mc', 'mc.company_alias', '=', 'pil.code')
                                //->join('master_policy', 'master_policy.product_sub_type_id', '=', 'master_home_view.product_sub_type_id')
                                ->where('pil.name', trim($value['previnsurcompanyname']))
                                ->where('pil.company_alias', $source)
                                ->select('mc.company_id','mc.company_name','mc.company_alias')
                                ->first();

                            if(empty($company_details) && $value['currentyearicname'] != NULL)
                            { 
                                $data_status_data = [
                                    'name'              => $value['currentyearicname'],
                                    'company_alias'     => 'HYUNDAI'
                                ];
                                $is_exits = DB::table('previous_insurer_lists')->where($data_status_data)->exists();
                                if(!$is_exits)
                                {
                                   DB::table('previous_insurer_lists')->insert($data_status_data);  
                                }                                               
                            }
                            if(empty($previous_company_details) && $value['previnsurcompanyname'] != NULL)
                            { 
                                $data_status_data = [
                                    'name'              => $value['previnsurcompanyname'],
                                    'company_alias'     => 'HYUNDAI'
                                ];
                                $is_exits = DB::table('previous_insurer_lists')->where($data_status_data)->exists();
                                if(!$is_exits)
                                {
                                   DB::table('previous_insurer_lists')->insert($data_status_data);  
                                }                                               
                            }
                            $previous_company_alias = $previous_company_name = $previous_company_id = NULL;
                            if(!empty($previous_company_details))
                            {
                                $previous_company_alias = $previous_company_details->company_alias;
                                $previous_company_name  = $previous_company_details->company_name;
                                $previous_company_id    = $previous_company_details->company_id;                     
                            }

                            $applicable_ncb = $previous_ncb = (int) $value['ncbpercent'];
                            $idv = (int) $value['totalidv'];

                            $gender_name = $gender = NULL;
                            if($value['gender'] == 'M')
                            {
                              $gender_name = $gender = 'Male';  
                            }
                            else if($value['gender'] == 'F')
                            {
                               $gender_name = $gender = 'Female';  
                            }

                            if($value['clienttype'] == 'INDIVIDUAL')
                            {
                               $vehicle_owner_type = 'I';
                               $dob = $value["dateofbirth"] != NULL ? Carbon::parse($value["dateofbirth"])->format('d-m-Y') : null;
                               $nominee_name = $value["paowndrivernomname"];
                               $nominee_age = $value["paowndrivernomage"];
                               $nominee_relationship = $value["paowndrivernomreleation"];
                               //$nominee_dob = ($value["paowndrivernomage"] != "" && $value["paowndrivernomage"] != "NULL") ? Carbon::parse($value["paowndrivernomage"])->format('Y-m-d') : null;
                               $nominee_dob = NULL;
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

                            $vehicle_register_date = $value['vehicleinvoicedate'];
                            $manufacture_year = trim('01-'.trim($value['yom']));
                            $user_product_journey->corporate_vehicles_quote_request()->create([
                                'version_id'                    => $version_id,
                                'business_type'                 => $business_type,
                                'policy_type'                   => $policy_type,
                                'vehicle_register_date'         => \Carbon\Carbon::parse($vehicle_register_date)->format('d-m-Y'),
                                'previous_policy_expiry_date'   => ($value['prevpolicyexpirydate'] != NULL ) ? \Carbon\Carbon::parse($value['prevpolicyexpirydate'])->format('d-m-Y') : null,
                                'vehicle_registration_no'       => ($value['registrationno'] != "" && $value['registrationno'] != "NULL") ? $value['registrationno'] : null,
                                'previous_policy_type'          => 'Comprehensive',//($value['prev_policy_type'] != "" && $value['prev_policy_type'] != "NULL") ? $value['prev_policy_type'] : null,
                                //'previous_insurer'              => ($value['previous_insurer'] != "" && $value['previous_insurer'] != "NULL") ? $value['previous_insurer'] : null,
                                'fuel_type'                     => $mmv_details['data']['version']['fuel_type'] ?? NULL,
                                //'fuel_type' => ($value['vehicle_fuel_type'] != "" && $value['vehicle_fuel_type'] != "NULL") ? $value['vehicle_fuel_type'] : null,
                                'manufacture_year'              => $manufacture_year,
                                'rto_code'                      => $rto_code ?? null,
                                'rto_city'                      => $rto_city ?? null,
                                'vehicle_owner_type'            => $vehicle_owner_type,
                                'is_claim'                      => $value['total_noofod_claims'] > 0 ? 'Y' : 'N',
                                'previous_ncb'                  => $previous_ncb,
                                'applicable_ncb'                => $applicable_ncb,
                                'version_name'                  => $mmv_details['data']['version']['version_name'] ?? NULL,
                                'is_renewal'                    => 'N',
                                'previous_insurer'              => $previous_company_name,
                                'previous_insurer_code'         => $previous_company_alias,
                                'insurance_company_id'          => $previous_company_id,
                                'product_id'                    => $product_sub_type_id,
                                'created_on'                    => $transaction_date
                            ]);

                            $selected_addons = [];
                            $true = 1;
                            if($value['addonisnildep'] == $true)
                            {
                               $selected_addons[]['name'] = 'Zero Depreciation'; 
                            }                    
                            if($value['addonisrsa'] == $true)
                            {
                               $selected_addons[]['name'] = 'Road Side Assistance'; 
                            }                    
                            if($value['addonisrti'] == $true)
                            {
                               $selected_addons[]['name'] = 'Return To Invoice'; 
                            } 
                            if($value['addonisncbprot'] == $true)
                            {
                               $selected_addons[]['name'] = 'NCB Protection'; 
                            }
                            if($value['addonisconsumable'] == $true)
                            {
                               $selected_addons[]['name'] = 'Consumable'; 
                            }
                            if($value['addonisengineprot'] == $true)
                            {
                               $selected_addons[]['name'] = 'Engine Protector'; 
                            }    
                            if($value['addonistyrecover'] == $true)
                            {
                               $selected_addons[]['name'] = 'Tyre Secure'; 
                            }
                            if($value['addoniskeyreplacement'] == $true)
                            {
                               $selected_addons[]['name'] = 'Key Replacement'; 
                            }
                            if($value['addonisemergencymedicalexpense'] == $true)
                            {
                               $selected_addons[]['name'] = 'Emergency Medical Expenses'; 
                            }

                            $accessories = [];
                            $acc_addons = [];
                            if($value['electricaccidv'] > 0)
                            {                       
                               $acc_addons['name'] = 'Electrical Accessories';  
                               $acc_addons['sumInsured'] = $value['electricaccidv'];
                               $accessories[] = $acc_addons;
                            }
                            if($value['nonelectricaccidv'] > 0)
                            {                       
                               $acc_addons['name'] = 'Non-Electrical Accessories';  
                               $acc_addons['sumInsured'] = $value['nonelectricaccidv']; 
                               $accessories[] = $acc_addons;
                            }
                            if($value['bifuelkitidv'] > 0)
                            {                       
                               $acc_addons['name'] = 'External Bi-Fuel Kit CNG/LPG';  
                               $acc_addons['sumInsured'] = $value['bifuelkitidv']; 
                               $accessories[] = $acc_addons;
                            }

                            $additionalCovers = [];

                            if($value['ispapaiddriver'] == $true)
                            {
                                $acc_addons['name'] = 'PA cover for additional paid driver';
                                $acc_addons['sumInsured'] = 0;
                                if($value['noofpaiddriverpa'] > 0)
                                {
                                    $acc_addons['sumInsured'] = 100000;
                                }                        
                                $additionalCovers[] = $acc_addons;
                            }
                            if($value['pasuminsuredperunnamedperson'] > 0)
                            {
                                $acc_addons['name'] = 'Unnamed Passenger PA Cover';  
                                $acc_addons['sumInsured'] = $value['pasuminsuredperunnamedperson']; 
                                $additionalCovers[] = $acc_addons;
                            }
                            if($value['isllpaiddriver'] == $true)
                            {
                                $acc_addons['name'] = 'LL paid driver';  
                                $acc_addons['sumInsured'] = 100000;
                                $additionalCovers[] = $acc_addons;
                            }

                            $discounts = [];
                            $acc_addons = [];
                            if($value['isantitheftattached'] == $true)
                            {
                                $acc_addons['name'] = 'anti-theft device'; 
                                $discounts[] = $acc_addons;
                            }

                            if($value['voluntarydeductible'] == $true)
                            {
                                $acc_addons['name'] = 'voluntary_insurer_discounts'; 
                                $acc_addons['sumInsured'] = $value['voluntarydeductible'];
                                $discounts[] = $acc_addons;
                            }
                            if((int) $value['totalcpapremium'] > 0)
                            {
                                $cpa['name'] = 'Compulsory Personal Accident';                        
                            }
                            else
                            {
                                $cpa['reason'] = 'I have another motor policy with PA owner driver cover in my name';
                            }

                            $user_product_journey->addons()->create([
                                'user_product_journey_id'       => $product_sub_type_id,
                                'addons'                        => count($selected_addons) > 0 ? $selected_addons : NULL,
                                'accessories'                   => count($accessories) > 0 ? $accessories : NULL,
                                'applicable_addons'             => count($selected_addons) > 0 ? $selected_addons : NULL,
                                'additional_covers'             => count($additionalCovers) > 0 ? $additionalCovers : NULL,
                                'compulsory_personal_accident'  => ([$cpa]),
                                'discounts'                     => count($discounts)  > 0 ? $discounts : NULL,
                                'created_at'                    => $transaction_date,
                                'updated_at'                    => $transaction_date
                            ]);

                            $user_product_journey->quote_log()->create([
                                'product_sub_type_id'   => $product_sub_type_id,
                                'ic_id'                 => $company_id,
                                'ic_alias'              => $company_name,
                                'od_premium'            => (int) $value['odpremiumamount'] ?? 0,
                                'tp_premium'            => (int) $value['totalnetpremium'] ?? 0,
                                'service_tax'           => (int) ($value['totalgrosspremium'] - $value['totalgrosspremium']),
                                'addon_premium'         => (int) ($value['addon_premium'] ?? 0),
                                'quote_data'            => "",
                                'idv'                   => $idv,
                                'updated_at'            => $transaction_date,
                                'searched_at'           => $transaction_date
                            ]);
        //                    print_r($value);
        //                    die;
                            $address_line1 = trim(trim($value['insaddrln1']).' '.trim($value['insaddrln2']));
                            $propsal_data = [
                                'title'                             => $title,
                                'first_name'                        => $full_name,
                                'email'                             => Str::lower($value['clientconemail']),
                                'office_email'                      => Str::lower($value['clientconemail']),
                                'mobile_number'                     => $value['clientconmobile'],
        //                        'marital_status'                  => "Married",
                                'dob'                               => $dob,
                                'gender'                            => $gender,
                                'gender_name'                       => $gender_name,
                                //'occupation'                      => "Business / Professional Services",
                                //"occupation_name"                 => "Business / Professional Services",
                                "pan_number"                        => $value['proposerpan'],
                                "address_line1"                     => $address_line1,
                                "pincode"                           => $value['pincode'],
                                "state"                             => $value['statecode'],
                                "city"                              => $value['cityname'],
                                "rto_location"                      => $rto_code,
                                "is_car_registration_address_same"  => 1,
                                "car_registration_address1"         => $address_line1,
                                "car_registration_pincode"          => $value['pincode'],
                                "car_registration_state"            => $value['statecode'],
                                "car_registration_city"             => $value['cityname'],
                                "vehicale_registration_number"      => $value['registrationno'],
                                "vehicle_manf_year"                 => $manufacture_year,
                                "engine_number"                     => $value['engineno'],
                                "chassis_number"                    => $value['chassisno'],
                                "is_vehicle_finance"                => $value['aggrementtype'] != NULL ? 1 : 0, 
                                "financer_agreement_type"           => $value['aggrementtype'],
                                "name_of_financer"                  => NULL,
                                "financer_location"                 => $value['financerbranch'],
                                "previous_insurance_company"        => $previous_company_name,
                                "previous_policy_number"            => $value["previouspolicyno"],
                                "nominee_name"                      => $nominee_name,
                                "nominee_age"                       => $nominee_age,
                                "nominee_relationship"              => $nominee_relationship,
                                "nominee_dob"                       => $nominee_dob,
                                "proposal_date"                     => Carbon::parse($value["proposaldate"])->format('Y-m-d H:i:s'),
                                "policy_start_date"                 => Carbon::parse($value["policyeffectivedate"])->format('d-m-Y'),
                                "policy_end_date"                   => Carbon::parse($value["policyexpirydate"])->format('d-m-Y'),
                                "tp_start_date"                     => Carbon::parse($value["tppolicyeffectivetime"])->format('d-m-Y'),
                                "tp_end_date"                       => Carbon::parse($value["tppolicyenddate"])->format('d-m-Y'),
                                "tp_insurance_company"              => $previous_company_name,
                                "tp_insurance_company_name"         => $previous_company_name,
                                "tp_insurance_number"               => $value["previouspolicyno"],
                                "prev_policy_expiry_date"           => $value['prevpolicyexpirydate'] != NULL ? \Carbon\Carbon::parse($value['prevpolicyexpirydate'])->format('d-m-Y') : null,
                                "prev_policy_start_date"            => $value['prevpolicyeffectivedate'] != NULL ? \Carbon\Carbon::parse($value['prevpolicyeffectivedate'])->format('d-m-Y') : null,
                                "proposal_no"                       => $proposal_no,
                                "od_premium"                        => $value["odpremiumamount"],
                                "tp_premium"                        => $value["tppremiumamount"],
                                "addon_premium"                     => 0,
                                "ncb_discount"                      => 0,
                                "total_premium"                     => $value["totalnetpremium"],
                                "service_tax_amount"                => $value["totalgrosspremium"] - $value["totalnetpremium"],
                                "final_payable_amount"              => $value["totalgrosspremium"],
                                "total_discount"                    => 0,
                                "owner_type"                        => $vehicle_owner_type,
                                "insurance_company_name"            => $previous_company_name,
                                "ic_name"                           => $company_name,
                                'ic_id'                             => $company_id,
                                'idv'                               => $idv,
                                "cpa_start_date"                    => null,
                                "cpa_end_date"                      => null,
                                "previous_ncb"                      => $previous_ncb,
                                "applicable_ncb"                    => $applicable_ncb,
                                'created_date'                      => $transaction_date,
                                'proposal_date'                     => $transaction_date
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

                            if($value['registrationno'] != NULL)
                            {
                                $full_reg_number = $value['registrationno'];
                                $reg = explode('-',$value['registrationno']);
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
                                    "mobileNumber"      => $value['clientconmobile'],
                                    "email"             => Str::lower($value['clientconemail']),
                                    "panNumber"         =>  null,
                                    "gstNumber"         =>  null,
                                    "stateId"           =>  $value['statecode'],
                                    "cityId"            =>  $value['cityname'],
                                    "state"             =>  $value['statecode'],
                                    "city"              =>  $value['cityname'],
                                    "addressLine1"      =>  $address_line1,
                                    "pincode"           =>  $value['pincode'],
                                    "officeEmail"       =>  Str::lower($value['clientconemail']),
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
                                    "chassisNumber"                 => $value['chassisno'],
                                    "vehicleManfYear"               => $manufacture_year,
                                    "engineNumber"                  => $value['engineno'],
                                    "vehicaleRegistrationNumber"    => $full_reg_number ?? $reg1,
                                    "vehicleColor"                  => null,
                                    "isValidPuc"                    => true,
                                    "isVehicleFinance"              => $value['aggrementtype'] != NULL ? true : false,
                                    "isCarRegistrationAddressSame"  => true,
                                    "rtoLocation"                   => $reg1,
                                    "registrationDate"              => $vehicle_register_date

                                ],
                                'prepolicy' => [                            
                                    "previousInsuranceCompany"  => $previous_company_name,
                                    "InsuranceCompanyName"      => $previous_company_name,
                                    "previousPolicyExpiryDate"  => $value['prevpolicyexpirydate'] != NULL ? \Carbon\Carbon::parse($value['prevpolicyexpirydate'])->format('d-m-Y') : null,
                                    "previousPolicyNumber"      => $value["previouspolicyno"],
                                    "isClaim"                   => $value['total_noofod_claims'] > 0 ? 'Y' : 'N',
                                    "applicableNcb"             => $applicable_ncb,
                                    "previousNcb"               => $previous_ncb,
                                    "prevPolicyExpiryDate"      => $value['prevpolicyexpirydate'] != NULL ? \Carbon\Carbon::parse($value['prevpolicyexpirydate'])->format('d-m-Y') : null,
                                ]                       
                            ];
                            $propsal_data['additional_details'] = json_encode($additinal_details);

                            $proposal = $user_product_journey->user_proposal()->create($propsal_data);

                            //print_r($proposal->user_proposal_id);
                            $user_product_journey->journey_stage()->create([
                                'ic_id'         => $company_id,
                                'proposal_id'   => $proposal->user_proposal_id,
                                'stage'         => STAGE_NAMES['POLICY_ISSUED'],
                                'created_at'    => $transaction_date,
                                'updated_at'    => $transaction_date
                            ]);

                            $user_product_journey->payment_response()->create([
                                'ic_id'             => $company_id,
                                'user_proposal_id'  => $proposal->user_proposal_id,
                                'proposal_no'       => $proposal_no,
                                'order_id'          => $proposal_no,
                                'active'            => 1,
                                'created_at'        => $transaction_date,
                                'updated_at'        => $transaction_date
                            ]);

                            $proposal->policy_details()->create([
                                'policy_number' => $policy_number,
                                'status'        => 'SUCCESS',
                                'created_on'    => $transaction_date
                            ]);
                            
                            $dealer_details = [
                                'user_product_journey_id'       =>  $user_product_journey->user_product_journey_id,
                                'dealercode'                    =>  $value['dealercode'],
                                'dealermastercode'              =>  $value['dealermastercode'],
                                'dealermastername'              =>  $value['dealermastername'],
                                'dealermasterlocation'          =>  $value['dealermasterlocation'],
                                'dealermastercategory'          =>  $value['dealermastercategory'],
                                'dealermasterzone'              =>  $value['dealermasterzone'],
                                'dealermasterregion'            =>  $value['dealermasterregion'],
                                'dealermasterstate'             =>  $value['dealermasterstate'],
                                'dealermasterspocname'          =>  $value['dealermasterspocname'],
                                'dealermasterspocemail'         =>  $value['dealermasterspocemail'],
                                'dealermasterrmname'            =>  $value['dealermasterrmname'],
                                'dealermasteremailid'           =>  $value['dealermasteremailid'],
                                'dealermasterbranchname'        =>  $value['dealermasterbranchname'],
                                'dealermasterheadname'          =>  $value['dealermasterheadname'],
                                'dealermasterbranchheadid'      =>  $value['dealermasterbranchheadid'],
                                'dealermasterbranchheademail'   =>  $value['dealermasterbranchheademail'],
                                'dealermasteremployeename'      =>  $value['dealermasteremployeename'],
                                'dealermasterzoneheadid'        =>  $value['dealermasterzoneheadid'],
                                'dealermasterzoneheademail'     =>  $value['dealermasterzoneheademail'],
                                'oem_id'                        =>  $value['oem_id'],
                                'suboem_id'                     =>  $value['suboem_id'],
                                'dealername'                    =>  $value['dealername'],
                                'dealerlocation'                =>  $value['dealerlocation'],
                                'created_at'                    =>  date('Y-m-d H:i:s'),
                                'updated_at'                    =>  date('Y-m-d H:i:s')
                            ];
                            DB::table('master_dealer_mapping')->insert($dealer_details);

                            $data_status_data = [
                                'user_product_journey_id'   => $user_product_journey->user_product_journey_id,
                                'vehicle_reg_no'            => $value['registrationno'],
                                'proposal_no'               => $proposal_no,
                                'policy_no'                 => $policy_number,
                                'source'                    => $source,
                                'policy_status'             => STAGE_NAMES['POLICY_ISSUED'],
                                'status'                    => 1,
                                'comments'                  => 'Data Inserted Sucessfully',
                                'transaction_date'          => $transaction_date,
                                'file_name'                 => $file,
                                'json_data'                 => json_encode($value)
                            ];
                            DB::table('hyundai_migration_data_reports')->insert($data_status_data);
                        } 
                    }
                    else
                    {
                       $data_status_data = [
                            'vehicle_reg_no'    => $value['registrationno'],
                            'vehicle_code'      => $version_id,
                            'policy_no'         => $policy_number,
                            'source'            => $source,
                            'manf'              => $value['make'],
                            'model'             => $value['model'],
                            'version'           => $value['vehicle_version'] ?? NULL,
                            'version_code'      => $value['variantcode'],
                            'comments'          => 'Vehicle Code Not Found In Fyntune Version Master',
                            'created_at'        => date('Y-m-d H:i:s')
                        ];
                        DB::table('hyundai_not_mapped_vehicle')->insert($data_status_data);
                        unset($data_status_data);
                    }                
		} 
		# echo "Processed";
		# die;
            }
	    unset($data);
	    try
	    {
		if( file_exists(  $file ) )
                {
                    copy ( $file, $movedFileName );
                    unlink( $file );
                }
	    }
	    catch( Exception $e )
            {
                    continue;
            }

            # $move_file_name = explode('/',$file)[1];        
            # Storage::move($file, 'hyundai_data_upload/'.$move_file_name);    
        }
       // $data = \Maatwebsite\Excel\Facades\Excel::toArray(new \App\Imports\UspImport, $file)[0];        
        return [];
    }
}