<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutMaster extends Model
{
    use HasFactory;

    protected $table = 'payout_master';
    protected $primaryKey = 'Payout_Id';
    protected $guarded = [];
    public $timestamps = false;
}
