<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VahanImportExcelLogs extends Model
{
    protected $guarded = [];
    protected $table = 'vahan_import_excel_logs';
    use HasFactory;

    public function getFilePathAttribute($value)
    {
        if (config('filesystems.default') == 's3') {
            return file_url($value);
        }
        return $value;
    }
}