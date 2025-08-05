<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AckoPreviousInsurer extends Model
{
    use HasFactory;

    protected $table = 'acko_previous_insurer';
    protected $primaryKey = 'company_id';
    protected $guarded = [];
    public $timestamps = false;
}
