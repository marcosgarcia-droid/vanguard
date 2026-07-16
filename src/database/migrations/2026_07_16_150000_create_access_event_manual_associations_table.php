<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'access_event_manual_associations',
            function (Blueprint $table): void {
                $table->string('id', 36)->primary();

                $table->string('access_event_id', 36);
                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);

                /*
                 * Gerada antes da operação para impedir que o mesmo
                 * envio seja persistido mais de uma vez.
                 */
                $table->string('idempotency_key', 64)
                    ->unique();

                $table->string(
                    'previous_visitor_id',
                    36
                )->nullable();

                $table->string(
                    'previous_visit_id',
                    36
                )->nullable();

                $table->string(
                    'selected_visitor_id',
                    36
                );

                $table->string(
                    'selected_visit_id',
                    36
                )->nullable();

                /*
                 * O usuário pode ser removido futuramente. O nome
                 * permanece preservado no snapshot textual.
                 */
                $table->foreignId('operator_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('operator_name');

                $table->string(
                    'previous_visitor_name'
                )->nullable();

                $table->string(
                    'previous_visit_reference'
                )->nullable();

                $table->string(
                    'selected_visitor_name'
                );

                $table->string(
                    'selected_visit_reference'
                )->nullable();

                $table->text('reason');

                $table->string(
                    'resulting_status',
                    30
                );

                $table->string(
                    'result_code',
                    100
                );

                $table->text(
                    'result_message'
                )->nullable();

                $table->dateTime('associated_at');

                $table->timestamps();

                $table->foreign(
                    'access_event_id',
                    'access_manual_assoc_event_fk'
                )
                    ->references('id')
                    ->on('access_events')
                    ->restrictOnDelete();

                $table->foreign(
                    'tenant_id',
                    'access_manual_assoc_tenant_fk'
                )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table->foreign(
                    'organization_id',
                    'access_manual_assoc_org_fk'
                )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table->foreign(
                    'previous_visitor_id',
                    'access_manual_assoc_prev_visitor_fk'
                )
                    ->references('id')
                    ->on('visitors')
                    ->nullOnDelete();

                $table->foreign(
                    'previous_visit_id',
                    'access_manual_assoc_prev_visit_fk'
                )
                    ->references('id')
                    ->on('visits')
                    ->nullOnDelete();

                $table->foreign(
                    'selected_visitor_id',
                    'access_manual_assoc_visitor_fk'
                )
                    ->references('id')
                    ->on('visitors')
                    ->restrictOnDelete();

                $table->foreign(
                    'selected_visit_id',
                    'access_manual_assoc_visit_fk'
                )
                    ->references('id')
                    ->on('visits')
                    ->nullOnDelete();

                $table->index(
                    [
                        'access_event_id',
                        'associated_at',
                    ],
                    'access_manual_assoc_event_time_idx'
                );

                $table->index(
                    [
                        'organization_id',
                        'associated_at',
                    ],
                    'access_manual_assoc_org_time_idx'
                );

                $table->index(
                    [
                        'selected_visitor_id',
                        'associated_at',
                    ],
                    'access_manual_assoc_visitor_time_idx'
                );

                $table->index(
                    [
                        'selected_visit_id',
                        'associated_at',
                    ],
                    'access_manual_assoc_visit_time_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'access_event_manual_associations'
        );
    }
};
