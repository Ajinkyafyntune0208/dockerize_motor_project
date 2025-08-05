<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class PospUtility extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'posp_utility';
    protected $primaryKey = 'utility_id';
    public $timestamps = false;
    protected $fillable = [
        'utility_id',
        'seller_user_id',
        'seller_type',
        'created_at'
    ];
    
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected static function boot()
    {
        $serviceType = 'POSP UTILITY';
        parent::boot();
        static::created(function ($model) use($serviceType){
            $model->logActivity('CREATED',$serviceType , $model->toArray());
        });

        static::updated(function ($model) use ($serviceType) {
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();
            $differences = [];
        
            foreach ($oldData as $key => $value) {
                if ($value !== $newData[$key]) {
                    $differences['original'][$key] = $value;
                    $differences['new'][$key] = $newData[$key];
                }
            }
            $oldData = !empty($differences['original']) ? json_encode($differences['original']) : null;
            $newData = !empty($differences['new']) ? json_encode($differences['new']) : null;
        
            $model->logUpdateActivity('UPDATED', $oldData, $newData, $serviceType);
        });
    }
}
