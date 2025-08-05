<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PccvTariff extends Model
{
    use HasFactory;

    protected $table = 'pccv_tariff';
    protected $primaryKey = 'tattif_id';
    protected $guarded = [];
    public $timestamps = false;
}
