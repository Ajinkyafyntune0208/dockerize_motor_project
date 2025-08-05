<?php

namespace App\Jobs;

use App\Models\EmbeddedLinkWhatsappRequests;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EmbeddedLinkWhatsappMessageSending implements ShouldQueue
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
        $embedded_whatsapp_requests = \App\Models\EmbeddedLinkWhatsappRequests::whereBetween('scheduled_at', [now()->subDay()->format('Y-m-d 00:00:00'), now()->subDay()->format('Y-m-d 23:59:59')])
            ->get(); // subDay() added to fetch whatsapp messages for the rc numbers which were processed yesterday

        foreach ($embedded_whatsapp_requests as $request_data)
        {
            $whatsapp_response = httpRequest('whatsapp', json_decode($request_data->request, TRUE));
            
            if ($whatsapp_response != null) {
                \App\Models\WhatsappRequestResponse::create([
                    'ip' => request()->ip(),
                    'enquiry_id' => $request_data->enquiry_id,
                    'request_id' => $whatsapp_response['response']['data'][0]['message_id'],
                    'mobile_no' => $whatsapp_response['response']['data'][0]['recipient'],
                    'request' => $whatsapp_response['request'],
                    'response' => $whatsapp_response['response'],
                ]);

                if (config('constants.LSQ.IS_LSQ_ENABLED') == 'Y' && ! is_null($request_data->lsq_activity_data))
                {
                    $activity_data = json_decode($request_data->lsq_activity_data, TRUE);

                    createLsqActivity($activity_data['enquiry_id'], $activity_data['create_lead_on'], $activity_data['message_type'], $activity_data['additional_data']);
                }

                $request_data->delete();
            }
        }
    }
}
