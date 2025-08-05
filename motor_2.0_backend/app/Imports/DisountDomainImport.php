<?php

namespace App\Imports;

use App\Models\DiscountDomain;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DisountDomainImport implements ToCollection, WithHeadingRow
{
    /**
    * @param Collection $collection
    */
    public function collection($rows)
    {
        $rows = $rows->toArray();
        foreach ($rows as $row) 
        {
            DiscountDomain::updateOrCreate($row,$row);
        }
    }
}
