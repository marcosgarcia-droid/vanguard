<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('status')->default('active');

            $table->char('cnpj', 14)->nullable()->unique();
            $table->string('cnpj_formatted', 18)->nullable();
            $table->char('cnpj_root', 8)->nullable()->index();
            $table->char('cnpj_branch', 4)->nullable();
            $table->char('cnpj_check_digits', 2)->nullable();

            $table->string('legal_name');
            $table->string('trade_name')->nullable();

            $table->string('establishment_type')->nullable();
            $table->boolean('is_head_office')->nullable();
            $table->string('head_office_organization_id')->nullable()->index();

            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();

            $table->string('legal_nature_code')->nullable()->index();
            $table->string('legal_nature_name')->nullable();

            $table->string('company_size_code')->nullable()->index();
            $table->string('company_size_name')->nullable();

            $table->decimal('share_capital', 18, 2)->nullable();

            $table->string('tax_registration_status_code')->nullable()->index();
            $table->string('tax_registration_status_name')->nullable();
            $table->date('tax_registration_status_date')->nullable();
            $table->string('tax_registration_status_reason')->nullable();

            $table->string('special_status')->nullable();
            $table->date('special_status_date')->nullable();

            $table->string('responsible_federative_entity')->nullable();

            $table->timestamp('cnpj_synced_at')->nullable();
            $table->string('cnpj_sync_provider')->nullable();
            $table->json('cnpj_normalized_data')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'legal_name']);
            $table->index(['tax_registration_status_code', 'tax_registration_status_date'], 'org_tax_status_date_idx');
        });

        Schema::create('organization_addresses', function (Blueprint $table) {
            $table->id();

            $table->string('organization_id');
            $table->string('type')->default('main');
            $table->string('label')->nullable();

            $table->string('postal_code', 20)->nullable()->index();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();

            $table->string('city')->nullable()->index();
            $table->string('city_code')->nullable()->index();
            $table->string('state', 2)->nullable()->index();
            $table->string('country_code', 2)->default('BR');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_primary')->default(false);
            $table->string('source')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'is_primary']);
        });

        Schema::create('organization_contacts', function (Blueprint $table) {
            $table->id();

            $table->string('organization_id');
            $table->string('type');
            $table->string('label')->nullable();

            $table->string('value');
            $table->string('normalized_value')->nullable()->index();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);

            $table->string('source')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'is_primary']);
        });

        Schema::create('organization_cnae_activities', function (Blueprint $table) {
            $table->id();

            $table->string('organization_id');

            $table->string('code', 20);
            $table->string('description');

            $table->boolean('is_primary')->default(false);
            $table->string('source')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->unique(['organization_id', 'code']);
            $table->index(['code', 'is_primary']);
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();

            $table->string('organization_id');

            $table->string('name');
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable()->index();

            $table->string('member_type')->nullable();
            $table->string('qualification_code')->nullable()->index();
            $table->string('qualification_name')->nullable();

            $table->string('role')->nullable();
            $table->boolean('is_legal_representative')->default(false);

            $table->date('joined_at')->nullable();
            $table->string('age_range')->nullable();
            $table->string('country_code', 2)->nullable();

            $table->string('representative_name')->nullable();
            $table->string('representative_document_type')->nullable();
            $table->string('representative_document_number')->nullable();

            $table->string('source')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'qualification_code']);
            $table->index(['organization_id', 'is_legal_representative'], 'org_members_legal_rep_idx');
        });

        Schema::create('organization_tax_regimes', function (Blueprint $table) {
            $table->id();

            $table->string('organization_id');

            $table->boolean('is_current')->default(true);

            $table->boolean('is_simples_nacional')->nullable();
            $table->date('simples_nacional_opted_at')->nullable();
            $table->date('simples_nacional_excluded_at')->nullable();

            $table->boolean('is_mei')->nullable();
            $table->date('mei_opted_at')->nullable();
            $table->date('mei_excluded_at')->nullable();

            $table->string('tax_regime')->nullable();
            $table->json('tax_regime_details')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->string('source')->nullable();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->index(['organization_id', 'is_current']);
            $table->index(['is_simples_nacional', 'is_mei']);
        });

        Schema::create('organization_cnpj_syncs', function (Blueprint $table) {
            $table->id();

            $table->string('organization_id')->nullable();
            $table->char('cnpj', 14)->index();

            $table->string('provider');
            $table->string('endpoint')->nullable();

            $table->string('status')->index();
            $table->unsignedSmallInteger('http_status')->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('normalized_payload')->nullable();

            $table->string('response_hash')->nullable()->index();

            $table->timestamps();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->nullOnDelete();

            $table->index(['provider', 'status']);
            $table->index(['cnpj', 'provider']);
            $table->index(['requested_at', 'status']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_cnpj_syncs');
        Schema::dropIfExists('organization_tax_regimes');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organization_cnae_activities');
        Schema::dropIfExists('organization_contacts');
        Schema::dropIfExists('organization_addresses');
        Schema::dropIfExists('organizations');
    }
};
