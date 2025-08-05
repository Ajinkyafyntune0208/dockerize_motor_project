<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class edelweissFinancierMaster extends Model
{
    use HasFactory;

    protected $table = 'edelweiss_finance_master';
    protected $guarded = [];
    public $timestamps = false;
}
