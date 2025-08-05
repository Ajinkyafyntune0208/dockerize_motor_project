<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicySummaryDetails extends Model
{
    use HasFactory;

    protected $table = 'policy_summary_details';
    protected $primaryKey = 'policy_summary_details_id';
    protected $guarded = [];
    public $timestamps = false;
}
