<?php

use App\Http\Controllers\ActivateOrganizationChartUnitController;
use App\Http\Controllers\AdminUsersIndexController;
use App\Http\Controllers\AttendanceMyController;
use App\Http\Controllers\AttendanceTeamController;
use App\Http\Controllers\BranchCalendarController;
use App\Http\Controllers\BranchCalendarEventsIndexController;
use App\Http\Controllers\BranchContextController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatRoomMessagesIndexController;
use App\Http\Controllers\CheckAdminUserFieldAvailabilityController;
use App\Http\Controllers\CheckEmployeeFieldAvailabilityController;
use App\Http\Controllers\CheckOrganizationChartUnitCodeAvailabilityController;
use App\Http\Controllers\CheckPositionCodeAvailabilityController;
use App\Http\Controllers\CompanyCalendarController;
use App\Http\Controllers\CompanyCalendarEventsIndexController;
use App\Http\Controllers\CompanyDocumentsIndexController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeactivateOrganizationChartEditUnitTypeController;
use App\Http\Controllers\DeactivateOrganizationChartUnitController;
use App\Http\Controllers\DeactivatePositionController;
use App\Http\Controllers\DeleteOrganizationChartEmployeeAssignmentController;
use App\Http\Controllers\DestroyBranchCalendarEventController;
use App\Http\Controllers\DestroyCalendarEventCategoryController;
use App\Http\Controllers\DestroyCompanyCalendarEventController;
use App\Http\Controllers\DestroyCompanyDocumentController;
use App\Http\Controllers\DestroyCompanyDocumentFolderController;
use App\Http\Controllers\DestroyEmployeeLeaveController;
use App\Http\Controllers\DestroyEmployeeOvertimeController;
use App\Http\Controllers\DestroyHolidayTypeController;
use App\Http\Controllers\DestroyLeavePolicyController;
use App\Http\Controllers\DestroyOrganizationChartEditAreaController;
use App\Http\Controllers\DestroyOrganizationChartEditUnitTypeController;
use App\Http\Controllers\DestroyOrganizationChartUnitController;
use App\Http\Controllers\DestroyOrganizationHolidayController;
use App\Http\Controllers\DestroyOvertimePolicyController;
use App\Http\Controllers\DestroyPositionController;
use App\Http\Controllers\DestroyTeamAttendanceDayController;
use App\Http\Controllers\DestroyTeamCalendarEventController;
use App\Http\Controllers\DestroyWorkScheduleTemplateController;
use App\Http\Controllers\DownloadCompanyDocumentController;
use App\Http\Controllers\DownloadDtrMockExcelController;
use App\Http\Controllers\EmployeesAboutMeController;
use App\Http\Controllers\EmployeesCreateController;
use App\Http\Controllers\EmployeesEmploymentHistoryController;
use App\Http\Controllers\EmployeesIndexController;
use App\Http\Controllers\ExpandTeamHrLeavePeriodController;
use App\Http\Controllers\HolidayCalendarController;
use App\Http\Controllers\IndexOrganizationChartEditAreasController;
use App\Http\Controllers\IndexTeamHrFormUnitsController;
use App\Http\Controllers\IndexTeamHrOrganizationHolidayRulesController;
use App\Http\Controllers\LeaveMyController;
use App\Http\Controllers\LeavePoliciesController;
use App\Http\Controllers\LeaveTeamController;
use App\Http\Controllers\MarkUnitChatRoomReadController;
use App\Http\Controllers\OrganizationChartController;
use App\Http\Controllers\OrganizationChartEditController;
use App\Http\Controllers\OrganizationHolidaysIndexController;
use App\Http\Controllers\OvertimeMyController;
use App\Http\Controllers\OvertimePoliciesController;
use App\Http\Controllers\OvertimeTeamController;
use App\Http\Controllers\PositionsIndexController;
use App\Http\Controllers\PositionsJobHistoryController;
use App\Http\Controllers\PreviewCompanyDocumentController;
use App\Http\Controllers\ScheduleAssignmentController;
use App\Http\Controllers\SearchOrganizationChartEmployeesController;
use App\Http\Controllers\SearchTeamHrDecisionMakerEmployeesController;
use App\Http\Controllers\SearchTeamHrFormEmployeesController;
use App\Http\Controllers\ShowEmployeeController;
use App\Http\Controllers\ShowPositionEmployeesController;
use App\Http\Controllers\StoreBranchCalendarEventController;
use App\Http\Controllers\StoreCalendarEventCategoryController;
use App\Http\Controllers\StoreCompanyCalendarEventController;
use App\Http\Controllers\StoreCompanyDocumentController;
use App\Http\Controllers\StoreCompanyDocumentFolderController;
use App\Http\Controllers\StoreEmployeeController;
use App\Http\Controllers\StoreEmployeeLeaveController;
use App\Http\Controllers\StoreEmployeeOvertimeController;
use App\Http\Controllers\StoreHolidayTypeController;
use App\Http\Controllers\StoreLeavePolicyController;
use App\Http\Controllers\StoreOrganizationChartEditAreaController;
use App\Http\Controllers\StoreOrganizationChartEmployeeAssignmentController;
use App\Http\Controllers\StoreOrganizationChartUnitController;
use App\Http\Controllers\StoreOrganizationChartUnitTypeController;
use App\Http\Controllers\StoreOrganizationHolidayController;
use App\Http\Controllers\StoreOvertimePolicyController;
use App\Http\Controllers\StorePositionController;
use App\Http\Controllers\StoreTeamAttendanceDayController;
use App\Http\Controllers\StoreTeamCalendarEventController;
use App\Http\Controllers\StoreUnitChatMessageController;
use App\Http\Controllers\StoreWorkScheduleTemplateController;
use App\Http\Controllers\SyncEmployeeAboutMeAddressesController;
use App\Http\Controllers\SyncEmployeeAboutMeContactsController;
use App\Http\Controllers\SyncEmployeeEmploymentPositionsAffiliationsController;
use App\Http\Controllers\TeamCalendarController;
use App\Http\Controllers\TeamCalendarEventsIndexController;
use App\Http\Controllers\TeamHrEmployeeLeaveUsageSummaryController;
use App\Http\Controllers\TeamHrEmployeeOvertimeUsageSummaryController;
use App\Http\Controllers\UpdateAdminNoAccountUserController;
use App\Http\Controllers\UpdateAdminUserController;
use App\Http\Controllers\UpdateBranchCalendarEventController;
use App\Http\Controllers\UpdateCalendarEventCategoryController;
use App\Http\Controllers\UpdateCompanyCalendarEventController;
use App\Http\Controllers\UpdateCompanyDocumentController;
use App\Http\Controllers\UpdateCompanyDocumentFolderController;
use App\Http\Controllers\UpdateCompanyDocumentInternalMetadataController;
use App\Http\Controllers\UpdateEmployeeAboutMeBasicsController;
use App\Http\Controllers\UpdateEmployeeAboutMeDemographicsController;
use App\Http\Controllers\UpdateEmployeeEmploymentDatesController;
use App\Http\Controllers\UpdateEmployeeLeaveController;
use App\Http\Controllers\UpdateEmployeeOvertimeController;
use App\Http\Controllers\UpdateEmployeeWorkScheduleTemplateController;
use App\Http\Controllers\UpdateHolidayTypeController;
use App\Http\Controllers\UpdateLeavePolicyController;
use App\Http\Controllers\UpdateOrganizationChartEditAreaController;
use App\Http\Controllers\UpdateOrganizationChartEmployeeAssignmentController;
use App\Http\Controllers\UpdateOrganizationChartUnitController;
use App\Http\Controllers\UpdateOrganizationChartUnitTypeController;
use App\Http\Controllers\UpdateOrganizationHolidayController;
use App\Http\Controllers\UpdateOvertimePolicyController;
use App\Http\Controllers\UpdatePositionController;
use App\Http\Controllers\UpdateTeamAttendanceDayController;
use App\Http\Controllers\UpdateTeamCalendarEventController;
use App\Http\Controllers\UpdateWorkScheduleTemplateController;
use App\Http\Controllers\WorkSchedulesController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('select-branch', [BranchContextController::class, 'create'])->name('branch.select');
    Route::post('select-branch', [BranchContextController::class, 'store'])->name('branch.store');
});

Route::middleware(['auth', 'verified', 'branch.selected'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('chat', ChatController::class)->name('chat');
    Route::get('chat/rooms/{room}/messages', ChatRoomMessagesIndexController::class)->name('chat.messages.index');
    Route::post('chat/rooms/{room}/messages', StoreUnitChatMessageController::class)->name('chat.messages.store');
    Route::post('chat/rooms/{room}/read', MarkUnitChatRoomReadController::class)->name('chat.read.store');

    Route::redirect('calendar', '/calendar/company');
    Route::redirect('attendance', '/attendance/my');
    Route::redirect('leave', '/leave/my');
    Route::redirect('overtime', '/overtime/my');
    Route::redirect('leave/request', '/leave/my');
    Route::redirect('overtime/request', '/overtime/my');
    Route::redirect('leave/my-requests', '/leave/my');
    Route::redirect('overtime/my-requests', '/overtime/my');
    Route::redirect('leave/team-requests', '/leave/team');
    Route::redirect('overtime/team-requests', '/overtime/team');

    Route::get('calendar/company', CompanyCalendarController::class)->name('calendar.company');
    Route::get('calendar/branch', BranchCalendarController::class)->name('calendar.branch');
    Route::get('calendar/team', TeamCalendarController::class)->name('calendar.team');
    Route::get('calendar/holidays', HolidayCalendarController::class)->name('calendar.holidays');
    Route::post('calendar/holiday-types', StoreHolidayTypeController::class)
        ->name('calendar.holiday-types.store');
    Route::patch('calendar/holiday-types/{holiday_type_slug}', UpdateHolidayTypeController::class)
        ->name('calendar.holiday-types.update');
    Route::delete('calendar/holiday-types/{holiday_type_slug}', DestroyHolidayTypeController::class)
        ->name('calendar.holiday-types.destroy');
    Route::post('calendar/organization-holidays', StoreOrganizationHolidayController::class)
        ->name('calendar.organization-holidays.store');
    Route::get('calendar/organization-holidays', OrganizationHolidaysIndexController::class)
        ->name('calendar.organization-holidays.index');
    Route::patch('calendar/organization-holidays/{organizationHoliday}', UpdateOrganizationHolidayController::class)
        ->name('calendar.organization-holidays.update');
    Route::delete('calendar/organization-holidays/{organizationHoliday}', DestroyOrganizationHolidayController::class)
        ->name('calendar.organization-holidays.destroy');
    Route::post('calendar/event-categories', StoreCalendarEventCategoryController::class)
        ->name('calendar.event-categories.store');
    Route::patch('calendar/event-categories/{calendarEventCategory}', UpdateCalendarEventCategoryController::class)
        ->name('calendar.event-categories.update');
    Route::delete('calendar/event-categories/{calendarEventCategory}', DestroyCalendarEventCategoryController::class)
        ->name('calendar.event-categories.destroy');
    Route::post('calendar/company/events', StoreCompanyCalendarEventController::class)
        ->name('calendar.company-events.store');
    Route::get('calendar/company/events', CompanyCalendarEventsIndexController::class)
        ->name('calendar.company-events.index');
    Route::patch('calendar/company/events/{companyCalendarEvent}', UpdateCompanyCalendarEventController::class)
        ->name('calendar.company-events.update');
    Route::delete('calendar/company/events/{companyCalendarEvent}', DestroyCompanyCalendarEventController::class)
        ->name('calendar.company-events.destroy');
    Route::post('calendar/branch/events', StoreBranchCalendarEventController::class)
        ->name('calendar.branch-events.store');
    Route::get('calendar/branch/events', BranchCalendarEventsIndexController::class)
        ->name('calendar.branch-events.index');
    Route::patch('calendar/branch/events/{branchCalendarEvent}', UpdateBranchCalendarEventController::class)
        ->name('calendar.branch-events.update');
    Route::delete('calendar/branch/events/{branchCalendarEvent}', DestroyBranchCalendarEventController::class)
        ->name('calendar.branch-events.destroy');
    Route::post('calendar/team/events', StoreTeamCalendarEventController::class)
        ->name('calendar.team-events.store');
    Route::get('calendar/team/events', TeamCalendarEventsIndexController::class)
        ->name('calendar.team-events.index');
    Route::patch('calendar/team/events/{teamCalendarEvent}', UpdateTeamCalendarEventController::class)
        ->name('calendar.team-events.update');
    Route::delete('calendar/team/events/{teamCalendarEvent}', DestroyTeamCalendarEventController::class)
        ->name('calendar.team-events.destroy');

    Route::middleware(['non.employee', 'employee.directory.access'])->group(function (): void {
        Route::get('employees', EmployeesIndexController::class)->name('employees');
        Route::get('employees/create', EmployeesCreateController::class)->name('employees.create');
        Route::get('employees/employment-history', EmployeesEmploymentHistoryController::class)->name('employees.employment-history');
        Route::get('employees/{employee}', ShowEmployeeController::class)
            ->whereNumber('employee')
            ->name('employees.show');
        Route::patch('employees/{employee}/about-me/basics', UpdateEmployeeAboutMeBasicsController::class)
            ->whereNumber('employee')
            ->name('employees.about-me.basics');
        Route::patch('employees/{employee}/about-me/demographics', UpdateEmployeeAboutMeDemographicsController::class)
            ->whereNumber('employee')
            ->name('employees.about-me.demographics');
        Route::patch('employees/{employee}/about-me/contacts', SyncEmployeeAboutMeContactsController::class)
            ->whereNumber('employee')
            ->name('employees.about-me.contacts');
        Route::patch('employees/{employee}/about-me/addresses', SyncEmployeeAboutMeAddressesController::class)
            ->whereNumber('employee')
            ->name('employees.about-me.addresses');
        Route::patch('employees/employments/{employment}', UpdateEmployeeEmploymentDatesController::class)
            ->whereNumber('employment')
            ->name('employees.employments.update-dates');
        Route::patch('employees/employments/{employment}/positions-affiliations', SyncEmployeeEmploymentPositionsAffiliationsController::class)
            ->whereNumber('employment')
            ->name('employees.employments.sync-positions-affiliations');
    });
    Route::get('employees/about-me', EmployeesAboutMeController::class)
        ->name('employees.about-me');
    Route::get('employees/check-availability', CheckEmployeeFieldAvailabilityController::class)
        ->name('employees.check-availability');
    Route::post('employees', StoreEmployeeController::class)->name('employees.store');
    Route::get('organization-chart', [OrganizationChartController::class, 'index'])->name('organization-chart');
    Route::get('organization-chart/employees/search', SearchOrganizationChartEmployeesController::class)
        ->name('organization-chart.employees.search');
    Route::post('organization-chart/employees', StoreOrganizationChartEmployeeAssignmentController::class)
        ->name('organization-chart.employees.store');
    Route::patch('organization-chart/employees/{assignment}', UpdateOrganizationChartEmployeeAssignmentController::class)
        ->name('organization-chart.employees.update');
    Route::delete('organization-chart/employees/{assignment}', DeleteOrganizationChartEmployeeAssignmentController::class)
        ->name('organization-chart.employees.destroy');
    Route::post('organization-chart/units', StoreOrganizationChartUnitController::class)
        ->name('organization-chart.units.store');
    Route::patch('organization-chart/units/{unit}', UpdateOrganizationChartUnitController::class)
        ->name('organization-chart.units.update');
    Route::patch('organization-chart/units/{unit}/activate', ActivateOrganizationChartUnitController::class)
        ->name('organization-chart.units.activate');
    Route::patch('organization-chart/units/{unit}/deactivate', DeactivateOrganizationChartUnitController::class)
        ->name('organization-chart.units.deactivate');
    Route::delete('organization-chart/units/{unit}', DestroyOrganizationChartUnitController::class)
        ->name('organization-chart.units.destroy');
    Route::get('organization-chart/units/check-code-availability', CheckOrganizationChartUnitCodeAvailabilityController::class)
        ->name('organization-chart.units.check-code-availability');

    Route::middleware('organization-chart.edit')->group(function (): void {
        Route::get('organization-chart/edit', OrganizationChartEditController::class)
            ->name('organization-chart.edit');
        Route::post('organization-chart/edit/unit-types', StoreOrganizationChartUnitTypeController::class)
            ->name('organization-chart.edit.unit-types.store');
        Route::patch('organization-chart/edit/unit-types/{unitType}', UpdateOrganizationChartUnitTypeController::class)
            ->name('organization-chart.edit.unit-types.update');
        Route::patch('organization-chart/edit/unit-types/{unitType}/deactivate', DeactivateOrganizationChartEditUnitTypeController::class)
            ->name('organization-chart.edit.unit-types.deactivate');
        Route::delete('organization-chart/edit/unit-types/{unitType}', DestroyOrganizationChartEditUnitTypeController::class)
            ->name('organization-chart.edit.unit-types.destroy');
        Route::get('organization-chart/edit/areas', IndexOrganizationChartEditAreasController::class)
            ->name('organization-chart.edit.areas.index');
        Route::post('organization-chart/edit/areas', StoreOrganizationChartEditAreaController::class)
            ->name('organization-chart.edit.areas.store');
        Route::patch('organization-chart/edit/areas/{area}', UpdateOrganizationChartEditAreaController::class)
            ->name('organization-chart.edit.areas.update');
        Route::delete('organization-chart/edit/areas/{area}', DestroyOrganizationChartEditAreaController::class)
            ->name('organization-chart.edit.areas.destroy');
    });

    Route::middleware('non.employee')->group(function () {
        Route::get('positions', PositionsIndexController::class)->name('positions');
        Route::get('positions/check-code-availability', CheckPositionCodeAvailabilityController::class)->name('positions.check-code-availability');
        Route::post('positions', StorePositionController::class)->name('positions.store');
        Route::patch('positions/{position}', UpdatePositionController::class)->name('positions.update');
        Route::patch('positions/{position}/deactivate', DeactivatePositionController::class)->name('positions.deactivate');
        Route::get('positions/job-history', PositionsJobHistoryController::class)->name('positions.job-history');
        Route::get('positions/{position}/employees', ShowPositionEmployeesController::class)->name('positions.employees');
        Route::delete('positions/{position}', DestroyPositionController::class)->name('positions.destroy');
    });

    Route::get('attendance/my', AttendanceMyController::class)->name('attendance.my');
    Route::get('attendance/reports/dtr-mock-sample', DownloadDtrMockExcelController::class)
        ->name('attendance.reports.dtr-mock-sample');
    Route::permanentRedirect('attendance/schedule-assignment', '/attendance/employee-schedules');

    Route::get('attendance/shifts', WorkSchedulesController::class)->name('attendance.shifts');

    Route::middleware('schedule.assignment')->group(function (): void {
        Route::post('attendance/work-schedule-templates', StoreWorkScheduleTemplateController::class)
            ->name('attendance.work-schedule-templates.store');
        Route::patch('attendance/work-schedule-templates/{workScheduleTemplate}', UpdateWorkScheduleTemplateController::class)
            ->name('attendance.work-schedule-templates.update');
        Route::delete('attendance/work-schedule-templates/{workScheduleTemplate}', DestroyWorkScheduleTemplateController::class)
            ->name('attendance.work-schedule-templates.destroy');
        Route::get('attendance/employee-schedules', ScheduleAssignmentController::class)
            ->name('attendance.employee-schedules');
        Route::patch('employees/{employee}/work-schedule-template', UpdateEmployeeWorkScheduleTemplateController::class)
            ->name('employees.work-schedule-template.update');
    });

    Route::get('leave/my', LeaveMyController::class)->name('leave.my');
    Route::get('leave/policies', LeavePoliciesController::class)->name('leave.policies');
    Route::post('leave/policies', StoreLeavePolicyController::class)->name('leave.policies.store');
    Route::patch('leave/policies/{leavePolicy}', UpdateLeavePolicyController::class)->name('leave.policies.update');
    Route::delete('leave/policies/{leavePolicy}', DestroyLeavePolicyController::class)->name('leave.policies.destroy');

    Route::get('overtime/my', OvertimeMyController::class)->name('overtime.my');
    Route::get('overtime/policies', OvertimePoliciesController::class)->name('overtime.policies');
    Route::post('overtime/policies', StoreOvertimePolicyController::class)->name('overtime.policies.store');
    Route::patch('overtime/policies/{overtimePolicy}', UpdateOvertimePolicyController::class)->name('overtime.policies.update');
    Route::delete('overtime/policies/{overtimePolicy}', DestroyOvertimePolicyController::class)->name('overtime.policies.destroy');

    Route::middleware('employee.team.hr.leave-overtime')->group(function (): void {
        Route::get('attendance/team', AttendanceTeamController::class)->name('attendance.team');
        Route::post('attendance/team/attendance-days', StoreTeamAttendanceDayController::class)
            ->name('attendance.team.attendance-days.store');
        Route::patch('attendance/team/attendance-days/{employeeAttendanceDay}', UpdateTeamAttendanceDayController::class)
            ->name('attendance.team.attendance-days.update');
        Route::delete('attendance/team/attendance-days/{employeeAttendanceDay}', DestroyTeamAttendanceDayController::class)
            ->name('attendance.team.attendance-days.destroy');
        Route::get('leave/team', LeaveTeamController::class)->name('leave.team');
        Route::post('leave/team/employee-leaves', StoreEmployeeLeaveController::class)
            ->name('leave.team.employee-leaves.store');
        Route::patch('leave/team/employee-leaves/{employeeLeave}', UpdateEmployeeLeaveController::class)
            ->name('leave.team.employee-leaves.update');
        Route::delete('leave/team/employee-leaves/{employeeLeave}', DestroyEmployeeLeaveController::class)
            ->name('leave.team.employee-leaves.destroy');

        Route::get('overtime/team', OvertimeTeamController::class)->name('overtime.team');
        Route::post('overtime/team/employee-overtimes', StoreEmployeeOvertimeController::class)
            ->name('overtime.team.employee-overtimes.store');
        Route::patch('overtime/team/employee-overtimes/{employeeOvertime}', UpdateEmployeeOvertimeController::class)
            ->name('overtime.team.employee-overtimes.update');
        Route::delete('overtime/team/employee-overtimes/{employeeOvertime}', DestroyEmployeeOvertimeController::class)
            ->name('overtime.team.employee-overtimes.destroy');

        Route::get('team-hr/units', IndexTeamHrFormUnitsController::class)
            ->name('team-hr.units.index');
        Route::get('team-hr/employees/search', SearchTeamHrFormEmployeesController::class)
            ->name('team-hr.employees.search');
        Route::get('team-hr/employees/decision-makers/search', SearchTeamHrDecisionMakerEmployeesController::class)
            ->name('team-hr.employees.decision-makers.search');
        Route::get('team-hr/organization-holidays', IndexTeamHrOrganizationHolidayRulesController::class)
            ->name('team-hr.organization-holidays.index');
        Route::get('team-hr/leave-period/expand', ExpandTeamHrLeavePeriodController::class)
            ->name('team-hr.leave-period.expand');
        Route::get('team-hr/leave-usage-summary', TeamHrEmployeeLeaveUsageSummaryController::class)
            ->name('team-hr.leave-usage-summary.index');
        Route::get('team-hr/overtime-usage-summary', TeamHrEmployeeOvertimeUsageSummaryController::class)
            ->name('team-hr.overtime-usage-summary.index');
    });

    Route::inertia('documents/my', 'Documents/My')->name('documents.my');
    Route::inertia('documents/team', 'Documents/Team')->name('documents.team');
    Route::inertia('documents/branch', 'Documents/Branch')->name('documents.branch');
    Route::inertia('documents/company', 'Documents/Company')->name('documents.company');
    Route::get('documents/company/items', CompanyDocumentsIndexController::class)
        ->name('documents.company.items.index');
    Route::post('documents/company/folders', StoreCompanyDocumentFolderController::class)
        ->name('documents.company.folders.store');
    Route::patch('documents/company/folders/{companyDocumentFolder}', UpdateCompanyDocumentFolderController::class)
        ->name('documents.company.folders.update');
    Route::delete('documents/company/folders/{companyDocumentFolder}', DestroyCompanyDocumentFolderController::class)
        ->name('documents.company.folders.destroy');
    Route::post('documents/company/files', StoreCompanyDocumentController::class)
        ->name('documents.company.files.store');
    Route::patch('documents/company/files/{companyDocument}', UpdateCompanyDocumentController::class)
        ->name('documents.company.files.update');
    Route::patch('documents/company/files/{companyDocument}/internal-metadata', UpdateCompanyDocumentInternalMetadataController::class)
        ->name('documents.company.files.internal-metadata.update');
    Route::delete('documents/company/files/{companyDocument}', DestroyCompanyDocumentController::class)
        ->name('documents.company.files.destroy');
    Route::get('documents/company/files/{companyDocument}/download', DownloadCompanyDocumentController::class)
        ->name('documents.company.files.download');
    Route::get('documents/company/files/{companyDocument}/preview', PreviewCompanyDocumentController::class)
        ->name('documents.company.files.preview');
    Route::inertia('documents/trash', 'Documents/Trash')->name('documents.trash');

    Route::middleware(['non.employee', 'administration.access'])->group(function () {
        Route::get('admin/users', AdminUsersIndexController::class)->name('admin.users');
        Route::patch('admin/users/{user}', UpdateAdminUserController::class)->name('admin.users.update');
        Route::patch('admin/users/no-account/{employee}', UpdateAdminNoAccountUserController::class)->name('admin.users.update-no-account');
        Route::get('admin/users/check-availability', CheckAdminUserFieldAvailabilityController::class)
            ->name('admin.users.check-availability');
    });
});

require __DIR__.'/settings.php';
