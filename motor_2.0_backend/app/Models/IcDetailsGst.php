<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcDetailsGst extends Model
{
    use HasFactory;

    protected $table = 'ic_details_gst';
    protected $primaryKey = 'ic_details_gst_id';
    protected $guarded = [];
    public $timestamps = false;
}
