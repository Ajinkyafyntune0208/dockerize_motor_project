<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateTokenHeaderRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (config('middleware.request.mandatory.validation') === 'Y' && empty($request->header('validation'))) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Request. Request header missing.',
            ], 403);
        }
        if ($request->header('validation')) {
            try {
                $key = $this->keyDecrypt($request->header('Validation'));
                list($psk, $encoded_full_path, $validity, $user_agent) = explode('|', $key);

                $encoded_full_path = trim(urldecode($encoded_full_path));
                $encoded_full_path = parse_url($encoded_full_path, PHP_URL_PATH);

                // Find the position of "/api" and extract the substring
                $apiStartPos = strpos($encoded_full_path, '/api');
                if ($apiStartPos !== false) {
                    $encoded_full_path = substr($encoded_full_path, $apiStartPos);
                }

                $route = $request->route();
                $uri = $route ? $route->uri() : null;
                $parameters = $route ? $route->parameters() : [];

                $currentRouteUri = $uri;
                foreach ($parameters as $key => $value) {
                    $currentRouteUri = str_replace("{{$key}}", $value, $currentRouteUri);
                }

                if (str_ends_with($encoded_full_path, $currentRouteUri) === false) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid Request.',
                    ], 403);
                }

                $exploded_parts = collect(explode('~:~', $psk));
                $decrypted_string = '';
                $exploded_parts->each(function ($enc_part) use (&$decrypted_string) {
                    if (strpos($enc_part, '{', 0) !== false && strpos($enc_part, '}', strlen($enc_part) - 1) !== false) {
                        $bracets_removed = str_replace(['{', '}'], '', $enc_part);
                        $decrypted_string .= $this->keyDecrypt($bracets_removed);
                    } else {
                        $decrypted_string .= $enc_part;
                    }
                });
                $validity = str_replace('/', '-', $validity);
                $decrypted_string = str_replace('"', '', $decrypted_string);
                // PSK and Date validation
                if ($decrypted_string != 'ijHjx4/alAwjLu1ftuwLF3g0w4pNORaol9GQ4Y0qYVM=' || \Illuminate\Support\Carbon::parse($validity) <= now()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid Request.',
                    ], 403);
                }
            } catch (\Exception $e) {
                info($e);
                return response()->json([
                    'status' => false,
                    'message' => 'Error occured while validiting the incoming request. Incorrect request.',
                ], 403);
            }
        }
        return $next($request);
    }

    public function keyDecrypt($encyptedKey)
    {
        return openssl_decrypt(base64_decode($encyptedKey), 'aes-256-cbc', '01234567890123456789012345678901', OPENSSL_RAW_DATA, '1234567890123412');
    }
}
