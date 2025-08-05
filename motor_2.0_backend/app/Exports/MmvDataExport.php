<?php

namespace App\Exports;

use App\Models\BrokerDetail;

// use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MmvDataExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $data = [];
        // Check if a file name is provided
        if (request()->file_name != null) {

            if (request()->file_name != null) {
                $data = collect(json_decode(Storage::get(request()->file_name), true));
            }
            
        }
        return $data;
    }

    public function headings(): array
    {
        $firstRow = $this->collection()->first();
        $headers = array_keys($firstRow);
        return $headers;
    }
}
