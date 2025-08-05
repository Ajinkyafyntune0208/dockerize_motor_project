<?php

namespace App\Exports;

use App\Models\PosServiceRequestResponse;
use Maatwebsite\Excel\Concerns\FromCollection;

class PosServiceReqResponse implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return PosServiceRequestResponse::limit(1000)->get();
    }
}
