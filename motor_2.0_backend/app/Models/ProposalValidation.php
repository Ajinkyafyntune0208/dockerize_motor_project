<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class ProposalValidation extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'proposal_validation';
    protected $primaryKey = 'validation_id';
    protected $guarded = [];
    public $timestamps = true;

    protected static function boot()
    {
        $serviceType = 'PROPOSAL VALIDATION';
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
