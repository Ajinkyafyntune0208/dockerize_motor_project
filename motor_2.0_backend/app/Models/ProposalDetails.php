<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalDetails extends Model
{
    use HasFactory;

    protected $table = 'proposal_details';
    protected $primaryKey = 'proposal_detail_id';
    protected $guarded = [];
    public $timestamps = false;
}
