<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ActivityTrait;

class PremCalcConfigurator extends Model
{
    use HasFactory, SoftDeletes,ActivityTrait;
    protected $table = 'prem_calc_configurator';
    protected $guarded = [];


    protected static function boot()
    {
        $serviceType = 'PREM CALC CONFIGURATION';
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
    public function getLabel()
    {
        return $this->hasOne(PremCalcLabel::class, 'id', 'label_id');
    }

    public function getFormula()
    {
        return $this->where('calculation_type', 'formula')
        ->first()
        ->hasOne(PremCalcFormula::class, 'id', 'formula_id');
    }

    public function getIcAttribute()
    {
        return $this->where('calculation_type', 'attribute')
        ->first()
        ->hasOne(PremCalcAttributes::class, 'id', 'attribute_id');
    }
}
