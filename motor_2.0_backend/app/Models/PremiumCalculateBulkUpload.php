<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremiumCalculateBulkUpload extends Model
{
    use HasFactory;

    protected $table = 'premium_calculate_bulk_upload';
    protected $guarded = [];
    public $timestamps = false;
}
