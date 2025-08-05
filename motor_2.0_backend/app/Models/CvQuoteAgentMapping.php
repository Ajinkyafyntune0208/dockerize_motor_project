<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvQuoteAgentMapping extends Model
{
    public $table = 'cv_quote_agent_mapping';
    protected $hidden = ['user_product_journey_id', 'created_at', 'updated_at'];
    use HasFactory;
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];


    /**
     * Get the journey till quotes for the agent.
     */
    public function journey_till_quotes()
    {
        return $this->hasMany(QuoteLog::class, 'user_product_journey_id', 'user_product_journey_id');
    }
    /**
     * Get the proposals for the agent.
     */
    public function journey_till_proposals()
    {
        return $this->hasMany(UserProposal::class, 'user_product_journey_id', 'user_product_journey_id');
    }
}
