<?php

namespace App\Exports;

use App\Models\PreviousInsurerMapppingNew;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class PreviousInsurerExport implements FromCollection, WithHeadings
{

    protected $columnNames;

    public function __construct()
    {
        $this->setColumnNames();
    }

    protected function setColumnNames()
    {
        $model = new PreviousInsurerMapppingNew;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('previous_insurer_mappping_new');
        
        $this->columnNames = $columnNames;
    }
    public function getColumnNames()
    {
        return $this->columnNames ?? null;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $columnNames = $this->getColumnNames();
        return PreviousInsurerMapppingNew::select($columnNames)->get();
    }
    public function headings(): array
    {  
        $columnNames = $this->getColumnNames();
        return [$columnNames];
    }

    public function headingRow(): int
    {
        return 1;
    }
}
