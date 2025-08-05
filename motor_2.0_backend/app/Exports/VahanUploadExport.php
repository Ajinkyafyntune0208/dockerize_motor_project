<?php

namespace App\Exports;

use App\Models\VahanUplordLogs;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\Exportable;

class VahanUploadExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    use Exportable;
    
    protected $start;
    protected $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function query()
    {
        return VahanUplordLogs::query()
            ->select('vehicle_reg_no', 'response', 'created_at')
            ->when($this->start, fn($q) => $q->whereDate('created_at', '>=', $this->start))
            ->when($this->end, fn($q) => $q->whereDate('created_at', '<=', $this->end));
    }
    public function headings(): array
    {
        return ['Vehicle Registration No', 'Response', 'Created At'];
    }

    public function map($row): array
    {
        return [
            $row->vehicle_reg_no,
            $row->response,
            $row->created_at
        ];
    }

    public function chunkSize(): int
    {
        return config('constants.vahan_import_excel.chunk_size' , 5000);
    }
}