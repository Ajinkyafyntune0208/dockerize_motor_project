<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequestResponse extends Model
{
    use HasFactory;

    protected $table = 'payment_request_response';
    protected $guarded = [];
    protected $appends = ['journey_id'];
    public $timestamps = false;

    public static function boot() {
        parent::boot();

        static::updating(function($item) {
            $item->updated_at = date('Y-m-d H:i:s');
        });
    }

    public function user_proposal()
    {
        return $this->hasOne(UserProposal::class, 'user_proposal_id', 'user_proposal_id');
    }
    public function getJourneyIdAttribute()
    {
            return customEncrypt($this->user_product_journey_id);
    }
}
