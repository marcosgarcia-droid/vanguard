<?php

namespace App\Providers;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\PartnerRecordPolicy;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecordPolicy;
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
        Gate::policy(OrganizationRecord::class, OrganizationRecordPolicy::class);
        Gate::policy(TenantRecord::class, TenantRecordPolicy::class);
        Gate::policy(EmployeeRecord::class, EmployeeRecordPolicy::class);
        Gate::policy(PartnerRecord::class, PartnerRecordPolicy::class);
        //
    }
}
