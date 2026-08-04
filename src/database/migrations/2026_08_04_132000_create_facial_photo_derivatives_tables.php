<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'facial_photo_derivatives',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('facial_photo_id');
                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);

                $table->string('profile', 100);
                $table->string('policy_version', 50);
                $table
                    ->string('status', 30)
                    ->default('pending');

                $table->char('source_sha256', 64);

                $table
                    ->unsignedBigInteger('media_id')
                    ->nullable();

                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->char('sha256', 64)->nullable();

                $table->dateTime('generated_at')->nullable();
                $table->dateTime('failed_at')->nullable();

                $table
                    ->string('last_failure_code', 80)
                    ->nullable();

                $table->timestamps();

                $table
                    ->foreign(
                        'facial_photo_id',
                        'fpd_photo_fk'
                    )
                    ->references('id')
                    ->on('facial_photos')
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'tenant_id',
                        'fpd_tenant_fk'
                    )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'organization_id',
                        'fpd_org_fk'
                    )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'media_id',
                        'fpd_media_fk'
                    )
                    ->references('id')
                    ->on('media')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'facial_photo_id',
                        'profile',
                        'policy_version',
                        'source_sha256',
                    ],
                    'fpd_identity_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'organization_id',
                        'status',
                    ],
                    'fpd_scope_status_idx'
                );

                $table->index(
                    [
                        'facial_photo_id',
                        'status',
                    ],
                    'fpd_photo_status_idx'
                );

                $table->index(
                    'media_id',
                    'fpd_media_idx'
                );
            }
        );

        Schema::create(
            'facial_photo_derivative_attempts',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('derivative_id');
                $table->uuid('facial_photo_id');

                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);

                $table
                    ->unsignedBigInteger('requested_by')
                    ->nullable();

                $table
                    ->string('requester_name')
                    ->nullable();

                $table->unsignedSmallInteger(
                    'attempt_number'
                );

                $table
                    ->string('status', 30)
                    ->default('processing');

                $table->string('normalizer', 100);
                $table->string('normalizer_version', 50);

                $table->char('source_sha256', 64);

                /*
                 * Somente dimensões, MIME type, tamanho,
                 * hash e demais metadados técnicos seguros.
                 */
                $table
                    ->json('output_metadata')
                    ->nullable();

                $table
                    ->string('failure_code', 80)
                    ->nullable();

                $table->dateTime('started_at');
                $table->dateTime('finished_at')->nullable();

                $table->timestamps();

                $table
                    ->foreign(
                        'derivative_id',
                        'fpda_derivative_fk'
                    )
                    ->references('id')
                    ->on('facial_photo_derivatives')
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'facial_photo_id',
                        'fpda_photo_fk'
                    )
                    ->references('id')
                    ->on('facial_photos')
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'tenant_id',
                        'fpda_tenant_fk'
                    )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'organization_id',
                        'fpda_org_fk'
                    )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'requested_by',
                        'fpda_requester_fk'
                    )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'derivative_id',
                        'attempt_number',
                    ],
                    'fpda_derivative_attempt_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'organization_id',
                        'status',
                    ],
                    'fpda_scope_status_idx'
                );

                $table->index(
                    [
                        'facial_photo_id',
                        'status',
                    ],
                    'fpda_photo_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'facial_photo_derivative_attempts'
        );

        Schema::dropIfExists(
            'facial_photo_derivatives'
        );
    }
};
