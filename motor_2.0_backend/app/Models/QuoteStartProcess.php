<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteStartProcess extends Model
{
    use HasFactory;
    protected $table = 'quote_start_process';
    protected $guarded = [];
    public $timestamps = true;
}
