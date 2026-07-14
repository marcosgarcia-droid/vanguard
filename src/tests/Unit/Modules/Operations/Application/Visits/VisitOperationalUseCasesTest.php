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
