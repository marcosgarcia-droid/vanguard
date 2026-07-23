<?php

namespace Tests\Unit\Modules\Operations\Application\Visits;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\Visits\AuthorizeVisit\AuthorizeVisitCommand;
use App\Modules\Operations\Application\Visits\AuthorizeVisit\AuthorizeVisitUseCase;
use App\Modules\Operations\Application\Visits\CancelVisit\CancelVisitCommand;
use App\Modules\Operations\Application\Visits\CancelVisit\CancelVisitUseCase;
use App\Modules\Operations\Application\Visits\CheckInVisit\CheckInVisitCommand;
use App\Modules\Operations\Application\Visits\CheckInVisit\CheckInVisitUseCase;
use App\Modules\Operations\Application\Visits\CheckOutVisit\CheckOutVisitCommand;
use App\Modules\Operations\Application\Visits\CheckOutVisit\CheckOutVisitUseCase;
use App\Modules\Operations\Application\Visits\RegisterVisitArrival\RegisterVisitArrivalCommand;
use App\Modules\Operations\Application\Visits\RegisterVisitArrival\RegisterVisitArrivalUseCase;
use App\Modules\Operations\Application\Visits\RejectVisit\RejectVisitCommand;
use App\Modules\Operations\Application\Visits\RejectVisit\RejectVisitUseCase;
use App\Modules\Operations\Application\Visits\VisitOperationException;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitOperationalUseCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_arrival_and_identity_verification(): void
    {
        $context = $this->createVisitContext();

        $visit = app(RegisterVisitArrivalUseCase::class)->execute(
            new RegisterVisitArrivalCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                arrivedAt: new DateTimeImmutable('2026-07-14 09:00:00'),
            )
        );

        $this->assertSame(
            VisitStatus::PendingAuthorization,
            $visit->status
        );

        $this->assertSame(
            $context['operator']->id,
            $visit->arrived_by
        );

        $this->assertSame(
            $context['operator']->id,
            $visit->identity_verified_by
        );

        $this->assertSame(
            '2026-07-14 09:00:00',
            $visit->arrived_at?->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-07-14 09:00:00',
            $visit->identity_verified_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_arrival_reports_a_change_only_on_the_first_execution(): void
    {
        $context = $this->createVisitContext();

        $firstResult = app(
            RegisterVisitArrivalUseCase::class
        )->execute(
            new RegisterVisitArrivalCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                arrivedAt: new DateTimeImmutable(
                    '2026-07-14 09:00:00'
                ),
            )
        );

        $this->assertTrue(
            $firstResult->wasChanged('arrived_at')
        );

        $secondResult = app(
            RegisterVisitArrivalUseCase::class
        )->execute(
            new RegisterVisitArrivalCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                arrivedAt: new DateTimeImmutable(
                    '2026-07-14 09:05:00'
                ),
            )
        );

        $this->assertFalse(
            $secondResult->wasChanged('arrived_at')
        );

        $this->assertSame(
            '2026-07-14 09:00:00',
            $secondResult->arrived_at?->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function test_authorization_reports_a_change_only_on_the_first_execution(): void
    {
        $context = $this->createVisitContext(
            status: VisitStatus::PendingAuthorization
        );

        $authorizedAt = new DateTimeImmutable(
            '2026-07-23 09:10:00'
        );

        $command = new AuthorizeVisitCommand(
            visitId: $context['visit']->id,
            authorizerEmployeeId: $context['host']->id,
            recordedByUserId: $context['operator']->id,
            method: VisitAuthorizationMethod::System,
            authorizedAt: $authorizedAt,
        );

        $firstResult = app(
            AuthorizeVisitUseCase::class
        )->execute($command);

        $this->assertTrue(
            $firstResult->wasChanged('authorized_at')
        );

        $secondResult = app(
            AuthorizeVisitUseCase::class
        )->execute($command);

        $this->assertFalse(
            $secondResult->wasChanged('authorized_at')
        );

        $this->assertSame(
            '2026-07-23 09:10:00',
            $secondResult->authorized_at?->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function test_rejection_reports_a_change_only_on_the_first_execution(): void
    {
        $context = $this->createVisitContext(
            status: VisitStatus::PendingAuthorization
        );

        $rejectedAt = new DateTimeImmutable(
            '2026-07-23 09:15:00'
        );

        $command = new RejectVisitCommand(
            visitId: $context['visit']->id,
            operatorUserId: $context['operator']->id,
            reason: 'Visitado indisponível.',
            rejectedAt: $rejectedAt,
        );

        $firstResult = app(
            RejectVisitUseCase::class
        )->execute($command);

        $this->assertTrue(
            $firstResult->wasChanged('rejected_at')
        );

        $secondResult = app(
            RejectVisitUseCase::class
        )->execute($command);

        $this->assertFalse(
            $secondResult->wasChanged('rejected_at')
        );

        $this->assertSame(
            '2026-07-23 09:15:00',
            $secondResult->rejected_at?->format(
                'Y-m-d H:i:s'
            )
        );
    }

    public function test_it_allows_a_different_employee_to_authorize(): void
    {
        $context = $this->createVisitContext(
            status: VisitStatus::PendingAuthorization
        );

        $visit = app(AuthorizeVisitUseCase::class)->execute(
            new AuthorizeVisitCommand(
                visitId: $context['visit']->id,
                authorizerEmployeeId: $context['authorizer']->id,
                recordedByUserId: $context['operator']->id,
                method: VisitAuthorizationMethod::Radio,
                notes: 'Anfitrião estava dentro da produção.',
                authorizedAt: new DateTimeImmutable('2026-07-14 09:10:00'),
            )
        );

        $this->assertSame(VisitStatus::Authorized, $visit->status);

        $this->assertSame(
            $context['host']->id,
            $visit->host_employee_id
        );

        $this->assertSame(
            $context['authorizer']->id,
            $visit->authorizer_employee_id
        );

        $this->assertNotSame(
            $visit->host_employee_id,
            $visit->authorizer_employee_id
        );

        $this->assertSame(
            VisitAuthorizationMethod::Radio,
            $visit->authorization_method
        );

        $this->assertSame(
            $context['operator']->id,
            $visit->authorized_by
        );

        $this->assertSame(
            'Anfitrião estava dentro da produção.',
            $visit->authorization_notes
        );
    }

    public function test_it_rejects_an_authorizer_from_another_group(): void
    {
        $context = $this->createVisitContext(
            status: VisitStatus::PendingAuthorization
        );

        $otherTenant = TenantRecord::query()->create([
            'name' => 'OUTRO GRUPO',
            'status' => 'active',
        ]);

        $otherOrganization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'status' => 'active',
            'legal_name' => 'OUTRA UNIDADE LTDA',
            'display_name' => 'OUTRA UNIDADE',
        ]);

        $otherEmployee = EmployeeRecord::query()->create([
            'tenant_id' => $otherTenant->id,
            'organization_id' => $otherOrganization->id,
            'full_name' => 'Funcionário de outro grupo',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $this->expectException(VisitOperationException::class);
        $this->expectExceptionMessage(
            'A pessoa autorizadora precisa ser um funcionário ativo do mesmo grupo empresarial da visita.'
        );

        app(AuthorizeVisitUseCase::class)->execute(
            new AuthorizeVisitCommand(
                visitId: $context['visit']->id,
                authorizerEmployeeId: $otherEmployee->id,
                recordedByUserId: $context['operator']->id,
                method: VisitAuthorizationMethod::Phone,
            )
        );
    }

    public function test_check_in_requires_a_visitor_photo(): void
    {
        $context = $this->createVisitContext(
            status: VisitStatus::Authorized,
            withPhoto: false,
        );

        $this->expectException(VisitOperationException::class);
        $this->expectExceptionMessage(
            'O visitante precisa possuir uma foto facial antes do registro de entrada.'
        );

        app(CheckInVisitUseCase::class)->execute(
            new CheckInVisitCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
            )
        );
    }

    public function test_it_registers_check_in_and_check_out(): void
    {
        $context = $this->createVisitContext(
            status: VisitStatus::Authorized
        );

        $checkedInVisit = app(CheckInVisitUseCase::class)->execute(
            new CheckInVisitCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                checkedInAt: new DateTimeImmutable(
                    '2026-07-14 10:00:00'
                ),
            )
        );

        $this->assertSame(
            VisitStatus::InProgress,
            $checkedInVisit->status
        );

        $this->assertSame(
            '2026-07-14 10:00:00',
            $checkedInVisit->checked_in_at?->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-07-14 10:00:00',
            $checkedInVisit->arrived_at?->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            '2026-07-14 10:00:00',
            $checkedInVisit->identity_verified_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $checkedOutVisit = app(CheckOutVisitUseCase::class)->execute(
            new CheckOutVisitCommand(
                visitId: $checkedInVisit->id,
                operatorUserId: $context['operator']->id,
                checkedOutAt: new DateTimeImmutable(
                    '2026-07-14 11:30:00'
                ),
            )
        );

        $this->assertSame(
            VisitStatus::Completed,
            $checkedOutVisit->status
        );

        $this->assertSame(
            '2026-07-14 11:30:00',
            $checkedOutVisit->checked_out_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_it_cancels_a_visit_without_excessive_requirements(): void
    {
        $context = $this->createVisitContext();

        $visit = app(CancelVisitUseCase::class)->execute(
            new CancelVisitCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                reason: 'Visitante informou que não comparecerá.',
                cancelledAt: new DateTimeImmutable(
                    '2026-07-14 08:30:00'
                ),
            )
        );

        $this->assertSame(VisitStatus::Cancelled, $visit->status);

        $this->assertSame(
            $context['operator']->id,
            $visit->cancelled_by
        );

        $this->assertSame(
            'Visitante informou que não comparecerá.',
            $visit->cancellation_reason
        );
    }

    public function test_cancellation_reports_a_change_only_on_the_first_execution(): void
    {
        $context = $this->createVisitContext();

        $firstResult = app(
            CancelVisitUseCase::class
        )->execute(
            new CancelVisitCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                reason: 'Cancelamento registrado inicialmente.',
                cancelledAt: new DateTimeImmutable(
                    '2026-07-14 08:30:00'
                ),
            )
        );

        $this->assertTrue(
            $firstResult->wasChanged(
                'cancelled_at'
            )
        );

        $secondResult = app(
            CancelVisitUseCase::class
        )->execute(
            new CancelVisitCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                reason: 'Tentativa repetida de cancelamento.',
                cancelledAt: new DateTimeImmutable(
                    '2026-07-14 08:45:00'
                ),
            )
        );

        $this->assertFalse(
            $secondResult->wasChanged(
                'cancelled_at'
            )
        );

        $this->assertSame(
            '2026-07-14 08:30:00',
            $secondResult->cancelled_at?->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertSame(
            'Cancelamento registrado inicialmente.',
            $secondResult->cancellation_reason
        );
    }

    public function test_it_rejects_a_visit_safely(): void
    {
        $context = $this->createVisitContext(
            VisitStatus::PendingAuthorization
        );

        $visit = app(RejectVisitUseCase::class)->execute(
            new RejectVisitCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                reason: '  Documento obrigatório não apresentado.  ',
                rejectedAt: new DateTimeImmutable(
                    '2026-07-14 09:15:00'
                ),
            )
        );

        $this->assertSame(
            VisitStatus::Rejected,
            $visit->status
        );

        $this->assertSame(
            $context['operator']->id,
            $visit->rejected_by
        );

        $this->assertSame(
            '2026-07-14 09:15:00',
            $visit->rejected_at?->format('Y-m-d H:i:s')
        );

        $this->assertSame(
            'Documento obrigatório não apresentado.',
            $visit->rejection_reason
        );

        $this->assertNull($visit->authorized_at);
        $this->assertNull($visit->authorized_by);
    }

    public function test_it_does_not_reject_an_in_progress_visit(): void
    {
        $context = $this->createVisitContext(
            VisitStatus::InProgress
        );

        $this->expectException(
            VisitOperationException::class
        );

        $this->expectExceptionMessage(
            'Não é possível recusar a visita quando a visita está com status "Em andamento".'
        );

        app(RejectVisitUseCase::class)->execute(
            new RejectVisitCommand(
                visitId: $context['visit']->id,
                operatorUserId: $context['operator']->id,
                reason: 'Tentativa inválida.',
            )
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     host: EmployeeRecord,
     *     authorizer: EmployeeRecord,
     *     visitor: VisitorRecord,
     *     operator: User,
     *     visit: VisitRecord
     * }
     */
    private function createVisitContext(
        VisitStatus $status = VisitStatus::Scheduled,
        bool $withPhoto = true,
    ): array {
        $tenant = TenantRecord::query()->create([
            'name' => 'GRUPO DEMONSTRAÇÃO',
            'status' => 'active',
        ]);

        $organization = OrganizationRecord::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'legal_name' => 'UNIDADE DEMONSTRAÇÃO LTDA',
            'display_name' => 'UNIDADE DEMONSTRAÇÃO',
        ]);

        $host = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Funcionário Anfitrião',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $authorizer = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Funcionária Autorizadora',
            'employment_type' => 'employee',
            'status' => 'active',
        ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'Pessoa Visitante',
            'status' => VisitorStatus::Active,
            'photo_disk' => 'local',
            'photo_path' => $withPhoto
                ? 'visitors/demo/visitor.jpg'
                : null,
            'photo_uploaded_at' => $withPhoto ? now() : null,
        ]);

        $operator = User::factory()->create();

        $visit = VisitRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'host_employee_id' => $host->id,
            'status' => $status,
            'purpose' => 'Reunião operacional',
            'expected_start_at' => now()->addHour(),
        ]);

        return compact(
            'tenant',
            'organization',
            'host',
            'authorizer',
            'visitor',
            'operator',
            'visit',
        );
    }
}
