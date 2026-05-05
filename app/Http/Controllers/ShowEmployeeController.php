<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ShowEmployeeController extends Controller
{
    public function __invoke(int $employee): Response
    {
        return Inertia::render('Employees/Show', [
            'employeeId' => $employee,
        ]);
    }
}
