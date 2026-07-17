<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'access_event_manual_review_consumptions',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('access_event_id');
                $table->uuid('manual_review_id');
                $table->uuid('operational_decision_id');
                $table->uuid('tenant_id');
                $table->uuid('organization_id');

                $table
                    ->uuid('visitor_id')
                    ->nullable();

                $table
                    ->uuid('visit_id')
                    ->nullable();

                $table->unsignedBigInteger(
                    'operator_user_id'
                );

                $table
                    ->string('idempotency_key', 64)
                    ->unique('aemrc_idempotency_unique');

                $table->string('operator_name');

                $table->unsignedSmallInteger(
                    'decision_version'
                );

                $table->string(
                    'disposition',
                    48
                );

                $table->timestamp('consumed_at');
                $table->timestamps();

                /*
                 * Uma análise pronta concede somente uma
                 * tentativa de reprocessamento.
                 */
                $table->unique(
                    'manual_review_id',
                    'aemrc_review_unique'
                );

                $table->index(
                    [
                        'access_event_id',
                        'consumed_at',
                    ],
                    'aemrc_event_consumed_idx'
                );

                $table->index(
                    [
                        'operational_decision_id',
                        'consumed_at',
                    ],
                    'aemrc_decision_consumed_idx'
                );

                $table->index(
                    [
                        'operator_user_id',
                        'consumed_at',
                    ],
                    'aemrc_operator_consumed_idx'
                );

                /*
                 * Nomes explícitos e curtos evitam o limite
                 * de 64 caracteres do MySQL.
                 */
                $table
                    ->foreign(
                        'access_event_id',
                        'aemrc_event_fk'
                    )
                    ->references('id')
                    ->on('access_events')
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'manual_review_id',
                        'aemrc_review_fk'
                    )
                    ->references('id')
                    ->on(
                        'access_event_manual_reviews'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'operational_decision_id',
                        'aemrc_decision_fk'
                    )
                    ->references('id')
                    ->on(
                        'access_event_operational_decisions'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'tenant_id',
                        'aemrc_tenant_fk'
                    )
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'organization_id',
                        'aemrc_organization_fk'
                    )
                    ->references('id')
                    ->on('organizations')
                    ->cascadeOnDelete();

                $table
                    ->foreign(
                        'visitor_id',
                        'aemrc_visitor_fk'
                    )
                    ->references('id')
                    ->on('visitors')
                    ->nullOnDelete();

                $table
                    ->foreign(
                        'visit_id',
                        'aemrc_visit_fk'
                    )
                    ->references('id')
                    ->on('visits')
                    ->nullOnDelete();

                $table
                    ->foreign(
                        'operator_user_id',
                        'aemrc_operator_fk'
                    )
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'access_event_manual_review_consumptions'
        );
    }
};
