<?php

namespace App\Console\Commands;

use App\Modules\Operations\Application\Visits\ExpireVisit\ExpireVisitCommand;
use App\Modules\Operations\Application\Visits\ExpireVisit\ExpireVisitUseCase;
use App\Modules\Operations\Domain\Visits\VisitStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitRecord;
use App\Modules\Operations\UI\Notifications\VisitHostNotifier;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class ExpireOverdueVisitsCommand extends Command
{
    protected $signature =
        'vanguard:visits:expire
        {--dry-run : Apenas informa quantas visitas estão elegíveis}
        {--force : Executa mesmo quando a automação está desativada}';

    protected $description =
        'Expira visitas antigas com segurança, sem alterar visitas com entrada registrada.';

    public function handle(
        ExpireVisitUseCase $expireVisit,
        VisitHostNotifier $hostNotifier,
    ): int {
        $dryRun = (bool) $this->option(
            'dry-run'
        );

        $force = (bool) $this->option(
            'force'
        );

        $enabled = (bool) config(
            'vanguard.operations.visits.expiration.enabled',
            false
        );

        if (! $enabled) {
            $this->warn(
                'A expiração automática de visitas está desativada.'
            );

            if (! $dryRun && ! $force) {
                $this->line(
                    'Nenhuma visita foi alterada. Use --dry-run para simular ou --force para uma execução controlada.'
                );

                return self::SUCCESS;
            }
        }

        $graceHours = max(
            0,
            (int) config(
                'vanguard.operations.visits.expiration.grace_hours',
                24
            )
        );

        $batchSize = min(
            1000,
            max(
                1,
                (int) config(
                    'vanguard.operations.visits.expiration.batch_size',
                    200
                )
            )
        );

        $referenceAt = now()->toImmutable();

        $cutoff = $referenceAt->subHours(
            $graceHours
        );

        $candidateQuery = $this->candidateQuery(
            $cutoff
        );

        $candidateCount = (
            clone $candidateQuery
        )->count();

        if ($dryRun) {
            $this->newLine();

            $this->info(
                "Simulação concluída: {$candidateCount} visita(s) elegível(is) para expiração."
            );

            $this->line(
                "Referência: {$referenceAt->format('d/m/Y H:i:s')}."
            );

            $this->line(
                "Carência configurada: {$graceHours} hora(s)."
            );

            $this->line(
                "Limite por execução: {$batchSize} visita(s)."
            );

            return self::SUCCESS;
        }

        $visitIds = (
            clone $candidateQuery
        )
            ->orderByRaw(
                'COALESCE(expected_end_at, expected_start_at)'
            )
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');

        $expired = 0;
        $skipped = 0;
        $failures = 0;
        $notificationFailures = 0;

        foreach ($visitIds as $visitId) {
            try {
                $visit = $expireVisit->execute(
                    new ExpireVisitCommand(
                        visitId: (string) $visitId,
                        referenceAt: $referenceAt,
                        graceHours: $graceHours,
                    )
                );

                if (
                    ! $visit->wasChanged(
                        'expired_at'
                    )
                ) {
                    $skipped++;

                    continue;
                }

                $expired++;

                try {
                    $hostNotifier
                        ->closeDecisionActions(
                            $visit
                        );
                } catch (
                    Throwable $notificationException
                ) {
                    report(
                        $notificationException
                    );

                    $notificationFailures++;

                    $this->warn(
                        "A visita {$visitId} foi expirada, mas as ações pendentes da notificação do visitado não puderam ser encerradas."
                    );
                }
            } catch (Throwable $exception) {
                report($exception);

                $failures++;

                $this->error(
                    "Falha ao processar a visita {$visitId}."
                );
            }
        }

        $this->newLine();

        $this->info(
            "{$expired} visita(s) expirada(s)."
        );

        $this->line(
            "{$skipped} visita(s) ignorada(s) após a revalidação."
        );

        $this->line(
            "{$candidateCount} visita(s) elegível(is) foram encontradas antes do limite do lote."
        );

        if ($notificationFailures > 0) {
            $this->warn(
                "{$notificationFailures} visita(s) foram expiradas, mas tiveram falha na sincronização das notificações."
            );
        }

        if ($failures > 0) {
            $this->error(
                "{$failures} visita(s) não puderam ser processadas."
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function candidateQuery(
        CarbonInterface $cutoff
    ): Builder {
        $statuses = array_map(
            static fn (
                VisitStatus $status
            ): string => $status->value,
            ExpireVisitUseCase::eligibleStatuses()
        );

        return VisitRecord::query()
            ->whereIn(
                'status',
                $statuses
            )
            ->whereNull('checked_in_at')
            ->where(
                function (
                    Builder $query
                ) use ($cutoff): void {
                    $query
                        ->where(
                            function (
                                Builder $query
                            ) use ($cutoff): void {
                                $query
                                    ->whereNotNull(
                                        'expected_end_at'
                                    )
                                    ->where(
                                        'expected_end_at',
                                        '<=',
                                        $cutoff
                                    );
                            }
                        )
                        ->orWhere(
                            function (
                                Builder $query
                            ) use ($cutoff): void {
                                $query
                                    ->whereNull(
                                        'expected_end_at'
                                    )
                                    ->where(
                                        'expected_start_at',
                                        '<=',
                                        $cutoff
                                    );
                            }
                        );
                }
            );
    }
}
