<?php

namespace App\Http\Middleware;

use App\Models\CvAgentMapping;
use App\Models\UserProductJourney;
use Closure;
use Illuminate\Http\Request;

class BlockJourneyMiddleware
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
        if (config('B2C_JOURNEY_DISABLE') === 'Y') {
            if (!empty($request->userProductJourneyId) || !empty($request->enquiryId)) {
                $enquireId = $request->userProductJourneyId ?? $request->enquiryId;
                $enquireId = (strlen($enquireId) < 16 ) ? customEncrypt($enquireId) : $enquireId;
                $b2c_journey_disable_by_seller = config('B2C_JOURNEY_DISABLE_BY_SELLER_TYPE');
                $b2c_journey_disable_by_sellers = explode(',',strtoupper($b2c_journey_disable_by_seller));
                
                
                $agent_details = CvAgentMapping::where('user_product_journey_id', customDecrypt($enquireId))
                                ->whereNotNull('seller_type')
                                ->where(function($query) use ($b2c_journey_disable_by_sellers){
                                    if (!empty($b2c_journey_disable_by_sellers)) {
                                        $query->whereNotIn('seller_type', $b2c_journey_disable_by_sellers);
                                    }
                                })
                                ->count();
            
                // NEW BLOCK ADDED FOR SECTIONWISE B2C BLOCK
                $b2c_journey_disable = config('B2C_JOURNEY_DISABLE_BY_SECTION');
                $b2c_journey_disable_section = explode(',',strtoupper($b2c_journey_disable)); 
                $user_product_journey_data = UserProductJourney::where('user_product_journey_id',customDecrypt($enquireId))
                                    ->first();
                $product_sub_type_id = $user_product_journey_data->product_sub_type_id;
                $b2c_check = false;
                $sections = [
                    1 => 'CAR',
                    2 => 'BIKE'
                ];
                $selected_journey = $sections[$product_sub_type_id] ?? 'CV';
                if(in_array($selected_journey, $b2c_journey_disable_section)) 
                {
                    $b2c_check = true;
                }
                // END
            }
            if ((in_array($request->path(), ['api/save', 'api/submit', 'api/saveQuoteRequestData', 'api/make-payment', 'api/car/make-payment', 'api/bike/make-payment']))) {
                if (($request->path() == "api/saveQuoteRequestData" && $request->stage != 1) && $b2c_check) {
                    if ($agent_details == 0) {
                        if(isset($request->productSubTypeCode))
                        {
                            $productSubTypeCode = ($request->productSubTypeCode == "BIKE" || $request->productSubTypeCode == "CAR") ? $request->productSubTypeCode : 'CV';
                        }else
                        {
                            $productSubTypeCode = $selected_journey;
                        }

                        if ($response instanceof \Illuminate\Http\JsonResponse) {
                            return $response->setData([
                                "status" => false,
                                "msg" => ucfirst($productSubTypeCode).' insurance is not available at moment',
                            ]);
                        } else{
                            abort(500);
                        }
                    }
                } else if (in_array($request->path(), ['api/save', 'api/submit', 'api/make-payment', 'api/car/make-payment', 'api/bike/make-payment']) && $agent_details == 0 && $b2c_check) {
                    if ($response instanceof \Illuminate\Http\JsonResponse) {
                        return $response->setData([
                            "status" => false,
                            "msg" => 'Access Control Error. User login required',
                        ]);
                    } else {
                        abort(500);
                    }
                }

            }

        }

        return $response;
    }
}
