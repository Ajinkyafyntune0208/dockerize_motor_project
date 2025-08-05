<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class CommunicationConfiguration extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'communication_configuration';

    protected $fillable = [
        'page_name',
        'slug',
        'email_is_enable',
        'email',
        'sms_is_enable',
        'sms',
        'whatsapp_api_enable',
        'whatsapp_api',
        'whatsapp_redirection_is_enable',
        'whatsapp_redirection',
        'all_btn'
    ];
    public $timestamps = true;

    protected static function boot()
    {
       
        $serviceType = 'Communication Configuration';
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