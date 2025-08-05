<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoverDetails extends Model
{
    use HasFactory;

    protected $table = 'cover_details';
    protected $primaryKey = 'cover_details_id';
    protected $guarded = [];
    public $timestamps = false;
}
