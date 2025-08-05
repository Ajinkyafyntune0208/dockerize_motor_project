<?php

namespace App\Models;

use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\ActivityTrait;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, ActivityTrait {
        roles as originalRoles;
    }

    public function roles()
    {
        return $this->originalRoles()->limit(1);
    }

    public function getPasswordAttribute() {
        return $this->attributes['password'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $table = 'user';
    protected $fillable = [
        'name',
        'email',
        'password',
        'api_token',
        'confirm_otp',
        'otp',
        'otp_expires_in',
        'otp_expires_at',
        'password_expire_at',
        'otp_type',
        'totp_secret',
        'authorization_status',
        'authorization_by_user',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static function boot()
    {
       
        $serviceType = 'User Instances';
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
