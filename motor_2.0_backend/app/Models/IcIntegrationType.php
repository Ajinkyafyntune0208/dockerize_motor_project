<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IcIntegrationType extends Model
{
    use HasFactory;
     protected $table = 'ic_integration_type';

    protected $guarded = [];

    public function activation()
    {
        return $this->hasOne(PremCalcActivation::class, 'slug', 'slug');
    }

}
