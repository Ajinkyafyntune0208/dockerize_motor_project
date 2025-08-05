<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateVehiclesQuotesRequest extends Model
{
    use HasFactory;

    protected $table = 'corporate_vehicles_quotes_request';
    protected $primaryKey = 'quotes_request_id';
    protected $guarded = [];
    public $timestamps = false;
    /*
    ongrid
    fastlane
    driver-app
    embeded-excel
    normal/(NULL)
    */

    public function product_sub_type()
    {
        return $this->hasOne(MasterProductSubType::class, 'product_sub_type_id', 'product_id');
    }
}
