<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitRecords;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Pages\ListVisitRecords;
use App\Modules\Operations\UI\Filament\Resources\VisitRecords\Tables\VisitRecordsTable;
use Carbon\Carbon;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class VisitRecordListTabsTest extends TestCase
{
    use RefreshDatabase;

    private TenantRecord $tenant;

    private OrganizationRecord $organization;

    private VisitorRecord $visitor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            '2026-07-18 10:00:00'
        );

        $this->tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO ABAS DE VISITAS',
            'status' => 'active',
        ]);

        $this->organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE ABAS DE VISITAS LTDA',
                'display_name' => 'UNIDADE ABAS DE VISITAS',
                'unit_code' => 'VIS-01',
            ]);

        $this->visitor = VisitorRecord::query()->create([
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'full_name' => 'VISITANTE DAS ABAS',
            'status' => VisitorStatus::Active,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_declares_the_operational_shortcut_tabs(): void
    {
        $page = app(
            ListVisitRecords::class
        );

        $tabs = $page->getTabs();

        $this->assertSame(
            [
                'all',
                'today',
                'pending_authorization',
                'authorized',
                'in_progress',
                'completed',
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
        $source = $this->sourceOf(
            ListVisitRecords::class
        );

        foreach ([
            "Tab::make('Todas')",
            "Tab::make('Hoje')",
            "'Aguardando autorização'",
            "Tab::make('Autorizadas')",
            "Tab::make('Em andamento')",
            "Tab::make('Concluídas')",
            'VisitRecordsTable::applyTodayFilter(',
            'VisitRecordsTable::applyStatusFilter(',
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
            'VisitRecord::query()->create(',
            '->save(',
            '->update(',
            'CheckInVisitUseCase',
            'CheckOutVisitUseCase',
            'AuthorizeVisitUseCase',
            'Http::',
            'dispatch(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_today_helper_filters_only_visits_expected_today(): void
    {
        $today = $this->createVisit(
            VisitStatus::Scheduled,
            '2026-07-18 14:00:00'
        );

        $tomorrow = $this->createVisit(
            VisitStatus::Scheduled,
            '2026-07-19 09:00:00'
        );

        $todayIds = VisitRecordsTable::applyTodayFilter(
            VisitRecord::query()
        )
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertContains(
            $today->id,
            $todayIds
        );

        $this->assertNotContains(
            $tomorrow->id,
            $todayIds
        );
    }

    public function test_status_helper_filters_each_operational_status(): void
    {
        $pending = $this->createVisit(
            VisitStatus::PendingAuthorization,
            '2026-07-18 08:00:00'
        );

        $authorized = $this->createVisit(
            VisitStatus::Authorized,
            '2026-07-18 09:00:00'
        );

        $inProgress = $this->createVisit(
            VisitStatus::InProgress,
            '2026-07-18 10:00:00'
        );

        $completed = $this->createVisit(
            VisitStatus::Completed,
            '2026-07-18 11:00:00'
        );

        $expectations = [
            [
                VisitStatus::PendingAuthorization,
                $pending->id,
            ],
            [
                VisitStatus::Authorized,
                $authorized->id,
            ],
            [
                VisitStatus::InProgress,
                $inProgress->id,
            ],
            [
                VisitStatus::Completed,
                $completed->id,
            ],
        ];

        foreach ($expectations as [$status, $expectedId]) {
            $ids = VisitRecordsTable::applyStatusFilter(
                VisitRecord::query(),
                $status->value
            )
                ->pluck('id')
                ->all();

            $this->assertSame(
                [$expectedId],
                $ids
            );
        }
    }

    public function test_invalid_status_does_not_change_the_query(): void
    {
        $this->createVisit(
            VisitStatus::Scheduled,
            '2026-07-18 12:00:00'
        );

        $this->assertSame(
            1,
            VisitRecordsTable::applyStatusFilter(
                VisitRecord::query(),
                'invalid-status'
            )->count()
        );
    }

    private function createVisit(
        VisitStatus $status,
        string $expectedStartAt
    ): VisitRecord {
        return VisitRecord::query()->create([
            'tenant_id' => $this->tenant->id,
            'organization_id' => $this->organization->id,
            'visitor_id' => $this->visitor->id,
            'status' => $status,
            'purpose' => 'TESTE DAS ABAS OPERACIONAIS',
            'expected_start_at' => $expectedStartAt,
        ]);
    }

    /**
     * @param  class-string  $class
     */
    private function sourceOf(string $class): string
    {
        $filename = (
            new ReflectionClass($class)
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        return $source;
    }
}
