<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application\FacialCredentials\Create;

use LogicException;

final readonly class FacialCredentialSynchronizationPreparation
{
    private function __construct(
        public ?FacialCredentialSynchronizationContext $context,
        public ?CreateFacialCredentialSynchronizationReason $reason,
    ) {
        if (
            ($context === null)
            === ($reason === null)
        ) {
            throw new LogicException(
                'A preparação deve estar pronta ou bloqueada.'
            );
        }

        if ($reason?->isSuccessful() === true) {
            throw new LogicException(
                'Uma preparação bloqueada não aceita resultado de persistência.'
            );
        }
    }

    public static function ready(
        FacialCredentialSynchronizationContext $context
    ): self {
        return new self(
            context: $context,
            reason: null,
        );
    }

    public static function blocked(
        CreateFacialCredentialSynchronizationReason $reason
    ): self {
        return new self(
            context: null,
            reason: $reason,
        );
    }

    public function isReady(): bool
    {
        return $this->context !== null;
    }
}
