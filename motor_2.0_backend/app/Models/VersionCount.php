<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VersionCount extends Model
{
    use HasFactory;
    protected $table = 'version_counts';

    protected $fillable = [
        'version','status','make','model','variant','policy_count'
    ];
}
