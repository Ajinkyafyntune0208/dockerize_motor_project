<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosUser extends Model
{
    use HasFactory;

    protected $table = 'pos_user';
    protected $primaryKey = 'pos_id';
    protected $guarded = [];
    public $timestamps = false;
}
