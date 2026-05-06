<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDtrMockExcelController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $mode = (string) $request->query('mode', 'sample');
        $dateFrom = (string) $request->query('date_from', now()->startOfMonth()->toDateString());
        $dateTo = (string) $request->query('date_to', now()->endOfMonth()->toDateString());
        $unitId = $request->query('unit_id');

        $rows = [
            ['Generated at', now()->toDateTimeString()],
            ['Mode', $mode],
            ['Date from', $dateFrom],
            ['Date to', $dateTo],
            ['Unit', $unitId !== null ? (string) $unitId : 'All'],
            [],
            ['Date', 'Attendance ID', 'Employee', 'Clock In', 'Clock Out', 'Net Hours', 'Status'],
            [$dateFrom, 'AT-001', 'Sample Employee A', '08:00', '17:00', '8.00', 'Complete'],
            [$dateFrom, 'AT-002', 'Sample Employee B', '08:12', '17:00', '7.80', 'Late'],
        ];

        $filename = sprintf(
            'dtr-%s-%s-%s.csv',
            $mode !== '' ? $mode : 'sample',
            $dateFrom !== '' ? $dateFrom : now()->toDateString(),
            $dateTo !== '' ? $dateTo : now()->toDateString(),
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
