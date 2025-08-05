<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TataAigFinanceMaster extends Model
{
    use HasFactory;

    protected $table = 'tata_aig_finance_master';
    protected $primaryKey = null;
    protected $guarded = [];
    public $timestamps = false;
}
