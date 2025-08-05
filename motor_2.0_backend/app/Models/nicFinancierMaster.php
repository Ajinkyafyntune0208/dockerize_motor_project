<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class nicFinancierMaster extends Model
{
    use HasFactory;

    protected $table = 'nic_finance_master';
    protected $guarded = [];
    public $timestamps = false;
}
