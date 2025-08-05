<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciLombardPincodeMaster extends Model
{
    use HasFactory;

    protected $table = 'icici_lombard_pincode_master';
    protected $guarded = [];
    public $timestamps = false;
    /**
     * Get the state for the pincode.
     */
    public function state()
    {
        return $this->hasOne(IciciLombardStateMaster::class,'il_state_id','il_state_id');
    }
}
