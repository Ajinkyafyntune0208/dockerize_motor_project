<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CancellationRequestMappingDocs extends Model
{
    use HasFactory;

    protected $table = 'cancellation_request_mapping_docs';
    protected $primaryKey = 'cancellation_request_mapping_id';
    protected $guarded = [];
    public $timestamps = false;
}
