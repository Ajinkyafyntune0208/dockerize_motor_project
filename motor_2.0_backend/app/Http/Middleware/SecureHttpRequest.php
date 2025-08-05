<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecureHttpRequest
{

    protected $except = [
        'api/aceCrmLeadId',
    ];
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip encryption for GET requests
            if((isset($request->payload) || $request->header('x-encryption') == 'keep')) {
                if($request->isMethod('POST'))
                {
                    $resDec = $this->decrypt($request);
                }

                if(isset($resDec['error']))
                {
                    $encrypted_data = $this->encryption_error();
                    return response()->json(['data' => $encrypted_data], 400);
                }
                
                $response = $next($request);
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $encrypted_data = $this->encrypt($response->content());
                    $response->setData(['data' => $encrypted_data]);
                    return $response;
                }
            }
            elseif($request->header('x-encryption') == 'keep')
            {
                $encrypted_data = $this->encryption_error();
                return response()->json(['data' => $encrypted_data], 400);
            }
            
        
        return $next($request);
    }

    private function encryption_error()
    {
        $encrypted_data = $this->encrypt(json_encode([
            'status' => false,
            "msg" => "invalid request"
        ]));
        return $encrypted_data;
    }

    public function encrypt($request)
    {
        return $encrypted_data = base64_encode(openssl_encrypt($request, 'aes-256-cbc', '01234567890123456789012345678901', OPENSSL_RAW_DATA, '1234567890123412'));
    }

    public function decrypt($request)
    {
        $decrypted_input = openssl_decrypt(base64_decode($request->payload), 'aes-256-cbc', '01234567890123456789012345678901', OPENSSL_RAW_DATA, '1234567890123412');

        if ($decrypted_input === false) {
            return [
                'error' => "invalid Request"
            ];
        }
        $decrypted_input = json_decode($decrypted_input, true);
        if(!empty($decrypted_input))
        {
            $request->merge($decrypted_input);
        }
        unset($request->payload);
        $request->replace($request->except('payload'));
    }

}
