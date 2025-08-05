<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateBranch extends Model
{
    use HasFactory;

    protected $table = 'corporate_branch';
    protected $primaryKey = 'corp_branch_id';
    protected $guarded = [];
    public $timestamps = false;
}
