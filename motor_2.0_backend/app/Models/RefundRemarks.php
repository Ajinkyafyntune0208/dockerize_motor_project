<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundRemarks extends Model
{
    use HasFactory;

    protected $table = 'refund_remarks';
    protected $guarded = [];
    public $timestamps = false;
}
