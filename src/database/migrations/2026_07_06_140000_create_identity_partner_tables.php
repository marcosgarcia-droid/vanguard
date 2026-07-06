<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->string('organization_id', 36)->nullable();

            $table->string('partner_code')->nullable();
            $table->string('person_type', 20)->default('company');
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('profiles')->nullable();

            $table->string('external_source')->nullable();
            $table->string('external_id')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'partners_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('organization_id', 'partners_org_fk')
                ->references('id')
                ->on('organizations')
                ->nullOnDelete();

            $table->index(['tenant_id', 'status'], 'partners_tenant_status_idx');
            $table->index(['tenant_id', 'person_type'], 'partners_tenant_person_idx');
            $table->unique(['tenant_id', 'partner_code'], 'partners_tenant_code_unique');
            $table->unique(['external_source', 'external_id'], 'partners_external_unique');
        });

        Schema::create('partner_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('partner_id', 36);
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

            $table->foreign('partner_id', 'partner_docs_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->index(['partner_id', 'type'], 'partner_docs_type_idx');
            $table->unique(['partner_id', 'type', 'normalized_number'], 'partner_docs_unique');
        });

        Schema::create('partner_addresses', function (Blueprint $table): void {
            $table->id();
            $table->string('partner_id', 36);
            $table->string('type', 50)->default('operational');
            $table->string('postal_code', 20)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 50)->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('country_code', 2)->default('BR');
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('partner_id', 'partner_addr_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->index(['partner_id', 'type'], 'partner_addr_type_idx');
        });

        Schema::create('partner_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('partner_id', 36);
            $table->string('type', 50);
            $table->string('label')->nullable();
            $table->string('value');
            $table->string('normalized_value')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('partner_id', 'partner_contacts_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->index(['partner_id', 'type'], 'partner_contacts_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contacts');
        Schema::dropIfExists('partner_addresses');
        Schema::dropIfExists('partner_documents');
        Schema::dropIfExists('partners');
    }
};
