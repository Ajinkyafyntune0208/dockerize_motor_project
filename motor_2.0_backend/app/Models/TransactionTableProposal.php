<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionTableProposal extends Model
{
    use HasFactory;

    protected $table = 'transaction_table_proposal';
    protected $primaryKey = 'transaction_id';
    protected $guarded = [];
    public $timestamps = false;
}
