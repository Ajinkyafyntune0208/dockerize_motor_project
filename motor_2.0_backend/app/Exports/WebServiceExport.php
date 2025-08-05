<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class WebServiceExport implements FromView
{
    // /**
    // * @return \Illuminate\Support\Collection
    // */
    // public function collection()
    // {
    //     //
    // }
    protected $logs = [];

    function __construct($logs = [])
    {
        $this->logs = $logs;
    }

    public function view(): View
    {
        return view('exports.web_service', ['logs' => $this->logs]);
    }
}
