<?php

namespace Tests\Unit\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttempt;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupAttemptAwareProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProvider;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupProviderException;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupResult;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSync;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncStatus;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjCommand;
use App\Modules\Identity\Application\Organizations\CnpjLookup\LookupOrganizationByCnpj\LookupOrganizationByCnpjUseCase;
use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use App\Support\Contracts\TransactionManager;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LookupOrganizationByCnpjUseCaseTest extends TestCase
{
    public function test_it_looks_up_cnpj_records_sync_history_and_returns_result(): void
    {
        $provider = new class implements CnpjLookupProvider
        {
            public ?Cnpj $lookedUpCnpj = null;

            public function name(): string
            {
                return 'fake-provider';
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                $this->lookedUpCnpj = $cnpj;

                return new CnpjLookupResult(
                    provider: $this->name(),
                    cnpj: $cnpj->value(),
                    legalName: 'Agronorte Distribuidora',
                    tradeName: 'Agronorte',
                    normalizedPayload: [
                        'cnpj' => $cnpj->value(),
                        'legal_name' => 'Agronorte Distribuidora',
                    ],
                    rawPayload: [
                        'razao_social' => 'Agronorte Distribuidora',
                    ],
                );
            }
        };

        $syncs = $this->syncRepository();
        $transactions = $this->transactionManager();

        $useCase = new LookupOrganizationByCnpjUseCase(
            provider: $provider,
            syncs: $syncs,
            transactions: $transactions,
        );

        $result = $useCase->execute(new LookupOrganizationByCnpjCommand(
            cnpj: '11.222.333/0001-81',
            organizationId: 'org-001',
        ));

        $this->assertSame(1, $transactions->runs);
        $this->assertSame('11222333000181', $provider->lookedUpCnpj?->value());

        $this->assertSame('fake-provider', $result->provider);
        $this->assertSame('11222333000181', $result->cnpj);
        $this->assertSame('Agronorte Distribuidora', $result->legalName);
        $this->assertSame('Agronorte', $result->tradeName);

        $this->assertCount(1, $syncs->saved);

        $sync = $syncs->saved[0];

        $this->assertSame('11222333000181', $sync->cnpj->value());
        $this->assertSame('fake-provider', $sync->provider);
        $this->assertSame(CnpjLookupSyncStatus::Success, $sync->status);
        $this->assertSame('org-001', $sync->organizationId);
        $this->assertSame(['cnpj' => '11222333000181', 'cnpj_formatted' => '11.222.333/0001-81'], $sync->requestPayload);
        $this->assertSame(['razao_social' => 'Agronorte Distribuidora'], $sync->responsePayload);
        $this->assertSame(['cnpj' => '11222333000181', 'legal_name' => 'Agronorte Distribuidora'], $sync->normalizedPayload);
        $this->assertNotNull($sync->requestedAt);
        $this->assertNotNull($sync->respondedAt);
        $this->assertNotNull($sync->responseHash);
    }

    public function test_it_records_failed_sync_history_when_provider_fails(): void
    {
        $provider = new class implements CnpjLookupProvider
        {
            public function name(): string
            {
                return 'failing-provider';
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                throw new RuntimeException('Provider unavailable.');
            }
        };

        $syncs = $this->syncRepository();
        $transactions = $this->transactionManager();

        $useCase = new LookupOrganizationByCnpjUseCase(
            provider: $provider,
            syncs: $syncs,
            transactions: $transactions,
        );

        try {
            $useCase->execute(new LookupOrganizationByCnpjCommand(
                cnpj: '11.222.333/0001-81',
                organizationId: 'org-001',
            ));

            $this->fail('Expected provider exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Provider unavailable.', $exception->getMessage());
        }

        $this->assertSame(1, $transactions->runs);
        $this->assertCount(1, $syncs->saved);

        $sync = $syncs->saved[0];

        $this->assertSame('11222333000181', $sync->cnpj->value());
        $this->assertSame('failing-provider', $sync->provider);
        $this->assertSame(CnpjLookupSyncStatus::Failed, $sync->status);
        $this->assertSame('org-001', $sync->organizationId);
        $this->assertSame(RuntimeException::class, $sync->errorCode);
        $this->assertSame('Provider unavailable.', $sync->errorMessage);
        $this->assertSame(['cnpj' => '11222333000181', 'cnpj_formatted' => '11.222.333/0001-81'], $sync->requestPayload);
        $this->assertSame([], $sync->responsePayload);
        $this->assertSame([], $sync->normalizedPayload);
        $this->assertNotNull($sync->requestedAt);
        $this->assertNotNull($sync->respondedAt);
    }

    public function test_it_records_each_attempt_when_attempt_aware_provider_succeeds_after_failover(): void
    {
        $provider = new class implements CnpjLookupAttemptAwareProvider
        {
            /**
             * @var list<CnpjLookupAttempt>
             */
            private array $attempts = [];

            public function name(): string
            {
                return 'failover-cnpj';
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                $now = new DateTimeImmutable;

                $this->attempts[] = CnpjLookupAttempt::failed(
                    provider: 'brasilapi',
                    exception: CnpjLookupProviderException::failed(
                        provider: 'brasilapi',
                        message: 'BrasilAPI failed.',
                        httpStatus: 503,
                        context: [
                            'cnpj' => $cnpj->value(),
                        ],
                    ),
                    requestedAt: $now,
                    respondedAt: $now,
                    durationMs: 10,
                    context: [
                        'http_status' => 503,
                    ],
                );

                $result = new CnpjLookupResult(
                    provider: 'receitaws',
                    cnpj: $cnpj->value(),
                    legalName: 'Agronorte Distribuidora',
                    tradeName: 'Agronorte',
                    normalizedPayload: [
                        'cnpj' => $cnpj->value(),
                        'legal_name' => 'Agronorte Distribuidora',
                    ],
                    rawPayload: [
                        'nome' => 'Agronorte Distribuidora',
                    ],
                );

                $this->attempts[] = CnpjLookupAttempt::success(
                    provider: 'receitaws',
                    result: $result,
                    requestedAt: $now,
                    respondedAt: $now,
                    durationMs: 20,
                );

                return $result;
            }

            public function attempts(): array
            {
                return $this->attempts;
            }
        };

        $syncs = $this->syncRepository();
        $transactions = $this->transactionManager();

        $useCase = new LookupOrganizationByCnpjUseCase(
            provider: $provider,
            syncs: $syncs,
            transactions: $transactions,
        );

        $result = $useCase->execute(new LookupOrganizationByCnpjCommand(
            cnpj: '11.222.333/0001-81',
            organizationId: 'org-001',
        ));

        $this->assertSame('receitaws', $result->provider);
        $this->assertSame(1, $transactions->runs);
        $this->assertCount(2, $syncs->saved);

        $firstAttempt = $syncs->saved[0];

        $this->assertSame('brasilapi', $firstAttempt->provider);
        $this->assertSame(CnpjLookupSyncStatus::Failed, $firstAttempt->status);
        $this->assertSame(CnpjLookupProviderException::class, $firstAttempt->errorCode);
        $this->assertSame('BrasilAPI failed.', $firstAttempt->errorMessage);
        $this->assertSame('org-001', $firstAttempt->organizationId);

        $secondAttempt = $syncs->saved[1];

        $this->assertSame('receitaws', $secondAttempt->provider);
        $this->assertSame(CnpjLookupSyncStatus::Success, $secondAttempt->status);
        $this->assertSame(['nome' => 'Agronorte Distribuidora'], $secondAttempt->responsePayload);
        $this->assertSame(['cnpj' => '11222333000181', 'legal_name' => 'Agronorte Distribuidora'], $secondAttempt->normalizedPayload);
        $this->assertNotNull($secondAttempt->responseHash);
    }

    public function test_it_records_each_failed_attempt_when_attempt_aware_provider_fails(): void
    {
        $provider = new class implements CnpjLookupAttemptAwareProvider
        {
            /**
             * @var list<CnpjLookupAttempt>
             */
            private array $attempts = [];

            public function name(): string
            {
                return 'failover-cnpj';
            }

            public function lookup(Cnpj $cnpj): CnpjLookupResult
            {
                $now = new DateTimeImmutable;

                $this->attempts[] = CnpjLookupAttempt::failed(
                    provider: 'brasilapi',
                    exception: CnpjLookupProviderException::failed(
                        provider: 'brasilapi',
                        message: 'BrasilAPI failed.',
                        httpStatus: 503,
                    ),
                    requestedAt: $now,
                    respondedAt: $now,
                    durationMs: 10,
                );

                $this->attempts[] = CnpjLookupAttempt::failed(
                    provider: 'receitaws',
                    exception: CnpjLookupProviderException::failed(
                        provider: 'receitaws',
                        message: 'ReceitaWS failed.',
                        httpStatus: 429,
                    ),
                    requestedAt: $now,
                    respondedAt: $now,
                    durationMs: 20,
                );

                throw CnpjLookupProviderException::failed(
                    provider: $this->name(),
                    message: 'All CNPJ lookup providers failed.',
                );
            }

            public function attempts(): array
            {
                return $this->attempts;
            }
        };

        $syncs = $this->syncRepository();
        $transactions = $this->transactionManager();

        $useCase = new LookupOrganizationByCnpjUseCase(
            provider: $provider,
            syncs: $syncs,
            transactions: $transactions,
        );

        try {
            $useCase->execute(new LookupOrganizationByCnpjCommand(
                cnpj: '11.222.333/0001-81',
                organizationId: 'org-001',
            ));

            $this->fail('Expected provider exception was not thrown.');
        } catch (CnpjLookupProviderException $exception) {
            $this->assertSame('failover-cnpj', $exception->provider());
            $this->assertSame('All CNPJ lookup providers failed.', $exception->getMessage());
        }

        $this->assertSame(1, $transactions->runs);
        $this->assertCount(2, $syncs->saved);

        $this->assertSame('brasilapi', $syncs->saved[0]->provider);
        $this->assertSame(CnpjLookupSyncStatus::Failed, $syncs->saved[0]->status);
        $this->assertSame('BrasilAPI failed.', $syncs->saved[0]->errorMessage);

        $this->assertSame('receitaws', $syncs->saved[1]->provider);
        $this->assertSame(CnpjLookupSyncStatus::Failed, $syncs->saved[1]->status);
        $this->assertSame('ReceitaWS failed.', $syncs->saved[1]->errorMessage);
    }

    private function syncRepository(): CnpjLookupSyncRepository
    {
        return new class implements CnpjLookupSyncRepository
        {
            /**
             * @var list<CnpjLookupSync>
             */
            public array $saved = [];

            public function save(CnpjLookupSync $sync): void
            {
                $this->saved[] = $sync;
            }
        };
    }

    private function transactionManager(): TransactionManager
    {
        return new class implements TransactionManager
        {
            public int $runs = 0;

            public function run(callable $callback): mixed
            {
                $this->runs++;

                return $callback();
            }
        };
    }
}
