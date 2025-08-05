<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\ActivityTrait;

class PreviousInsurer extends Model
{
    use HasFactory,ActivityTrait;
    protected $table = "previous_insurer_mappping";

    protected $appends = ['logo'];
    /**
     * Get the previous insurer logo.
     *
     */
    public function getLogoAttribute()
    {
        return url(config('constants.motorConstant.logos')).'/'.strtolower(trim($this->company_alias)).'.png';
        //return /* Storage::url */file_url('previous_insurer_logos/'.Str::camel($this->company_alias).'.png');
    }

    protected static function boot()
    {
       
        $serviceType = 'Previous Insurer';
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
