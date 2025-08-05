<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TataFtMapping extends Model
{
    use HasFactory;

    protected $table = 'tata_ft_mapping';
    protected $guarded = [];
    public $timestamps = false;
}
