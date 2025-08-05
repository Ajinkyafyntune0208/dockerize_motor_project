<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SelfInspectionAppDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getVideoUrlAttribute($value)
    {
        return route('home', ['yovwzkbasa' => encrypt($value)]);
        // if (!empty(request()->all())) {
        //     return response()->file(\Illuminate\Support\Facades\Storage::path(decrypt(\Illuminate\Support\Arr::first(request()->all()))));
        // }
    }
}
