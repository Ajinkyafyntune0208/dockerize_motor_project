<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterCorporateClient extends Model
{
    use HasFactory;

    protected $table = 'master_corporate_client';
    protected $primaryKey = 'corp_client_id';
    protected $guarded = [];
    public $timestamps = false;
}
