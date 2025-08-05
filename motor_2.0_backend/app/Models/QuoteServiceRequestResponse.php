<?php

namespace App\Models;

use App\Builders\EncryptionQueryBuilder;
use App\Casts\PersonalDataEncryption;
use App\Models\QuoteLog;
use App\Models\UserProposal;
use Illuminate\Support\Facades\DB;
use App\Models\CorporateVehiclesQuotesRequest;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteServiceRequestResponse extends Model
{
    use HasFactory;
    protected $table = 'quote_webservice_request_response_data';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'request'=> PersonalDataEncryption::class,
        'response'=> PersonalDataEncryption::class,
    ];
    
    //protected $dates = ['response_time','start_time'];
    protected $dates = ['start_time'];
    // protected $casts = [
    //     "response_time" => "datetime:s"
    // ];
    // protected $appends = ['vehicle_details'];

    // public function getVehicleDetailsAttribute()
    // {
    //     $quote_log = \Illuminate\Support\Facades\DB::table('quote_log')->where('user_product_journey_id', $this->attributes['enquiry_id'])->first();
    //     return json_decode(json_decode($quote_log->quote_data, true), true);
    // }

    public function newEloquentBuilder($query)
    {
        return new EncryptionQueryBuilder($query);
    }

    public function vehicle_details()
    {
        return $this->hasOne(QuoteLog::class, 'user_product_journey_id', 'enquiry_id')/* ->first() */;
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
