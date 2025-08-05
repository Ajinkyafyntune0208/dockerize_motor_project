<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GcvFyntuneMaster extends Model
{
    use HasFactory;

    protected $table = 'gcv_fyntune_master';
    protected $primaryKey = 'master_id';
    protected $guarded = [];
    public $timestamps = false;
}
