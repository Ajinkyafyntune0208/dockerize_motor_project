<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    use HasFactory;

    protected $table = 'office';
    protected $primaryKey = 'office_id';
    protected $guarded = [];
    public $timestamps = false;
}
