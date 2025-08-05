<?php

namespace App\Models\Finsall;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinsallDataTable extends Model
{
    use HasFactory;

    protected $table = 'finsall_data_table';
    protected $guarded = [];
}
