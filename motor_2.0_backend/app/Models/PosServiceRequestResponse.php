<?php

namespace App\Models;

use App\Builders\EncryptionQueryBuilder;
use App\Casts\PersonalDataEncryption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosServiceRequestResponse extends Model
{
    use HasFactory;

    protected $table = 'pos_service_request_response_data';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = [
        'request'=> PersonalDataEncryption::class,
        'response'=> PersonalDataEncryption::class,
    ];
    protected $dates = ['start_time'];
    public function newEloquentBuilder($query)
    {
        return new EncryptionQueryBuilder($query);
    }
}
