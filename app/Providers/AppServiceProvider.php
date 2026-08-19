<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Override;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
    }

    /**
     * Configure Eloquent for a fast-moving codebase.
     *
     * Models are unguarded application-wide, so every array handed to fill(), create()
     * or update() must be hand-crafted rather than passed straight from a request.
     * Form Requests are what make that safe here: controllers only ever pass validated().
     */
    private function configureModels(): void
    {
        Model::unguard();

        // Surface N+1 queries and silent attribute typos in development, where they
        // are cheap to fix, rather than as a slow page in production.
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    private function configureDefaults(): void
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
