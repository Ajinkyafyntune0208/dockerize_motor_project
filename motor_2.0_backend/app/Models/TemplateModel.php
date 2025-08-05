<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class TemplateModel extends Model
{
    use HasFactory,ActivityTrait;
    
    protected $primaryKey = 'template_id';
    protected $guarded = [];

    public function getStatusAttribute ($value) {
        return $value=='Y' ? 'Active' : 'Inactive';
    }

    protected static function boot()
    {
       
        $serviceType = 'Template Instance';
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
