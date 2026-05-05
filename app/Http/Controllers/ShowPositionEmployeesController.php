<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Position;
use App\Services\BranchContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowPositionEmployeesController extends Controller
{
    public function __invoke(Request $request, Position $position, BranchContextService $branchContextService): JsonResponse
    {
        $organization = $branchContextService->defaultOrganization();

        if ($organization === null || $position->organization_id !== $organization->id) {
            abort(404);
        }

        $today = now()->toDateString();

        $employees = Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.middle_name',
                'employees.last_name',
                'employees.suffix',
                'employees.id_number',
            ])
            ->whereNull('employees.deleted_at')
            ->whereExists(function ($query) use ($position, $today): void {
                $query->selectRaw('1')
                    ->from('employee_positions')
                    ->whereColumn('employee_positions.employee_id', 'employees.id')
                    ->where('employee_positions.position_id', $position->id)
                    ->whereNull('employee_positions.deleted_at')
                    ->where(function ($q) use ($today): void {
                        $q->whereNull('employee_positions.end_date')
                            ->orWhereDate('employee_positions.end_date', '>=', $today);
                    });
            })
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->get();

        return response()->json([
            'employees' => $employees->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'display_name' => $this->formatEmployeeDisplayName($employee),
                'id_number' => $employee->id_number,
            ])->values()->all(),
        ]);
    }

    private function formatEmployeeDisplayName(Employee $employee): string
    {
        $parts = array_values(array_filter([
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
            $employee->suffix,
        ], fn (?string $part): bool => filled($part)));

        if ($parts === []) {
            return 'Employee #'.$employee->id;
        }

        return implode(' ', $parts);
    }
}
