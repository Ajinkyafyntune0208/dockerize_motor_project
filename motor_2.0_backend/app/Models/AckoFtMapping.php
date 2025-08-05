<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AckoFtMapping extends Model
{
    use HasFactory;

    protected $table = 'acko_ft_mapping';
    protected $guarded = [];
    public $timestamps = false;
}
