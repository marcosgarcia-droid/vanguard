<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\ManualReview;

use App\Modules\Operations\Application\AccessControl\Events\ManualReview\RecordAccessEventManualReviewRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRecordAccessEventManualReviewRepository;
use Tests\TestCase;

class RecordAccessEventManualReviewRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentRecordAccessEventManualReviewRepository::class,
            app(
                RecordAccessEventManualReviewRepository::class
            )
        );
    }
}
