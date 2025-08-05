<?php

namespace App\Models;

use App\Models\MasterCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FastlanePreviousIcMapping extends Model
{
    use HasFactory;
    protected $table = 'fastlane_previous_ic_mapping';
    protected $primaryKey = 'id';
    protected $guarded = [];
    public $timestamps = false;

    /**
     * Get the Master Company details associated with the fastlane ic alias.
     */
    public function masterCompany()
    {
        return $this->hasOne(MasterCompany::class, 'company_alias' , 'company_alias');
    }
}
