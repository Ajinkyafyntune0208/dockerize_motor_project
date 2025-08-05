<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VahanFileLogs extends Model
{
    protected $guarded = [];
    protected $table = 'vahan_file_logs';
    use HasFactory;
}
