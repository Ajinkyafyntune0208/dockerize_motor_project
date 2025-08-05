<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmbeddedLinkWhatsappRequests extends Model
{
    use HasFactory;

    protected $fillable = ['enquiry_id', 'request', 'lsq_activity_data', 'scheduled_at'];
}
