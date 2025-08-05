<?php

namespace App\Exports;

// use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class EmbeddedScrubDataExports implements FromView
{
    protected $data = [];
    public function __construct(array $data)
    {
        $this->data = $data;
    }
  
    public function view(): View
    {
        // return collect($this->data);
        return view('exports.embedded_scrub_export', [
            'data' => $this->data
        ]);
    }
}
