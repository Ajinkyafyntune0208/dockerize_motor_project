<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HidePyi extends Model
{
    protected $table = 'previous_year_insurer_toggle';
    protected $guarded = [];

    use HasFactory;
}
