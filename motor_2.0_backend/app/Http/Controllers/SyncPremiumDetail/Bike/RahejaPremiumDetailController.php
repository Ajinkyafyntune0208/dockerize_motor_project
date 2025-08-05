<?php

namespace App\Http\Controllers\SyncPremiumDetail\Bike;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RahejaPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        return [
            'status' => false,
            'message' => 'Integration not yet done.',
        ];
    }
}
