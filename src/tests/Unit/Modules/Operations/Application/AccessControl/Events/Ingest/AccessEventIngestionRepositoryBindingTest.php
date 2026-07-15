<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Ingest;

use App\Modules\Operations\Application\AccessControl\Events\Ingest\AccessEventIngestionRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentAccessEventIngestionRepository;
use Tests\TestCase;

class AccessEventIngestionRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentAccessEventIngestionRepository::class,
            app(
                AccessEventIngestionRepository::class
            )
        );
    }
}
