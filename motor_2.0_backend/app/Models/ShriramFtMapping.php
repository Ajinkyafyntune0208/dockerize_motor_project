<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShriramFtMapping extends Model
{
    use HasFactory;

    protected $table = 'shriram_ft_mapping';
    protected $guarded = [];
    public $timestamps = false;
}
