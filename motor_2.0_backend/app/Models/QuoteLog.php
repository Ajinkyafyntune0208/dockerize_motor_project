<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteLog extends Model
{
    use HasFactory;

    protected $table = 'quote_log';
    protected $primaryKey = 'quote_id';
    protected $guarded = [];
    public $timestamps = false;
    protected $hidden = ['user_product_journey_id','searched_at','updated_at','quote_data'];

    protected $casts = [
        'premium_json' => 'array',
        'quote_data' => 'array'
    ];

   /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['quote_details'];
    /**
     * Get quote data in array .
     *
     * @param  string  $value
     * @return string
     */
    public function getQuoteDetailsAttribute()
    {
        return  json_decode($this->quote_data,true);
    }
    /**
     * Get the master policy associated with the quote_log.
     */
    public function master_policy()
    {
        return $this->hasOne(MasterPolicy::class,'policy_id', 'master_policy_id')->with('master_product');
    }

    public static function boot() {
        parent::boot();

        static::updating(function($item) {
            $item->updated_at = date('Y-m-d H:i:s');
        });
    }
    

}
