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
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReader;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReaderResolver;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadGuard;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadRepository;
use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowRepository;
use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventRepository;
use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionRepository;
use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionRepository;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\AccessEventIngestionRepository;
use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventRepository;
use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewRepository;
use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventRepository;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowRepository;
use App\Modules\Operations\Infrastructure\Concurrency\CacheAccessDeviceConfigurationReadGuard;
use App\Modules\Operations\Infrastructure\Integrations\ConfiguredAccessDeviceConfigurationReaderResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\IntelbrasFacialReadOnlyReader;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecordPolicy;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecordPolicy;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentAccessDeviceConfigurationReadRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentAccessEventIngestionRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentContinueManuallyAssociatedAccessEventFlowRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentDecideAccessEventRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentExecuteAccessEventOperationalExecutionRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentManualAssociateAccessEventRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentProcessAccessEventRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRecordAccessEventManualReviewRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRegisterAccessEventOperationalExecutionRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentReprocessAccessEventFlowRepository;
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
        $this->app->bind(
            ReprocessAccessEventFlowRepository::class,
            EloquentReprocessAccessEventFlowRepository::class
        );

        $this->app->bind(
            AccessDeviceConfigurationReadGuard::class,
            CacheAccessDeviceConfigurationReadGuard::class
        );

        $this->app->bind(
            AccessDeviceConfigurationReader::class,
            IntelbrasFacialReadOnlyReader::class
        );

        $this->app->bind(
            AccessDeviceConfigurationReaderResolver::class,
            ConfiguredAccessDeviceConfigurationReaderResolver::class
        );

        $this->app->bind(
            AccessDeviceConfigurationReadRepository::class,
            EloquentAccessDeviceConfigurationReadRepository::class
        );

        $this->app->bind(
            AccessEventIngestionRepository::class,
            EloquentAccessEventIngestionRepository::class
        );

        $this->app->bind(
            ProcessAccessEventRepository::class,
            EloquentProcessAccessEventRepository::class
        );
        $this->app->bind(
            ManualAssociateAccessEventRepository::class,
            EloquentManualAssociateAccessEventRepository::class
        );
        $this->app->bind(
            ContinueManuallyAssociatedAccessEventFlowRepository::class,
            EloquentContinueManuallyAssociatedAccessEventFlowRepository::class
        );
        $this->app->bind(
            RecordAccessEventManualReviewRepository::class,
            EloquentRecordAccessEventManualReviewRepository::class
        );

        $this->app->bind(
            DecideAccessEventRepository::class,
            EloquentDecideAccessEventRepository::class
        );

        $this->app->bind(
            RegisterAccessEventOperationalExecutionRepository::class,
            EloquentRegisterAccessEventOperationalExecutionRepository::class
        );

        $this->app->bind(
            ExecuteAccessEventOperationalExecutionRepository::class,
            EloquentExecuteAccessEventOperationalExecutionRepository::class
        );
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
        Gate::policy(
            AccessDeviceRecord::class,
            AccessDeviceRecordPolicy::class
        );
        Gate::policy(
            AccessEventRecord::class,
            AccessEventRecordPolicy::class
        );
        Gate::policy(VisitorRecord::class, VisitorRecordPolicy::class);
        Gate::policy(VisitRecord::class, VisitRecordPolicy::class);
        Gate::policy(
            ClassificationOptionRecord::class,
            ClassificationOptionRecordPolicy::class
        );
    }
}
