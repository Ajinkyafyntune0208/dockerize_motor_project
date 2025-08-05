<?php

namespace App\Models\Finsall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinsallExternalEntity extends Model
{
    use HasFactory;

    protected $table = 'finsall_external_entity';
    protected $guarded = [];
}
