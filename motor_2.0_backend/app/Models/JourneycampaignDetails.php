<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneycampaignDetails extends Model
{
    use HasFactory;
    protected $table = 'journey_campaign_details';
    protected $fillable = [
        'user_product_journey_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'lead_source'
    ];
}