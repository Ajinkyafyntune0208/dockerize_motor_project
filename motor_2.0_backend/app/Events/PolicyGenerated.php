<?php

namespace App\Events;

use App\Models\PolicyDetails;
use App\Models\UserProposal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PolicyGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $enquiryId;
    public $policyDetails;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(PolicyDetails $policyDetails)
    {
        $this->enquiryId = UserProposal::find($policyDetails->proposal_id)->first()->user_product_journey_id ?? 0;
        $this->policyDetails = $policyDetails;
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
