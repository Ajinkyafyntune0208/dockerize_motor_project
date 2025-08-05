<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddonBundle extends Model
{
    use HasFactory;

    protected $table = 'addon_bundle';
    protected $primaryKey = 'addon_bundle_id';
    protected $guarded = [];
    public $timestamps = false;
}
