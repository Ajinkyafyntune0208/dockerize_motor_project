<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class MasterCompany extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'master_company';
    protected $primaryKey = 'company_id';
    protected $guarded = [];
    public $timestamps = false;

    public function getLogoAttribute($value)
    {
        // return url('uploads/logos/'.$value);
        return url(config('constants.motorConstant.logos')).'/'.strtolower(trim($this->company_alias)).'.png';
    }    
    public function master_policy()
    {
        return $this->hasMany(MasterPolicy::class, 'insurance_company_id', 'company_id');

    }

    protected static function boot()
    {
       
        $serviceType = 'Master Company';
        parent::boot();
        static::created(function ($model) use($serviceType){
            $model->logActivity('CREATED',$serviceType , $model);
            
        });

        static::updated(function ($model) use($serviceType){
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();
           
            $model->logUpdateActivity('UPDATED',$oldData, $newData, $serviceType);
         
        });

        static::deleted(function ($model) use($serviceType){
           
            $oldData = $model->getOriginal();
            $model->logDeletedActivity('DELETED',$serviceType,$oldData);
        });
    
    }  

}
