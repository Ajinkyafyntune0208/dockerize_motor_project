<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutHeadsMaster extends Model
{
    use HasFactory;

    protected $table = 'payout_heads_master';
    protected $primaryKey = 'Payout_Head_Id';
    protected $guarded = [];
    public $timestamps = false;
}
