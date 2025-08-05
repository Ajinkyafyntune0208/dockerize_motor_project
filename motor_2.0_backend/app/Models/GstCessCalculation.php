<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstCessCalculation extends Model
{
    use HasFactory;

    protected $table = 'gst_cess_calculation';
    protected $primaryKey = 'gst_id';
    protected $guarded = [];
    public $timestamps = false;
}
