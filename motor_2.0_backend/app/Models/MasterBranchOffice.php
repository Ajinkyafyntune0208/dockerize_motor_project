<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBranchOffice extends Model
{
    use HasFactory;

    protected $table = 'master_branch_office';
    protected $primaryKey = 'branch_office_id';
    protected $guarded = [];
    public $timestamps = false;
}
