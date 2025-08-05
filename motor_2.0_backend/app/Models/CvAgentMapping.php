<?php

namespace App\Models;

use App\Models\CvJourneyStages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CvAgentMapping extends Model
{
    use HasFactory;
 //   protected $hidden = ['user_product_journey_id', 'created_at', 'updated_at'];
     /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    public function journeyStage()
    {
        return $this->belongsTo(CvJourneyStages::class, 'user_product_journey_id','user_product_journey_id');
    }

    
}
