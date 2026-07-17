<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\AccessEventRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Pages\ListAccessEventRecords;
use App\Modules\Operations\UI\Filament\Resources\AccessEventRecords\Tables\AccessEventRecordsTable;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class AccessEventRecordListTabsTest extends TestCase
{
    use RefreshDatabase;

    private TenantRecord $tenant;

    private OrganizationRecord $organization;

    private AccessDeviceRecord $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO ABAS SINTÉTICAS',
            'status' => 'active',
        ]);

        $this->organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE ABAS SINTÉTICAS LTDA',
                'display_name' => 'UNIDADE ABAS SINTÉTICAS',
                'unit_code' => 'ABA-01',
            ]);

        $this->device = AccessDeviceRecord::query()->create([
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'code' => 'FAC-ABAS-01',
            'name' => 'LEITOR SINTÉTICO DAS ABAS',
            'provider' => 'simulator',
            'direction' => AccessDeviceDirection::Entry,
            'status' => AccessDeviceStatus::Active,
        ]);
    }

    public function test_it_declares_the_operational_shortcut_tabs(): void
    {
        $page = app(
            ListAccessEventRecords::class
        );

        $tabs = $page->getTabs();

        $this->assertSame(
            [
                'all',
                'pending_association',
                'manual_review',
                'blocked_attempts',
                'failed',
            ],
            array_keys($tabs)
        );

        foreach ($tabs as $tab) {
            $this->assertInstanceOf(
                Tab::class,
                $tab
            );
        }
    }

    public function test_it_keeps_tabs_read_only_and_without_extra_count_queries(): void
    {
        $reflection = new ReflectionClass(
            ListAccessEventRecords::class
        );

        $source = file_get_contents(
            (string) $reflection->getFileName()
        );

        $this->assertIsString($source);

        foreach ([
            "Tab::make('Todos')",
            "'Aguardando associação'",
            "'Revisão manual'",
            "'Tentativas bloqueadas'",
            "Tab::make('Falhas')",
            'AccessEventRecordsTable::applyEventStatusFilter(',
            'AccessEventRecordsTable::applyOpenManualReviewFilter(',
            'AccessEventRecordsTable::applyLatestExecutionStatusFilter(',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        foreach ([
            '->badge(',
            '->badge(fn',
            '::count(',
            '->count(',
            '::create(',
            '->save(',
            '->update(',
            'ContinueAccessEventFlowUseCase',
            'ReprocessAccessEventFlowAction',
            'Http::',
            'dispatch(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_event_status_helper_filters_pending_and_failed_events(): void
    {
        $pending = AccessEventRecord::query()->create([
            'access_device_id' => $this->device->id,
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'external_event_id' => 'tabs-pending',
            'event_type' => 'face_recognition',
            'direction' => 'entry',
            'occurred_at' => '2026-07-16 08:00:00',
            'status' => AccessEventStatus::PendingAssociation,
            'received_at' => '2026-07-16 08:00:00',
            'processing_attempts' => 1,
        ]);

        $failed = AccessEventRecord::query()->create([
            'access_device_id' => $this->device->id,
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'external_event_id' => 'tabs-failed',
            'event_type' => 'face_recognition',
            'direction' => 'entry',
            'occurred_at' => '2026-07-16 09:00:00',
            'status' => AccessEventStatus::Failed,
            'received_at' => '2026-07-16 09:00:00',
            'processing_attempts' => 1,
        ]);

        $pendingIds =
            AccessEventRecordsTable::applyEventStatusFilter(
                AccessEventRecord::query(),
                AccessEventStatus::PendingAssociation->value
            )
                ->pluck('id')
                ->all();

        $failedIds =
            AccessEventRecordsTable::applyEventStatusFilter(
                AccessEventRecord::query(),
                AccessEventStatus::Failed->value
            )
                ->pluck('id')
                ->all();

        $this->assertSame(
            [$pending->id],
            $pendingIds
        );

        $this->assertSame(
            [$failed->id],
            $failedIds
        );
    }

    public function test_invalid_event_status_does_not_change_the_query(): void
    {
        AccessEventRecord::query()->create([
            'access_device_id' => $this->device->id,
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'external_event_id' => 'tabs-invalid-status',
            'event_type' => 'face_recognition',
            'direction' => 'entry',
            'occurred_at' => '2026-07-16 10:00:00',
            'status' => AccessEventStatus::Received,
            'received_at' => '2026-07-16 10:00:00',
            'processing_attempts' => 0,
        ]);

        $this->assertSame(
            1,
            AccessEventRecordsTable::applyEventStatusFilter(
                AccessEventRecord::query(),
                'invalid-status'
            )->count()
        );
    }

    public function test_manual_review_tab_uses_the_open_review_filter(): void
    {
        $page = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/AccessEventRecords/Pages/ListAccessEventRecords.php'
            )
        );

        $table = file_get_contents(
            app_path(
                'Modules/Operations/UI/Filament/Resources/AccessEventRecords/Tables/AccessEventRecordsTable.php'
            )
        );

        $this->assertIsString($page);
        $this->assertIsString($table);

        $this->assertStringContainsString(
            'AccessEventRecordsTable::applyOpenManualReviewFilter(',
            $page
        );

        $this->assertStringContainsString(
            'AccessEventManualReviewDisposition::ResolvedWithoutOperation',
            $table
        );

        $this->assertStringContainsString(
            'access_event_manual_reviews as resolved_review',
            $table
        );

        $this->assertStringContainsString(
            "'latestManualReview'",
            $table
        );
    }
}
