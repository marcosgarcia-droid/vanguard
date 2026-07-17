<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'access_event_manual_reviews',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table
                    ->foreignUuid('access_event_id')
                    ->constrained('access_events')
                    ->cascadeOnDelete();

                $table
                    ->foreignUuid('operational_decision_id')
                    ->constrained(
                        'access_event_operational_decisions'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignUuid('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();

                $table
                    ->foreignUuid('organization_id')
                    ->constrained('organizations')
                    ->cascadeOnDelete();

                $table
                    ->foreignUuid('visitor_id')
                    ->nullable()
                    ->constrained('visitors')
                    ->nullOnDelete();

                $table
                    ->foreignUuid('visit_id')
                    ->nullable()
                    ->constrained('visits')
                    ->nullOnDelete();

                $table
                    ->foreignId('operator_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table
                    ->string('idempotency_key', 64)
                    ->unique('aemr_idempotency_unique');

                $table->string('operator_name');
                $table->unsignedSmallInteger(
                    'decision_version'
                );

                $table->string(
                    'decision_reason_code',
                    100
                );

                $table
                    ->text('decision_reason_message')
                    ->nullable();

                $table->string('disposition', 48);
                $table->text('notes');
                $table->timestamp('reviewed_at');
                $table->timestamps();

                $table->index(
                    [
                        'access_event_id',
                        'reviewed_at',
                    ],
                    'aemr_event_reviewed_idx'
                );

                $table->index(
                    [
                        'operational_decision_id',
                        'reviewed_at',
                    ],
                    'aemr_decision_reviewed_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'organization_id',
                        'disposition',
                    ],
                    'aemr_scope_disposition_idx'
                );

                $table->index(
                    [
                        'operator_user_id',
                        'reviewed_at',
                    ],
                    'aemr_operator_reviewed_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'access_event_manual_reviews'
        );
    }
};
