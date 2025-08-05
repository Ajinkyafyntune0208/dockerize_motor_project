<?php

namespace App\Exports;

use App\Models\FinanceAgreementNew;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FinanceAgreementNewExport implements FromCollection, WithHeadings
{
    protected $columnNames;

    public function __construct()
    {
        $this->setColumnNames();
    }

    protected function setColumnNames()
    {
        $model = new FinanceAgreementNew;
        $columnNames = $model->getConnection()->getSchemaBuilder()->getColumnListing('financier_agreement_type_new');
        $valueToMove = 'financier_agreement_name';
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
        return FinanceAgreementNew::select($columnNames)->get();
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
