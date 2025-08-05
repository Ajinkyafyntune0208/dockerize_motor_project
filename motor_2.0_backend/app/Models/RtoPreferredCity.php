<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class RtoPreferredCity extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'rto_preferred_city';
    protected $primaryKey = 'preferred_city_id';
    public $timestamps = false;

    protected $fillable = [
        // 'preferred_city_id',
        'city_name',
        'priority',
    ];


    protected static function boot()
    {
       
        $serviceType = 'Rto Preferred City';
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
