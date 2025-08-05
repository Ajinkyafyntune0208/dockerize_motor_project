<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrLink extends Model
{
    use HasFactory;

    protected $table = 'qr_links';
    protected $guarded = [];
    public $timestamps = true;
}
