<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'access_event_operational_decisions',
            function (Blueprint $table): void {
                $table->string('id', 36)->primary();

                $table->string('access_event_id', 36);
                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);

                $table->string('visitor_id', 36)->nullable();
                $table->string('visit_id', 36)->nullable();

                /*
                 * Uma nova versão é criada somente quando a decisão
                 * derivada ou seu contexto operacional é alterado.
                 */
                $table->unsignedSmallInteger('version');

                $table->string('decision', 40);
                $table->string('reason_code', 100);
                $table->text('reason_message')->nullable();

                /*
                 * Registra se a execução automática estava autorizada
                 * pelo runtime no instante da decisão. Esta coluna não
                 * executa entrada, saída ou comandos físicos.
                 */
                $table->boolean(
                    'automatic_execution_enabled'
                )->default(false);

                $table->dateTime('decided_at');

                $table->timestamps();

                $table->foreign(
                    'access_event_id',
                    'access_event_decisions_event_fk'
                )
                    ->references('id')
                    ->on('access_events')
                    ->cascadeOnDelete();

                $table->foreign(
                    'tenant_id',
                    'access_event_decisions_tenant_fk'
                )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table->foreign(
                    'organization_id',
                    'access_event_decisions_org_fk'
                )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table->foreign(
                    'visitor_id',
                    'access_event_decisions_visitor_fk'
                )
                    ->references('id')
                    ->on('visitors')
                    ->nullOnDelete();

                $table->foreign(
                    'visit_id',
                    'access_event_decisions_visit_fk'
                )
                    ->references('id')
                    ->on('visits')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'access_event_id',
                        'version',
                    ],
                    'access_event_decisions_event_version_unique'
                );

                $table->index(
                    [
                        'access_event_id',
                        'decided_at',
                    ],
                    'access_event_decisions_event_decided_idx'
                );

                $table->index(
                    [
                        'organization_id',
                        'decision',
                        'decided_at',
                    ],
                    'access_event_decisions_org_decision_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'access_event_operational_decisions'
        );
    }
};
