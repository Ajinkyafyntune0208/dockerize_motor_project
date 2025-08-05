<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclineRto extends Model
{
    use HasFactory;

    protected $table = 'decline_rto';
    protected $primaryKey = 'decline_rto_id';
    protected $guarded = [];
    public $timestamps = false;
}
