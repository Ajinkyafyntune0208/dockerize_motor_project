<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NomineeRelationshipNew extends Model
{
    use HasFactory;

    protected $table = 'nominee_relationship_new';
    protected $guarded = [];
    public $timestamps = false;
}
