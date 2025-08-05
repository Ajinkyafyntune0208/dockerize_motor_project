<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterProduct extends Model
{
    use HasFactory;

    protected $table = 'master_product';
    protected $primaryKey = 'product_id';
    protected $guarded = [];
    public $timestamps = true;
}
