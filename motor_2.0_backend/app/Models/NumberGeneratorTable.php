<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumberGeneratorTable extends Model
{
    use HasFactory;

    protected $table = 'number_generator_table';
    protected $primaryKey = 'number_gen_id';
    protected $guarded = [];
    public $timestamps = false;
}
