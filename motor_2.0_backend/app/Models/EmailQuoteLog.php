<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailQuoteLog extends Model
{
    use HasFactory;

    protected $table = 'email_quote_log';
    protected $primaryKey = 'email_quote_log_id';
    protected $guarded = [];
    public $timestamps = false;
}
