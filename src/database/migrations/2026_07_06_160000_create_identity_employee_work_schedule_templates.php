<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_work_schedule_templates', function (Blueprint $table): void {
            $table->string('id', 36)->primary();
            $table->string('tenant_id', 36);
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('type', 50)->default('standard');
            $table->text('description')->nullable();
            $table->unsignedInteger('weekly_workload_minutes')->nullable();
            $table->unsignedInteger('daily_workload_minutes')->nullable();
            $table->unsignedSmallInteger('tolerance_before_start_minutes')->default(0);
            $table->unsignedSmallInteger('tolerance_after_end_minutes')->default(0);
            $table->string('status', 20)->default('active');
            $table->boolean('is_system')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id', 'employee_ws_templates_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['tenant_id', 'code'], 'employee_ws_templates_tenant_code_unique');
            $table->index(['tenant_id', 'status'], 'employee_ws_templates_tenant_status_idx');
        });

        Schema::create('employee_work_schedule_template_days', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_work_schedule_template_id', 36);
            $table->unsignedTinyInteger('weekday');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->boolean('is_working_day')->default(true);
            $table->time('work_starts_at')->nullable();
            $table->time('work_ends_at')->nullable();
            $table->boolean('ends_next_day')->default(false);
            $table->time('break_starts_at')->nullable();
            $table->time('break_ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_work_schedule_template_id', 'employee_ws_template_days_template_fk')
                ->references('id')
                ->on('employee_work_schedule_templates')
                ->cascadeOnDelete();

            $table->unique(
                ['employee_work_schedule_template_id', 'weekday', 'sequence'],
                'employee_ws_template_days_unique',
            );
        });

        Schema::table('employee_work_schedules', function (Blueprint $table): void {
            $table->string('employee_work_schedule_template_id', 36)
                ->nullable()
                ->after('employee_id');

            $table->foreign('employee_work_schedule_template_id', 'employee_ws_template_fk')
                ->references('id')
                ->on('employee_work_schedule_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_work_schedules', function (Blueprint $table): void {
            $table->dropForeign('employee_ws_template_fk');
            $table->dropColumn('employee_work_schedule_template_id');
        });

        Schema::dropIfExists('employee_work_schedule_template_days');
        Schema::dropIfExists('employee_work_schedule_templates');
    }
};
