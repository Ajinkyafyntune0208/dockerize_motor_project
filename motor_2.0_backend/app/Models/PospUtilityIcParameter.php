<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class PospUtilityIcParameter extends Model
{
    use HasFactory,ActivityTrait,SoftDeletes;

    protected $table = 'posp_utility_ic_parameter';
    protected $primaryKey = 'ic_param_id';
    

    protected $fillable = [
        'ic_param_id',
        'utility_id',
        'segment_id',
        'ic_integration_type',
        'matrix',
        'imd_id',
        'created_at',
        'created_by',
        'created_source',
        'updated_at',
        'updated_source',
        'updated_by'
    ];

    protected $casts = [
        'matrix' => 'array', // Ensure the matrix field is cast to JSON
    ];

    public $timestamps = true;

    protected static function boot()
    {
        $serviceType = 'POSP UTILITY IC PARAMETER';
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

        static::deleted(function ($model) use( $serviceType){
            $oldData = $model->getOriginal();
            $model->logDeletedActivity('DELETED', $serviceType ,$oldData);
        });
    }
}
