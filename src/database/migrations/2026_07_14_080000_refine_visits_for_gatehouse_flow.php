<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dateTime('arrived_at')
                ->nullable()
                ->after('expected_end_at');

            $table->foreignId('arrived_by')
                ->nullable()
                ->after('arrived_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('authorizer_employee_id', 36)
                ->nullable()
                ->after('arrived_by');

            $table->string('authorization_method', 30)
                ->nullable()
                ->after('authorizer_employee_id');

            $table->text('authorization_notes')
                ->nullable()
                ->after('authorization_method');

            $table->dateTime('identity_verified_at')
                ->nullable()
                ->after('authorization_notes');

            $table->foreignId('identity_verified_by')
                ->nullable()
                ->after('identity_verified_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreign(
                'authorizer_employee_id',
                'visits_authorizer_employee_fk'
            )
                ->references('id')
                ->on('employees')
                ->nullOnDelete();

            $table->index(
                ['organization_id', 'arrived_at'],
                'visits_org_arrived_at_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropIndex('visits_org_arrived_at_idx');

            $table->dropForeign(
                'visits_authorizer_employee_fk'
            );

            $table->dropForeign([
                'identity_verified_by',
            ]);

            $table->dropForeign([
                'arrived_by',
            ]);

            $table->dropColumn([
                'arrived_at',
                'arrived_by',
                'authorizer_employee_id',
                'authorization_method',
                'authorization_notes',
                'identity_verified_at',
                'identity_verified_by',
            ]);
        });
    }
};
