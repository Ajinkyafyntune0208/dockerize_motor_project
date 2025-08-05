<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class userActivityLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            self::setCommitId($model);
            self::setIp($model);
        });

        self::created(function ($model) {
            self::setCommitId($model);
            self::setIp($model);
        });

        self::updating(function ($model) {
            self::setCommitId($model);
            self::setIp($model);
        });

        self::updated(function ($model) {
            self::setCommitId($model);
            self::setIp($model);
        });

        self::deleting(function ($model) {
            self::setCommitId($model);
            self::setIp($model);
        });

        self::deleted(function ($model) {
            self::setCommitId($model);
            self::setIp($model);
        });
    }

    protected static function setCommitId(&$modelObject): void
    {
        if (empty(config('user.activity.commit_id'))) {
            Config::set('user.activity.commit_id', getUUID());
        }
        $modelObject->commit_id = config('user.activity.commit_id');
    }

    protected static function setIp(&$modelObject): void
    {
        $modelObject->ip = request()->ip();
    }
}
