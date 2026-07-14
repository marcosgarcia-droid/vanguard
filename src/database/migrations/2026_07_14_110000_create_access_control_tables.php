<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_devices', function (Blueprint $table): void {
            $table->string('id', 36)->primary();

            $table->string('tenant_id', 36);
            $table->string('organization_id', 36);

            $table->string('code', 100);
            $table->string('name');

            $table->string('device_type', 50)
                ->default('facial_reader');

            $table->string('provider', 60)
                ->default('intelbras');

            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('external_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('port')->nullable();

            $table->string('protocol', 20)
                ->default('http');

            $table->string('auth_type', 30)
                ->default('digest');

            /*
             * Valores criptografados pelo cast do Eloquent.
             */
            $table->text('credential_username')->nullable();
            $table->text('credential_password')->nullable();

            $table->string('direction', 20);
            $table->string('status', 20)->default('active');

            /*
             * Configurações técnicas não secretas do provider.
             * Senhas e tokens não devem ser armazenados neste JSON.
             */
            $table->json('settings')->nullable();

            $table->dateTime('last_communication_at')->nullable();
            $table->string('last_communication_status', 30)->nullable();
            $table->text('last_communication_message')->nullable();
            $table->dateTime('last_event_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'access_devices_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign(
                'organization_id',
                'access_devices_org_fk'
            )
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();

            $table->unique(
                ['tenant_id', 'organization_id', 'code'],
                'access_devices_scope_code_unique'
            );

            $table->unique(
                ['tenant_id', 'provider', 'external_id'],
                'access_devices_provider_external_unique'
            );

            $table->index(
                ['tenant_id', 'organization_id', 'status'],
                'access_devices_scope_status_idx'
            );

            $table->index(
                ['organization_id', 'direction', 'status'],
                'access_devices_org_direction_idx'
            );
        });

        Schema::create('access_events', function (Blueprint $table): void {
            $table->string('id', 36)->primary();

            $table->string('access_device_id', 36);
            $table->string('tenant_id', 36);
            $table->string('organization_id', 36);

            $table->string('visitor_id', 36)->nullable();
            $table->string('visit_id', 36)->nullable();

            $table->string('external_event_id', 191)->nullable();
            $table->string('external_person_id')->nullable();

            $table->string('event_type', 50)
                ->default('face_recognition');

            $table->string('direction', 20);
            $table->dateTime('occurred_at');

            $table->string('status', 30)
                ->default('received');

            $table->string('result_code', 100)->nullable();
            $table->text('result_message')->nullable();

            /*
             * Payload técnico original. Não será incluído no log
             * genérico de auditoria.
             */
            $table->json('raw_payload')->nullable();

            $table->timestamp('received_at')->useCurrent();
            $table->dateTime('processed_at')->nullable();

            $table->unsignedSmallInteger('processing_attempts')
                ->default(0);

            $table->timestamps();

            $table->foreign(
                'access_device_id',
                'access_events_device_fk'
            )
                ->references('id')
                ->on('access_devices')
                ->restrictOnDelete();

            $table->foreign('tenant_id', 'access_events_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign(
                'organization_id',
                'access_events_org_fk'
            )
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();

            $table->foreign(
                'visitor_id',
                'access_events_visitor_fk'
            )
                ->references('id')
                ->on('visitors')
                ->nullOnDelete();

            $table->foreign('visit_id', 'access_events_visit_fk')
                ->references('id')
                ->on('visits')
                ->nullOnDelete();

            $table->unique(
                ['access_device_id', 'external_event_id'],
                'access_events_device_external_unique'
            );

            $table->index(
                ['organization_id', 'occurred_at'],
                'access_events_org_occurred_idx'
            );

            $table->index(
                ['status', 'received_at'],
                'access_events_status_received_idx'
            );

            $table->index(
                ['visitor_id', 'occurred_at'],
                'access_events_visitor_occurred_idx'
            );

            $table->index(
                ['visit_id'],
                'access_events_visit_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_events');
        Schema::dropIfExists('access_devices');
    }
};
