<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

enum IntelbrasFacialCredentialResponseStatus: string
{
    case Succeeded = 'succeeded';

    case DuplicatePhoto = 'duplicate_photo';

    case Failed = 'failed';

    case InvalidResponse = 'invalid_response';

    public function safeMessage(): string
    {
        return match ($this) {
            self::Succeeded => 'A operação facial foi aceita pelo equipamento.',

            self::DuplicatePhoto => 'A foto facial já está cadastrada no equipamento.',

            self::Failed => 'O equipamento rejeitou a operação facial.',

            self::InvalidResponse => 'O equipamento retornou uma resposta facial inválida.',
        };
    }
}
