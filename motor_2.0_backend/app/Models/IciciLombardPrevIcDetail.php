<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciLombardPrevIcDetail extends Model
{
    use HasFactory;

    protected $table = 'icici_lombard_prev_ic_detail';
    protected $primaryKey = 'company_id';
    protected $guarded = [];
    public $timestamps = false;
}
