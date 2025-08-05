<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterIcRtoTest extends Model
{
    use HasFactory;

    protected $table = 'master_ic_rto_test';
    protected $guarded = [];
    public $timestamps = false;
}
