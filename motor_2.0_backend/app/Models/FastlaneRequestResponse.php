<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FastlaneRequestResponse extends Model
{
    use HasFactory;
    // protected $fillable = ['response', 'request'];
    protected $table = 'fastlane_request_response';
    protected $guarded = [];
    public $timestamps = false;

    public function agent_details()
    {
        return $this->hasOne(CvAgentMapping::class, 'user_product_journey_id', 'enquiry_id');
    }

    public function user_product_journey()
    {
        return $this->hasOne(UserProductJourney::class, 'user_product_journey_id', 'enquiry_id');
    }

    public function scopeRcReportData($query, $inputData)
    {
        return $query->select('id','transaction_type','type','request','response','endpoint_url','response_time','created_at')
                    ->when(!empty($inputData['from']) && !empty($inputData['to']), function ($query) {
                        $query->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime(request()->from)))
                              ->where('created_at', '<', date('Y-m-d 23:59:59', strtotime(request()->to)));
                    })->when(!empty($inputData['service_type']), function ($query) {
                        $query->where('transaction_type',  request()->service_type);
                    })->when(!empty($inputData['rc_number']), function ($query) {
                        $query->where('request',  request()->rc_number);
                    })->when(!empty($inputData['journey_type']), function ($query) {
                        $query->where('type',  request()->journey_type);
                    });
    }
}
