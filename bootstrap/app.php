<?php

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
            'administration.access' => \App\Http\Middleware\EnsureAdministrationAccess::class,
            'branch.selected' => \App\Http\Middleware\EnsureBranchSelected::class,
            'employee.team.hr.leave-overtime' => \App\Http\Middleware\EnsureEmployeeTeamHrLeaveOvertimeAccess::class,
            'non.employee' => \App\Http\Middleware\EnsureNotEmployeeRole::class,
            'organization-chart.edit' => \App\Http\Middleware\EnsureOrganizationChartEditAccess::class,
            'schedule.assignment' => \App\Http\Middleware\EnsureScheduleAssignmentAccess::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
