<?php

namespace App\Models\Finsall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinsallEntityType extends Model
{
    use HasFactory;

    protected $table = 'finsall_entity_type';
    protected $guarded = [];
}
