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
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecordPolicy;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecordPolicy;
use App\Support\ActivityLog\VanguardActivityLogParentResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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
        Activity::created(function (Activity $activity): void {
            if (filled(data_get($activity->properties, 'vanguard_parent_type'))) {
                return;
            }

            $resolver = app(VanguardActivityLogParentResolver::class);
            $parent = $resolver->resolve($activity);

            if ($parent === null) {
                return;
            }

            $properties = $activity->properties?->toArray() ?? [];

            $activity->properties = array_merge($properties, [
                'vanguard_parent_type' => $parent['type'],
                'vanguard_parent_id' => (string) $parent['id'],
                'vanguard_parent_label' => $parent['label'],
            ]);

            $activity->saveQuietly();
        });

        Event::listen(Login::class, function (Login $event): void {
            app(TenantContext::class)->initializeForUser($event->user);
        });

        Gate::policy(OrganizationRecord::class, OrganizationRecordPolicy::class);
        Gate::policy(TenantRecord::class, TenantRecordPolicy::class);
        Gate::policy(EmployeeRecord::class, EmployeeRecordPolicy::class);
        Gate::policy(
            EmployeeWorkScheduleTemplateRecord::class,
            EmployeeWorkScheduleTemplateRecordPolicy::class
        );
        Gate::policy(PartnerRecord::class, PartnerRecordPolicy::class);
        Gate::policy(VisitorRecord::class, VisitorRecordPolicy::class);
        Gate::policy(VisitRecord::class, VisitRecordPolicy::class);
        Gate::policy(
            ClassificationOptionRecord::class,
            ClassificationOptionRecordPolicy::class
        );
    }
}
