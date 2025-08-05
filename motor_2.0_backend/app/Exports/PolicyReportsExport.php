<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Str;

class PolicyReportsExport implements FromCollection , WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $data;
    public function __construct($data)
    {
        $this->data = $data;
    }
    public function collection()
    {
        return collect($this->data);
    }
    public function headings(): array
    {
            $columnHeads = array_keys($this->collection()->first());
        $final = [];
        foreach ($columnHeads as $head) 
        {
            $headName = Str::title(str_replace('_', ' ', $head));
            $final[] = $headName;
        }
        return $final;
    }
}
