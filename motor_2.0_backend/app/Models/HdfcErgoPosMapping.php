<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HdfcErgoPosMapping extends Model
{
    use HasFactory;
    protected $table = 'hdfc_ergo_pos_mapping';
    protected $primaryKey = 'ic_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
