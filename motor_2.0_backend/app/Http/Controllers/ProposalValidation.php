<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProposalValidation as proposal_validation;
use App\Models\ProposalFields;
use Illuminate\Support\Facades\Validator;

class ProposalValidation extends Controller
{
    public static function addProposalValidation(Request $request )
    {
        try {
            $exists= proposal_validation::where('broker_name','=',config('app.name'))->exists();
            $data = $request->all();
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (!is_array($value) && empty($value)) {
                        //removed extra path getting passed in the request
                        unset($data[$key]);
                    }
                }
            }
            if($exists){
                $proposal = proposal_validation::where('broker_name','=',config('app.name'))->first();
                $proposal->data = json_encode($data);
                $proposal = $proposal->save();
                if($proposal == true){
                    return [
                        'status' => true,
                        "message" => "Data Updated Successfully",
                    ];
                }
            }else{
               $proposal = new proposal_validation();
               $proposal->broker_name = config('app.name');
               $proposal->data = json_encode($data);
               $proposal = $proposal->save();
               if($proposal == true){
                return [
                    'status' => true,
                    "message" => "Data Inserted Successfully",
                ];
            }
            }
            
        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "error " . $e->getMessage(),

            ], 500);
        }
    }

    public static function getProposalValidation()
    {
        try {
        $result = proposal_validation::where('broker_name', config('app.name'))->select('data')->first();
        if (!isset($result->data)) {
            return [
                'data'=>null,
                'status' => true
            ];
        }
        if($result->data==null || $result->data=='[]'){
            return [
                "data"=>null,
                'status' => true
            ];
        }
        return [
            'status' => true,
            "data" => json_decode($result->data)
        ];
        } catch (\Exception $e) {
            return response()->json([
                "data"=>null,
                "status" => false,
                "message" => "error " . $e->getMessage(),

            ], 500);
        }
    }


    public static function addProposalfield(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'company_alias' => 'required',
            'section' => 'required',
            'owner_type' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        }
        try {
            $where=[];
            if($request->company_alias=='all' && $request->section=='all'){
                $where=[
                    "owner_type" =>$request->owner_type
                ];
            }elseif($request->company_alias=='all' && $request->section!='all'){
                $where=[
                    "section" =>$request->section,
                    "owner_type" =>$request->owner_type
                ];
            }elseif($request->company_alias!='all' && $request->section=='all'){
                $where=[
                    "company_alias" =>$request->company_alias,
                    "owner_type" =>$request->owner_type
                ];
            }
                $ckycTypeValue = $request->ckyc_type ?? null;
                $poilistValue = $request->poilist ?? null;
                $poalistValue = $request->poalist ?? null;
                $mapping_value = [
                    'panNumber' => [
                        "label" => "PAN Number",
                        "placeholder" => "Upload PAN Card Image",
                        "length" => 10,
                        "fileKey" => "panCard"
                    ],
                    'cretificateOfIncorporaion' => [
                        "label" => "Certificate of Incorporation",
                        "placeholder" => "Upload Certificate",
                        "fileKey" => "certificate_of_incorporation_image"
                    ],
                    'nationalPopulationRegisterLetter' => [
                        "label" => "National Population Letter",
                        "placeholder" => "Upload Letter",
                        "fileKey" => "national_population_register_letter_image"
                    ],
                    'registrationCertificate' => [
                        "label" => "Registration Certificate",
                        "placeholder" => "Upload Certificate",
                        "length" => 20,
                        "fileKey" => "registration_certificate_image"
                    ],
                    'cinNumber' => [
                        "label" => "CIN Number",
                        "placeholder" => "Upload CIN Number Certificate",
                        "fileKey" => "cinNumber"
                    ],
                    'aadharNumber' => [
                        "label" => "Adhaar Number",
                        "placeholder" => "Upload Adhaar Card Image",
                        "length" => 12,
                        "fileKey" => "aadharCard"
                    ],
                    'e-eiaNumber' => [
                        "label" => "e-Insurance Account Number",
                        "fileKey" => "eiaNumber"
                    ],
                    'passportNumber' => [
                        "label" => "Passport Number",
                        "placeholder" => "Upload Passport Image",
                        "length" => 8,
                        "fileKey" => "passport_image"
                    ],
                    'voterId' => [
                        "label" => "Voter ID Number",
                        "placeholder" => "Upload Voter ID Card Image",
                        "fileKey" => "voter_card"
                    ],
                    'drivingLicense' => [
                        "label" => "Driving License Number",
                        "placeholder" => "Upload Driving License Image",
                        "fileKey" => "driving_license"
                    ],
                    'gstNumber' => [
                        "label" => "GST Number",
                        "placeholder" => "Upload GST Number Certificate",
                        "length" => 15,
                        "fileKey" => "gst_certificate"
                    ],
                    'nregaJobCard' => [
                        "label" => "NREGA Job Card",
                        "placeholder" => "Upload NREGA Card Image",
                        "length" => 18,
                        "fileKey" => "nrega_job_card_image"
                    ]
                ];
                $specificValues=[];
                if (is_array($ckycTypeValue) || is_object($ckycTypeValue)) {
                    foreach ($ckycTypeValue as $data) {
                        if (isset($data['value']) && isset($mapping_value[$data['value']])) {
                            $specificValues[] = $mapping_value[$data['value']];    
                        }
                    }
                }
                if (is_array($poilistValue) || is_object($poilistValue)) {
                    foreach ($poilistValue as $data) {
                        if (isset($data['value']) && isset($mapping_value[$data['value']])) {
                            $specificValues[] = $mapping_value[$data['value']];
                        }
                    }
                }
                if (is_array($poalistValue) || is_object($poalistValue)) {
                    foreach ($poalistValue as $data) {
                        if (isset($data['value']) && isset($mapping_value[$data['value']])) {
                            $specificValues[] = $mapping_value[$data['value']];
                        }
                    }
                }
                    $mapping = [
                        'panNumber' => 'panCard',
                        'cretificateOfIncorporaion' => 'certificate_of_incorporation_image',
                        'nationalPopulationRegisterLetter' => 'national_population_register_letter_image',
                        'cinNumber' => 'cinNumber',
                        'aadharNumber' => 'aadharCard',
                        'e-eiaNumber' => 'eiaNumber',
                        'passportNumber' => 'passport_image',
                        'voterId' => 'voter_card',
                        'drivingLicense' => 'driving_license',
                        'gstNumber' => 'gst_certificate',
                        'nregaJobCard' => 'nrega_job_card_image',
                        'registrationCertificate' => 'registration_certificate_image'
                    ];
                        $ckyc_type=[];
                        if (is_array($request->ckyc_type) || is_object($request->ckyc_type)) {
                            foreach ($request->ckyc_type as $ckyc_value) {
                                foreach ($specificValues as $value) {
                                    if (array_key_exists('value', $ckyc_value) && array_key_exists($ckyc_value['value'], $mapping)) {
                                        if (isset($value['fileKey']) && $value['fileKey'] == $mapping[$ckyc_value['value']]) {
                                            $ckyc_type_key = $ckyc_value['value'] . '_' . $value['fileKey'];
                                            if (!isset($ckyc_type[$ckyc_type_key])) {
                                                $ckyc_type[$ckyc_type_key] = array_merge($ckyc_value, $value);
                                            }
                                        }
                                    }
                                }
                            }
                            $ckyc_type = array_values($ckyc_type);
                        }
                        
                        $poi_type=[];
                        if (is_array($request->poilist) || is_object($request->poilist)) {
                            foreach ($request->poilist as $poi_value) {
                                foreach ($specificValues as $value) {
                                    if (array_key_exists($poi_value['value'], $mapping)) {
                                        if ($value['fileKey'] == $mapping[$poi_value['value']]) {
                                            $poi_type_key = $poi_value['value'] . '_' . $value['fileKey'];
                                            if (!isset($poi_type[$poi_type_key])) {
                                                $poi_type[$poi_type_key] = array_merge($poi_value, $value);
                                            }
                                        }
                                    }
                                }
                            }
                            $poi_type = array_values($poi_type);
                        }
                        
                        $poa_type=[];
                        if (is_array($request->poalist) || is_object($request->poalist)) {
                            foreach ($request->poalist as $poa_value) {
                                foreach ($specificValues as $value) {
                                    if (array_key_exists($poa_value['value'], $mapping)) {
                                        if ($value['fileKey'] == $mapping[$poa_value['value']]) {
                                            $poa_type_key = $poa_value['value'] . '_' . $value['fileKey'];
                                            if (!isset($poa_type[$poa_type_key])) {
                                                $poa_type[$poa_type_key] = array_merge($poa_value, $value);
                                            }
                                        }
                                    }
                                }
                            }
                            $poa_type = array_values($poa_type);
                        }
               
            if($request->company_alias=='all' || $request->section=='all'){
                $result = ProposalFields::where($where)->update(
                    [
                        "fields" => json_encode($request->fields)
                    ]);
            } else{
                if($request->section == 'all'){
                    $section = ['car', 'bike', 'cv'];
                    foreach ($section as $key => $value) {
                        $updateValues=[
                            "company_alias" =>$request->company_alias,
                            "section" =>$value,
                            "owner_type" =>$request->owner_type
                        ];
                        if ($request->config) {
                            $updateValues['renewal_fields']=json_encode($request->fields);
                        } elseif(!empty($ckycTypeValue) || !empty($poilistValue) || !empty($poalistValue)) {
                            $fields = array(
                                'fields' => $request->fields,
                                'ckyc_type' => $ckyc_type,
                                'poilist' => $poi_type,
                                'poalist' => $poa_type
                            );
                            $updateValues['fields'] = json_encode($fields);
                            
                        }else {
                            $updateValues['fields']=json_encode($request->fields);
                        }
                        $result = ProposalFields::updateOrCreate(
                            [
                                "company_alias" =>$request->company_alias,
                                "section" =>$value,
                                "owner_type" =>$request->owner_type
                            ],$updateValues);
                    }
                } else{
                    $updateValues=[
                        "company_alias" =>$request->company_alias,
                        "section" =>$request->section,
                        "owner_type" =>$request->owner_type
                    ];
                    if ($request->config) {
                        $updateValues['renewal_fields']=json_encode($request->fields);
                    } elseif(!empty($ckycTypeValue) || !empty($poilistValue) || !empty($poalistValue)) {
                            $fields = array(
                                'fields' => $request->fields,
                                'ckyc_type' => $ckyc_type,
                                'poilist' => $poi_type,
                                'poalist' => $poa_type
                            );
                        $updateValues['fields'] = json_encode($fields);
                    }else{
                        $updateValues['fields']=json_encode($request->fields);
                    }
                    $result = ProposalFields::updateOrCreate(
                        [
                            "company_alias" =>$request->company_alias,
                            "section" =>$request->section,
                            "owner_type" =>$request->owner_type
                        ],
                    $updateValues);
                }
            }

            return [
                'status' => true,
                "message" => "Data Inserted Successfully",
            ];
        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "error " . $e->getMessage(),

            ], 500);
        }
    }

    public static function getProposalFields(Request $request)
    {

        $validate = Validator::make($request->all(), [
            'company_alias' => 'required',
            'section' => 'required',
            'owner_type' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'error' => $validate->errors(),
            ]);
        }
        try {
            $result = ProposalFields::where([
                "company_alias" =>$request->company_alias,
                "section" =>$request->section,
                "owner_type" =>$request->owner_type,])->select('fields','renewal_fields')->first();
                $defaultarr = [
                    "gstNumber",
                    "maritalStatus",
                    "occupation",
                    "panNumber",
                    "vehicleColor",
                    "hypothecationCity",
                    "dob",
                    "gender",
                    "cpaOptOut",
                    "email"
                ];
                $fields=(empty($result) ? $defaultarr :json_decode($result->fields,true));
                if ($request->config) {
                    $fields=$result ? json_decode($result->renewal_fields) : null;
                }
                
            return [
                'status' => true,
                "data" => $fields
            ];
        } catch (\Exception $e) {
            return response()->json([
                "status" => false,
                "message" => "error " . $e->getMessage(),

            ], 500);
        }
    }
}
