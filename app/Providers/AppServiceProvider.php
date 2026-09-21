<?php

namespace App\Providers;

use App\Models\User;
use App\View\Composers\MemberLayoutComposer;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
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
        Model::preventLazyLoading();

        Gate::define('view-employee-attendance', fn (User $user): bool => $user->role === 'admin' || $user->isHeadCoach());

        Gate::define('impersonate', function (User $admin, User $target): bool {
            return $admin->role === 'admin'
                && $admin->is_active
                && ! session()->has('impersonator_id')
                && ! $admin->is($target)
                && $target->is_active
                && ! in_array($target->role, ['admin', 'sales', 'cleaning_service'], true);
        });

        Gate::define('stop-impersonating', fn (User $user): bool => session()->has('impersonator_id'));

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->guard === 'web' && session()->has('impersonator_id')) {
                Log::info('impersonation.stopped', [
                    'admin_id' => session('impersonator_id'),
                    'target_id' => $event->user?->getAuthIdentifier(),
                    'reason' => 'logout',
                    'at' => now()->toIso8601String(),
                ]);
                session()->forget('impersonator_id');
            }
        });

        View::composer('layouts::member', MemberLayoutComposer::class);
    }
}
