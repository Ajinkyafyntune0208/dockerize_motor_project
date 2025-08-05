<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IciciLombardPosMapping extends Model
{
    use HasFactory;
    protected $table = 'icici_lombard_pos_mapping';
    protected $primaryKey = 'ic_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
