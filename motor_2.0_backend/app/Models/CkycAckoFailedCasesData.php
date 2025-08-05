<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CkycAckoFailedCasesData extends Model
{
    use HasFactory;
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;
}
