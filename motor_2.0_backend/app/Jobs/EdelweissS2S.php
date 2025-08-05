<?php

namespace App\Jobs;

use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use App\Models\ServerToServerModel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class EdelweissS2S implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $request;
    protected $section;
    protected $s2s_id;
    public function __construct($request, $section, $s2s_id)
    {
        $this->request = $request;
        $this->section = $section;
        $this->s2s_id = $s2s_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        switch ($this->section) {
            case 'car':
                $return_data = \App\Http\Controllers\Payment\Services\Car\edelweissPaymentGateway::serverToServer($this->request);
                $data = ServerToServerModel::find($this->s2s_id);
                $data->system_response = json_encode($return_data);
                $data->save();

                break;

            case 'bike':
                $return_data = \App\Http\Controllers\Payment\Services\Bike\edelweissPaymentGateway::serverToServer($this->request);
                $data = ServerToServerModel::find($this->s2s_id);
                $data->system_response = json_encode($return_data);
                $data->save();

                break;
        }
    }
}
