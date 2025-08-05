<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalDetails extends Model
{
    use HasFactory;
    protected $table = 'additional_details';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;
}
