<?php

namespace App\Models\Finsall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinsallTransactionData extends Model
{
    use HasFactory;

    protected $table = 'finsall_transaction_data';
    protected $guarded = [];
}
