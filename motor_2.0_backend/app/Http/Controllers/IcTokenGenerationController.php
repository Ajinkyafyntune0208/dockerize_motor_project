<?php

namespace App\Http\Controllers;

use App\Models\MasterCompany;
use App\Models\UserProductJourney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class IcTokenGenerationController extends Controller
{
    public function index(Request $request, String $company_alias)
    {
        $requestData = $request->all();
        $requestData['company_alias'] = $company_alias;

        $allIcs = MasterCompany::whereNotNull('company_alias')->where('status', 'Active')->select('company_alias')->get()->pluck('company_alias')->toArray();
        $allIcs = implode(',', $allIcs);

        $validation = Validator::make($requestData, [
            'userProductJourneyId' => 'required',
            'type' => ['required', 'in:quote,proposal'],
            'company_alias' => ['required', 'in:' . $allIcs . ',tata_aig_v2'],
        ]);

        if ($validation->fails()) {
            return response()->json([
                "status" => false,
                "message" => $validation->errors(),
            ]);
        }

        $enquiryId = customDecrypt($request->userProductJourneyId);
        $productCode = get_parent_code(UserProductJourney::select('product_sub_type_id')->find($enquiryId)?->product_sub_type_id);

        if (empty($productCode)) {
            return response()->json([
                'status' => false,
                'message' => 'Product code not found for the specified trace id.',
            ]);
        }
        $className = '\\App\\IcTokenGeneration\\' . ucwords(strtolower($productCode)) . '\\' . Str::studly($company_alias) . 'TokenGeneration';

        if (!class_exists($className, 'generateToken')) {
            return response()->json([
                'status' => false,
                'message' => 'Requested file not found for token generation.',
            ]);
        }

        $tokenFile = new $className();
        $requestData = getQuotation($enquiryId);
        return $tokenFile->generateToken($request, $requestData);

    }
}
