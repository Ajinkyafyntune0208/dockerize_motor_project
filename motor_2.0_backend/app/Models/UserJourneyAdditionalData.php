<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserJourneyAdditionalData extends Model
{
    use HasFactory;
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = true;
}
