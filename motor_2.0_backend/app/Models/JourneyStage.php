<?php

namespace App\Models;

use App\Events\JourneyStageUpdated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneyStage extends Model
{
    use HasFactory;

    protected $table = 'cv_journey_stages';
    protected $primaryKey = 'id';
    protected $guarded = [];

    protected $dispatchesEvents = [
        "saved" => JourneyStageUpdated::class,
    ];

    public function getCreatedAtAttribute($date)
    {
        return \Carbon\Carbon::parse($date)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function getUpdatedAtAttribute($date)
    {
        return \Carbon\Carbon::parse($date)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    public function user_product_journay()
    {
        return $this->belongsTo(UserProductJourney::class, 'user_product_journey_id', 'user_product_journey_id');
    }

}
