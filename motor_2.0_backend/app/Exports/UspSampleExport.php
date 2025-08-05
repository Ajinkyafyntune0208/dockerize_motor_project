<?php

namespace App\Exports;

use App\Models\MasterCompany;
use Maatwebsite\Excel\Concerns\FromCollection;

class UspSampleExport implements FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $master_company = MasterCompany::whereNotNull('company_alias')->get('company_alias');

        $data[] = [
            'usp_desc',
            'ic_alias'
        ];
        foreach ($master_company as $key => $value) {
            $data[] = [
                'usp_desc' => '',
                'ic_alias' => $value->company_alias
            ];
        }
        return collect($data);
    }
}
