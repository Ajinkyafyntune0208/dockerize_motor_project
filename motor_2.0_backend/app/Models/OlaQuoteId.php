<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OlaQuoteId extends Model
{
    use HasFactory;

    protected $table = 'ola_quote_id';
    protected $guarded = [];
    public $timestamps = false;
}
