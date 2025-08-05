<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvProposalAgentMapping extends Model
{
    public $table = 'cv_proposal_agent_mapping';
    protected $hidden = ['user_product_journey_id', 'created_at', 'updated_at'];
    public $timestamps = false;
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
