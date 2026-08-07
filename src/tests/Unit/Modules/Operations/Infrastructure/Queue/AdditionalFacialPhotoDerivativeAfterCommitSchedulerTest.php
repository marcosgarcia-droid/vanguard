<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Queue;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Schedule\FacialPhotoDerivativeAfterCommitScheduler;
use App\Modules\Operations\Infrastructure\Queue\AdditionalFacialPhotoDerivativeAfterCommitScheduler;
use PHPUnit\Framework\TestCase;

final class AdditionalFacialPhotoDerivativeAfterCommitSchedulerTest extends TestCase
{
    public function test_it_schedules_the_primary_and_additional_derivative(): void
    {
        $delegate = new class implements FacialPhotoDerivativeAfterCommitScheduler
        {
            /** @var list<GenerateFacialPhotoDerivativeCommand> */
            public array $commands = [];

            public function schedule(
                GenerateFacialPhotoDerivativeCommand $command
            ): bool {
                $this->commands[] = $command;

                return true;
            }
        };

        $scheduler = $this->scheduler(
            $delegate
        );

        self::assertTrue(
            $scheduler->schedule(
                $this->genericCommand()
            )
        );

        self::assertCount(
            2,
            $delegate->commands
        );

        self::assertSame(
            'vanguard_normalized',
            $delegate->commands[0]
                ->profile
                ->value
        );

        self::assertSame(
            'intelbras_facial_credential',
            $delegate->commands[1]
                ->profile
                ->value
        );

        self::assertSame(
            'intelbras-facial-credential-v1',
            $delegate->commands[1]
                ->policyVersion
        );

        self::assertSame(
            'spatie-gd',
            $delegate->commands[1]
                ->normalizer
        );

        self::assertSame(
            'spatie-gd-v1',
            $delegate->commands[1]
                ->normalizerVersion
        );

        self::assertSame(
            $delegate->commands[0]->photoId,
            $delegate->commands[1]->photoId
        );

        self::assertSame(
            $delegate->commands[0]->requestedBy,
            $delegate->commands[1]->requestedBy
        );

        self::assertSame(
            $delegate->commands[0]->requesterName,
            $delegate->commands[1]->requesterName
        );
    }

    public function test_it_does_not_duplicate_an_already_intelbras_command(): void
    {
        $delegate = new class implements FacialPhotoDerivativeAfterCommitScheduler
        {
            /** @var list<GenerateFacialPhotoDerivativeCommand> */
            public array $commands = [];

            public function schedule(
                GenerateFacialPhotoDerivativeCommand $command
            ): bool {
                $this->commands[] = $command;

                return true;
            }
        };

        $scheduler = $this->scheduler(
            $delegate
        );

        self::assertTrue(
            $scheduler->schedule(
                new GenerateFacialPhotoDerivativeCommand(
                    photoId: '11111111-1111-4111-8111-111111111111',

                    profile: 'intelbras_facial_credential',

                    policyVersion: 'intelbras-facial-credential-v1',

                    normalizer: 'spatie-gd',

                    normalizerVersion: 'spatie-gd-v1',
                )
            )
        );

        self::assertCount(
            1,
            $delegate->commands
        );
    }

    public function test_it_stops_if_the_primary_scheduler_is_unavailable(): void
    {
        $delegate = new class implements FacialPhotoDerivativeAfterCommitScheduler
        {
            public int $calls = 0;

            public function schedule(
                GenerateFacialPhotoDerivativeCommand $command
            ): bool {
                $this->calls++;

                return false;
            }
        };

        self::assertFalse(
            $this->scheduler(
                $delegate
            )->schedule(
                $this->genericCommand()
            )
        );

        self::assertSame(
            1,
            $delegate->calls
        );
    }

    private function scheduler(
        FacialPhotoDerivativeAfterCommitScheduler $delegate
    ): AdditionalFacialPhotoDerivativeAfterCommitScheduler {
        return new AdditionalFacialPhotoDerivativeAfterCommitScheduler(
            scheduler: $delegate,

            additionalProfile: 'intelbras_facial_credential',

            additionalPolicyVersion: 'intelbras-facial-credential-v1',

            additionalNormalizer: 'spatie-gd',

            additionalNormalizerVersion: 'spatie-gd-v1',
        );
    }

    private function genericCommand(): GenerateFacialPhotoDerivativeCommand
    {
        return new GenerateFacialPhotoDerivativeCommand(
            photoId: '11111111-1111-4111-8111-111111111111',

            profile: 'vanguard_normalized',

            policyVersion: 'vanguard-normalization-v1',

            normalizer: 'spatie-gd',

            normalizerVersion: 'spatie-gd-v1',

            requestedBy: 77,

            requesterName: 'OPERADOR SINTÉTICO',
        );
    }
}
