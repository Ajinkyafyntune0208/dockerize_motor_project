<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddonApplyOn extends Model
{
    use HasFactory;

    protected $table = 'addon_apply_on';
    protected $primaryKey = 'addon_apply_on_id';
    protected $guarded = [];
    public $timestamps = false;
}
