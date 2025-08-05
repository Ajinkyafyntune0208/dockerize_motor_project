<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class MasterPolicy extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'master_policy';
    protected $primaryKey = 'policy_id';
    protected $guarded = [];
    public $timestamps = true;

    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';

    /**
     * Get the master product associated with the master_policy.
     */
     protected static function boot()
    {
        $serviceType = 'MASTER POLICY';
        parent::boot();
        static::created(function ($model) use( $serviceType){
            $model->logActivity('CREATED',  $serviceType , $model->toArray());
        });

        static::updated(function ($model) use( $serviceType){
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();
            $model->logUpdateActivity('UPDATED',$oldData, $newData,  $serviceType);
        });

        static::deleted(function ($model) use( $serviceType){
            $oldData = $model->getOriginal();
            $model->logDeletedActivity('DELETED', $serviceType ,$oldData);
        });
    }
    public function master_product()
    {
        return $this->hasOne(MasterProduct::class,'master_policy_id', 'policy_id');
    }

    public function product_sub_type_code()
    {
        return $this->hasOne(MasterProductSubType::class, 'product_sub_type_id', 'product_sub_type_id');
    }

    public function premium_type()
    {
        return $this->hasOne(MasterPremiumType::class, 'id', 'premium_type_id');
    }


}
