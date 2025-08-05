<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerTable extends Model
{
    use HasFactory;

    protected $table = 'ledger_table';
    protected $primaryKey = 'ledger_id';
    protected $guarded = [];
    public $timestamps = false;
}
