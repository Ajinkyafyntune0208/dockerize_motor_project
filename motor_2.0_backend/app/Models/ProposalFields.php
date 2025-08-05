<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalFields extends Model
{
    use HasFactory;

    protected $table = 'proposal_field';
    protected $primaryKey = 'field_id';
    protected $guarded = [];
    public $timestamps = true;
}
