<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class PospUtilityImd extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'posp_utility_imd';
    protected $primaryKey = 'imd_id';
    protected $fillable = [
        'imd_code',
        'imd_fields_data',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'created_source',
        'updated_source'
    ];
    
    protected $casts = [
        'imd_fields_data' => 'json', // Ensure the matrix field is cast to JSON
    ];

    public $timestamps = true;

    protected static function boot()
    {
        $serviceType = 'POSP UTILITY IMD';
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
