<?php

namespace App\Http\Controllers\Ckyc;

use App\Http\Controllers\Controller;
use App\Models\ProposalHash;
use Illuminate\Http\Request;

class statusCheckController extends Controller
{
    public static function checkStatus($proposal, $ckycHash){

        // $checkProposalHash = ProposalHash::where(['user_product_journey_id'=> $proposal->user_product_journey_id, 'hash' => $ckycHash])->exists();

        $checkProposalHash = ProposalHash::where(['user_product_journey_id' => $proposal->user_product_journey_id, 'hash' => $ckycHash])
        ->latest('created_at') // Order by the created_at column in descending order
        ->first();

        if ((config('constants.IS_CKYC_ENABLED') == 'Y') &&  !empty($proposal->proposal_no ?? '')) {
            if ($checkProposalHash) {
                $responseArray = [
                    "status" => true,
                    "ckyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                    "kyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                    "msg" => "Proposal Submited Successfully..!",
                    "data" => [
                        "ckyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        "kyc_status" => (($proposal->is_ckyc_verified == 'Y') ? true : false),
                        'proposalId' => $proposal->user_proposal_id,
                        'userProductJourneyId' => $proposal->user_product_journey_id,
                        'proposalNo' => $proposal->proposal_no,
                        'finalPayableAmount' => $proposal->final_payable_amount,
                    ]
                ];

                return ['msg' => true, 'data' => $responseArray];
            }
        }

        return ['msg' => false];
    }
}

?>
