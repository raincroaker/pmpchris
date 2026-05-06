<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\EmployeeEmployment;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = (string) $request->string('email');
            $password = (string) $request->string('password');

            $user = User::query()
                ->with(['employee.currentEmployment', 'employee.employments'])
                ->where('email', $email)
                ->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                return null;
            }

            if ($user->employee_id === null) {
                return $user;
            }

            $employee = $user->employee;
            $currentEmployment = $employee?->currentEmployment;

            if (
                $employee === null
                || $currentEmployment === null
                || $currentEmployment->employment_status !== EmployeeEmployment::STATUS_ACTIVE
            ) {
                throw ValidationException::withMessages([
                    'portal_access' => $this->buildInactiveEmploymentMessage($user),
                ]);
            }

            return $user;
        });
    }

    private function buildInactiveEmploymentMessage(User $user): string
    {
        $latestSeparatedEmployment = $user->employee?->employments
            ->whereNotNull('separation_date')
            ->sortByDesc('separation_date')
            ->first();

        if (! $latestSeparatedEmployment) {
            return 'Your account is no longer active for HRIS access. Please contact HR for assistance.';
        }

        $statusLabel = match ($latestSeparatedEmployment->employment_status) {
            EmployeeEmployment::STATUS_RESIGNED => 'resigned',
            EmployeeEmployment::STATUS_TERMINATED => 'terminated',
            EmployeeEmployment::STATUS_RETIRED => 'retired',
            EmployeeEmployment::STATUS_CONTRACT_ENDED => 'contract ended',
            default => 'inactive',
        };

        $effectiveDate = $latestSeparatedEmployment->separation_date?->toFormattedDateString();

        if ($effectiveDate === null) {
            return "Your employment is marked as {$statusLabel}. You no longer have system access. Please contact HR for assistance.";
        }

        return "Your employment is marked as {$statusLabel} effective {$effectiveDate}. You no longer have system access. Please contact HR for assistance.";
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
