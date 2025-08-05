<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\ActivityTrait;

class Menu extends Model
{
    use HasFactory,ActivityTrait;

    protected $table = 'menu_master';
    protected $primaryKey = 'menu_id';
    protected $guarded = [];
    protected $fillable = ['menu_name','parent_id','menu_slug','menu_url','menu_icon','status','created_at','updated_at'];


    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    public static function getBreadcrumbsByUrl($currentUrl)
    {
        $currentMenu = self::where('menu_url', $currentUrl)->first();
        $breadcrumbs = [];

        if(str_contains($currentUrl, 'edit')){
            $cleanedUrl = preg_replace('/\/\d+\/(edit)?/', '', $currentUrl);
            $cleanedUrl = preg_replace('/\/(edit\/)?\d+/', '', $cleanedUrl);
            $cleanedUrl = $cleanedUrl."/edit";
            $currentMenu = self::where('menu_url', $cleanedUrl)->first();
        }

        while ($currentMenu) {
            array_unshift($breadcrumbs, $currentMenu);
            $currentMenu = $currentMenu->parent;
        }
        return $breadcrumbs;
    }

    protected static function boot()
    {
       
        $serviceType = 'Menu Master';
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
