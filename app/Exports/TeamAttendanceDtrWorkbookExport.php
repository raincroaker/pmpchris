<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TeamAttendanceDtrWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  list<DtrMockExport>  $sheets
     */
    public function __construct(
        private array $sheets,
    ) {}

    /**
     * @return list<DtrMockExport>
     */
    public function sheets(): array
    {
        return $this->sheets;
    }
}

