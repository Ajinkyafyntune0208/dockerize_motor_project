<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Controllers\EnhanceJourneyController;
use App\Models\WhatsappTemplate;
use App\Models\CvAgentMapping;
use App\Models\UserProductJourney;
use App\Models\SelectedAddons;
use App\Models\UserProposal;
use App\Models\MasterCompany;
use App\Models\EmbeddedLinkRequestData;
use App\Models\EmbeddedLinkWhatsappRequests;

class EmbeddedLinkGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);

        $embedded_requests = \App\Models\EmbeddedLinkRequestData::where('is_processed', 0)->where(function($query) {
            $query->where('attempts', '<', 3)->orWhereNull('attempts');
        })->limit(10)->get();

        if (count($embedded_requests) < 1) {
            return;
        }

        $responses = Http::pool(function (Pool $pool) use ($embedded_requests) {
            foreach ($embedded_requests as $embedded_request) {
                EmbeddedLinkRequestData::where('id', $embedded_request->id)
                    ->update([
                        'attempts' => $embedded_request->attempts + 1,
                        'is_processed' => 2
                    ]);

                $response[] = $pool->withoutVerifying()->acceptJson()->post($embedded_request->url . '/api/generateEmbeddedLink', $embedded_request->data);
            }

            return $response;
        });

        foreach ($embedded_requests as $key => $embedded_request) {
            $result = $responses[$key]->json() ?? $responses[$key]->body();

            EmbeddedLinkRequestData::where('id', $embedded_request->id)
                ->update([
                    'response' => $result,
                    'is_processed' => isset($result['status']) ? 1 : 0
                ]);

            httpRequest('embedded_link_dashboard_push', $result);
        }
        EmbeddedLinkRequestData::where('updated_at', '<', now()->subHours(2))->where( 'is_processed', 2)->update([
            "is_processed" => 0,
            "attempts" => 0
        ]);

    }
}
