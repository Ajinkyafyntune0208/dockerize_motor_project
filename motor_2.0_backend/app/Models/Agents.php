<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agents extends Model
{
    use HasFactory;
    protected $table = 'agents';
    protected $primaryKey = 'ag_id';
    protected $guarded = [];
    public $timestamps = false;
}
