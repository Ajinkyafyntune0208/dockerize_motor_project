<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterHomeView extends Model
{
    use HasFactory;

    protected $table = 'master_home_view';
    protected $primaryKey = 'home_view_id';
    protected $guarded = [];
    public $timestamps = false;
}
