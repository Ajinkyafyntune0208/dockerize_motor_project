<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FtIcRtoMap extends Model
{
    use HasFactory;

    protected $table = 'ft_ic_rto_map';
    protected $primaryKey = 'ic_id';
    protected $guarded = [];
    public $timestamps = false;
}
