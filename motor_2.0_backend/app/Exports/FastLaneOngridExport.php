<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class FastLaneOngridExport implements FromCollection
{
    public $logs = [];
    public function __construct($logs) {
        $this->logs = $logs;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $data = [];
      foreach ($this->logs as $key => $value) {
          if ($key == 0) {
              $column = [];
            foreach($value as $key1 => $value1){

                $column[] = \Illuminate\Support\Str::title(str_replace('_', ' ', $key1));
            }
            $data[] = $column;
          }
          $data[] = $value;
      }
      return collect(json_decode(json_encode($data), true));
    }
}
