<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class ThirdPartySetting extends Model
{
    use HasFactory,ActivityTrait;

    protected $fillable = [ 'name', 'url', 'method', 'headers', 'body', 'options'];

    protected $casts = [
        'headers' => 'array',
        'body' => 'array',
        'options' => 'array',
    ];

    protected static function boot()
    {
        $serviceType = 'THIRD PARTY SETTINGS';
        parent::boot();
        static::created(function ($model) use($serviceType){
            $newData =$model->toArray();
            $newData['headers'] = json_encode($newData['headers']);
            $newData['body'] = json_encode($newData['body']);
            $newData['options'] = json_encode($newData['options']);
            $model->logActivity('CREATED', $serviceType, $newData);
        });

        static::updated(function ($model) use($serviceType){
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();
            $oldData['headers'] = json_encode($oldData['headers']);
            $oldData['body'] = json_encode($oldData['body']);
            $oldData['options'] = json_encode($oldData['options']);
            $model->logUpdateActivity('UPDATED',$oldData, $newData, $serviceType);
        });

        static::deleted(function ($model) use($serviceType){
            $oldData = $model->getOriginal();
            $oldData['headers'] = json_encode($oldData['headers']);
            $oldData['body'] = json_encode($oldData['body']);
            $oldData['options'] = json_encode($oldData['options']);
            $model->logDeletedActivity('DELETED',$serviceType,$oldData);
        });
    }
        
}
