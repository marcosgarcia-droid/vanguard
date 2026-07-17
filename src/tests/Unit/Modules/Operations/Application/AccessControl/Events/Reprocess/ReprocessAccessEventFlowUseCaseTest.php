<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Reprocess;

use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowException;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowUseCase;
use ReflectionClass;
use Tests\TestCase;

class ReprocessAccessEventFlowUseCaseTest extends TestCase
{
    public function test_it_consumes_the_release_before_orchestration(): void
    {
        $source = $this->source();

        $preparePosition = strpos(
            $source,
            '$this->repository->prepare('
        );

        $flowPosition = strpos(
            $source,
            '$this->continueFlow->execute('
        );

        $this->assertIsInt($preparePosition);
        $this->assertIsInt($flowPosition);

        $this->assertTrue(
            $preparePosition < $flowPosition
        );

        foreach ([
            'idempotencyKey:',
            'ReprocessAccessEventFlowResult(',
            'manualReviewConsumptionId:',
            'A liberação manual foi consumida',
        ] as $expected) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }
    }

    public function test_exception_preserves_consumption_context(): void
    {
        $exception =
            new ReprocessAccessEventFlowException(
                message: 'Falha sintética.',
                manualReviewReleaseConsumed: true,
                manualReviewId: 'review-id',

                manualReviewConsumptionId: 'consumption-id',
            );

        $this->assertTrue(
            $exception->manualReviewReleaseConsumed
        );

        $this->assertSame(
            'review-id',
            $exception->manualReviewId
        );

        $this->assertSame(
            'consumption-id',
            $exception->manualReviewConsumptionId
        );
    }

    private function source(): string
    {
        $filename = (
            new ReflectionClass(
                ReprocessAccessEventFlowUseCase::class
            )
        )->getFileName();

        $this->assertIsString($filename);

        $source = file_get_contents($filename);

        $this->assertIsString($source);

        return $source;
    }
}
