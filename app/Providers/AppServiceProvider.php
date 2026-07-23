<?php

namespace App\Providers;

use App\Events\FeeCollected;
use App\Listeners\PostFeeCollectionToLedger;
use App\Models\Hr\Employee;
use App\Models\Students\Student;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
    }
}
