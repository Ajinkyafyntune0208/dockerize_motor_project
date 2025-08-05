<?php

namespace App\Http\Middleware;

use App\Models\UserJourneyActivity;
use Closure;
use Illuminate\Http\Request;
use App\Models\UserProposal;
use Illuminate\Support\Facades\Route;

class BlockMultipleJourneyUpdate
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
        //From front end token will be received in the headers
        $headers = $request->header();
        if (config('MULTIPLE_JOURNEY_DISABLE') == 'Y' || (($headers['testblockmultiplejourney'][0] ?? 'N') == 'Y')) {
            $allowedApis = config('userActivity.apis', []);

            $currentRoute = Route::getRoutes()->match($request);

            if (in_array($currentRoute->uri(), $allowedApis)) {
                $isNewTokenGenerated = false;
                $enquiryId = customDecrypt($request->enquiryId ?? $request->userProductJourneyId);

                $userActivity = UserJourneyActivity::where('user_product_journey_id', $enquiryId)->first();

                if (!empty($userActivity) && !empty($userActivity->st_token)) {

                    $errorMessage = 'Access violation: Multiple users are currently accessing this journey.';

                    // check whether user is inactive
                    $expireDuration = '+'.config('USER_INACTIVITY_TIME', 5). 'minutes';
                    $expireTime = date('d-m-Y H:i:s', strtotime($expireDuration, strtotime($userActivity->updated_at)));

                    if (strtotime($expireTime) > time()) {

                        if (isset($headers['lstoken'])) {
                            $lsToken = $headers['lstoken'][0] ?? null;
                            if ($lsToken != $userActivity->ls_token){

                                //check and compare st token
                                $stToken = $headers['sttoken'][0] ?? null;

                                if ($userActivity->st_token != $stToken) {
                                    return response()->json([
                                        'status' => false,
                                        'message' => $errorMessage
                                    ]);
                                }

                                // if st token is correct the generate new token
                                generateUserActivityToken($enquiryId);
                                $isNewTokenGenerated = true;
                            }
                        } else {
                            
                            //if st token is present and ls is not there, that means url  with st stoken is shared with other user
                            $stToken = $headers['sttoken'][0] ?? null;

                            if ($userActivity->st_token != $stToken) {
                                return response()->json([
                                    'status' => false,
                                    'message' => $errorMessage
                                ]);
                            }

                            
                            generateUserActivityToken($enquiryId);
                            $isNewTokenGenerated = true;
                        }

                        
                    } else {
                        generateUserActivityToken($enquiryId);
                        $isNewTokenGenerated = true;
                    }
                } else {

                    //if st token is not present in the headers or in the table, generate new token
                    generateUserActivityToken($enquiryId);
                    $isNewTokenGenerated = true;
                }
                $response=$next($request);
                $content = json_decode($response->content(), true);

                $userActivity = UserJourneyActivity::where('user_product_journey_id', $enquiryId)->first();

                UserJourneyActivity::where('user_product_journey_id', $enquiryId)->update([]);

                if ($isNewTokenGenerated) {
                    //append the tokens in the response
                    if (isset($content['responseToken'])) {
                        $content['responseToken']['stToken'] = $userActivity->st_token;
                        $content['responseToken']['lsToken'] = $userActivity->ls_token;
                    } else {
                        $content['responseToken'] = [
                            'stToken' => $userActivity->st_token,
                            'lsToken' => $userActivity->ls_token
                        ];
                    }
                }
                return response()->json($content);
            }
        }
        return $next($request);
    }
}
