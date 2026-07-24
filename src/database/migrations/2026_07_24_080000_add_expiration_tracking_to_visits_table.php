<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dateTime('expired_at')
                ->nullable()
                ->after('cancellation_reason');

            $table->index(
                [
                    'status',
                    'checked_in_at',
                    'expected_end_at',
                ],
                'visits_status_checkin_end_idx'
            );

            $table->index(
                [
                    'status',
                    'checked_in_at',
                    'expected_start_at',
                ],
                'visits_status_checkin_start_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropIndex(
                'visits_status_checkin_end_idx'
            );

            $table->dropIndex(
                'visits_status_checkin_start_idx'
            );

            $table->dropColumn('expired_at');
        });
    }
};
