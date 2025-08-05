<?php

namespace App\Http\Controllers\SyncPremiumDetail\Car;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BhartiAxaPremiumDetailController extends Controller
{
    public function syncDetails($enquiryId)
    {
        return [
            'status' => false,
            'message' => 'Integration not yet done.',
        ];
    }
}
