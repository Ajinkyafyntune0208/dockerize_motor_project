<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreviousPolicyType extends Model
{
    use HasFactory;

    protected $table = 'previous_policy_type';
    protected $primaryKey = 'previous_policy_type_id';
    protected $guarded = [];
    public $timestamps = false;
}
