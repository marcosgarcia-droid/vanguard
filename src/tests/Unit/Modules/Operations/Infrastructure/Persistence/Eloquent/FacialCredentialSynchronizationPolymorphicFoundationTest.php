<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tests\TestCase;

final class FacialCredentialSynchronizationPolymorphicFoundationTest extends TestCase
{
    public function test_polymorphic_subject_is_part_of_the_model_contract(): void
    {
        $record = new FacialCredentialSynchronizationRecord;

        self::assertContains(
            'subject_type',
            $record->getFillable()
        );

        self::assertContains(
            'subject_id',
            $record->getFillable()
        );

        self::assertContains(
            'visitor_id',
            $record->getFillable()
        );

        self::assertInstanceOf(
            MorphTo::class,
            $record->subject()
        );
    }

    public function test_legacy_visitor_relationship_is_preserved(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/Infrastructure/'
                .'Persistence/Eloquent/'
                .'FacialCredentialSynchronizationRecord.php'
            )
        );

        self::assertIsString($source);

        self::assertStringContainsString(
            "VisitorRecord::class,\n            'visitor_id'",
            $source
        );
    }

    public function test_creation_repository_dual_writes_the_visitor_subject(): void
    {
        $source = file_get_contents(
            app_path(
                'Modules/Operations/Infrastructure/'
                .'Persistence/Eloquent/'
                .'EloquentCreateFacialCredentialSynchronizationRepository.php'
            )
        );

        self::assertIsString($source);

        $usesLegacyVisitorSubject =
            str_contains(
                $source,
                "'subject_type' => VisitorRecord::class"
            )
            && str_contains(
                $source,
                "'subject_id' => \$context->visitorId"
            )
            && str_contains(
                $source,
                "'visitor_id' => \$context->visitorId"
            );

        $usesPolymorphicVisitorSubject =
            str_contains(
                $source,
                "'subject_type' => \$subjectMorphType"
            )
            && str_contains(
                $source,
                "'subject_id' => \$context->subjectId"
            )
            && str_contains(
                $source,
                "'visitor_id' => \$context->subjectId"
            )
            && str_contains(
                $source,
                'FacialCredentialSubjectType::Visitor'
            )
            && str_contains(
                $source,
                'VisitorRecord::class'
            );

        self::assertTrue(
            $usesLegacyVisitorSubject
                || $usesPolymorphicVisitorSubject
        );
    }

    public function test_transition_migration_preserves_legacy_visitor_column(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/'
                .'2026_08_07_135000_'
                .'add_subject_to_facial_credential_syncs.php'
            )
        );

        self::assertIsString($migration);

        self::assertStringContainsString(
            "'subject_type'",
            $migration
        );

        self::assertStringContainsString(
            "'subject_id'",
            $migration
        );

        self::assertStringContainsString(
            'fcs_subject_device_op_version_uq',
            $migration
        );

        self::assertStringContainsString(
            'fcs_subject_status_idx',
            $migration
        );

        self::assertStringNotContainsString(
            "dropColumn('visitor_id')",
            $migration
        );

        self::assertStringNotContainsString(
            "dropForeign('fcs_visitor_fk')",
            $migration
        );
    }

    public function test_legacy_visitor_column_becomes_nullable_without_losing_compatibility(): void
    {
        $migration = file_get_contents(
            database_path(
                'migrations/'
                .'2026_08_07_164300_'
                .'make_visitor_id_nullable_on_facial_credential_syncs.php'
            )
        );

        self::assertIsString($migration);

        self::assertMatchesRegularExpression(
            "/->uuid\('visitor_id'\)\s*"
                .'->nullable\\(\\)\\s*'
                .'->change\\(\\)/',
            $migration
        );

        self::assertStringContainsString(
            "->whereNull('visitor_id')",
            $migration
        );

        self::assertStringContainsString(
            'throw new RuntimeException(',
            $migration
        );

        self::assertMatchesRegularExpression(
            "/->uuid\('visitor_id'\)\s*"
                .'->nullable\\(false\\)\\s*'
                .'->change\\(\\)/',
            $migration
        );

        self::assertStringNotContainsString(
            "dropForeign('fcs_visitor_fk')",
            $migration
        );

        self::assertStringNotContainsString(
            "dropIndex('fcs_visitor_status_idx')",
            $migration
        );

        self::assertStringNotContainsString(
            "dropUnique('fcs_visitor_device_op_version_uq')",
            $migration
        );

        self::assertStringNotContainsString(
            "dropColumn('visitor_id')",
            $migration
        );
    }
}
