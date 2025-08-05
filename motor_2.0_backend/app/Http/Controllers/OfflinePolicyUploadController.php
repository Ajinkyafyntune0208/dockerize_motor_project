<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OfflinePolicyUploadController extends Controller
{
    public function upload(Request $request)
    {
        if (config('constants.motorConstant.OFFLINE_DATA_UPLOAD_v2_ALLOWED') != 'Y') {
            return response()->json([
                'status' => false,
                'message' => 'Not allowed'
            ]);
        }

        $productName = \App\Models\MasterProductSubType::select('product_sub_type_code')
            ->whereNotNull('product_sub_type_code')
            ->pluck('product_sub_type_code')
            ->toArray();
        $masterCompany = \App\Models\MasterCompany::whereNotNull('company_alias')
        ->pluck('company_alias')
        ->toArray();
        
        $masterCompany = implode(',', $masterCompany);

        $validator = Validator::make($request->all(), [
            'policies' => 'required|array',
            'policies.*.product_name' => ['required', 'in:' . implode(',', $productName)],
            'policies.*.secondary_unique_key' => ['required', 'string'],
            'policies.*.mobile_no' => ['required', 'string'],
            'policies.*.seller_type' => ['nullable', 'in:P,E,Partner'],
            'policies.*.company_alias' => ['nullable', 'in:'.$masterCompany],
            'policies.*.policy_end_date' => ['nullable', 'date_format:Y-m-d'],
            'policies.*.policy_type' => 'nullable|in:OD,COMPREHENSIVE,TP'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 400);
        }

        $reference = (string) \Illuminate\Support\Str::uuid() . '-' . time();
        try {
            $fileName = (string) \Illuminate\Support\Str::uuid();
            $count = 0;
            $filePath  = 'renewalDataUploadV2/' . $fileName . '.json';

            while (Storage::exists($filePath)) {
                $count++;
                $filePath = 'renewalDataUploadV2/' . $fileName . '_' . $count . '.json';
            }

            Storage::put($filePath, json_encode([
                'meta' => [
                    'reference' => $reference,
                    'uploaded_at' => now()->toDateTimeString()
                ],
                'data' => $request->all()
            ], JSON_PRETTY_PRINT));

            return response()->json([
                'status' => true,
                'data' => [
                    'total_records' => count($request->policies ?? []),
                    'timestamp' => now(),
                    'reference' => $reference
                ],
                'msg' => 'Data uploaded Successfully...!'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Offline policy v2 upload failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
