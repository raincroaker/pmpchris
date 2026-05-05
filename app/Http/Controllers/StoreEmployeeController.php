<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Services\StoreEmployeeWizardService;
use Illuminate\Http\RedirectResponse;

class StoreEmployeeController extends Controller
{
    public function __construct(
        private StoreEmployeeWizardService $storeEmployeeWizardService,
    ) {}

    public function __invoke(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->storeEmployeeWizardService->store($request);

        return to_route('employees')->with('success', 'Employee created successfully.');
    }
}
