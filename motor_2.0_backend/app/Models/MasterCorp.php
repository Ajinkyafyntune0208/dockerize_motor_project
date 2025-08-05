<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterCorp extends Model
{
    use HasFactory;

    protected $table = 'master_corp';
    protected $primaryKey = 'corp_id';
    protected $guarded = [];
    public $timestamps = false;
}
