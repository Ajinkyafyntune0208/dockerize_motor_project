<?php

namespace App\Models;

use App\Builders\EncryptionQueryBuilder;
use App\Casts\PersonalDataEncryption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ckycRequestResponse extends Model
{
    use HasFactory;
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        //data encryption
        'kyc_response' => PersonalDataEncryption::class,
    ];

    public function newEloquentBuilder($query)
    {
        return new EncryptionQueryBuilder($query);
    }
}
