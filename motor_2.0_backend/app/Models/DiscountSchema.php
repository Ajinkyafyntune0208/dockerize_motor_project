<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountSchema extends Model
{
    use HasFactory;

    protected $table = 'discount_schema';
    protected $primaryKey = 'discount_schema_id';
    protected $guarded = [];
    public $timestamps = false;
}
