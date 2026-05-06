<?php

use App\Http\Middleware\EnsureAdministrationAccess;
use App\Http\Middleware\EnsureBranchSelected;
use App\Http\Middleware\EnsureEmployeeDirectoryAccess;
use App\Http\Middleware\EnsureEmployeeTeamHrLeaveOvertimeAccess;
use App\Http\Middleware\EnsureNotEmployeeRole;
use App\Http\Middleware\EnsureOrganizationChartEditAccess;
use App\Http\Middleware\EnsureScheduleAssignmentAccess;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->alias([
            'administration.access' => EnsureAdministrationAccess::class,
            'branch.selected' => EnsureBranchSelected::class,
            'employee.directory.access' => EnsureEmployeeDirectoryAccess::class,
            'employee.team.hr.leave-overtime' => EnsureEmployeeTeamHrLeaveOvertimeAccess::class,
            'non.employee' => EnsureNotEmployeeRole::class,
            'organization-chart.edit' => EnsureOrganizationChartEditAccess::class,
            'schedule.assignment' => EnsureScheduleAssignmentAccess::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
