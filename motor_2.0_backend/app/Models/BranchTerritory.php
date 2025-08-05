<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchTerritory extends Model
{
    use HasFactory;

    protected $table = 'branch_territory';
    protected $primaryKey = 'branch_id';
    protected $guarded = [];
    public $timestamps = false;
}
