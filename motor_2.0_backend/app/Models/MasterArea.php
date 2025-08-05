<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterArea extends Model
{
    use HasFactory;

    protected $table = 'master_area';
    protected $primaryKey = 'area_id';
    protected $guarded = [];
    public $timestamps = false;
}
