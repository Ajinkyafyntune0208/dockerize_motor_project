<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterBrokers extends Model
{
    use HasFactory;

    protected $table = 'master_brokers';
    protected $primaryKey = 'broker_id';
    protected $guarded = [];
    public $timestamps = false;
}
