<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Builders\EncryptionQueryBuilder;
use App\Casts\PersonalDataEncryption;

class PdfRequestResponse extends Model
{
    use HasFactory;
    protected $table = 'pdf_request_response';
    public $timestamps = false;
}