<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_work_schedules', function (Blueprint $table): void {
            $table->id();

            $table->string('employee_id');

            $table->string('name')->default('Jornada principal');
            $table->string('type')->default('fixed');

            $table->unsignedSmallInteger('weekly_workload_minutes')->nullable();
            $table->unsignedSmallInteger('daily_workload_minutes')->nullable();

            $table->unsignedSmallInteger('tolerance_before_start_minutes')->default(0);
            $table->unsignedSmallInteger('tolerance_after_end_minutes')->default(0);

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();

            $table->index(['employee_id', 'is_active']);
            $table->index(['type', 'is_active']);
            $table->index(['valid_from', 'valid_until']);
        });

        Schema::create('employee_work_schedule_days', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('employee_work_schedule_id');

            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('sequence')->default(1);

            $table->boolean('is_working_day')->default(true);

            $table->time('work_starts_at')->nullable();
            $table->time('work_ends_at')->nullable();
            $table->boolean('ends_next_day')->default(false);

            $table->time('break_starts_at')->nullable();
            $table->time('break_ends_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('employee_work_schedule_id', 'emp_schedule_day_schedule_fk')
                ->references('id')
                ->on('employee_work_schedules')
                ->cascadeOnDelete();

            $table->unique(['employee_work_schedule_id', 'weekday', 'sequence'], 'emp_schedule_day_unique');
            $table->index(['weekday', 'is_working_day'], 'emp_schedule_day_weekday_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_schedule_days');
        Schema::dropIfExists('employee_work_schedules');
    }
};
