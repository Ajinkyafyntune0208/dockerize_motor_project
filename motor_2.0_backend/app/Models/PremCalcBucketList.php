<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremCalcBucketList extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function label()
    {
        return $this->hasOne(PremCalcLabel::class, 'id', 'label_id');
    }
}
