<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciFtMapping extends Model
{
    use HasFactory;

    protected $table = 'icici_ft_mapping';
    protected $primaryKey = 'icici_ft_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
