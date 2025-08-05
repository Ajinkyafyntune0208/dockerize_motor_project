<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBundleAddon extends Model
{
    use HasFactory;

    protected $table = 'master_bundle_addon';
    protected $primaryKey = 'master_bundle_id';
    protected $guarded = [];
    public $timestamps = false;
}
