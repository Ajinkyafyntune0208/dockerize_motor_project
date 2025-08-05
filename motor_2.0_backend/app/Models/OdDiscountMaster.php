<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OdDiscountMaster extends Model
{
    use HasFactory;

    protected $table = 'od_discount_master';
    protected $primaryKey = 'od_discount_id';
    protected $guarded = [];
    public $timestamps = false;
}
