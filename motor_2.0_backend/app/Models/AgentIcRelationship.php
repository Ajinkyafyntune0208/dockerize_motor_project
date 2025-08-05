<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentIcRelationship extends Model
{
    use HasFactory;
    protected $table = 'agent_ic_relationship';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;
}
