<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FacialCredentialSynchronizationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_synchronization_table_exposes_required_schema(): void
    {
        self::assertTrue(
            Schema::hasColumns(
                'facial_credential_syncs',
                [
                    'id',
                    'tenant_id',
                    'organization_id',
                    'visitor_id',
                    'facial_photo_id',
                    'facial_photo_derivative_id',
                    'access_device_id',
                    'operation',
                    'status',
                    'version',
                    'plan_fingerprint',
                    'context_fingerprint',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_attempt_table_exposes_required_schema(): void
    {
        self::assertTrue(
            Schema::hasColumns(
                'facial_credential_sync_attempts',
                [
                    'id',
                    'facial_credential_sync_id',
                    'attempt_number',
                    'status',
                    'provider',
                    'scenario',
                    'failure_code',
                    'message',
                    'duration_ms',
                    'plan_fingerprint',
                    'started_at',
                    'completed_at',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_required_unique_indexes_are_present(): void
    {
        $this->assertUniqueIndex(
            'facial_credential_syncs',
            ['context_fingerprint']
        );

        $this->assertUniqueIndex(
            'facial_credential_syncs',
            [
                'visitor_id',
                'access_device_id',
                'operation',
                'version',
            ]
        );

        $this->assertUniqueIndex(
            'facial_credential_sync_attempts',
            [
                'facial_credential_sync_id',
                'attempt_number',
            ]
        );
    }

    public function test_required_foreign_keys_are_present(): void
    {
        $this->assertForeignKey(
            'facial_credential_syncs',
            'tenant_id',
            'tenants'
        );

        $this->assertForeignKey(
            'facial_credential_syncs',
            'organization_id',
            'organizations'
        );

        $this->assertForeignKey(
            'facial_credential_syncs',
            'visitor_id',
            'visitors'
        );

        $this->assertForeignKey(
            'facial_credential_syncs',
            'facial_photo_id',
            'facial_photos'
        );

        $this->assertForeignKey(
            'facial_credential_syncs',
            'facial_photo_derivative_id',
            'facial_photo_derivatives'
        );

        $this->assertForeignKey(
            'facial_credential_syncs',
            'access_device_id',
            'access_devices'
        );

        $this->assertForeignKey(
            'facial_credential_sync_attempts',
            'facial_credential_sync_id',
            'facial_credential_syncs'
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertUniqueIndex(
        string $table,
        array $columns
    ): void {
        $indexes = Schema::getIndexes($table);

        $found = collect($indexes)->contains(
            static function (array $index) use ($columns): bool {
                return (bool) ($index['unique'] ?? false)
                    && array_values(
                        $index['columns'] ?? []
                    ) === $columns;
            }
        );

        self::assertTrue(
            $found,
            sprintf(
                'Índice único não localizado em %s para: %s.',
                $table,
                implode(', ', $columns)
            )
        );
    }

    private function assertForeignKey(
        string $table,
        string $column,
        string $foreignTable,
        string $foreignColumn = 'id'
    ): void {
        $foreignKeys = Schema::getForeignKeys($table);

        $found = collect($foreignKeys)->contains(
            static function (array $foreignKey) use (
                $column,
                $foreignTable,
                $foreignColumn
            ): bool {
                return array_values(
                    $foreignKey['columns'] ?? []
                ) === [$column]
                    && ($foreignKey['foreign_table'] ?? null)
                        === $foreignTable
                    && array_values(
                        $foreignKey['foreign_columns'] ?? []
                    ) === [$foreignColumn];
            }
        );

        self::assertTrue(
            $found,
            sprintf(
                'Chave estrangeira não localizada: %s.%s → %s.%s.',
                $table,
                $column,
                $foreignTable,
                $foreignColumn
            )
        );
    }
}
