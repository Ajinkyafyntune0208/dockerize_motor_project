<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcProduct extends Model
{
    use HasFactory;

    protected $table = 'ic_product';
    protected $primaryKey = 'ic_policy_id';
    protected $guarded = [];
    public $timestamps = false;

    /**
     * Define a relationship with the IcProduct model.
     */

    public function masterPolicy()
    {
        return $this->belongsTo(MasterPolicy::class, 'insurance_company_id');
    }
}
