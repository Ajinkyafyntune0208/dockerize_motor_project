<?php

namespace App\Models\Quotes\Cv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CvQuoteModel extends Model
{
    function getProductDataByIc($policyId)
    {
    	$productData = DB::table('master_policy as mp')
    					->join('master_company as mc', 'mc.company_id', '=', 'mp.insurance_company_id')
                        ->join('master_product_sub_type as mpst', 'mpst.product_sub_type_id', '=', 'mp.product_sub_type_id')
    					->where('policy_id', $policyId)
    					->select('mp.policy_id', 'mp.product_sub_type_id','mp.premium_type_id', 'mp.is_premium_online', 'mp.is_proposal_online', 'mp.is_payment_online', 'mc.company_id', 'mc.company_alias', 'mp.default_discount', 'mp.status', 'mc.company_name', 'mc.logo', 'mc.company_id','mpst.product_sub_type_code', 'mpst.product_sub_type_name')
    					->first();

    	if(!empty($productData))
    	{
    		return $productData;
    	}

    	return false;
    }
}
