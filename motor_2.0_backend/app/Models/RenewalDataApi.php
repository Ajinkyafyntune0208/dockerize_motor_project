<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalDataApi extends Model
{
    use HasFactory;

    protected $table = 'renewal_data_api';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;
}
