<?php

namespace App\Providers;

use App\Contracts\FaceRecognitionService;
use App\Models\User;
use App\Services\Attendance\AttendanceDayCalculator;
use App\Services\Attendance\AttendancePeriodSummary;
use App\Services\Attendance\ScheduleWindowResolver;
use App\Services\Attendance\WorkDateResolver;
use App\Services\Face\FaceRecognitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El proveedor biométrico se resuelve dinámicamente: manager → adaptador
        // de AWS, simulación explícita (`fake`) o implementación inerte (D-13).
        $this->app->scoped(FaceRecognitionManager::class);
        $this->app->bind(
            FaceRecognitionService::class,
            fn (Application $app): FaceRecognitionService => $app->make(FaceRecognitionManager::class)->service(),
        );

        // El motor de jornada comparte caché de horarios durante la petición.
        $this->app->scoped(ScheduleWindowResolver::class);
        $this->app->scoped(AttendanceDayCalculator::class);
        $this->app->scoped(AttendancePeriodSummary::class);
        $this->app->scoped(WorkDateResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * El rol `admin` pasa cualquier ability; el resto se resuelve con Policies
     * o con `User::hasPermission()` (D-01).
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->hasRole(User::ADMIN_ROLE) ? true : null;
        });
    }

    /**
     * Limitadores del checador (§6): 10 marcajes por minuto y por IP y 5 por
     * minuto por número de empleado. La clave del límite por colaborador
     * incluye la IP para evitar bloqueos dirigidos contra un número ajeno.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('kiosk-pin', fn (Request $request): Limit => Limit::perMinute(10)->by('kiosk-pin-ip:'.$request->ip()));

        RateLimiter::for('kiosk-pin-employee', function (Request $request): Limit {
            $employeeNumber = trim((string) $request->input('employee_number'));

            return Limit::perMinute(5)->by('kiosk-pin-employee:'.Str::lower($employeeNumber).'|'.$request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
