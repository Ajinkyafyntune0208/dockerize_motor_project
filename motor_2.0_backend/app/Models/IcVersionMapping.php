<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcVersionMapping extends Model
{
    use HasFactory;

    protected $table = 'ic_version_mapping';
    protected $primaryKey = 'ic_version_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
