<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExcDataValidation extends Model
{
    use HasFactory;

    protected $table = 'exc_data_validation';
    protected $primaryKey = 'exc_id';
    protected $guarded = [];
    public $timestamps = false;
}
