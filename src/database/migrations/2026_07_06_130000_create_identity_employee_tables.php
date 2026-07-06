<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->string('id')->primary();

            $table->string('tenant_id');
            $table->string('organization_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('manager_employee_id')->nullable();

            $table->string('employee_code')->nullable();
            $table->string('full_name');
            $table->string('preferred_name')->nullable();

            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();

            $table->string('photo_disk')->default('private');
            $table->string('photo_path')->nullable();
            $table->timestamp('photo_uploaded_at')->nullable();

            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('employment_type')->default('employee');
            $table->string('status')->default('active');

            $table->date('hired_at')->nullable();
            $table->date('terminated_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->restrictOnDelete();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->nullOnDelete();

            $table->foreign('manager_employee_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();

            $table->unique(['tenant_id', 'employee_code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'organization_id']);
            $table->index(['tenant_id', 'full_name']);
            $table->index(['tenant_id', 'manager_employee_id']);
            $table->index(['user_id']);
        });

        Schema::create('employee_documents', function (Blueprint $table): void {
            $table->id();

            $table->string('employee_id');

            $table->string('type');
            $table->string('number');
            $table->string('issuing_authority')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();

            $table->unique(['employee_id', 'type', 'number']);
            $table->index(['employee_id', 'type']);
            $table->index(['type', 'number']);
        });

        Schema::create('employee_addresses', function (Blueprint $table): void {
            $table->id();

            $table->string('employee_id');

            $table->string('type')->default('residential');
            $table->string('postal_code')->nullable();
            $table->string('street')->nullable();
            $table->string('number')->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('country', 2)->default('BR');

            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();

            $table->index(['employee_id', 'type']);
            $table->index(['city', 'state']);
            $table->index(['postal_code']);
        });

        Schema::create('employee_contacts', function (Blueprint $table): void {
            $table->id();

            $table->string('employee_id');

            $table->string('type');
            $table->string('label')->nullable();
            $table->string('value');
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();

            $table->index(['employee_id', 'type']);
            $table->index(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contacts');
        Schema::dropIfExists('employee_addresses');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employees');
    }
};
