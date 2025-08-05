<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LlCover extends Model
{
    use HasFactory;

    protected $table = 'll_cover';
    protected $primaryKey = 'll_cover_id';
    protected $guarded = [];
    public $timestamps = false;
}
