<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class getUserRequestResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $hide_proposal_fields = [
            'fk_proposal_detail_id',
            'user_product_journey_id',
            'additional_details',
            'proposal_stage',
            'fk_quote_id',
            'user_proposal_id'
        ];
        $hide_addons_fields = [
            'user_product_journey_id',
            'created_at',
            'updated_at'
        ];
        $addons = [];

        foreach ($this->addons->makeHidden($hide_addons_fields) as $key => $addon) {
           $addons[$key]['addons'] = json_decode($addon->addons, true);
           $addons[$key]['accessories'] = json_decode($addon->accessories, true);
           $addons[$key]['additional_covers'] = json_decode($addon->additional_covers, true);
           $addons[$key]['voluntary_insurer_discounts'] = json_decode($addon->voluntary_insurer_discounts, true);
        }
        $data = [
            "inputDetails" => [
                "user_product_journey_id" => customEncrypt($this->userProductJourneyId),
                "quote_data" => isset($this->quote_log) ? json_decode($this->quote_log->quote_data, true) : null,
            ],
            "selectedQuoteDetails" => isset($this->quote_log) ? json_decode($this->quote_log->premium_json, true) : null,
            "addons" => $addons,
            "proposalDetails" => isset($this->user_proposal) ? json_decode($this->user_proposal->makeHidden($hide_proposal_fields)) : null,
            "paymentDetails" => []

        ];
        return camelCase($data);
    }
}
