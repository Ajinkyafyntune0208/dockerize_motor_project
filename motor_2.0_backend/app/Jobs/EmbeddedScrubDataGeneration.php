<?php

namespace App\Jobs;

use App\Http\Controllers\EnhanceJourneyController;
use App\Models\EmbeddedScrubData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

class EmbeddedScrubDataGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        set_time_limit(0);
        
        \App\Models\EmbeddedScrubData::where('is_processed', 2)->where('updated_at', '<', now()->subHours(1))->update([
            'is_processed' => 0
        ]);

        $embedded_requests = EmbeddedScrubData::where('is_processed', 0)->where('attempts', '<', 3)->limit(8)->get();

        if ($embedded_requests)
        {
            $enhance_journey_controller = new EnhanceJourneyController;

            EmbeddedScrubData::whereIn('id', $embedded_requests->pluck('id'))->update([
                'is_processed' => 2
            ]);

            foreach ($embedded_requests as $embedded_request)
            {
                EmbeddedScrubData::where('id', $embedded_request->id)
                    ->update([
                        'attempts' => $embedded_request->attempts + 1
                    ]);

                $response = $enhance_journey_controller->getScrubData(request()->replace($embedded_request->request));

                $result = json_decode($response->content(), TRUE);

                EmbeddedScrubData::where('id', $embedded_request->id)
                    ->update([
                        'response' => json_encode($result, JSON_UNESCAPED_SLASHES),
                        'is_processed' => isset($result['status']) && $result['status'] ? 1 : 0
                    ]);

                httpRequest('embedded_link_dashboard_push', $result);
            }
        }
    }
}
