<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class MasterProductSubType extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'master_product_sub_type';
    protected $primaryKey = 'product_sub_type_id';
    protected $guarded = [];
    public $timestamps = false;

  public function parent() {
    return $this->belongsTo(self::class, 'parent_product_sub_type_id');
  }

  protected static function boot()
  {
     
      $serviceType = 'Master Product Sub Type';
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
