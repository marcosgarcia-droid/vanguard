<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'access_event_operational_executions',
            function (Blueprint $table): void {
                $table->string('id', 36)->primary();

                $table->string(
                    'operational_decision_id',
                    36
                );

                $table->string(
                    'access_event_id',
                    36
                );

                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);

                $table->string('visitor_id', 36)
                    ->nullable();

                $table->string('visit_id', 36)
                    ->nullable();

                /*
                 * Usuário humano responsável, quando a tentativa
                 * tiver origem manual. Execuções técnicas poderão
                 * manter este campo nulo.
                 */
                $table->foreignId('operator_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->unsignedSmallInteger(
                    'attempt_number'
                );

                $table->string('source', 30);
                $table->string('status', 30);

                $table->string('reason_code', 100);
                $table->text('reason_message')
                    ->nullable();

                /*
                 * Snapshot da autorização do runtime no momento
                 * da tentativa. Não executa a operação por si só.
                 */
                $table->boolean(
                    'automatic_execution_allowed'
                )->default(false);

                $table->string(
                    'visit_status_before',
                    30
                )->nullable();

                $table->string(
                    'visit_status_after',
                    30
                )->nullable();

                $table->dateTime('attempted_at');
                $table->dateTime('completed_at')
                    ->nullable();

                $table->timestamps();

                $table->foreign(
                    'operational_decision_id',
                    'access_op_exec_decision_fk'
                )
                    ->references('id')
                    ->on(
                        'access_event_operational_decisions'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'access_event_id',
                    'access_op_exec_event_fk'
                )
                    ->references('id')
                    ->on('access_events')
                    ->cascadeOnDelete();

                $table->foreign(
                    'tenant_id',
                    'access_op_exec_tenant_fk'
                )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table->foreign(
                    'organization_id',
                    'access_op_exec_org_fk'
                )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table->foreign(
                    'visitor_id',
                    'access_op_exec_visitor_fk'
                )
                    ->references('id')
                    ->on('visitors')
                    ->nullOnDelete();

                $table->foreign(
                    'visit_id',
                    'access_op_exec_visit_fk'
                )
                    ->references('id')
                    ->on('visits')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'operational_decision_id',
                        'attempt_number',
                    ],
                    'access_op_exec_decision_attempt_unique'
                );

                $table->index(
                    [
                        'operational_decision_id',
                        'status',
                    ],
                    'access_op_exec_decision_status_idx'
                );

                $table->index(
                    [
                        'organization_id',
                        'status',
                        'attempted_at',
                    ],
                    'access_op_exec_org_status_idx'
                );

                $table->index(
                    [
                        'visit_id',
                        'attempted_at',
                    ],
                    'access_op_exec_visit_attempted_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'access_event_operational_executions'
        );
    }
};
