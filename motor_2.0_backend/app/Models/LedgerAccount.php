<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    use HasFactory;

    protected $table = 'ledger_account';
    protected $primaryKey = 'ledger_id';
    protected $guarded = [];
    public $timestamps = false;
}
