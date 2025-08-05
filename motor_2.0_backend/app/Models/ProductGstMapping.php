<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGstMapping extends Model
{
    use HasFactory;

    protected $table = 'product_gst_mapping';
    protected $primaryKey = 'Product_GST_Mapping_ID';
    protected $guarded = [];
    public $timestamps = false;
}
