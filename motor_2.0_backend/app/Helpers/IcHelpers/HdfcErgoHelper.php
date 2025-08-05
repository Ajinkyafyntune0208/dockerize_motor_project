<?php

namespace App\Helpers\IcHelpers;

use App\Models\HdfcErgoToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HdfcErgoHelper
{
    public static function getTokenByProductCode($productCode)
    {
        try {
            if (
                config('constant.IcConstant.hdfcErgo.ENABLE_NEW_TOKEN_LOGIC', 'N') != 'Y' ||
                empty($productCode)
            ) {
                return null;
            }

            $token = HdfcErgoToken::where('product_code', $productCode)
                ->where('expired_at', '>=', now())
                ->orderBy('id', 'desc')
                ->pluck('token')
                ->first();

            if (!empty($token)) {
                $token = [
                    'StatusCode' => 200,
                    'Authentication' => [
                        'Token' => $token
                    ]
                ];

                return json_encode($token);
            }

            return Cache::lock(
                "hdfc_ergo_token_lock_$productCode",
                config('constant.IcConstant.hdfcErgo.TOKEN_CACHE_LOCK_TIME', 15)
            )
            ->block(
                config('constant.IcConstant.hdfcErgo.TOKEN_CACHE_LOCK_TIME', 15),
                function () use ($productCode) {

                    $token = HdfcErgoToken::where('product_code', $productCode)
                        ->where('expired_at', '>=', now())
                        ->orderBy('id', 'desc')
                        ->pluck('token')
                        ->first();

                    if (!empty($token)) {
                        $token = [
                            'StatusCode' => 200,
                            'Authentication' => [
                                'Token' => $token
                            ]
                        ];

                        return json_encode($token);
                    }

                    sleep(5); // Simulate delay for token generation

                    $token = HdfcErgoToken::where('product_code', $productCode)
                        ->where('expired_at', '>=', now())
                        ->orderBy('id', 'desc')
                        ->pluck('token')
                        ->first();

                    if (!empty($token)) {
                        $token = [
                            'StatusCode' => 200,
                            'Authentication' => [
                                'Token' => $token
                            ]
                        ];

                        return json_encode($token);
                    }
                }
            );

        } catch (\Throwable $th) {
            Log::error($th);
        }

        return null;
    }

    public static function storeToken($additionalData, $response)
    {
        try {
            $type = $additionalData['type'] ?? $additionalData['method'] ?? null;
            $productCode = $additionalData['PRODUCT_CODE'] ?? $additionalData['headers']['PRODUCT_CODE'] ?? null;
            if (
                in_array(strtolower($type), [
                    'gettoken',
                    'token generation'
                ]) &&
                !empty($productCode) &&
                !empty($response)
            ) {
                $response = json_decode($response, true);
                $token = $response['Authentication']['Token'] ?? null;

                if (!empty($token)) {

                    $exists = HdfcErgoToken::where('product_code', $productCode)->exists();
                    if ($exists) {
                        // Update all matching records
                        HdfcErgoToken::where('product_code', $productCode)->update([
                            'token' => $token,
                            'expired_at' => now()->endOfDay(),
                        ]);
                    } else {
                        HdfcErgoToken::create([
                            'product_code' => $productCode,
                            'token' => $token,
                            'expired_at' => now()->endOfDay(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    public static function checkForToken($additionalData)
    {
        try {
            $type = $additionalData['type'] ?? $additionalData['method'] ?? null;
            $productCode = $additionalData['PRODUCT_CODE'] ?? $additionalData['headers']['PRODUCT_CODE'] ?? null;

            if (
                in_array(strtolower($type), [
                    'gettoken',
                    'token generation'
                ]) &&
                !empty($productCode)
            ) {
                return self::getTokenByProductCode($productCode);
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }

        return null;
    }
}
