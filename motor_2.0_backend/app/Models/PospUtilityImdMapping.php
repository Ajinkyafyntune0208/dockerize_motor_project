<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class PospUtilityImdMapping extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'posp_utility_imd_mapping';
    protected $primaryKey = 'imd_mapping_id';
    protected $fillable = [
        'segment_id',
        'ic_integration_type',
        'seller_type',
        'imd_id',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at'
    ];
    
    public $timestamps = true;

    protected static function boot()
    {
        $serviceType = 'POSP UTILITY IMD MAPPING';
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
        
        static::deleted(function ($model) use($serviceType){
            $oldData = $model->getOriginal();
            $model->logDeletedActivity('DELETED',$serviceType,$oldData);
        });
    }
}
