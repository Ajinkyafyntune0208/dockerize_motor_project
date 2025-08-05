<?php

namespace App\Exports;

use App\Models\ErrorList;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ErrorListExport implements FromCollection, WithHeadings
{
    protected $columnNames;

    public function __construct()
    {
        $this->setColumnNames();
    }

    protected function setColumnNames()
    {
        $model =new ErrorList;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('Error_list');
        $unwantedItems = array('id', 'created_at','updated_at');
        $columnNames = array_diff($columnNames, $unwantedItems);
        $this->columnNames = $columnNames;
    }

    public function getColumnNames()
    {
        return $this->columnNames ?? null;
    }

    public function collection()
    {
        $columnNames = $this->getColumnNames();
        return ErrorList::select($columnNames)->get();
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

