<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MisUserDetails extends Model
{
    use HasFactory;

    protected $table = 'mis_user_details';
    protected $primaryKey = 'mis_user_id';
    protected $guarded = [];
    public $timestamps = false;
}
