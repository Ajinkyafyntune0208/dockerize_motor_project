<?php

namespace App\Http\Middleware;

use App\Models\InternalApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class ApiLogMiddleware
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
        $response =  $next($request);
        $currentRouteUri = $request->route()?->uri();

        $routeList = [
            'api/saveQuoteRequestData',
            'api/updateQuoteRequestData',
            'api/save',
            'api/ckyc-verifications',
        ];

        if (in_array($currentRouteUri, $routeList)) {
            
            $logResponse = $response;
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $logResponse = $response->getData(true);
                if (is_array($logResponse)) {
                    unset(
                        $logResponse['queries'],
                        $logResponse['total_query_time'],
                        $logResponse['memory']
                    );
                }

                $logResponse = json_encode($logResponse);
            }

            InternalApiLog::create([
                'endpoint' => $currentRouteUri,
                'enquiry_id' => $request->enquiryId ?? $request->userProductJourneyId ?? null,
                'request' => $request->input(),
                'response' => $logResponse
            ]);
        }
        return $response;
    }
}
