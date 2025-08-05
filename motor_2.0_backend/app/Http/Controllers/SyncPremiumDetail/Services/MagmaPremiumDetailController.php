<?php

namespace App\Http\Controllers\SyncPremiumDetail\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MagmaPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        return [
            'status' => false,
            'message' => 'Integration not yet done.',
        ];
    }
}
