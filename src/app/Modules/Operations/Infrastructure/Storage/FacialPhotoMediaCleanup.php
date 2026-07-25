<?php

namespace App\Modules\Operations\Infrastructure\Storage;

use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class FacialPhotoMediaCleanup
{
    /**
     * @return array{
     *     disk: string,
     *     directory: string
     * }
     */
    public function reference(
        Media $media
    ): array {
        $relativePath = str_replace(
            '\\',
            '/',
            $media->getPathRelativeToRoot()
        );

        /*
         * Caminho esperado:
         *
         * tenants/{tenant}/organizations/{organization}/
         * photos/{photo}/media/{media}/original/file.ext
         *
         * Dois dirname() retornam o diretório individual:
         *
         * tenants/.../media/{media}
         */
        $directory = dirname(
            dirname($relativePath)
        );

        if (! $this->isSafeDirectory($directory)) {
            throw new LogicException(
                'A mídia facial gerou um caminho de limpeza inseguro.'
            );
        }

        return [
            'disk' => (string) $media->disk,
            'directory' => $directory,
        ];
    }

    /**
     * @param array{
     *     disk: string,
     *     directory: string
     * }|null $reference
     */
    public function remove(
        ?array $reference
    ): void {
        if ($reference === null) {
            return;
        }

        $directory = trim(
            str_replace(
                '\\',
                '/',
                $reference['directory']
            ),
            '/'
        );

        if (! $this->isSafeDirectory($directory)) {
            report(
                new RuntimeException(
                    'A compensação facial recebeu um caminho inseguro.'
                )
            );

            return;
        }

        try {
            $storage = Storage::disk(
                $reference['disk']
            );

            $deleted = $storage->deleteDirectory(
                $directory
            );

            if (
                ! $deleted
                && $storage->allFiles(
                    $directory
                ) !== []
            ) {
                report(
                    new RuntimeException(
                        'Não foi possível limpar a mídia facial compensada.'
                    )
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function isSafeDirectory(
        string $directory
    ): bool {
        return preg_match(
            '#^tenants/[^/]+'
            .'/organizations/[^/]+'
            .'/photos/[^/]+'
            .'/media/[^/]+$#',
            trim($directory, '/')
        ) === 1;
    }
}
