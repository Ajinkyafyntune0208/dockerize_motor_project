<?php

namespace App\Http\Controllers\Lte\Admin;

use App\Http\Controllers\CommonController;
use App\Http\Controllers\Controller;
use App\Imports\RenewalUploadImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RenewalUploadExcelController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('upload_data.list')) {
            return response('unauthorized action', 401);
        }

        return view('admin_lte.renewal_data_upload.index');
    }

    public function uploadRenewalExcel(Request $request)
    {   
        if (!auth()->user()->can('upload_data.list')) {
            return response('unauthorized action', 401);
        }

        $validator = Validator::make($request->all(), [
            'renewal_excel' => 'required|mimes:xls,xlsx',
            'have_dashboard' => 'nullable',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        try {
        
            $data = Excel::toArray(new RenewalUploadImport, $request->file('renewal_excel'));

            // Function to convert Excel date value to a standard date format
            $checkdatecolumn = ['date','dates','dob'];
            // Iterate through the data and convert Excel date cells
            foreach ($data[0] as &$row) {
                foreach ($row as $key => &$cell) {
                    // To Check if the cell contains an Excel date value
                    foreach ($checkdatecolumn as $datecolumn) {
                        if(in_array($datecolumn,explode('_',$key)) ){
                            if (is_numeric($cell)) {
                                $cell = Self::excelToStandardDate($cell);
                            }
                        }
                    }
                }
            }
            // return response()->json(["policies" => $data[0][0]]);
            $collectheading = [];
            foreach ($data[0][0] as $headkey => $value){
                $collectheading[]=$headkey;
            }    
            // return response()->json($collectheading);
        
            $allData = [];
            $i = 1;
            foreach ($data[0] as $policy) {
                $temp = [];
                $temp["policy_row_no"] = $i;
                $temp["product_name"] = $policy['product_name_carbike'];
                $temp["vehicle_registration_number"] = $policy['vehicle_reg_no'] ?? null;
                $temp["rto_code"] = $policy['rto_code'] ?? null ;
                $temp["engine_no"] = $policy['engine_no'] ?? null ;
                $temp["chassis_no"] = $policy['chassis_no'] ?? null ;
                $temp["registration_date"] = $policy['registration_date'] ?? null ;
                $temp["manufacturer_date"] = $policy['manufacturer_date'] ?? null ;

                $temp["vehicle_manufacturer"] = $policy['vehicle_manufacturer'] ?? null ;
                $temp["vehicle_model"] = $policy['vehicle_model'] ?? null ;

                $temp["vehicle_colour"] = $policy['vehicle_colour'] ?? null ;
                $temp["vehicle_variant"] = $policy['vehicle_variant'] ?? null ;
                $temp["vehicle_uses_type_cv"] = $policy['vehicle_usage_type_cv'] ?? null ;
                $temp["vehicle_carrier_type"] = $policy['vehicle_carrier_type_public_private'] ?? null ;

                $temp["insurer_vehicle_model_code"] = $policy['insurer_vehicle_model_code'] ?? null ;
                $temp["fyntune_version_id"] = $policy['fyntune_version_id'] ?? null ;

                $temp["prev_policy_type"] = $policy['previous_policy_type_comprehensivesatpsaod_bundle_13_15_package_33_55'] ?? null ;
                $temp["previous_insurer_company"] = $policy['previous_insurer_company_comprehensive_od'] ?? null ;
                $temp["previous_policy_number"] = $policy['previous_policy_number_comprehensive_od'] ?? null ;
                $temp["previous_policy_start_date"] = $policy['previous_policy_start_date_comprehensive_od'] ?? null ;
                $temp["previous_policy_end_date"] = $policy['previous_policy_end_date_comprehensive_od'] ?? null ;

                $temp["previous_tp_insurer_company"] = $policy['previous_tp_insurer_company'] ?? null ;
                $temp["previous_tp_policy_number"] = $policy['previous_tp_policy_number'] ?? null ;
                $temp["previous_tp_start_date"] = $policy['previous_tp_start_date'] ?? null ;
                $temp["previous_tp_end_date"] = $policy['previous_tp_end_date'] ?? null ;

                $temp["previous_ncb"] = $policy['previous_ncb'] ?? null ;
                $temp["previous_claim_status"] = $policy['previous_claim_status'] ?? null ;
                $temp["previous_claim_amount"] = $policy['previous_claim_amount'] ?? null ;
                $temp["no_of_claims"] = $policy['no_of_claims'] ?? null ;

                $temp["cpa_opt-out_reason"] = $policy['cpa_opt_out_reason'] ?? null ;
                $temp["transefer_of_ownership"] = $policy['transefer_of_ownership_yn'] ?? null ;

                $temp["idv"] = $policy['idv'];
                $temp["financier_agreement_type"] = $policy['financier_agreement_type'] ?? null ;
                $temp["financier_name"] = $policy['financier_name'] ?? null ;
                $temp["financier_city_branch"] = $policy['financier_citybranch'] ?? null ;
                $temp["puc_end_date"] = $policy['puc_end_date'] ?? null ;
                $temp["puc_number"] = $policy['puc_number'] ?? null ;

                $temp["owner_type"] = ($policy['owner_type_individual_company'] == 'individual') ? 'I' : (($policy['owner_type_individual_company'] == 'company') ? 'C': null);
                $temp["full_name"] = $policy['full_name'] ?? null ;
                $temp["mobile_no"] = $policy['mobile_no'] ?? null ;
                $temp["email_address"] = $policy['email_address'] ?? null ;
                $temp["occupation"] = $policy['occupation'] ?? null ;
                $temp["marital_status"] = $policy['marital_status'] ?? null ;
                $temp["dob"] = $policy['dob'] ?? null ;
                $temp["eai_number"] = $policy['eai_number'] ?? null ;
                $temp["gst_no"] = $policy['gst_no'] ?? null ;
                $temp["pan_card"] = $policy['pan_card'] ?? null ;
                $temp["gender"] = $policy['gender'] ?? null ;

                $temp["communication_address"] = $policy['communication_address'] ?? null ;
                $temp["communication_pincode"] = $policy['communication_pincode'] ?? null ;
                $temp["communication_city"] = $policy['communication_city'] ?? null ;
                $temp["communication_state"] = $policy['communication_state'] ?? null ;
                
                $temp["nominee_name"] = $policy['nominee_name'] ?? null ;
                $temp["nominee_dob"] = $policy['nominee_dob'] ?? null ;
                $temp["relationship_with_nominee"] = $policy['relationship_with_nominee'] ?? null ;
                $temp["vehicle_registration_address"] = $policy['vehicle_registration_address'] ?? null ;
                $temp["vehicle_registration_pincode"] = $policy['vehicle_registration_pincode'] ?? null ;
                $temp["vehicle_registration_state"] = $policy['vehicle_registration_state'] ?? null ;
                $temp["vehicle_registration_city"] = $policy['vehicle_registration_city'] ?? null ;

                $temp["premium_amount"] = $policy['premium_amount'] ?? null ;

                $temp["cpa_tenure"] = $policy['cpa_tenure_1_3_years_5_years'] ?? null ;
                $temp["zero_dep"] = $policy['zero_dep'] ?? null ;
                $temp["rsa"] = $policy['rsa'] ?? null ;
                $temp["consumable"] = $policy['consumable'] ?? null ;
                $temp["key_replacement"] = $policy['key_replacement'] ?? null ;
                $temp["engine_protector"] = $policy['engine_protector'] ?? null ;
                $temp["ncb_protection"] = $policy['ncb_protection'] ?? null ;
                $temp["tyre_secure"] = $policy['tyre_secure'] ?? null ;
                $temp["return_to_invoice"] = $policy['return_to_invoice'] ?? null ;
                $temp["loss_of_personal_belonging"] = $policy['loss_of_personal_belonging'] ?? null ;
                $temp["electrical_si_amount"] = $policy['electrical_si_amount'] ?? null ;
                $temp["not-electrical"] = $policy['not_electrical'] ?? null ;
                $temp["external_bifuel_cng_lpg"] = $policy['external_bifuel_cnglpg'] ?? null ;
                $temp["pa_cover_for_additional_paid_driver"] = $policy['pa_cover_for_additional_paid_driver'] ?? null ;
                $temp["unnammed_passenger_pa_cover"] = $policy['unnammed_passenger_pa_cover'] ?? null ;
                $temp["ll_paid_driver"] = $policy['ll_paid_driver'] ?? null ;
                $temp["geogarphical_extension"] = $policy['geographical_extension'] ?? null ;
                $temp["arai_approved"] = $policy['arai_approved'] ?? null ;
                $temp["voluntary_deductible_si_amount"] = $policy['voluntary_deductible_si_amount'] ?? null ;
                $temp["tppd_cover"] = $policy['tppd_cover'] ?? null ;

                $temp["cv_zero_dep"] = $policy['cv_zero_dep'] ?? null ;
                $temp["cv_rsa"] = $policy['cv_rsa'] ?? null ;
                $temp["cv_imt_23"] = $policy['cv_imt_23'] ?? null ;
                $temp["cv_electrical_si_amount"] = $policy['cv_electrical_si_amount'] ?? null ;
                $temp["cv_not-electrical"] = $policy['cv_not_electrical'] ?? null ;
                $temp["cv_external_bifuel_cng_lpg"] = $policy['cv_external_bifuel_cnglpg'] ?? null ;
                $temp["cv_ll_paid_driver"] = $policy['cv_ll_paid_driver'] ?? null ;
                $temp["cv_ll_paid_conductor"] = $policy['cv_ll_paid_conductor'] ?? null ;
                $temp["cv_ll_paid_coolie"] = $policy['cv_ll_paid_coolie'] ?? null ;
                $temp["cv_ll_paid_cleaner"] = $policy['cv_ll_paid_cleaner'] ?? null ;
                $temp["cv_pa_paid_driver"] = $policy['cv_pa_paid_driver'] ?? null ;
                $temp["cv_pa_paid_conductor"] = $policy['cv_pa_paid_conductor'] ?? null ;
                $temp["cv_pa_paid_cleaner"] = $policy['cv_pa_paid_cleaner'] ?? null ;
                $temp["cv_geogarphical_extension"] = $policy['cv_geogarphical_extension'] ?? null ;
                $temp["cv_vehicle_limited_to_own_premissis"] = $policy['cv_vehicle_limited_to_own_premissis'] ?? null ;
                $temp["cv_tppd"] = $policy['cv_tppd'] ?? null ;

                $temp["rm_username"] = $policy['rm_name'] ?? null;
                $temp["rm_mobile"] = $policy['rm_mobile'] ?? null ;
                $temp["rm_email"] = $policy['rm_email'] ?? null ;
                $temp["rm_id"] = $policy['rm_id'] ?? null ;

                $temp["pos_username"] = $policy['pos_name'] ?? null;
                $temp["pos_code"] = $policy['pos_code'] ?? null ;
                $temp["pos_pan_no"] = $policy['pos_pan_no'] ?? null ;
                $temp["pos_aadhar_no"] = $policy['pos_aadhar_no'] ?? null ;

                $temp["partner_username"] = $policy['partner_name'] ?? null;
                $temp["partner_id_code"] = $policy['partner_idcode'] ?? null ;
                $temp["partner_mobile"] = $policy['partner_mobile'] ?? null ;
                $temp["partner_email"] = $policy['partner_email'] ?? null ;

                if (isset($policy['rm_name']) && $policy['rm_name'] != null) {
                    $temp["seller_type"] = "E";
                } elseif (isset($policy['pos_name']) && $policy['pos_name'] != null) {
                    $temp["seller_type"] = "P";
                } elseif (isset($policy['partner_name']) && $policy['partner_name'] != null) {
                    $temp["seller_type"] = "Partner";
                } else {
                    $temp["seller_type"] = null;
                }

                if ($temp["seller_type"] == 'E' && empty($request->have_dashboard)) {

                    $temp["seller_id"] = $policy['rm_id'] ?? null ;
                    $temp["seller_username"] = $policy['rm_name'] ?? null ;
                    $temp["seller_mobile"] = $policy['rm_mobile'] ?? null ;

                    $temp["seller_aadhar_no"] = $policy['seller_aadhar_no'] ?? null ;
                    $temp["seller_pan_no"] = $policy['seller_pan_no'] ?? null ;

                } else if ($temp["seller_type"] == 'P'  && empty($request->have_dashboard)) {

                    
                    $temp["seller_id"] = $policy['pos_code'] ?? null ;
                    $temp["seller_username"] = $policy['pos_name'] ?? null ;              
                    $temp["seller_aadhar_no"] = $policy['pos_aadhar_no'] ?? null ;
                    $temp["seller_pan_no"] = $policy['pos_pan_no'] ?? null ;
                    
                } else if ($temp["seller_type"] == 'Partner'  && empty($request->have_dashboard)) {

                    
                    $temp["seller_id"] = $policy['partner_idcode'] ?? null ;
                    $temp["seller_username"] = $policy['partner_name'] ?? null ;
                    $temp["seller_mobile"] = $policy['partner_mobile'] ?? null ;
                    
                    $temp["seller_aadhar_no"] = $policy['seller_aadhar_no'] ?? null ;
                    $temp["seller_pan_no"] = $policy['seller_pan_no'] ?? null ;

                } else {

                    $temp["seller_id"] = $policy['seller_id'] ?? null ;
                    $temp["seller_username"] = $policy['seller_username'] ?? null ;
                    $temp["seller_mobile"] = $policy['seller_mobile'] ?? null ;
                    $temp["seller_aadhar_no"] = $policy['seller_aadhar_no'] ?? null ;
                    $temp["seller_pan_no"] = $policy['seller_pan_no'] ?? null ;
                
                }

                $temp["is_vahan_ic_hit"] = $policy['is_vahan_requiredyn'] ?? null;
            
                array_push($allData, $temp); 
                $i++;
            }

            // return response()->json(["policies" => $allData]);
            $collecterror = [];
            $errorcount = 0;
            $newallData = [];

        
            if ($request->have_dashboard == "true") {

                $collectrm = [];
                $collectpos = [];
                $collectpartner = [];
        
                foreach ($allData as $key => $value){
        
                    if ( isset($value['rm_username']) && $value['rm_username'] != null && !in_array($value['rm_username'], $collectrm)) {
                        array_push($collectrm, $value['rm_username']);
                    }
                    if ( isset($value['pos_username']) && $value['pos_username'] != null && !in_array($value['pos_username'], $collectpos)) {
                        array_push($collectpos, $value['pos_username']);
                    }
                    if ( isset($value['partner_username']) && $value['partner_username'] != null && !in_array($value['partner_username'], $collectpartner)) {
                        array_push($collectpartner, $value['partner_username']);
                    }
        
                }

                // dd($collectrm, $collectpos, $collectpartner);
                $data = [
                    "employee" => [
                        "seller_code" => $collectrm
                    ],
                    "pos" => [
                        "seller_code" => $collectpos
                    ],
                    "partner" => [
                        "seller_code" => $collectpartner
                    ],
                ];
                
                // dd([$data]);
                $fetchdetails = HttpRequestNormal(config('DASHBOARD_FETCH_USER_DETAILS'),"POST", $data, [], []);

                if ( $fetchdetails['status'] &&  !empty($fetchdetails['response'])) {
        
                    foreach ($allData as $key => $value){
            
                        if (  
                            (isset($value['rm_username']) && !isset($value['pos_username']) && !isset($value['partner_username'])) ||
                            (!isset($value['rm_username']) && isset($value['pos_username']) && !isset($value['partner_username'])) ||
                            (!isset($value['rm_username']) && !isset($value['pos_username']) && isset($value['partner_username']))
                        ) {

                            if ( isset($value['rm_username']) && $value['rm_username'] != null ) {
            
            
                                foreach ($fetchdetails['response']['employee'] as $k => $v) {
            
                                    // dd($k,$v);
                                    if ($value['rm_username'] == $k) {
            
                                        if ( $v['status'] != false){
            
                                            $value["seller_id"] = $v['data']['seller_id'] ?? null;
                                            $value["seller_username"] = $v['data']['user_name'] ?? null;
                                            $value["seller_mobile"] = $v['data']['mobile'] ?? null;
                                            $value["seller_aadhar_no"] =$v['data']['aadhar_no'] ?? null;
                                            $value["seller_pan_no"] = $v['data']['pan_no'] ?? null ;
            
                                            $value["rm_username"] = $v['data']['user_name'];
                                            $value["rm_mobile"] = $v['data']['mobile'] ?? null ;
                                            $value["rm_email"] = $v['data']['email'] ?? null ;
                                            $value["rm_id"] = $v['data']['seller_id'] ?? null ;
                                            array_push($newallData, $value); 
            
                                        } else {
                                            $value['error'] = 'RM not found';
                                            array_push($newallData, $value); 
                                            $errorcount++;
                                        }
            
            
                                    }
            
                                }
                            
                            }
                            if ( isset($value['pos_username']) && $value['pos_username'] != null ) {
            
                                foreach ($fetchdetails['response']['pos'] as $k => $v) {
                                    
                                    if ($value['pos_username'] == $k) {
            
                                        if ($v['status'] != false){
            
                                            $value["seller_id"] = $v['data']['seller_id'] ?? null;
                                            $value["seller_username"] = $v['data']['user_name'] ?? null;
                                            $value["seller_mobile"] = $v['data']['mobile'] ?? null;
                                            $value["seller_aadhar_no"] =$v['data']['aadhar_no'] ?? null;
                                            $value["seller_pan_no"] = $v['data']['pan_no'] ?? null ;
            
                                            $value["pos_username"] = $v['data']['user_name'] ?? null;
                                            $value["pos_code"] = $v['data']['seller_id'] ?? null ;
                                            $value["pos_pan_no"] = $v['data']['pan_no'] ?? null ;
                                            $value["pos_aadhar_no"] = $v['data']['aadhar_no'] ?? null ;
            
                                            array_push($newallData, $value); 
            
                                        } else {
                                            $value['error'] = 'POS not found';
                                            array_push($newallData, $value); 
                                            $errorcount++;
                                        }
            
            
                                    }
            
                                }
            
                            }
                            if ( isset($value['partner_username']) && $value['partner_username'] != null) {
            
                                foreach ($fetchdetails['response']['Partner'] as $k => $v) {
            
                                    // dd($k,$v);
                                    if ($value['partner_username'] == $k) {
            
                                        if ( $v['status'] != false){
            
                                            $value["seller_id"] = $v['data']['seller_id'] ?? null;
                                            $value["seller_username"] = $v['data']['user_name'] ?? null;
                                            $value["seller_mobile"] = $v['data']['mobile'] ?? null;
                                            $value["seller_aadhar_no"] =$v['data']['aadhar_no'] ?? null;
                                            $value["seller_pan_no"] = $v['data']['pan_no'] ?? null ;
            
                                            $value["partner_id_code"] =$v['data']['seller_id'] ?? null;
                                            $value["partner_username"] = $v['data']['user_name'] ?? null;
                                            $value["partner_mobile"] = $v['data']['mobile'] ?? null;
                                            $value["partner_email"] = $v['data']['email'] ?? null;
            
                                            array_push($newallData, $value); 
            
                                        } else {
            
                                            $value['error'] = 'Partner not found';
                                            array_push($newallData, $value); 
                                            $errorcount++;
                                        }
            
            
                                    }
            
                                }
                            
                                
                            }

                        } else {

                            if ( $value['rm_username'] == null && $value['pos_username'] == null && $value['partner_username'] == null) {

                                $value['error'] = 'Please mention one of following RM or POS or Partner';
                                array_push($newallData, $value); 
                                $errorcount++;
                            
                            } else {

                                $value['error'] = 'Please mention only one of following RM or POS or Partner';
                                array_push($newallData, $value); 
                                $errorcount++;
                            }
                        }
            
                    }
                } else {
                    return redirect()->back()->with([
                        'error' => 'Unable to fetch user details from dashboard. Please try again later.',
                    ]);
                }
                
                $noDash = false;
                // dd($fetchdetails); 
                // dd($newallData, $errorcount);
            } else {
            
                foreach ($allData as $key => $value){
        
                    if (  
                        (isset($value['rm_username']) && !isset($value['pos_username']) && !isset($value['partner_username'])) ||
                        (!isset($value['rm_username']) && isset($value['pos_username']) && !isset($value['partner_username'])) ||
                        (!isset($value['rm_username']) && !isset($value['pos_username']) && isset($value['partner_username']))
                        ) {
                            array_push($newallData, $value); 
                        } else {
                            
                            if ( $value['rm_username'] == null && $value['pos_username'] == null && $value['partner_username'] == null) {
                                
                            $value['error'] = 'Please mention one of following RM or POS or Partner';
                            array_push($newallData, $value); 
                            $errorcount++;

                        } else {

                            $value['error'] = 'Please mention only one of following RM or POS or Partner';
                            array_push($newallData, $value); 
                            $errorcount++;
                        }
                    }
        
                }
                $noDash = true;
            
            }

            if ($errorcount == 0 ){

                //setting default chunk to 100 , change according as per requirement
                if ($request->have_dashboard == "true"){
                    $chunks = array_chunk($newallData, 100);
                } else if ($noDash){
                    $chunks = array_chunk($newallData, 100);
                } else {
                    $chunks = array_chunk($allData, 100);
                }

                foreach ($chunks as $chunk){

                    try {

                        $upload = httpRequestNormal(route('api.renewal-data-upload'),"POST", ["policies" => $chunk], [], [
                            'Content-Type' => 'application/json'
                        ], [], false);
                    
                        if (is_array($upload['response']) && isset($upload['response']['message']) && is_array($upload['response']['message']) && !$upload['response']['status']) {
                            
                            array_push($collecterror, $upload['response']['message']);
                            if ($upload['response']['status'] == false){
                                foreach ($allData as $key => $value) {

                                    foreach ($collecterror as $error){

                                        foreach ($error as $errkey => $errval){

                                            $collerror =  explode('.',$errval[0]);
                    
                                            if ($value['policy_row_no'] == $collerror[1] ) {

                                                if ( isset($allData[$key]['error']) &&  !in_array($collerror[2],explode(',',$allData[$key]['error'])) ) {

                                                    $allData[$key]['error'] = $allData[$key]['error'].",".$collerror[2];

                                                } else if (!isset($allData[$key]['error'])) {
                                                    
                                                    $allData[$key]['error'] = $collerror[2];
                                                    $errorcount++;

                                                }

                                            }
                                        }
                    
                                    }
                                }
                            }
                        }
                
                    } catch (\Exception $e) {
                        Log::error("message: Admin Panel RenewalDataUpload Error." . $e);
                    }
                
                }

                $datauploaded = count($allData) - $errorcount;
                $totaldata = count($allData);

                return redirect()->back()->with([
                    'status' => true,
                    'allDatawitherror' => json_encode($allData, JSON_UNESCAPED_SLASHES),
                    'collectheading' => json_encode($collectheading, JSON_UNESCAPED_SLASHES),
                    'errorcount' => $errorcount,
                    'datauploaded' => $datauploaded, 
                    'totaldata' => $totaldata
                ]);

            } else {

                $datauploaded = count($newallData) - $errorcount;
                $totaldata = count($newallData);
                
                // dd($collecterror,$newallData,$datauploaded, $totaldata , $errorcount);

                return redirect()->back()->with([
                    'status' => true,
                    'allDatawitherror' => json_encode($newallData, JSON_UNESCAPED_SLASHES),
                    'collectheading' => json_encode($collectheading, JSON_UNESCAPED_SLASHES),
                    'errorcount' => $errorcount,
                    'datauploaded' => $datauploaded, 
                    'totaldata' => $totaldata
                ]);
            }
            
        }  catch (\Exception $e) {
            Log::error("message: Admin Panel RenewalDataUpload Error." . $e);
            return back()->with(  
                'message', 'Something wents wrong while processing data. Please try again after some time or contact technical support',
            );
        }

    }
    public static function excelToStandardDate($excelDateValue) {
        $unixTimestamp = ($excelDateValue - 25569) * 86400; // Convert to Unix timestamp
        return date('Y-m-d', $unixTimestamp); // Format as yyyy-mm-dd
    }
    public static function renewalErrorExcelExport(Request $request)
    {
        if (!auth()->user()->can('upload_data.list')) {
            return response('unauthorized action', 401);
        }

        $collectedheading = json_decode($request->errorexcelheading,true);
        $jsonData = json_decode($request->errorexcel,true);

        array_push($collectedheading, "error");

        $Globalheading = [
            // temp vs excel column
            "product_name" => 'product_name_carbike',
            "vehicle_registration_number" => 'vehicle_reg_no',
            "vehicle_manufacturer" => 'vehicle_manufacturer',
            "vehicle_model" => 'vehicle_model',
            "rto_code" => 'rto_code' ,
            "engine_no" => 'engine_no' ,
            "chassis_no" => 'chassis_no' ,
            "registration_date" => 'registration_date' ,
            "manufacturer_date" => 'manufacturer_date' ,
            "vehicle_colour" => 'vehicle_colour' ,
            "vehicle_variant" => 'vehicle_variant' ,
            "vehicle_uses_type_cv" => 'vehicle_usage_type_cv' ,
            "vehicle_carrier_type" => 'vehicle_carrier_type_public_private' ,
            "insurer_vehicle_model_code" => 'insurer_vehicle_model_code' ,
            "fyntune_version_id" => 'fyntune_version_id' ,
            "prev_policy_type" => 'previous_policy_type_comprehensivesatpsaod_bundle_13_15_package_33_55' ,
            "previous_insurer_company" => 'previous_insurer_company_comprehensive_od' ,
            "previous_policy_number" => 'previous_policy_number_comprehensive_od' ,
            "previous_policy_start_date" => 'previous_policy_start_date_comprehensive_od' ,
            "previous_policy_end_date" => 'previous_policy_end_date_comprehensive_od' ,
            "previous_tp_insurer_company" => 'previous_tp_insurer_company' ,
            "previous_tp_policy_number" => 'previous_tp_policy_number' ,
            "previous_tp_start_date" => 'previous_tp_start_date' ,
            "previous_tp_end_date" => 'previous_tp_end_date' ,
            "previous_ncb" => 'previous_ncb' ,
            "previous_claim_status" => 'previous_claim_status' ,
            "previous_claim_amount" => 'previous_claim_amount' ,
            "no_of_claims" => 'no_of_claims' ,
            "cpa_opt-out_reason" => 'cpa_opt_out_reason' ,
            "transefer_of_ownership" => 'transefer_of_ownership_yn' ,
            "idv" => 'idv',
            "financier_agreement_type" => 'financier_agreement_type' ,
            "financier_name" => 'financier_name' ,
            "financier_city_branch" => 'financier_citybranch' ,
            "puc_end_date" => 'puc_end_date' ,
            "puc_number" => 'puc_number' ,
            "owner_type" =>'owner_type_individual_company',
            "full_name" => 'full_name' ,
            "mobile_no" => 'mobile_no' ,
            "email_address" => 'email_address' ,
            "occupation" => 'occupation' ,
            "marital_status" => 'marital_status' ,
            "dob" => 'dob' ,
            "eai_number" => 'eai_number' ,
            "gst_no" => 'gst_no' ,
            "pan_card" => 'pan_card' ,
            "gender" => 'gender' ,
            "communication_address" => 'communication_address' ,
            "communication_pincode" => 'communication_pincode' ,
            "communication_city" => 'communication_city' ,
            "communication_state" => 'communication_state' ,          
            "nominee_name" => 'nominee_name' ,
            "nominee_dob" => 'nominee_dob' ,
            "relationship_with_nominee" => 'relationship_with_nominee' ,
            "vehicle_registration_address" => 'vehicle_registration_address' ,
            "vehicle_registration_pincode" => 'vehicle_registration_pincode' ,
            "vehicle_registration_state" => 'vehicle_registration_state' ,
            "vehicle_registration_city" => 'vehicle_registration_city' ,
            "premium_amount" => 'premium_amount' ,
            "cpa_tenure" => 'cpa_tenure_1_3_years_5_years' ,
            "zero_dep" => 'zero_dep' ,
            "rsa" => 'rsa' ,
            "consumable" => 'consumable' ,
            "key_replacement" => 'key_replacement' ,
            "engine_protector" => 'engine_protector' ,
            "ncb_protection" => 'ncb_protection' ,
            "tyre_secure" => 'tyre_secure' ,
            "return_to_invoice" => 'return_to_invoice' ,
            "loss_of_personal_belonging" => 'loss_of_personal_belonging' ,
            "electrical_si_amount" => 'electrical_si_amount' ,
            "not-electrical" => 'not_electrical' ,
            "external_bifuel_cng_lpg" => 'external_bifuel_cnglpg' ,
            "pa_cover_for_additional_paid_driver" => 'pa_cover_for_additional_paid_driver' ,
            "unnammed_passenger_pa_cover" => 'unnammed_passenger_pa_cover' ,
            "ll_paid_driver" => 'll_paid_driver' ,
            "geogarphical_extension" => 'geographical_extension' ,
            "arai_approved" => 'arai_approved' ,
            "voluntary_deductible_si_amount" => 'voluntary_deductible_si_amount' ,
            "tppd_cover" => 'tppd_cover' ,
            "cv_zero_dep" => 'cv_zero_dep' ,
            "cv_rsa" => 'cv_rsa' ,
            "cv_imt_23" => 'cv_imt_23' ,
            "cv_electrical_si_amount" => 'cv_electrical_si_amount' ,
            "cv_not-electrical" => 'cv_not_electrical' ,
            "cv_external_bifuel_cng_lpg" => 'cv_external_bifuel_cnglpg' ,
            "cv_ll_paid_driver" => 'cv_ll_paid_driver' ,
            "cv_ll_paid_conductor" => 'cv_ll_paid_conductor' ,
            "cv_ll_paid_coolie" => 'cv_ll_paid_coolie' ,
            "cv_ll_paid_cleaner" => 'cv_ll_paid_cleaner' ,
            "cv_pa_paid_driver" => 'cv_pa_paid_driver' ,
            "cv_pa_paid_conductor" => 'cv_pa_paid_conductor' ,
            "cv_pa_paid_cleaner" => 'cv_pa_paid_cleaner' ,
            "cv_geogarphical_extension" => 'cv_geogarphical_extension' ,
            "cv_vehicle_limited_to_own_premissis" => 'cv_vehicle_limited_to_own_premissis' ,
            "cv_tppd" => 'cv_tppd' ,
            "rm_username" => 'rm_name',
            "rm_mobile" => 'rm_mobile' ,
            "rm_email" => 'rm_email' ,
            "rm_id" => 'rm_id' ,
            "pos_username" => 'pos_name',
            "pos_code" => 'pos_code' ,
            "pos_pan_no" => 'pos_pan_no' ,
            "pos_aadhar_no" => 'pos_aadhar_no' ,
            "partner_username" => 'partner_name',
            "partner_id_code" => 'partner_idcode' ,
            "partner_mobile" => 'partner_mobile' ,
            "partner_email" => 'partner_email' ,
            "is_vahan_ic_hit" => 'is_vahan_requiredyn' ,
            'error' => 'error'

        ];

        //sorting excel json data with respect to collectedheading of excel
        $sortRef = [];
        
        foreach( $collectedheading as $ckey => $cvalue){
            foreach ( $Globalheading as $Gkey => $Gvalue ){

                if ( $cvalue == $Gvalue){
                    $sortRef[$Gkey] = $cvalue;
                }
            }

        }

        $sortedArray = [];
        foreach ($jsonData as $subArray) {
            uksort($subArray, function ($a, $b) use ($sortRef) {
                $posA = array_search($a, array_keys($sortRef));
                $posB = array_search($b, array_keys($sortRef));
                return $posA - $posB;
            });
        
            $sortedArray[] = $subArray;
        }

        // dd($sortRef, $collectedheading,$jsonData,$sortedArray);

        //filtering excel headings we need and sortedArray data that we require
        $headings = [
            // actual excel vs temp
            'Product Name ( CAR/BIKE )' =>'product_name_carbike',
            'Vehicle reg no' => 'vehicle_reg_no',
            'Vehicle Manufacturer' => 'vehicle_manufacturer',
            'Vehicle Model' => 'vehicle_model',
            'Vehicle Variant' => 'vehicle_variant',
            'Insurer Vehicle Model Code'  => 'insurer_vehicle_model_code',
            'Fyntune Version ID'  => 'fyntune_version_id' ,
            'RTO Code' => 'rto_code',
            'Engine No'  => 'engine_no' ,
            'Chassis No' => 'chassis_no',
            'registration date' => 'registration_date',
            'Manufacturer date' => 'manufacturer_date',
            'vehicle colour' => 'vehicle_colour',
            'Vehicle usage Type (cv)' => 'vehicle_usage_type_cv',
            'Vehicle carrier Type (Public / Private)' => 'vehicle_carrier_type_public_private',
            'Previous Policy Type (Comprehensive/SATP/SAOD / Bundle (1+3 / 1+5) / Package (3+3 / 5+5)'  => 'previous_policy_type_comprehensivesatpsaod_bundle_13_15_package_33_55',
            'Previous insurer company (Comprehensive / OD)' => 'previous_insurer_company_comprehensive_od',
            'Previous policy Number (Comprehensive / OD)' => 'previous_policy_number_comprehensive_od' ,
            'Previous Policy start Date (Comprehensive / OD)' => 'previous_policy_start_date_comprehensive_od' ,
            'Previous Policy end Date (Comprehensive / OD)' => 'previous_policy_end_date_comprehensive_od' ,
            'Previous TP insurer company' => 'previous_tp_insurer_company',
            'Previous TP Policy Number'  => 'previous_tp_policy_number',
            'Previous TP start date' => 'previous_tp_start_date' ,
            'Previous TP end date' => 'previous_tp_end_date' ,
            'Previous NCB' => 'previous_ncb' ,
            'Previous Claim Status' => 'previous_claim_status' ,
            'Previous Claim amount' => 'previous_claim_amount',
            'No of claims' => 'no_of_claims',
            'CPA opt-out reason' => 'cpa_opt_out_reason',
            'Transefer of ownership (Y/N)' => 'transefer_of_ownership_yn',
            'IDV' => 'idv',
            'financier Agreement type' => 'financier_agreement_type',
            'financier name' => 'financier_name' ,
            'Financier city/Branch' => 'financier_citybranch',
            'PUC end date' => 'puc_end_date',
            'PUC number' => 'puc_number',
            'Owner Type (Individual / company)' =>'owner_type_individual_company',
            'Full Name' => 'full_name',
            'Mobile No' => 'mobile_no',
            'Email Address' => 'email_address',
            'Occupation' => 'occupation',
            'Marital status' => 'marital_status',
            'DOB' => 'dob',
            'eAI Number' => 'eai_number',
            'GST No' => 'gst_no',
            'PAN card' => 'pan_card',
            'Gender' => 'gender',
            'Communication address' => 'communication_address',
            'Communication pincode' => 'communication_pincode',
            'Communication city' => 'communication_city',
            'Communication state' => 'communication_state',
            'Nominee name' => 'nominee_name',
            'Nominee DOB' => 'nominee_dob',
            'Relationship with Nominee' => 'relationship_with_nominee',
            'Vehicle registration address' => 'vehicle_registration_address',
            'Vehicle registration pincode' => 'vehicle_registration_pincode',
            'Vehicle registration state' => 'vehicle_registration_state',
            'Vehicle registration city' => 'vehicle_registration_city',
            "Premium amount" => 'premium_amount' ,
            'CPA tenure (1 / 3 years/ 5 years)' => 'cpa_tenure_1_3_years_5_years',
            'Zero dep' => 'zero_dep',
            'RSA' => 'rsa',
            'Consumable' => 'consumable',
            'Key Replacement' => 'key_replacement',
            'Engine Protector' => 'engine_protector',
            'NCB protection' => 'ncb_protection',
            'Tyre Secure' => 'tyre_secure',
            'Return To invoice' => 'return_to_invoice',
            'Loss Of personal Belonging' => 'loss_of_personal_belonging',
            'Electrical ( SI amount )' => 'electrical_si_amount',
            'Not-Electrical' => 'not_electrical',
            'External Bifuel CNG/LPG' => 'external_bifuel_cnglpg',
            'PA Cover for additional Paid Driver' => 'pa_cover_for_additional_paid_driver',
            'Unnammed Passenger PA Cover' => 'unnammed_passenger_pa_cover',
            'LL paid Driver' => 'll_paid_driver',
            'Geographical Extension' => 'geographical_extension',
            'ARAI Approved' => 'arai_approved',
            'Voluntary Deductible (SI Amount)' => 'voluntary_deductible_si_amount',
            'TPPD cover' => 'tppd_cover',
            'cv_Zero Dep' => 'cv_zero_dep',
            'cv_RSA' => 'cv_rsa',
            'cv_IMT 23' => 'cv_imt_23',
            'cv_Electrical ( SI amount )' => 'cv_electrical_si_amount',
            'cv_Not-Electrical' => 'cv_not_electrical',
            'cv_External Bifuel CNG/LPG' => 'cv_external_bifuel_cnglpg',
            'cv_LL paid Driver' => 'cv_ll_paid_driver',
            'cv_LL paid Conductor' => 'cv_ll_paid_conductor',
            'cv_LL paid Coolie' => 'cv_ll_paid_coolie',
            'cv_LL paid Cleaner' => 'cv_ll_paid_cleaner',
            'cv_PA Paid Driver' => 'cv_pa_paid_driver',
            'cv_PA paid Conductor' => 'cv_pa_paid_conductor',
            'cv_PA paid Cleaner' => 'cv_pa_paid_cleaner',
            'cv_Geogarphical Extension' => 'cv_geogarphical_extension',
            'cv_Vehicle Limited to Own Premissis' => 'cv_vehicle_limited_to_own_premissis',
            'cv_TPPD' => 'cv_tppd',
            'RM Name' => 'rm_name',
            'RM Mobile' => 'rm_mobile',
            'RM Email' => 'rm_email',
            'RM ID' => 'rm_id',
            'POS code' => 'pos_code',
            'POS Name' => 'pos_name',
            'POS PAN No' => 'pos_pan_no',
            'POS Aadhar No' => 'pos_aadhar_no',
            'Partner ID/Code' => 'partner_idcode',
            'Partner Name' => 'partner_name',
            'Partner Mobile' => 'partner_mobile',
            'Partner Email' => 'partner_email',
            'Is Vahan Required(Y/N)' => 'is_vahan_requiredyn' ,
            'Error Message' => 'error'
        ];

        $firstFilter = [];
        foreach( $collectedheading as $ckey => $cvalue){
            foreach ( $Globalheading as $Gkey => $Gvalue){
                if (  $cvalue == $Gvalue){
                    $firstFilter[$Gkey] = $cvalue;
                }
            }
        }
        // dd($firstFilter,$collectedheading);

        $secondFilter = [];
        foreach( $firstFilter as $fkey => $fvalue){
            foreach ( $headings as $Hkey => $Hvalue){
                if ( $Hvalue == $fvalue){
                    $secondFilter[$Hkey] = $fkey;
                }
            }
        }

        $finalData = [];

        foreach ($sortedArray as $data) {
            $mappedData = [];

            foreach ($data as $jkey => $jvalue) {
                if (in_array($jkey, $secondFilter)) {
                    $skey = array_search($jkey, $secondFilter);
                    $mappedData[$skey] = $jvalue;
                }
            }
            array_push($finalData, $mappedData);
        }
        
        // foreach ($sortedArray as $data){
        //     $mappedData = [];
        //     foreach ($data as $jkey => $jvalue){
        //         foreach ($secondFilter as $skey => $svalue){
        //             if ( in_array($jkey,$secondFilter) &&  $jkey == $svalue){
                                    
        //                 $mappedData[$skey] = $jvalue;                      
        //             }
        //         } 
        //     }
        //     array_push($finalData,$mappedData); 
        // }
    
        // dd($collectedheading,$firstFilter,$secondFilter,$finalData);

        return Excel::download(new class($finalData,$secondFilter) implements FromCollection, WithHeadings {
            public function __construct($data,$secondFilter)
            {
                $this->data = $data;
                $this->secondFilter = $secondFilter;
            }

            public function collection()
            {
                return collect([$this->data]);
            }

            public function headings(): array
            {
                return array_keys($this->secondFilter);
            }
        }, 'Upload Renewal Validation Error.xls');


    }
    public static function viewLogs(){

        return redirect()->route('admin.renewal-data-migration.index');
    }
  
    
}
