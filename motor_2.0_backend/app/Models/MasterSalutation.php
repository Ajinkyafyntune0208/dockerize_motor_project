<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterSalutation extends Model
{
    use HasFactory;

    protected $table = 'master_salutation';
    protected $primaryKey = 'salutation_id';
    protected $guarded = [];
    public $timestamps = false;
}
