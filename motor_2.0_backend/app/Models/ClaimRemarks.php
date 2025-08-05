<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimRemarks extends Model
{
    use HasFactory;

    protected $table = 'claim_remarks';
    protected $guarded = [];
    public $timestamps = false;
}
