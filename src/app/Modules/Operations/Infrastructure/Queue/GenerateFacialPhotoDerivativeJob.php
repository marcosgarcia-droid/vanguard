<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Queue;

use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\FacialPhotoDerivativeGenerator;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Generate\GenerateFacialPhotoDerivativeCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class GenerateFacialPhotoDerivativeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    public int $uniqueFor;

    public bool $failOnTimeout = true;

    /**
     * @var list<int>
     */
    private array $backoffSeconds;

    public function __construct(
        public string $photoId,
        public string $profile,
        public string $policyVersion,
        public string $normalizer,
        public string $normalizerVersion,
        public ?int $requestedBy,
        public ?string $requesterName,
        int $tries,
        int $timeout,
        int $uniqueFor,
        array $backoffSeconds,
    ) {
        $command = new GenerateFacialPhotoDerivativeCommand(
            photoId: $this->photoId,
            profile: $this->profile,
            policyVersion: $this->policyVersion,
            normalizer: $this->normalizer,
            normalizerVersion: $this->normalizerVersion,
            requestedBy: $this->requestedBy,
            requesterName: $this->requesterName,
        );

        $this->photoId = $command->photoId;
        $this->profile = $command->profile->value;
        $this->policyVersion = $command->policyVersion;
        $this->normalizer = $command->normalizer;
        $this->normalizerVersion =
            $command->normalizerVersion;
        $this->requesterName =
            $command->requesterName;

        $this->tries = max(
            1,
            min(
                10,
                $tries
            )
        );

        $this->timeout = max(
            30,
            min(
                900,
                $timeout
            )
        );

        $this->uniqueFor = max(
            $this->timeout,
            min(
                3600,
                $uniqueFor
            )
        );

        $normalizedBackoff = array_values(
            array_map(
                static fn (mixed $seconds): int => max(
                    1,
                    min(
                        3600,
                        (int) $seconds
                    )
                ),
                $backoffSeconds
            )
        );

        $this->backoffSeconds = $normalizedBackoff !== []
            ? $normalizedBackoff
            : [10, 30, 60];
    }

    public function handle(
        FacialPhotoDerivativeGenerator $generator
    ): void {
        $generator->execute(
            new GenerateFacialPhotoDerivativeCommand(
                photoId: $this->photoId,
                profile: $this->profile,
                policyVersion: $this->policyVersion,
                normalizer: $this->normalizer,
                normalizerVersion: $this->normalizerVersion,
                requestedBy: $this->requestedBy,
                requesterName: $this->requesterName,
            )
        );
    }

    public function uniqueId(): string
    {
        return hash(
            'sha256',
            implode(
                '|',
                [
                    $this->photoId,
                    $this->profile,
                    $this->policyVersion,
                    $this->normalizer,
                    $this->normalizerVersion,
                ]
            )
        );
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return $this->backoffSeconds;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'facial-photo-derivative',
            'photo:'.$this->photoId,
            'profile:'.$this->profile,
        ];
    }
}
