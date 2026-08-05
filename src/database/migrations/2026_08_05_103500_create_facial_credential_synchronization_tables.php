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
            'facial_credential_syncs',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('tenant_id');
                $table->uuid('organization_id');
                $table->uuid('visitor_id');
                $table->uuid('facial_photo_id');
                $table->uuid('facial_photo_derivative_id');
                $table->uuid('access_device_id');

                $table->string('operation', 32);

                $table
                    ->string('status', 32)
                    ->default('pending');

                $table->unsignedInteger('version')->default(1);

                $table->char('plan_fingerprint', 64);
                $table->char('context_fingerprint', 64);

                $table->timestamps();

                $table->unique(
                    'context_fingerprint',
                    'fcs_context_uq'
                );

                $table->unique(
                    [
                        'visitor_id',
                        'access_device_id',
                        'operation',
                        'version',
                    ],
                    'fcs_visitor_device_op_version_uq'
                );

                $table->index(
                    ['tenant_id', 'organization_id'],
                    'fcs_tenant_org_idx'
                );

                $table->index(
                    ['visitor_id', 'status'],
                    'fcs_visitor_status_idx'
                );

                $table->index(
                    ['access_device_id', 'status'],
                    'fcs_device_status_idx'
                );

                $table
                    ->foreign(
                        'tenant_id',
                        'fcs_tenant_fk'
                    )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'organization_id',
                        'fcs_organization_fk'
                    )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'visitor_id',
                        'fcs_visitor_fk'
                    )
                    ->references('id')
                    ->on('visitors')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'facial_photo_id',
                        'fcs_photo_fk'
                    )
                    ->references('id')
                    ->on('facial_photos')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'facial_photo_derivative_id',
                        'fcs_derivative_fk'
                    )
                    ->references('id')
                    ->on('facial_photo_derivatives')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'access_device_id',
                        'fcs_device_fk'
                    )
                    ->references('id')
                    ->on('access_devices')
                    ->restrictOnDelete();
            }
        );

        Schema::create(
            'facial_credential_sync_attempts',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('facial_credential_sync_id');

                $table->unsignedInteger('attempt_number');

                $table
                    ->string('status', 32)
                    ->default('pending');

                $table->string('provider', 32);
                $table->string('scenario', 64)->nullable();

                $table->string('failure_code', 64)->nullable();
                $table->string('message', 255)->nullable();

                $table->unsignedBigInteger('duration_ms')->nullable();

                $table->char('plan_fingerprint', 64);

                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'facial_credential_sync_id',
                        'attempt_number',
                    ],
                    'fcsa_sync_attempt_uq'
                );

                $table->index(
                    ['status', 'created_at'],
                    'fcsa_status_created_idx'
                );

                $table->index(
                    ['provider', 'status'],
                    'fcsa_provider_status_idx'
                );

                $table
                    ->foreign(
                        'facial_credential_sync_id',
                        'fcsa_sync_fk'
                    )
                    ->references('id')
                    ->on('facial_credential_syncs')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'facial_credential_sync_attempts'
        );

        Schema::dropIfExists(
            'facial_credential_syncs'
        );
    }
};
