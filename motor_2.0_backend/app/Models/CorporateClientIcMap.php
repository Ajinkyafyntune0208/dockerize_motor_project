<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateClientIcMap extends Model
{
    use HasFactory;

    protected $table = 'corporate_client_ic_map';
    protected $primaryKey = 'corporate_client_ic_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
