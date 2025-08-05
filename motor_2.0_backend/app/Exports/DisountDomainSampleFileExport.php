<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class DisountDomainSampleFileExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return collect([
            ['Domain Name']
        ]);
    }
}
