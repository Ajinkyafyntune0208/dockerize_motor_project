<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class PolicyDownloadController extends Controller
{
    public function download($enquiryId)
    {
        $userProductJourneyId = customDecrypt($enquiryId);
        $userProductJourney = \App\Models\UserProductJourney::where('user_product_journey_id', $userProductJourneyId)
            ->with([
                'user_proposal.policy_details' => function ($query) {
                    $query->select('pdf_url', 'proposal_id');
                },
            ])
            ->first();
        
        if (empty($userProductJourney?->user_proposal?->policy_details?->pdf_url)) {
            return 'Policy Document Not Found';
        }

        $pdfUrl = $userProductJourney?->user_proposal?->policy_details?->pdf_url;
        $response = Http::get($pdfUrl);

        if ($response->successful()) {
            $content = $response->body();
            $contentType = $response->header('Content-Type');

            return response($content, 200)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'attachment; filename='.$enquiryId.'.pdf');
        } else {
            return 'Policy Document Not Found';
        }
    }

    public function downloadWithUrl($url)
    {
        if (empty($url))
        {
            return 'Invalid URL';
        }
        $url = decrypt($url);
        $response = Http::get($url);
        if ($response->successful())
        {
            $content = $response->body();
            $contentType = $response->header('Content-Type');
			preg_match('/\/([^\/\?]+\.pdf)/', $url, $matches);
            return response($content, 200)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'attachment; filename='.$matches[1]);
        }
		else
		{
            return response("Invalid URL", 200);
        }
    }
}
