<?php

namespace App\Http\Controllers\EnquiryIdValidation;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;

class EnquiryIdController extends Controller
{
    public function isEnquiryIdValid(Request $request)
    {
        try {
            $enquiryId = getDecryptedEnquiryId($request->enquiryId);
            return response()->json([
                'status' => true,
                'enquiryId' => $enquiryId,
                'message' => 'success'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Enquiry ID is not valid'
            ]);
        }
    }
}
