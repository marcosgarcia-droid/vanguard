<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table): void {
            $table->string('id', 36)->primary();

            $table->string('tenant_id', 36);
            $table->string('organization_id', 36);
            $table->string('partner_id', 36)->nullable();

            $table->string('visitor_code')->nullable();
            $table->string('full_name');
            $table->string('preferred_name')->nullable();
            $table->date('birth_date')->nullable();

            $table->string('photo_disk')->default('private');
            $table->string('photo_path')->nullable();
            $table->timestamp('photo_uploaded_at')->nullable();

            $table->string('status', 20)->default('active');

            $table->string('external_source')->nullable();
            $table->string('external_id')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'visitors_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('organization_id', 'visitors_org_fk')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();

            $table->foreign('partner_id', 'visitors_partner_fk')
                ->references('id')
                ->on('partners')
                ->nullOnDelete();

            $table->unique(
                ['tenant_id', 'organization_id', 'visitor_code'],
                'visitors_tenant_org_code_unique'
            );

            $table->unique(
                ['external_source', 'external_id'],
                'visitors_external_unique'
            );

            $table->index(
                ['tenant_id', 'organization_id', 'status'],
                'visitors_scope_status_idx'
            );

            $table->index(
                ['tenant_id', 'organization_id', 'full_name'],
                'visitors_scope_name_idx'
            );

            $table->index(['partner_id'], 'visitors_partner_idx');
        });

        Schema::create('visitor_documents', function (Blueprint $table): void {
            $table->id();

            $table->string('visitor_id', 36);

            $table->string('type', 50);
            $table->string('number');
            $table->string('normalized_number')->nullable();

            $table->string('state', 2)->nullable();
            $table->string('issuing_authority')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('visitor_id', 'visitor_docs_visitor_fk')
                ->references('id')
                ->on('visitors')
                ->cascadeOnDelete();

            $table->unique(
                ['visitor_id', 'type', 'normalized_number'],
                'visitor_docs_unique'
            );

            $table->index(
                ['visitor_id', 'type'],
                'visitor_docs_type_idx'
            );

            $table->index(
                ['type', 'normalized_number'],
                'visitor_docs_number_idx'
            );
        });

        Schema::create('visitor_contacts', function (Blueprint $table): void {
            $table->id();

            $table->string('visitor_id', 36);

            $table->string('type', 50);
            $table->string('label')->nullable();
            $table->string('value');
            $table->string('normalized_value')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('visitor_id', 'visitor_contacts_visitor_fk')
                ->references('id')
                ->on('visitors')
                ->cascadeOnDelete();

            $table->index(
                ['visitor_id', 'type'],
                'visitor_contacts_type_idx'
            );

            $table->index(
                ['type', 'normalized_value'],
                'visitor_contacts_value_idx'
            );
        });

        Schema::create('visits', function (Blueprint $table): void {
            $table->string('id', 36)->primary();

            $table->string('tenant_id', 36);
            $table->string('organization_id', 36);
            $table->string('visitor_id', 36);
            $table->string('host_employee_id', 36)->nullable();
            $table->string('partner_id', 36)->nullable();

            $table->string('status', 30)->default('scheduled');
            $table->string('purpose');

            $table->dateTime('expected_start_at');
            $table->dateTime('expected_end_at')->nullable();

            $table->foreignId('authorized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('authorized_at')->nullable();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('checked_in_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('checked_in_at')->nullable();

            $table->foreignId('checked_out_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('checked_out_at')->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'visits_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('organization_id', 'visits_org_fk')
                ->references('id')
                ->on('organizations')
                ->restrictOnDelete();

            $table->foreign('visitor_id', 'visits_visitor_fk')
                ->references('id')
                ->on('visitors')
                ->restrictOnDelete();

            $table->foreign('host_employee_id', 'visits_host_employee_fk')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();

            $table->foreign('partner_id', 'visits_partner_fk')
                ->references('id')
                ->on('partners')
                ->nullOnDelete();

            $table->index(
                ['tenant_id', 'organization_id', 'status'],
                'visits_scope_status_idx'
            );

            $table->index(
                ['organization_id', 'expected_start_at'],
                'visits_org_expected_start_idx'
            );

            $table->index(
                ['visitor_id', 'status'],
                'visits_visitor_status_idx'
            );

            $table->index(
                ['host_employee_id', 'expected_start_at'],
                'visits_host_expected_start_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
        Schema::dropIfExists('visitor_contacts');
        Schema::dropIfExists('visitor_documents');
        Schema::dropIfExists('visitors');
    }
};
