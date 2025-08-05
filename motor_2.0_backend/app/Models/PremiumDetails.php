<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremiumDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_product_journey_id',
        'details',
        'commission_details',
        'commission_conf_id',
        "payin_details",
        "payin_conf_id",
    ];

    protected $casts = [
        'details' => 'array',
        'commission_details' => 'array',
        'payin_details' => 'array'
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $breakupArray = [
                // OD Tags
                "basic_od_premium" => 0, // Excluding OD discounts (Voluntary, Anti-Theft, NCB Dist)
                "loading_amount" => 0,
                "od_premium" => 0, // Including Basic OD, Accessorries, CNG and Geographical Extension
                "final_od_premium" => 0, // Including Addons
                // TP Tags
                "basic_tp_premium" => 0, // Including TP discount (TPPD Discount)
                "final_tp_premium" => 0, // Including all TP accessories and TP Covers
                // Accessories
                "electric_accessories_value" => 0,
                "non_electric_accessories_value" => 0,
                "bifuel_od_premium" => 0,
                "bifuel_tp_premium" => 0,
                // Addons
                "compulsory_pa_own_driver" => 0,
                "zero_depreciation" => 0,
                "road_side_assistance" => 0,
                "imt_23" => 0,
                "consumable" => 0,
                "key_replacement" => 0,
                "engine_protector" => 0,
                "ncb_protection" => 0,
                "tyre_secure" => 0,
                "return_to_invoice" => 0,
                "loss_of_personal_belongings" => 0,
                "eme_cover" => 0,
                "accident_shield" => 0,
                "conveyance_benefit" => 0,
                "passenger_assist_cover" => 0,

                //for inbulit addons
                'in_built_addons' => [
                ],

                // Covers
                "pa_additional_driver" => 0,

                "unnamed_passenger_pa_cover" => 0,
                "ll_paid_driver" => 0,
                "ll_paid_employee" => 0,
                "geo_extension_odpremium" => 0,
                "geo_extension_tppremium" => 0,
                // Discounts
                "anti_theft" => 0,
                "voluntary_excess" => 0,
                "tppd_discount" => 0,
                "other_discount" => 0,
                "ncb_discount_premium" => 0,
                // Final tags
                "net_premium" => 0,
                "service_tax_amount" => 0,
                "final_payable_amount" => 0,
            ];
            $originalArray = $model->details;
            foreach ($originalArray as $k => $v) {
                if (is_numeric($v)) {
                    $originalArray[$k] = (float) $v;
                }
            }
            $model->details = array_replace($breakupArray, $originalArray);
        });
    }
}
