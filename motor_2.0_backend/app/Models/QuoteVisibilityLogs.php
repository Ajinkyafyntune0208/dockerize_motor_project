<?php

namespace App\Models;

use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteVisibilityLogs extends Model
{
    use HasFactory;

    protected $table = 'quote_visibility_logs';
    protected $fillable = ['enquiry_id'];
    protected $guarded = [];

    public function vehicle_details()
    {
        return $this->hasOne(QuoteLog::class, 'user_product_journey_id', 'enquiry_id');
    }

    public function corporate_details()
    {
        return $this->hasOne(CorporateVehiclesQuotesRequest::class, 'user_product_journey_id', 'enquiry_id');
    }

    public function user_proposal_details()
    {
        return $this->hasOne(UserProposal::class, 'user_product_journey_id', 'enquiry_id');
    }
     
}
