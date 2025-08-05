<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationDetails extends Model
{
    // protected $fillable = ['vehicle_reg_no','vehicle_details'];
    protected $guarded = [];
    protected $table = 'registration_details';
    use HasFactory;
}
