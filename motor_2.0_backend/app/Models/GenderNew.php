<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class GenderNew extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'gender_new';
    protected $guarded = [];
    public $timestamps = false;

    protected static function boot()
    {
       
        $serviceType = 'Gender New';
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
