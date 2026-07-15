<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Process;

use App\Modules\Operations\Application\AccessControl\Events\Process\ProcessAccessEventRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentProcessAccessEventRepository;
use Tests\TestCase;

class ProcessAccessEventRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentProcessAccessEventRepository::class,
            app(
                ProcessAccessEventRepository::class
            )
        );
    }
}
