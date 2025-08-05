<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditTriggerTable extends Model
{
    use HasFactory;

    protected $table = 'credit_trigger_table';
    protected $primaryKey = 'trigger_id';
    protected $guarded = [];
    public $timestamps = false;
}
