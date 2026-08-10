<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use Tests\TestCase;

final class FacialPhotoConfirmationConsumptionNullableVisitorTest extends TestCase
{
    public function test_migration_makes_legacy_visitor_reference_optional_safely(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/'
                .'2026_08_10_090300_'
                .'make_visitor_id_nullable_on_'
                .'facial_photo_confirmation_consumptions.php'
            )
        );

        self::assertIsString($migration);

        self::assertStringContainsString(
            "->uuid('visitor_id')",
            $migration
        );

        self::assertStringContainsString(
            '->nullable()',
            $migration
        );

        self::assertStringContainsString(
            '->change()',
            $migration
        );

        self::assertStringContainsString(
            "->whereNull('visitor_id')",
            $migration
        );

        self::assertStringContainsString(
            'throw new RuntimeException',
            $migration
        );

        self::assertStringContainsString(
            '->nullable(false)',
            $migration
        );

        self::assertStringNotContainsString(
            "dropForeign('fpcc_visitor_fk')",
            $migration
        );

        self::assertStringNotContainsString(
            "dropIndex('fpcc_visitor_consumed_idx')",
            $migration
        );

        self::assertStringNotContainsString(
            "dropColumn('visitor_id')",
            $migration
        );
    }
}
