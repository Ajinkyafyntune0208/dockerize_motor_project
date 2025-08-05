<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PcvApplicableAddon extends Model
{
    use HasFactory;
    protected $table = 'pcv_applicable_addons';
    protected $primaryKey = 'addon_id';
    protected $guarded = [];
    public $timestamps = false;
}
