<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPaymentModeMap extends Model
{
    use HasFactory;

    protected $table = 'user_payment_mode_map';
    protected $primaryKey = 'user_payment_mode_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
