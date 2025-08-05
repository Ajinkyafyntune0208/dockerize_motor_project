<?php

namespace App\Exports;

use App\Models\NomineeRelationshipNew;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class NomineeExport implements FromCollection, WithHeadings
{
    protected $columnNames;

    public function __construct()
    {
        $this->setColumnNames();
    }

    protected function setColumnNames()
    {
        $model = new NomineeRelationshipNew;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('nominee_relationship_new');
        $valueToMove = 'relation_name';
        $index = array_search($valueToMove, $columnNames);
        if ($index !== false) {
            array_splice($columnNames, $index, 1);
            array_unshift($columnNames, $valueToMove);
        }
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
        return NomineeRelationshipNew::select($columnNames)->get();
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
