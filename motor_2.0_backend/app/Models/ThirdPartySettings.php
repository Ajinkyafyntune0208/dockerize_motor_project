<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirdPartySettings extends Model
{
    use HasFactory;
    protected $table = 'third_party_settings';
    protected $primaryKey = 'id';
    protected $guarded = [];
}
