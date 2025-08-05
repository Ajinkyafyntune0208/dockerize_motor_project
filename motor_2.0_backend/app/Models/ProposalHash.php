<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalHash extends Model
{
    use HasFactory; 
    protected $fillable = [
        'user_product_journey_id',
        'user_proposal_id',
        'hash',
        'additional_details_data'
    ];
}
