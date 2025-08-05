<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AceTokenRoleData extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user_product_journey()
    {
        $this->belongsTo(\App\Models\UserProductJourney::class, 'user_product_journey_id');
    }
}
