<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationActivityTranslationTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function facialSynchronizationEvents(): array
    {
        return [
            'created' => [
                'visitor_facial_credential_synchronization_created',
                'Intenção de sincronização facial criada',
            ],

            'reused' => [
                'visitor_facial_credential_synchronization_reused',
                'Intenção de sincronização facial reutilizada',
            ],

            'blocked' => [
                'visitor_facial_credential_synchronization_blocked',
                'Preparação da sincronização facial bloqueada',
            ],

            'failed' => [
                'visitor_facial_credential_synchronization_failed',
                'Falha ao preparar sincronização facial',
            ],
        ];
    }

    #[DataProvider('facialSynchronizationEvents')]
    public function test_it_translates_facial_synchronization_events(
        string $event,
        string $expected,
    ): void {
        $translations = require base_path(
            'lang/vendor/filament-activity-log/'
            .'pt_BR/activity.php'
        );

        self::assertIsArray($translations);

        self::assertArrayHasKey(
            'event',
            $translations
        );

        self::assertIsArray(
            $translations['event']
        );

        self::assertArrayHasKey(
            $event,
            $translations['event']
        );

        self::assertSame(
            $expected,
            $translations['event'][$event]
        );

        self::assertSame(
            $expected,
            trans(
                'filament-activity-log::activity.event.'
                .$event,
                locale: 'pt_BR'
            )
        );
    }

    public function test_the_blocked_event_does_not_render_the_technical_english_label(): void
    {
        $translated = trans(
            'filament-activity-log::activity.event.'
            .'visitor_facial_credential_synchronization_blocked',
            locale: 'pt_BR'
        );

        self::assertSame(
            'Preparação da sincronização facial bloqueada',
            $translated
        );

        self::assertNotSame(
            'Visitor Facial Credential Synchronization Blocked',
            $translated
        );
    }
}
