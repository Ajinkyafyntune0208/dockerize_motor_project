<?php

namespace App\Events;

use App\Models\JourneyStage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JourneyStageUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $journeyStage;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    //Calling this event when Journey Stages are Updated/Created
    public function __construct(JourneyStage $event)
    {
        $this->journeyStage = $event;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
