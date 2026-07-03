<?php

namespace Tests\Unit\Infrastructure\Persistence\Database;

use App\Infrastructure\Persistence\Database\LaravelTransactionManager;
use App\Support\Contracts\TransactionManager;
use Tests\TestCase;

class LaravelTransactionManagerTest extends TestCase
{
    public function test_it_resolves_the_transaction_manager_contract(): void
    {
        $transactionManager = $this->app->make(TransactionManager::class);

        $this->assertInstanceOf(LaravelTransactionManager::class, $transactionManager);
    }

    public function test_it_runs_a_callback_and_returns_its_result(): void
    {
        $transactionManager = $this->app->make(TransactionManager::class);

        $result = $transactionManager->run(function (): string {
            return 'transaction executed';
        });

        $this->assertSame('transaction executed', $result);
    }
}
