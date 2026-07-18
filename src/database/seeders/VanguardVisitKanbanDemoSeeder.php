<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Domain\Visits\VisitAuthorizationMethod;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class VanguardVisitKanbanDemoSeeder extends Seeder
{
    private const EXTERNAL_SOURCE =
        'vanguard_visit_kanban_demo';

    private const RECORDS_PER_STATUS = 12;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'O seeder sintético do Kanban só pode ser executado nos ambientes local e testing.'
            );
        }

        DB::transaction(function (): void {
            $organization = $this->organization();

            $employees = $this->employees(
                $organization
            );

            $operatorUserId = User::query()
                ->orderBy('id')
                ->value('id');

            $sequence = 0;

            foreach ($this->statuses() as $status) {
                for (
                    $index = 1;
                    $index <= self::RECORDS_PER_STATUS;
                    $index++
                ) {
                    $sequence++;

                    $employee = $employees->isNotEmpty()
                        ? $employees[
                            ($sequence - 1) % $employees->count()
                        ]
                        : null;

                    $visitor = $this->persistVisitor(
                        organization: $organization,
                        status: $status,
                        index: $index,
                        sequence: $sequence,
                    );

                    $this->persistVisit(
                        organization: $organization,
                        visitor: $visitor,
                        hostEmployeeId: $employee?->id,
                        operatorUserId: $operatorUserId !== null
                            ? (int) $operatorUserId
                            : null,
                        status: $status,
                        index: $index,
                        sequence: $sequence,
                    );
                }
            }

            $this->report(
                $organization
            );
        });
    }

    private function organization(): OrganizationRecord
    {
        $requestedOrganizationId = trim(
            (string) env(
                'VANGUARD_KANBAN_DEMO_ORGANIZATION_ID',
                ''
            )
        );

        $query = OrganizationRecord::query()
            ->where('status', 'active');

        if ($requestedOrganizationId !== '') {
            $query->whereKey(
                $requestedOrganizationId
            );
        }

        $organization = $query
            ->orderBy('legal_name')
            ->first();

        if (! $organization instanceof OrganizationRecord) {
            throw new RuntimeException(
                'Nenhuma unidade ativa foi encontrada para gerar a massa sintética do Kanban.'
            );
        }

        return $organization;
    }

    /**
     * @return Collection<int, EmployeeRecord>
     */
    private function employees(
        OrganizationRecord $organization
    ): Collection {
        return EmployeeRecord::query()
            ->where(
                'tenant_id',
                $organization->tenant_id
            )
            ->where(
                'organization_id',
                $organization->id
            )
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();
    }

    /**
     * @return array<int, VisitStatus>
     */
    private function statuses(): array
    {
        return [
            VisitStatus::Scheduled,
            VisitStatus::PendingAuthorization,
            VisitStatus::Authorized,
            VisitStatus::InProgress,
            VisitStatus::Completed,
        ];
    }

    private function persistVisitor(
        OrganizationRecord $organization,
        VisitStatus $status,
        int $index,
        int $sequence,
    ): VisitorRecord {
        $externalId = sprintf(
            'kanban-%s-%02d',
            $status->value,
            $index
        );

        $visitor = VisitorRecord::query()
            ->withTrashed()
            ->where(
                'external_source',
                self::EXTERNAL_SOURCE
            )
            ->where(
                'external_id',
                $externalId
            )
            ->first();

        if (! $visitor instanceof VisitorRecord) {
            $visitor = new VisitorRecord;
            $visitor->id = (string) Str::uuid();
        }

        $name = $this->visitorName(
            $sequence
        );

        $visitor->forceFill([
            'tenant_id' => $organization->tenant_id,
            'organization_id' => $organization->id,
            'partner_id' => null,
            'visitor_code' => sprintf(
                'KAN-%s-%02d',
                $this->statusCode($status),
                $index
            ),
            'full_name' => $name,
            'preferred_name' => $sequence % 3 === 0
                ? explode(' ', $name)[0]
                : null,
            'birth_date' => now()
                ->subYears(22 + ($sequence % 35))
                ->subDays($sequence * 3)
                ->toDateString(),
            'photo_disk' => 'private',
            'photo_path' => null,
            'status' => VisitorStatus::Active,
            'external_source' => self::EXTERNAL_SOURCE,
            'external_id' => $externalId,
            'notes' => sprintf(
                'Visitante sintético para validação visual do Kanban — %s.',
                $status->label()
            ),
            'deleted_at' => null,
        ])->saveQuietly();

        return $visitor;
    }

    private function persistVisit(
        OrganizationRecord $organization,
        VisitorRecord $visitor,
        ?string $hostEmployeeId,
        ?int $operatorUserId,
        VisitStatus $status,
        int $index,
        int $sequence,
    ): void {
        $visit = VisitRecord::query()
            ->withTrashed()
            ->where(
                'visitor_id',
                $visitor->id
            )
            ->first();

        if (! $visit instanceof VisitRecord) {
            $visit = new VisitRecord;
            $visit->id = (string) Str::uuid();
        }

        $expectedStart = $this->expectedStart(
            $status,
            $index
        );

        $timeline = $this->timeline(
            status: $status,
            expectedStart: $expectedStart,
            operatorUserId: $operatorUserId,
            hostEmployeeId: $hostEmployeeId,
        );

        $visit->forceFill([
            'tenant_id' => $organization->tenant_id,
            'organization_id' => $organization->id,
            'visitor_id' => $visitor->id,
            'host_employee_id' => $hostEmployeeId,
            'partner_id' => null,
            'status' => $status,
            'purpose' => $this->purpose(
                $sequence
            ),
            'expected_start_at' => $expectedStart,
            'expected_end_at' => $expectedStart
                ->copy()
                ->addMinutes(
                    60 + (($index % 4) * 30)
                ),
            ...$timeline,
            'notes' => sprintf(
                'Massa sintética do Kanban — %s — registro %02d.',
                $status->label(),
                $index
            ),
            'deleted_at' => null,
        ])->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    private function timeline(
        VisitStatus $status,
        Carbon $expectedStart,
        ?int $operatorUserId,
        ?string $hostEmployeeId,
    ): array {
        $empty = [
            'arrived_at' => null,
            'arrived_by' => null,
            'authorizer_employee_id' => null,
            'authorization_method' => null,
            'authorization_notes' => null,
            'authorized_at' => null,
            'authorized_by' => null,
            'identity_verified_at' => null,
            'identity_verified_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'checked_in_at' => null,
            'checked_in_by' => null,
            'checked_out_at' => null,
            'checked_out_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
        ];

        if ($status === VisitStatus::Scheduled) {
            return $empty;
        }

        $arrivedAt = $expectedStart
            ->copy()
            ->subMinutes(12);

        $authorizedAt = $arrivedAt
            ->copy()
            ->addMinutes(6);

        $checkedInAt = $authorizedAt
            ->copy()
            ->addMinutes(8);

        $checkedOutAt = $checkedInAt
            ->copy()
            ->addMinutes(75);

        $arrival = [
            'arrived_at' => $arrivedAt,
            'arrived_by' => $operatorUserId,
            'identity_verified_at' => $arrivedAt,
            'identity_verified_by' => $operatorUserId,
        ];

        if (
            $status === VisitStatus::PendingAuthorization
        ) {
            return [
                ...$empty,
                ...$arrival,
            ];
        }

        $authorization = [
            'authorizer_employee_id' => $hostEmployeeId,
            'authorization_method' => VisitAuthorizationMethod::Phone->value,
            'authorization_notes' => 'Autorização sintética para validação visual.',
            'authorized_at' => $authorizedAt,
            'authorized_by' => $operatorUserId,
        ];

        if ($status === VisitStatus::Authorized) {
            return [
                ...$empty,
                ...$arrival,
                ...$authorization,
            ];
        }

        $checkIn = [
            'checked_in_at' => $checkedInAt,
            'checked_in_by' => $operatorUserId,
        ];

        if ($status === VisitStatus::InProgress) {
            return [
                ...$empty,
                ...$arrival,
                ...$authorization,
                ...$checkIn,
            ];
        }

        return [
            ...$empty,
            ...$arrival,
            ...$authorization,
            ...$checkIn,
            'checked_out_at' => $checkedOutAt,
            'checked_out_by' => $operatorUserId,
        ];
    }

    private function expectedStart(
        VisitStatus $status,
        int $index
    ): Carbon {
        $base = now()
            ->startOfMinute();

        return match ($status) {
            VisitStatus::Scheduled => $base
                ->copy()
                ->addDay()
                ->addMinutes($index * 20),

            VisitStatus::PendingAuthorization => $base
                ->copy()
                ->subMinutes(45)
                ->addMinutes($index * 4),

            VisitStatus::Authorized => $base
                ->copy()
                ->addMinutes(15)
                ->addMinutes($index * 8),

            VisitStatus::InProgress => $base
                ->copy()
                ->subHours(2)
                ->addMinutes($index * 6),

            VisitStatus::Completed => $base
                ->copy()
                ->subDay()
                ->addMinutes($index * 18),

            default => $base,
        };
    }

    private function visitorName(
        int $sequence
    ): string {
        $firstNames = [
            'ALINE',
            'BRUNO',
            'CAMILA',
            'DANIEL',
            'ELISA',
            'FELIPE',
            'GABRIELA',
            'HENRIQUE',
            'ISABELA',
            'JOÃO',
            'KARINA',
            'LUCAS',
        ];

        $lastNames = [
            'ALMEIDA',
            'BARBOSA',
            'CARDOSO',
            'DUARTE',
            'FERREIRA',
        ];

        $secondLastNames = [
            'COSTA',
            'MARTINS',
            'NOGUEIRA',
            'OLIVEIRA',
            'SANTOS',
        ];

        $offset = $sequence - 1;

        return sprintf(
            '%s %s %s',
            $firstNames[
                $offset % count($firstNames)
            ],
            $lastNames[
                intdiv(
                    $offset,
                    count($firstNames)
                ) % count($lastNames)
            ],
            $secondLastNames[
                ($offset * 3)
                % count($secondLastNames)
            ],
        );
    }

    private function purpose(
        int $sequence
    ): string {
        $purposes = [
            'REUNIÃO COMERCIAL',
            'ENTREGA DE DOCUMENTOS',
            'VISITA TÉCNICA',
            'MANUTENÇÃO PROGRAMADA',
            'AUDITORIA INTERNA',
            'ENTREVISTA COM RECURSOS HUMANOS',
            'REUNIÃO COM SUPRIMENTOS',
            'TREINAMENTO OPERACIONAL',
            'INSPEÇÃO DE SEGURANÇA',
            'APRESENTAÇÃO DE PROPOSTA',
            'ACOMPANHAMENTO DE SERVIÇO',
            'VISITA INSTITUCIONAL',
        ];

        return $purposes[
            ($sequence - 1) % count($purposes)
        ];
    }

    private function statusCode(
        VisitStatus $status
    ): string {
        return match ($status) {
            VisitStatus::Scheduled => 'AGE',
            VisitStatus::PendingAuthorization => 'AUT',
            VisitStatus::Authorized => 'LIB',
            VisitStatus::InProgress => 'AND',
            VisitStatus::Completed => 'CON',
            default => 'OUT',
        };
    }

    private function report(
        OrganizationRecord $organization
    ): void {
        $counts = VisitRecord::query()
            ->whereHas(
                'visitor',
                fn ($query) => $query->where(
                    'external_source',
                    self::EXTERNAL_SOURCE
                )
            )
            ->selectRaw(
                'status, count(*) as total'
            )
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->command?->info(
            'Massa sintética do Kanban criada.'
        );

        $this->command?->line(
            sprintf(
                'Unidade: %s',
                $organization->operational_name
                    ?: $organization->display_name
                    ?: $organization->legal_name
            )
        );

        foreach ($this->statuses() as $status) {
            $this->command?->line(
                sprintf(
                    '%s: %d',
                    $status->label(),
                    (int) ($counts[$status->value] ?? 0)
                )
            );
        }

        $this->command?->line(
            sprintf(
                'Total: %d',
                (int) $counts->sum()
            )
        );
    }
}
