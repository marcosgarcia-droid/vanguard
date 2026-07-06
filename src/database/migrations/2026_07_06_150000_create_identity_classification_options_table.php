<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classification_options', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->string('category', 80);
            $table->string('code', 120);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'classification_options_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'category', 'code'], 'class_options_tenant_category_code_unique');
            $table->index(['tenant_id', 'category', 'status'], 'class_options_tenant_category_status_idx');
            $table->index(['tenant_id', 'sort_order'], 'class_options_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classification_options');
    }
};
