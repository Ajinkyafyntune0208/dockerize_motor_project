<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VahanExportLog extends Model
{
    use HasFactory;
    protected  $table = 'vahan_export_logs';

    protected $fillable = ['user_id','request','file','file_expiry','file_deleted','uid','source'];

}
