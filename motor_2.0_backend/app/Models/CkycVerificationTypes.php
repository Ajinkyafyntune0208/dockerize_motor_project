<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class CkycVerificationTypes extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'ckyc_verification_types';
    public $timestamps = true;
    protected $fillable = [
        'id',
        'company_alias' ,
        'mode',
    ];

    protected static function boot()
    {
        $serviceType = 'CKYC VERIFICATION TYPES';
        parent::boot();
        static::created(function ($model) use($serviceType) {
            $model->logActivity('CREATED', $serviceType, $model->toArray());
        });

        static::updated(function ($model) use($serviceType) {
            $oldData = $model->getOriginal();
            $newData = $model->getAttributes();
            $model->logUpdateActivity('UPDATED',$oldData, $newData, $serviceType);
        });

        static::deleted(function ($model) use($serviceType) {
            $oldData = $model->getOriginal();
            $model->logDeletedActivity('DELETED',$serviceType ,$oldData);
        });
    }
}
