<?php

namespace App\Helpers\IcHelpers;

use App\Models\TataAigToken;
use Illuminate\Support\Facades\Log;

class TataAigHelper
{
    public static function checkForToken($additionalData, $tokenRequest)
    {
        try {
            $type = $additionalData['method'] ?? $additionalData['type'] ?? null;
            $clientId = is_array($tokenRequest) ? ($tokenRequest['client_id'] ?? null) : null;
            $clientSecret = is_array($tokenRequest) ? ($tokenRequest['client_secret'] ?? null) : null;

            if (
                !empty($type) &&
                !empty($clientId) &&
                !empty($clientSecret) &&
                in_array(strtolower($type), [
                    'token',
                    'token generation'
                ])
            ) {
                return self::getTokenByProductCode($clientId, $clientSecret);
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }

        return null;
    }

    public static function getTokenByProductCode($clientId, $clientSecret)
    {
        try {
            if (
                config('constant.IcConstant.tataAig.ENABLE_NEW_TOKEN_LOGIC', 'N') != 'Y' ||
                empty($clientId) || empty($clientSecret)
            ) {
                return null;
            }

            $token = TataAigToken::where([
                'client_id' => $clientId,
                'client_secret' => $clientSecret
            ])
                ->where('expired_at', '>=', now())
                ->orderBy('id', 'desc')
                ->pluck('token')
                ->first();

            if (!empty($token)) {
                $token = [
                    'access_token' => $token
                ];

                return json_encode($token);
            }

            return \Illuminate\Support\Facades\Cache::lock(
                "tata_aig_token_lock_$clientId" . "_$clientSecret",
                config('constant.IcConstant.tataAig.TOKEN_CACHE_LOCK_TIME', 15)
            )
                ->block(
                    config('constant.IcConstant.tataAig.TOKEN_CACHE_LOCK_TIME', 15),
                    function () use ($clientId, $clientSecret) {

                        $token = TataAigToken::where([
                            'client_id' => $clientId,
                            'client_secret' => $clientSecret
                        ])
                            ->where('expired_at', '>=', now())
                            ->orderBy('id', 'desc')
                            ->pluck('token')
                            ->first();

                        if (!empty($token)) {
                            $token = [
                                'access_token' => $token
                            ];

                            return json_encode($token);
                        }

                        sleep(5); // Simulate delay for token generation

                        $token = TataAigToken::where([
                            'client_id' => $clientId,
                            'client_secret' => $clientSecret
                        ])
                            ->where('expired_at', '>=', now())
                            ->orderBy('id', 'desc')
                            ->pluck('token')
                            ->first();

                        if (!empty($token)) {
                            $token = [
                                'access_token' => $token
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

    public static function storeToken($additionalData, $tokenRequest, $response)
    {
        try {
            $type = $additionalData['method'] ?? $additionalData['type'] ?? null;
            $clientId = is_array($tokenRequest) ? ($tokenRequest['client_id'] ?? null) : null;
            $clientSecret = is_array($tokenRequest) ? ($tokenRequest['client_secret'] ?? null) : null;

            if (
                !empty($type) &&
                !empty($clientId) &&
                !empty($clientSecret) &&
                !empty($response) &&
                in_array(strtolower($type), [
                    'token',
                    'token generation'
                ])
            ) {
                $response = json_decode($response, true);
                $token = $response['access_token'] ?? null;

                if (!empty($token)) {

                    $exists = TataAigToken::where([
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret
                    ])->exists();
                    if ($exists) {
                        // Update all matching records
                        TataAigToken::where([
                            'client_id' => $clientId,
                            'client_secret' => $clientSecret
                        ])->update([
                            'token' => $token,
                            'expired_at' => now()->addMinutes(30),
                        ]);
                    } else {
                        TataAigToken::create([
                            'client_id' => $clientId,
                            'client_secret' => $clientSecret,
                            'token' => $token,
                            'expired_at' => now()->addMinutes(30),
                        ]);
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }
}
