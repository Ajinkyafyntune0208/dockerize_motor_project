<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisibilityReportLogSummary extends Model
{
    use HasFactory;

    protected $table = 'visibility_report_log_summary';

    protected $fillable = ['from'];

    protected $casts = [
        'data' => 'array',
    ];
}
