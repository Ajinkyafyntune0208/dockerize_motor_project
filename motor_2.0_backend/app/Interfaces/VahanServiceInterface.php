<?php

namespace App\Interfaces;

use Illuminate\Http\Request;

interface VahanServiceInterface
{
    public function getCredential(String $keyName);
    public function getVahanDetails(Request $request);
    public function validateVehicleService(Request $request);
}
