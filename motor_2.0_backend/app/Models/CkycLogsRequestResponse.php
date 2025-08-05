<?php

namespace App\Models;

use App\Builders\EncryptionQueryBuilder;
use App\Casts\PersonalDataEncryption;
use App\Models\CorporateVehiclesQuotesRequest;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CkycLogsRequestResponse extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'request'=> PersonalDataEncryption::class,
        'response'=> PersonalDataEncryption::class,
        'headers'=> PersonalDataEncryption::class,
    ];

    public function vehicle_details()
    {
        return $this->hasOne(QuoteLog::class, 'user_product_journey_id', 'enquiry_id') /* ->first() */;
    }

    public function corporate_details()
    {
        return $this->hasOne(CorporateVehiclesQuotesRequest::class, 'user_product_journey_id', 'enquiry_id');
    }

    public function user_proposal_details()
    {
        return $this->hasOne(UserProposal::class, 'user_product_journey_id', 'enquiry_id');
    }

    public function newEloquentBuilder($query)
    {
        return new EncryptionQueryBuilder($query);
    }
    
}
