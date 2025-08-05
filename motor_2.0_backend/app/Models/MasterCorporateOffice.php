<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterCorporateOffice extends Model
{
    use HasFactory;

    protected $table = 'master_corporate_office';
    protected $primaryKey = 'corporate_office_id';
    protected $guarded = [];
    public $timestamps = false;
}
