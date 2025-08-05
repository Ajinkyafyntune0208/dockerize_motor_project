<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterRegionalOffice extends Model
{
    use HasFactory;

    protected $table = 'master_regional_office';
    protected $primaryKey = 'regional_office_id';
    protected $guarded = [];
    public $timestamps = false;
}
