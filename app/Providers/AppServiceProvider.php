<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Hosting platforms end HTTPS at their load balancer; keep generated links (e.g. tracking URLs on notes) on https.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
        Gate::before(fn (User $user) => $user->role === Role::Owner ? true : null);
    }
}
