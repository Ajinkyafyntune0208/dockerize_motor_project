<?php

namespace App\Models;

use App\Http\Controllers\IcConfig\IcConfigurationController;
use App\Traits\ActivityTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremCalcFormula extends Model
{
    use HasFactory, ActivityTrait;

    protected $guarded = [];

    protected static function boot()
    {
        $serviceType = 'PREM_CALC_FORMULA';
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

    public function getFullFormulaAttribute()
    {
        return IcConfigurationController::findFormula($this->matrix);
    }

    public function getshortFormulaAttribute()
    {
        return IcConfigurationController::findFormula($this->matrix, false, false, true);
    }

    public function getFullFormulaWithLabelAttribute()
    {
        return IcConfigurationController::findFormula($this->matrix, false, true);
    }

    public function getExtractFormulaAttribute()
    {
        return IcConfigurationController::extractFormula($this->matrix);
    }
}
