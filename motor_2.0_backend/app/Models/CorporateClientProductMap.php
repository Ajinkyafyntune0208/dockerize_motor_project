<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateClientProductMap extends Model
{
    use HasFactory;

    protected $table = 'corporate_client_product_map';
    protected $primaryKey = 'corporate_client_product_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
