<?php

namespace App\Exports;

use App\Models\UserTrail;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserTrailsExport implements FromQuery, WithHeadings, WithMapping
{
    protected $user_id;
    protected $start_date;
    protected $end_date;

    public function __construct($user_id, $start_date, $end_date)
    {
        $this->user_id = $user_id;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function query()
    {
        $query = UserTrail::query();

        if ($this->user_id) {
            $query->where('user_id', $this->user_id);
        }

        if ($this->start_date) {
            $query->whereDate('created_at', '>=', $this->start_date);
        }

        if ($this->end_date) {
            $query->whereDate('created_at', '<=', $this->end_date);
        }

        return $query;
    }

    // Define the column headings for the Excel file
    public function headings(): array
    {
        return [
            'User ID',
            'URL',
            'Parameters',
            'Timestamp',
        ];
    }

    // Map each row of data to the columns in Excel
    public function map($trail): array
    {
        return [
            $trail->user_id,
            $trail->url,
            $trail->parameters,
            $trail->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
