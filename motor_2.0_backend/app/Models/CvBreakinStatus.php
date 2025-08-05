<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CvBreakinStatus extends Model
{
    use HasFactory;

    protected $table = 'cv_breakin_status';
    protected $primaryKey = 'cv_breakin_id';
    protected $guarded = [];
    

    public function user_proposal()
    {
        return $this->belongsTo(UserProposal::class, 'user_proposal_id', 'user_proposal_id');
    }
}
