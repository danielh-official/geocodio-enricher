<?php

namespace App\Providers;

use App\Services\FakeGeocodio;
use Carbon\CarbonImmutable;
use Geocodio\Geocodio;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Overrides the package's own binding: app providers register after
        // discovered ones. 'Geocodio' is the package config default, which is
        // what you get when GEOCODIO_API_KEY is unset.
        $this->app->bind(Geocodio::class, function (): Geocodio {
            $key = config('geocodio.api_key');

            return blank($key) || $key === 'Geocodio'
                ? new FakeGeocodio
                : (new Geocodio)
                    ->setApiKey($key)
                    ->setHostname(config('geocodio.hostname'))
                    ->setApiVersion(config('geocodio.api_version'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
