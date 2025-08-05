<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimSettlement extends Model
{
    use HasFactory;

    protected $table = 'claim_settlement';
    protected $guarded = [];
    public $timestamps = false;
}
