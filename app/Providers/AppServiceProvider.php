<?php

namespace App\Providers;

use App\Models\Hr\Employee;
use App\Models\Students\Student;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    }
}
