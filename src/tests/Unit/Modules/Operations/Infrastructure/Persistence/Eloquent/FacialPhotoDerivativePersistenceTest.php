<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FacialPhotoDerivativePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_derivative_tables_expose_the_required_schema(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'facial_photo_derivatives',
                [
                    'id',
                    'facial_photo_id',
                    'tenant_id',
                    'organization_id',
                    'profile',
                    'policy_version',
                    'status',
                    'source_sha256',
                    'media_id',
                    'width',
                    'height',
                    'mime_type',
                    'size_bytes',
                    'sha256',
                    'generated_at',
                    'failed_at',
                    'last_failure_code',
                    'created_at',
                    'updated_at',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'facial_photo_derivative_attempts',
                [
                    'id',
                    'derivative_id',
                    'facial_photo_id',
                    'tenant_id',
                    'organization_id',
                    'requested_by',
                    'requester_name',
                    'attempt_number',
                    'status',
                    'normalizer',
                    'normalizer_version',
                    'source_sha256',
                    'output_metadata',
                    'failure_code',
                    'started_at',
                    'finished_at',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_derivative_identity_is_unique(): void
    {
        $this->assertTrue(
            Schema::hasIndex(
                'facial_photo_derivatives',
                [
                    'facial_photo_id',
                    'profile',
                    'policy_version',
                    'source_sha256',
                ],
                'unique'
            ),
            'A identidade da derivação facial deve possuir índice único.'
        );
    }

    public function test_attempt_number_is_unique_per_derivative(): void
    {
        $this->assertTrue(
            Schema::hasIndex(
                'facial_photo_derivative_attempts',
                [
                    'derivative_id',
                    'attempt_number',
                ],
                'unique'
            ),
            'O número da tentativa deve ser único por derivação.'
        );
    }

    public function test_derivative_foreign_keys_are_present(): void
    {
        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivatives',
                ['facial_photo_id']
            )
        );

        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivatives',
                ['tenant_id']
            )
        );

        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivatives',
                ['organization_id']
            )
        );

        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivatives',
                ['media_id']
            )
        );
    }

    public function test_attempt_foreign_keys_are_present(): void
    {
        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivative_attempts',
                ['derivative_id']
            )
        );

        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivative_attempts',
                ['facial_photo_id']
            )
        );

        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivative_attempts',
                ['tenant_id']
            )
        );

        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivative_attempts',
                ['organization_id']
            )
        );

        $this->assertTrue(
            Schema::hasForeignKey(
                'facial_photo_derivative_attempts',
                ['requested_by']
            )
        );
    }
}
