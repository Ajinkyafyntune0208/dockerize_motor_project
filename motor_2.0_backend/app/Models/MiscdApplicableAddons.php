<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiscdApplicableAddons extends Model
{
    use HasFactory;
    protected $primaryKey = 'addon_id';
    protected $guarded = [];
    public $timestamps = false;
}
