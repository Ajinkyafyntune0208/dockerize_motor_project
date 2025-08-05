<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MagmaPosReqResponse extends Model
{
    use HasFactory;
    protected $table = 'magma_pos_req_response';
    protected $primaryKey = 'ic_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
