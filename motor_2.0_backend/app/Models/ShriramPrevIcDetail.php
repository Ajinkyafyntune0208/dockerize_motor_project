<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShriramPrevIcDetail extends Model
{
    use HasFactory;

    protected $table = 'shriram_prev_ic_detail';
    protected $primaryKey = 'shriram_prev_ic_detail_id';
    protected $guarded = [];
    public $timestamps = false;
}
