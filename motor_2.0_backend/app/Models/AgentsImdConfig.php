<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentsImdConfig extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        "credentials" => "array"
    ];

    public function agent_detail()
    {
        return $this->belongsTo(Agents::class, 'agent_id', 'agent_id');
    }

    public function product_sub_type()
    {
        return $this->belongsTo(MasterProductSubType::class, 'master_product_sub_type_id', 'product_sub_type_id');
    }

    public function insurance_company()
    {
        return $this->belongsTo(MasterCompany::class, 'ic_id', 'company_id');
    }
}
