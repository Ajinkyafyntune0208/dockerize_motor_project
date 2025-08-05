<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiExceptionHandler
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
        $response = $next($request);
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return $response;
        }
        if ($response->exception) {
            if ($response->exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Invalid request URL. Please check the requested URL and try again.',
                    'errorSpecific' => $response->exception->getMessage(),
                ], 404);
            }
            if ($response->exception->getMessage() == 'Unauthenticated.') {
                return redirect()->route('admin.login');
            }
            $this->storeErrorMsg($request, $response);
            $message = config('SYSTEM_GENERIC_ERROR_MESSAGE', 'Something went wrong while processing your request.');
            
            if (stripos($response->exception->getMessage(), 'cURL error 28') !== false) {
                $message = config('API_TIMEOUT_ERROR_MESSAGE', 'API operation timed out. Kindly try after some time.');
            }

            return response()->json([
                'status' => false,
                'msg' => $message,
                'errorSpecific' => $response->exception->getMessage(),
                // 'file' => $response->exception->getFile(),
                // 'line' => $response->exception->getLine(),
                // 'trace' => $response->exception->getTrace(),
            ]);
        } else {
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $d = $response->original;
                $is_status_false = isset($d['status']) && ($d['status'] === false);
                $is_message_empty = isset($d['message']) && empty($d['message']);
                $is_msg = isset($d['msg']) && empty($d['msg']);
                if ($is_message_empty && $is_status_false) {
                    return $this->returnDefaultMessage($d);
                } else if ($is_msg && $is_status_false) {
                    return $this->returnDefaultMessage($d);
                }
            } else {
                $content = $response->content();
                $is_status_false = isset($content['status']) && ($content['status'] === false);
                $is_message_empty = isset($content['message']) && empty($content['message']);
                $is_msg = isset($content['msg']) && empty($content['msg']);
                if ($is_message_empty && $is_status_false) {
                    return $this->returnDefaultMessage($content);
                } else if ($is_msg && $is_status_false) {
                    return $this->returnDefaultMessage($content);
                }
            }
            return $response;
        }
    }
    public function returnDefaultMessage($actualResponse = [])
    {
        $responseData = [
            'status' => false,
            'message' => config('BLANK_SYSTEM_GENERIC_ERROR_MESSAGE', 'We are unable to process your request, please try again.'),
            'detail' => 'Blank message received in the API response.',
            'actualResponse' => $actualResponse,
        ];

        if ($actualResponse['data']['dummyTile'] ?? null) {
            $responseData['data'] = $actualResponse['data'];
        }
        return response()->json($responseData);
    }

    public function storeErrorMsg($request, $response)
    {
        try {
            if (empty($response->exception->getMessage())) {
                return false;
            }
            \App\Models\MotorServerErrors::insert([
                'url' => $request->path(),
                'request' => json_encode($request->all()),
                'error' => $response->exception->getMessage(),
                'source' => app()->runningInConsole() ? 'cron' : 'api',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception$e) {
            \Illuminate\Support\Facades\Log::info('Error occured while saving the Error Msg : ' . $e->getMessage());
        }

    }

}
