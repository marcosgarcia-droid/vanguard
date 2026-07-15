<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Modules\Operations\Application\AccessControl\Events\Execute\RegisterAccessEventOperationalExecutionRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRegisterAccessEventOperationalExecutionRepository;
use Tests\TestCase;

class RegisterAccessEventOperationalExecutionRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentRegisterAccessEventOperationalExecutionRepository::class,
            app(
                RegisterAccessEventOperationalExecutionRepository::class
            )
        );
    }
}
