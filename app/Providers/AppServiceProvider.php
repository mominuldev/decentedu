<?php

namespace App\Providers;

use App\Events\FeeCollected;
use App\Listeners\PostFeeCollectionToLedger;
use App\Models\Hr\Employee;
use App\Models\Students\Student;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One active-branch context per request, shared across scope + middleware.
        $this->app->scoped(BranchContext::class);

        // No real SMS gateway is configured for this project — see LogSmsGateway docblock.
        $this->app->bind(SmsGatewayInterface::class, LogSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Stable polymorphic type aliases (attendance device maps, future media/attachments).
        Relation::morphMap([
            'student' => Student::class,
            'employee' => Employee::class,
        ]);

        // Fees stays ignorant of Accounting internals — a receive voucher is posted async-free,
        // in the same request, so the receipt response can include GL confirmation.
        Event::listen(FeeCollected::class, PostFeeCollectionToLedger::class);

        // The SPA (same origin as the API) owns the reset-password screen, not a Blade view.
        ResetPassword::createUrlUsing(fn (User $notifiable, string $token) => rtrim(config('app.url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($notifiable->email));

        // Super Admin bypasses every permission:/role: check — branch scoping already
        // guarantees row-level isolation, so this doesn't widen access across branches.
        Gate::before(fn (User $user) => $user->hasRole('Super Admin') ? true : null);

        // ->uncompromised() calls the HaveIBeenPwned API — skip it outside production so local
        // dev/tests don't depend on outbound network access.
        Password::defaults(fn () => app()->environment('production')
            ? Password::min(8)->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->mixedCase()->numbers());

        // Named limiters for the routes doc 08 flags as needing rate limits beyond login
        // (paid SMS sends, heavy report generation, bulk imports).
        RateLimiter::for('sms', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('reports', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?? $r->ip()));
        RateLimiter::for('bulk-import', fn (Request $r) => Limit::perMinute(5)->by($r->user()?->id ?? $r->ip()));

        // Throws on any lazy-loaded relation outside prod so N+1s surface in dev/tests
        // instead of silently costing extra queries per row in production.
        Model::preventLazyLoading(! app()->isProduction());
    }
}
