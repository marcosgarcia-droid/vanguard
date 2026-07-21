<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'visit_vehicle_authorization_requests',
            function (Blueprint $table): void {
                $table->string('id', 36)->primary();

                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);
                $table->string('visit_id', 36);

                $table->foreignId('visit_vehicle_id');

                $table->string('status', 30)
                    ->default('pending');

                $table->boolean('pending_marker')
                    ->nullable()
                    ->default(true);

                $table->string('idempotency_key', 100)
                    ->unique();

                $table->foreignId('requested_by_user_id')
                    ->nullable();

                $table->string('requested_by_name');
                $table->text('request_notes')->nullable();
                $table->dateTime('requested_at');

                $table->foreignId('decided_by_user_id')
                    ->nullable();

                $table->string('decided_by_name')->nullable();
                $table->text('decision_notes')->nullable();
                $table->dateTime('decided_at')->nullable();

                $table->timestamps();

                $table->foreign(
                    'requested_by_user_id',
                    'visit_vehicle_auth_requested_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'decided_by_user_id',
                    'visit_vehicle_auth_decided_by_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'tenant_id',
                    'visit_vehicle_auth_tenant_fk'
                )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->foreign(
                    'organization_id',
                    'visit_vehicle_auth_org_fk'
                )
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();

                $table->foreign(
                    'visit_id',
                    'visit_vehicle_auth_visit_fk'
                )
                    ->references('id')
                    ->on('visits')
                    ->cascadeOnDelete();

                $table->foreign(
                    'visit_vehicle_id',
                    'visit_vehicle_auth_vehicle_fk'
                )
                    ->references('id')
                    ->on('visit_vehicles')
                    ->cascadeOnDelete();

                $table->index(
                    [
                        'tenant_id',
                        'organization_id',
                        'status',
                    ],
                    'visit_vehicle_auth_scope_idx'
                );

                $table->unique(
                    [
                        'visit_vehicle_id',
                        'pending_marker',
                    ],
                    'visit_vehicle_auth_pending_unique'
                );

                $table->index(
                    [
                        'visit_vehicle_id',
                        'status',
                        'requested_at',
                    ],
                    'visit_vehicle_auth_status_idx'
                );

                $table->index(
                    [
                        'visit_id',
                        'requested_at',
                    ],
                    'visit_vehicle_auth_visit_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'visit_vehicle_authorization_requests'
        );
    }
};
