<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalPaymentMap extends Model
{
    use HasFactory;

    protected $table = 'proposal_payment_map';
    protected $primaryKey = 'proposal_payment_map_id';
    protected $guarded = [];
    public $timestamps = false;
}
