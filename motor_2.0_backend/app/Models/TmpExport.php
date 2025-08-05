<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TmpExport extends Model
{
    use HasFactory;

    protected $table = 'tmp_export';
    protected $guarded = [];
    public $timestamps = false;
}
