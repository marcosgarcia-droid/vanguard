<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'access_devices',
            function (Blueprint $table): void {
                $table->json(
                    'current_configuration'
                )->nullable();

                $table->json(
                    'desired_configuration'
                )->nullable();

                $table->json(
                    'capabilities'
                )->nullable();

                $table->dateTime(
                    'configuration_read_at'
                )->nullable();

                $table->string(
                    'configuration_read_status',
                    30
                )->nullable();

                $table->text(
                    'configuration_read_message'
                )->nullable();
            }
        );

        Schema::create(
            'access_device_configuration_snapshots',
            function (Blueprint $table): void {
                $table->string('id', 36)->primary();

                $table->string(
                    'access_device_id',
                    36
                );

                $table->string('tenant_id', 36);
                $table->string(
                    'organization_id',
                    36
                );

                $table->unsignedBigInteger(
                    'requested_by'
                )->nullable();

                $table->string(
                    'source',
                    30
                )->default('manual');

                $table->string(
                    'status',
                    30
                );

                $table->string(
                    'device_model'
                )->nullable();

                $table->string(
                    'firmware_version'
                )->nullable();

                $table->json(
                    'configuration'
                )->nullable();

                $table->json(
                    'capabilities'
                )->nullable();

                /*
                 * Resposta técnica sanitizada.
                 * Não armazenar faces, imagens, templates
                 * biométricos ou credenciais.
                 */
                $table->json(
                    'sanitized_response'
                )->nullable();

                $table->string(
                    'configuration_hash',
                    64
                )->nullable();

                $table->dateTime('read_at');

                $table->unsignedInteger(
                    'duration_ms'
                )->nullable();

                $table->text('message')->nullable();

                $table->timestamps();

                $table->foreign(
                    'access_device_id',
                    'adc_snapshots_device_fk'
                )
                    ->references('id')
                    ->on('access_devices')
                    ->restrictOnDelete();

                $table->foreign(
                    'tenant_id',
                    'adc_snapshots_tenant_fk'
                )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table->foreign(
                    'organization_id',
                    'adc_snapshots_org_fk'
                )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table->foreign(
                    'requested_by',
                    'adc_snapshots_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'access_device_id',
                        'read_at',
                    ],
                    'adc_snapshots_device_read_idx'
                );

                $table->index(
                    [
                        'status',
                        'read_at',
                    ],
                    'adc_snapshots_status_read_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'access_device_configuration_snapshots'
        );

        Schema::table(
            'access_devices',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'current_configuration',
                    'desired_configuration',
                    'capabilities',
                    'configuration_read_at',
                    'configuration_read_status',
                    'configuration_read_message',
                ]);
            }
        );
    }
};
