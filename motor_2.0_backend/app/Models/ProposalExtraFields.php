<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalExtraFields extends Model
{
    use HasFactory;
    protected $table = 'proposal_extra_fields';
    protected $fillable = [
        'enquiry_id',
        'original_agent_details',
        'reference_code',
        'cis_url',
        'upload_secondary_key',
        'vahan_serial_number_count',
        'frontend_handling'
    ];

    public function getCisUrlAttribute($value){
        if (!empty($value)) {
            return file_url($value);
        }
        return $value;
    }
    public function user_proposal_details()
    {
        return $this->hasOne(UserProposal::class, 'user_product_journey_id', 'enquiry_id');
    }

    public function user_product_journey()
    {
        return $this->hasOne(UserProductJourney::class, 'user_product_journey_id', 'enquiry_id');
    }
}
