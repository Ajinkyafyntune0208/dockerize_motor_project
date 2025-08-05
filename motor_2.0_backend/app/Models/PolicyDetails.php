<?php

namespace App\Models;

use App\Events\PolicyGenerated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PolicyDetails extends Model
{
    use HasFactory;

    protected $table = 'policy_details';
    protected $primaryKey = 'policy_id';
    protected $guarded = [];
    public $timestamps = false;

    protected $dispatchesEvents = [
        "saved" => PolicyGenerated::class,
        // "created" => PolicyGenerated::class,
        // "updated" => PolicyGenerated::class,
    ];

    public function getPdfUrlAttribute($value){
        $isUrl = filter_var($value, FILTER_VALIDATE_URL) !== false;
        if (!empty($value) && !$isUrl) {
            // return Storage::url($value);
            return file_url($value);
        }
        return $value;
    }

    public function user_proposal()
    {
        return $this->hasOne(UserProposal::class, 'user_proposal_id', 'proposal_id');
    }
}