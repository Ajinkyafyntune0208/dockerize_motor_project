<?php

namespace App\Imports;

use App\Models\PreviousInsurer;
use Maatwebsite\Excel\Concerns\ToModel;

class PreviousInsurerImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new PreviousInsurer([
            //
        ]);
    }
}
