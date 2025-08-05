<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommonTransactionDetail extends Model
{
    use HasFactory;

    protected $table = 'common_transaction_detail';
    protected $primaryKey = 'c_id';
    protected $guarded = [];
    public $timestamps = false;
}
