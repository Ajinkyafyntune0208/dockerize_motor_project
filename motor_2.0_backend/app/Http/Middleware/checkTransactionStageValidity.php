<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class checkTransactionStageValidity
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
        $routes = [
            'api/saveQuoteRequestData',
            'api/saveQuoteData',
            'api/saveAddonData',
            'api/updateQuoteRequestData',
            'api/updateUserJourney',
            'api/save',
            'api/updateJourneyUrl'
        ];

        #for decrypting Payload data
        if (count($request->all()) == 1 && isset($request->payload) && !empty($request->payload)) {
            app(\App\Http\Middleware\SecureHeaders::class)->handle($request, function ($request) use ($next) {
                return $next($request);
            });
        }
        
        $url = $request->path();
        if(in_array($url, $routes)) 
        {
            $userJourneyId = $request->enquiryId ?? $request->userProductJourneyId ?? null;

            if (empty($userJourneyId)) {
                return response()->json([
                    'status' => false,
                    'msg' => 'Enquiry ID is missing in the request.',
                ], 403);
            }

            $checkTransactionStageValidity = checkTransactionStageValidity(customDecrypt($userJourneyId));
            if(!$checkTransactionStageValidity['status']) 
            {
                return response()->json($checkTransactionStageValidity);
            }

        }
        
        return $next($request);
    }
}
