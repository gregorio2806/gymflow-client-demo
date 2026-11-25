<?php

namespace App\Providers;

use App\Enums\GymFeature;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\CurrentGym;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentGym::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Platform admins (SaaS operator) pass every authorization check.
        Gate::before(fn (User $user) => $user->is_platform_admin ? true : null);
        Gate::define('access-platform-admin', fn (User $user) => $user->is_platform_admin);

        $this->configureFeatures();
        RateLimiter::for('website-inquiry', fn (Request $request) => [
            Limit::perHour(5)->by('website-inquiry:'.app(CurrentGym::class)->id().':'.$request->ip()),
            Limit::perDay(50)->by('website-inquiry-gym:'.app(CurrentGym::class)->id()),
        ]);

        RateLimiter::for('portal-resolution', fn (Request $request) => Limit::perMinute(10)->by('portal-qr:'.app(CurrentGym::class)->id().':'.$request->ip()));
        RateLimiter::for('portal-login', fn (Request $request) => [
            Limit::perMinute(10)->by('portal-ip:'.app(CurrentGym::class)->id().':'.$request->ip()),
            Limit::perMinute(5)->by('portal-login:'.app(CurrentGym::class)->id().':'.hash('sha256', strtolower(is_string($request->input('login')) ? $request->input('login') : '')).':'.$request->ip()),
        ]);

        // "Try demo": each click creates a gym + account, so limit per visitor.
        RateLimiter::for('demo', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        // Public "Request access" form: each submission emails the platform admin.
        // Per visitor, plus an overall cap so bots rotating IP addresses can't flood the inbox.
        RateLimiter::for('access-request', fn (Request $request) => [
            Limit::perHour(3)->by('ip:'.$request->ip()),
            Limit::perHour(30)->by('all'),
        ]);

        RateLimiter::for('check-in', fn (Request $request) => [
            Limit::perMinute(60)->by('check-in:'.app(CurrentGym::class)->id().':'.$request->user()->id),
            Limit::perMinute(120)->by('check-in-ip:'.$request->ip()),
        ]);
        RateLimiter::for('attendance-search', fn (Request $request) => Limit::perMinute(60)->by('attendance-search:'.app(CurrentGym::class)->id().':'.$request->user()->id));
    }

    /**
     * Per-gym feature switches (Laravel Pennant). Features are checked for the current gym;
     * with no current gym every feature is off.
     */
    protected function configureFeatures(): void
    {
        Feature::resolveScopeUsing(fn () => app(CurrentGym::class)->get());

        foreach (GymFeature::cases() as $feature) {
            Feature::define($feature->value, fn (Gym $gym) => $gym->feature_overrides[$feature->value] ?? $feature->enabledByDefault($gym->plan()));
        }
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
