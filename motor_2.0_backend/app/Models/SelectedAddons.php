<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SelectedAddons extends Model
{
    use HasFactory;

    protected $table = 'selected_addons';
    protected $guarded = [];
    public $timestamps = true;
    protected $appends = ['selected_addons'];
    protected $casts = [
        'additional_covers' => 'array',
        "accessories" => "array",
        "addons" => "array",
        "voluntary_insurer_discounts" => "array",
        "compulsory_personal_accident" => "array",
        "applicable_addons" => "array",
        "discounts" => "array",
        "agent_discount" => "array",
        "frontend_tags" => "array"
    ];

    public function getSelectedAddonsAttribute()
    {
        return array_merge($this->applicable_addons ?? [], ['compulsory_personal_accident' => $this->compulsory_personal_accident ?? []]);
    }
}
