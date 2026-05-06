<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncEmployeeAboutMeContactsRequest;
use App\Models\Employee;
use App\Services\AboutMeEmployeeDirectoryProfileWriter;
use Illuminate\Http\RedirectResponse;

class SyncEmployeeAboutMeContactsController extends Controller
{
    public function __invoke(SyncEmployeeAboutMeContactsRequest $request, Employee $employee, AboutMeEmployeeDirectoryProfileWriter $writer): RedirectResponse
    {
        /** @var array<int, array<string, mixed>> $personalInput */
        $personalInput = $request->validated()['personal'];

        /** @var array<int, array<string, mixed>> $emergencyInput */
        $emergencyInput = $request->validated()['emergency'];

        $personal = [];
        foreach ($personalInput as $row) {
            $label = isset($row['channel_label']) && is_string($row['channel_label']) ? trim($row['channel_label']) : 'Mobile';

            $personal[] = [
                'type' => strtolower($label),
                'contact_number' => (string) $row['contact_number'],
                'email' => $row['email'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ];
        }

        $emergency = [];
        foreach ($emergencyInput as $row) {
            $label = isset($row['channel_label']) && is_string($row['channel_label']) ? trim($row['channel_label']) : '';
            if ($label === '') {
                $label = 'Mobile';
            }

            $emergency[] = [
                'type' => strtolower($label),
                'contact_person' => (string) $row['contact_person'],
                'relationship' => isset($row['relationship'])
                    && is_string($row['relationship'])
                    && trim($row['relationship']) !== ''
                    ? trim($row['relationship'])
                    : null,
                'contact_number' => (string) $row['contact_number'],
                'email' => $row['email'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ];
        }

        $writer->syncContacts($employee, $personal, $emergency);

        return back()->with('success', 'Contacts updated.');
    }
}
