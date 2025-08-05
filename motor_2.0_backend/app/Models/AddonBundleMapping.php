<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddonBundleMapping extends Model
{
    use HasFactory;

    protected $table = 'addon_bundle_mapping';
    protected $primaryKey = 'mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
