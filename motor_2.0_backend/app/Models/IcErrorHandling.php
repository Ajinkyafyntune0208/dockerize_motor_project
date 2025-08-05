<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class IcErrorHandling extends Model
{
    use HasFactory,ActivityTrait;
    protected $guarded = [];
    public $timestamps = true;
    protected $fillable = [
        'company_alias', 'section', 'ic_error', 'custom_error', 'status', 'checksum',
    ];

    protected static function boot()
    {
       
        $serviceType = 'Ic Handling Error';
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
