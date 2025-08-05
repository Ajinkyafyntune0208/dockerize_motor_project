<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PreviousInsurerMapppingNew extends Model
{
    use HasFactory;
    protected $table = "previous_insurer_mappping_new";

    protected $appends = ['logo'];
    public $timestamps = false;
    /**
     * Get the previous insurer logo.
     *
     */
    public function getLogoAttribute()
    {
        return /* Storage::url */file_url('previous_insurer_logos/'.Str::camel($this->company_alias).'.png');
    }
}

