<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Modules\Operations\Application\AccessControl\Events\Execute\ExecuteAccessEventOperationalExecutionRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentExecuteAccessEventOperationalExecutionRepository;
use Tests\TestCase;

class ExecuteAccessEventOperationalExecutionRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentExecuteAccessEventOperationalExecutionRepository::class,
            app(
                ExecuteAccessEventOperationalExecutionRepository::class
            )
        );
    }
}
