<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciMmvFtMapping extends Model
{
    use HasFactory;

    protected $table = 'icici_mmv_ft_mapping';
    protected $primaryKey = 'version_id';
    protected $guarded = [];
    public $timestamps = false;
}
