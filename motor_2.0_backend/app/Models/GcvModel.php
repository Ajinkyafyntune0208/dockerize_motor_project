<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GcvModel extends Model
{
    use HasFactory;

    protected $table = 'gcv_model';
    protected $primaryKey = 'model_id';
    protected $guarded = [];
    public $timestamps = false;
}
