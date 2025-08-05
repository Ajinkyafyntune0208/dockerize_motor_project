<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyUpdateLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'trace_id',
        'user_id',
        'action_type',
        'old_data',
        'new_data',
        'policy_number',
        'screenshot_url',
        'source'
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function getScreenshotUrlAttribute($value){
        if (!is_null($value)) {
            // return Storage::url($value);
            return file_url($value);
        }
        return $value;
    }
}