<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();

        $this->configureAuthorization();
    }

    private function configureAuthorization(): void
    {
        Auth::provider('active-eloquent', function (Application $app, array $config): EloquentUserProvider {
            return (new EloquentUserProvider($app->make('hash'), $config['model']))
                ->withQuery(fn (Builder $query) => $query->where('is_active', true));
        });

        Gate::before(fn (User $user): ?bool => $user->is_active ? null : false);
        Gate::define('access-admin', fn (User $user): bool => $user->role === UserRole::Admin);
        Gate::define('operate-orders', fn (User $user): bool => in_array(
            $user->role,
            [UserRole::Staff, UserRole::Admin],
            true,
        ));
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
