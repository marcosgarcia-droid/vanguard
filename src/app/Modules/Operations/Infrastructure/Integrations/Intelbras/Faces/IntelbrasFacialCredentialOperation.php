<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces;

enum IntelbrasFacialCredentialOperation: string
{
    case Insert = 'insert';

    case Update = 'update';

    public function actionFor(
        IntelbrasFacialCredentialTransport $transport
    ): string {
        return match ([$transport, $this]) {
            [
                IntelbrasFacialCredentialTransport::AccessFaceBatch,
                self::Insert,
            ] => 'insertMulti',

            [
                IntelbrasFacialCredentialTransport::AccessFaceBatch,
                self::Update,
            ] => 'updateMulti',

            [
                IntelbrasFacialCredentialTransport::FaceInfoManagerSingle,
                self::Insert,
            ] => 'add',

            default => throw new \InvalidArgumentException(
                'A operação facial não é suportada pelo transporte informado.'
            ),
        };
    }
}
