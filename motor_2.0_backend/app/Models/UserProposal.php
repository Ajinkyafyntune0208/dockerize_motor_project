<?php

namespace App\Models;
use App\Builders\EncryptionQueryBuilder;
use App\Casts\PersonalDataEncryption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class UserProposal extends Model
{
    use HasFactory;

    protected $table = 'user_proposal';
    protected $primaryKey = 'user_proposal_id';
    protected $guarded = [];
    protected $casts = [
        "ic_vehicle_details" => "array",

        // data encryption
        'additional_details' => PersonalDataEncryption::class,
        'additional_details_data' => PersonalDataEncryption::class,

        'address_line1' => PersonalDataEncryption::class,
        'address_line2' => PersonalDataEncryption::class,
        'address_line3' => PersonalDataEncryption::class,
        'pincode' => PersonalDataEncryption::class,

        'car_registration_address1' => PersonalDataEncryption::class,
        'car_registration_address2' => PersonalDataEncryption::class,
        'car_registration_address3' => PersonalDataEncryption::class,
        'car_registration_city' => PersonalDataEncryption::class,
        'car_registration_city_id' => PersonalDataEncryption::class,
        'car_registration_pincode' => PersonalDataEncryption::class,
        'car_registration_state' => PersonalDataEncryption::class,
        'car_registration_state_id' => PersonalDataEncryption::class,

        'ckyc_extras' => PersonalDataEncryption::class,
        'ckyc_type_value' => PersonalDataEncryption::class,
        'ckyc_meta_data' => PersonalDataEncryption::class,
        'ckyc_number' => PersonalDataEncryption::class,
        'ckyc_reference_id' => PersonalDataEncryption::class,
        'pan_number' => PersonalDataEncryption::class,
        'gst_number' => PersonalDataEncryption::class,

        'dob' => PersonalDataEncryption::class,
        'email' => PersonalDataEncryption::class,
        'first_name' => PersonalDataEncryption::class,
        'fullName' => PersonalDataEncryption::class,
        'gender' => PersonalDataEncryption::class,
        'gender_name' => PersonalDataEncryption::class,
        'last_name' => PersonalDataEncryption::class,
        'marital_status' => PersonalDataEncryption::class,
        'mobile_number' => PersonalDataEncryption::class,
        'office_email' => PersonalDataEncryption::class,
        // 'owner_type' => PersonalDataEncryption::class,

        'full_name_finance' => PersonalDataEncryption::class,
        'hypothecation_city' => PersonalDataEncryption::class,

        'nominee_age' => PersonalDataEncryption::class,
        'nominee_dob' => PersonalDataEncryption::class,
        'nominee_name' => PersonalDataEncryption::class,
        'nominee_relationship' => PersonalDataEncryption::class,
        
        'occupation' => PersonalDataEncryption::class,
        'occupation_name' => PersonalDataEncryption::class,
    ];
    
    public $appends = ['additonal_data'];


    public function newEloquentBuilder($query)
    {
        return new EncryptionQueryBuilder($query);
    }
    /**
     * Get the additional_details in formatted.
     *
     * @return string
     */
    public function getAdditonalDataAttribute()
    {
        return json_decode($this->additional_details, true);
    }

    /**
     * Get the prolicy record associated with the user product journey.
     */
    public function policy_details()
    {
        return $this->hasOne(PolicyDetails::class, 'proposal_id','user_proposal_id');
    }

    public function breakin_status()
    {
        return $this->belongsTo(CvBreakinStatus::class, 'user_proposal_id', 'user_proposal_id');
    }

    public function quote_log()
    {
        return $this->hasOne(QuoteLog::class, 'user_product_journey_id', 'user_product_journey_id');
    }

    public function user_product_journey()
    {
        return $this->hasOne(UserProductJourney::class, 'user_product_journey_id', 'user_product_journey_id');
    }

    public function journey_stage()
    {
        return $this->hasOne(JourneyStage::class, 'user_product_journey_id', 'user_product_journey_id');
    }

    public function selected_addons()
    {
        return $this->hasOne(SelectedAddons::class, 'user_product_journey_id', 'user_product_journey_id');
    }

    public function corporate_vehicles_quotes_request()
    {
        return $this->hasOne(CorporateVehiclesQuotesRequest::class, 'user_product_journey_id', 'user_product_journey_id');
    }

    public function quote_service_request_response()
    {
        return $this->hasMany(QuoteServiceRequestResponse::class, 'enquiry_id', 'user_product_journey_id')->select('enquiry_id');
    }

    public function web_service_request_response()
    {
        return $this->hasMany(WebServiceRequestResponse::class, 'enquiry_id', 'user_product_journey_id')->select('enquiry_id');
    }

    public function ckyc_upload_documents()
    {
        return $this->hasOne(ckycUploadDocuments::class, 'user_product_journey_id', 'user_product_journey_id');
    }

    public function proposer_ckyc_details()
    {
        return $this->hasOne(ProposerCkycDetails::class, 'user_proposal_id', 'user_proposal_id');
    }

    public function ckyc_request_response()
    {
        return $this->hasOne(ckycRequestResponse::class, 'user_product_journey_id', 'user_product_journey_id');
    }

    /* Scopes */

    public function scopeAgentDetails($query)
    {
        $query->whereHas('user_product_journey.agent_details', function (Builder $query) {
            $i = 0;
            foreach (request()->combined_seller_ids as $key => $value) {

                if ($i == 0) {
                    $where_condition = 'where';
                } else {
                    $where_condition = 'orWhere';
                }

                $query->$where_condition(function (Builder $query) use ($key, $value) {
                    if (
                        $key == 'b2c'
                    ) {
                        $query = $query->where(function (Builder $query) use ($key, $value) {
                            $query = $query->whereNull('seller_type')->orWhere('seller_type', '');
                        })->whereNotNull('user_id');
                        if (!empty($value)) {
                            $query->whereIn('user_id', $value);
                        }
                    } else if ($key == 'U') {
                        $query = $query->where('seller_type', $key)->whereNotNull('user_id');
                        if (!empty($value)) {
                            $query->whereIn('user_id', $value);
                        }
                    } else {
                        $query = $query->where('seller_type', $key);
                        if (!empty($value)) {
                            $query->whereIn('agent_id', $value);
                        }
                    }
                });
                $i++;
            }
        });
    }
}
