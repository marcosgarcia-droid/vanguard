<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();

            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('document')->nullable()->index();

            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'name']);
        });

        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id();

            $table->string('tenant_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('role')->default('member');
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'role']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('tenant_id')->nullable()->after('id');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'legal_name']);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'legal_name']);
            $table->dropColumn('tenant_id');
        });

        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
