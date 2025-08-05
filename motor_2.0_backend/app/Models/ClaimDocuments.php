<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimDocuments extends Model
{
    use HasFactory;

    protected $table = 'claim_documents';
    protected $guarded = [];
    public $timestamps = false;
}
