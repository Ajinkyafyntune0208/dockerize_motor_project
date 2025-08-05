<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class RunAndDownloadExport implements FromCollection , WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */

    protected $records;
    protected $headings;
    function __construct(array $records,$headings)
    {
        $this->records = $records;
        $this->headings = $headings;
    }

    public function headings(): array
    {
        return $this->headings;     
    }
    
    public function collection()
    {
        return  collect($this->records); 
    }
    
    
}
