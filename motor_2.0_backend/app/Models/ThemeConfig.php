<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThemeConfig extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected  $casts = [
        "theme_config" => "array",
        "otp_config" => "array",
        "broker_config" => "array",
    ];

    static public function active()
    {
        return self::where('status', 'Active');
    }
}
