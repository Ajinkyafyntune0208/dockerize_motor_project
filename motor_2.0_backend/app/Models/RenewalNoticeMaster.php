<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalNoticeMaster extends Model
{
    use HasFactory;

    protected $table = 'renewal_notice_master';
    protected $primaryKey = 'renewal_notice_id';
    protected $guarded = [];
    public $timestamps = false;
}
