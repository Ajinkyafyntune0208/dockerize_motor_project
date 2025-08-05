<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NomineeRelationship extends Model
{
    use HasFactory;
    protected $table = 'nominee_relationship';
    protected $guarded = [];
}
