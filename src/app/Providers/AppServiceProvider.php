<?php

namespace App\Providers;

use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeWorkScheduleTemplateRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecordPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        Event::listen(Login::class, function (Login $event): void {
            app(TenantContext::class)->initializeForUser($event->user);
        });

        Gate::policy(OrganizationRecord::class, OrganizationRecordPolicy::class);
        Gate::policy(TenantRecord::class, TenantRecordPolicy::class);
        Gate::policy(EmployeeRecord::class, EmployeeRecordPolicy::class);
        Gate::policy(EmployeeWorkScheduleTemplateRecord::class, EmployeeWorkScheduleTemplateRecordPolicy::class);
        Gate::policy(PartnerRecord::class, PartnerRecordPolicy::class);
        Gate::policy(ClassificationOptionRecord::class, ClassificationOptionRecordPolicy::class);
        //
    }
}
