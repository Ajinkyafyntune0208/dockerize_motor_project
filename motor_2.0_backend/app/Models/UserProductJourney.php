<?php

namespace App\Models;

use App\Models\Finsall\FinsallPolicyDeatail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProductJourney extends Model
{
    use HasFactory;

    protected $table = 'user_product_journey';
    protected $primaryKey = 'user_product_journey_id';
    protected $guarded = [];
    public $timestamps = false;
    protected $appends = ['journey_id'];
    protected $hidden = ['user_product_journey_id'];

    public function getJourneyIdAttribute()
    {
        return customEncrypt($this->user_product_journey_id);
        // if(config('enquiry_id_encryption') == 'Y'){
        //     return customEncrypt($this->user_product_journey_id);
        // }
        // return $this->attributes['journey_id'] = \Carbon\Carbon::parse($this->created_on)->format('Ymd'). sprintf('%08d',$this->user_product_journey_id);
        // return $this->attributes['journey_id'] = customEncrypt($this->attributes['user_product_journey_id']);
    }

    /**
     * Get the quote log associated with the user_product_journey.
     */
    public function quote_log()
    {
        return $this->hasOne(QuoteLog::class, 'user_product_journey_id');
    }

    /**
     * Get the corporate vehicles quotes request associated with the user_product_journey.
     */
    public function corporate_vehicles_quote_request()
    {
        return $this->hasOne(CorporateVehiclesQuotesRequest::class, 'user_product_journey_id');
    }

    /**
     * Get the user proposal associated with the user_product_journey.
     */
    public function user_proposal()
    {
        return $this->hasOne(UserProposal::class, 'user_product_journey_id');
    }

    /**
     * Get the sub product associated with the user_product_journey.
     */
    public function sub_product()
    {
        return $this->hasOne(MasterProductSubType::class, 'product_sub_type_id', 'product_sub_type_id');
    }

    public function link_delivery_status()
    {
        return $this->hasOne(LinkDeliverystatus::class, 'user_product_journey_id');
    }

    /**
     * Get the quote agent associated with the user_product_journey.
     */
    public function agent_details()
    {
        //return $this->hasMany(CvAgentMapping::class, 'user_product_journey_id')->latest()->limit(1);//this will return only 1 recored if multiple enquiry id
        return $this->hasMany(CvAgentMapping::class, 'user_product_journey_id')->latest('id');//get last data by id
    }


    /**
     * Get the user proposal associated with the user_product_journey.
     */
    public function addons()
    {
        return $this->hasMany(SelectedAddons::class, 'user_product_journey_id');
    }

    /**
     * Get the quote agent associated with the user_product_journey.
     */
    public function journey_stage()
    {
        return $this->hasOne(JourneyStage::class, 'user_product_journey_id');
    }

    public function payment_response()
    {
        return $this->hasOne(PaymentRequestResponse::class, 'user_product_journey_id');
    }

    public function payment_response_all()
    {
        return $this->hasMany(PaymentRequestResponse::class, 'user_product_journey_id');
    }

    public function payment_response_success()
    {
        //return $this->hasOne(PaymentRequestResponse::class, 'user_product_journey_id')->where('status', STAGE_NAMES['PAYMENT_SUCCESS'])->latest();
        return $this->hasOne(PaymentRequestResponse::class, 'user_product_journey_id')->latest();
    }

    public function lsq_journey_id_mapping()
    {
        return $this->hasOne(LsqJourneyIdMapping::class, 'enquiry_id', 'user_product_journey_id');
    }

    public function lsq_activities()
    {
        return $this->hasOne(LsqActivities::class, 'enquiry_id', 'user_product_journey_id');
    }

    public function finsall_payment_details()
    {
        return $this->hasOne(FinsallPolicyDeatail::class, 'user_product_journey_id')->where("status", STAGE_NAMES['PAYMENT_SUCCESS']);
    }

    public function smsOtps()
    {
        return $this->hasOne(PolicySmsOtp::class,'enquiryId','user_product_journey_id');
    }

    public function additional_details(){
        return $this->hasOne(AdditionalDetails::class, 'user_product_journey_id');
    }

    public function proposal_extra_fields(){
        return $this->hasOne(ProposalExtraFields::class,'enquiry_id', 'user_product_journey_id');
    }

    public function premium_details()
    {
        return $this->hasOne(PremiumDetails::class, 'user_product_journey_id');
    }
}
