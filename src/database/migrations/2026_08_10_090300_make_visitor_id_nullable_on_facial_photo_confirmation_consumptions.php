<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'facial_photo_confirmation_consumptions',
            function (Blueprint $table): void {
                $table
                    ->uuid('visitor_id')
                    ->nullable()
                    ->change();
            }
        );
    }

    public function down(): void
    {
        $employeeOrOtherSubjectCount = DB::table(
            'facial_photo_confirmation_consumptions'
        )
            ->whereNull('visitor_id')
            ->count();

        if ($employeeOrOtherSubjectCount !== 0) {
            throw new RuntimeException(
                'Não é seguro restaurar visitor_id como obrigatório: '
                .'existem consumos faciais sem visitante legado.'
            );
        }

        Schema::table(
            'facial_photo_confirmation_consumptions',
            function (Blueprint $table): void {
                $table
                    ->uuid('visitor_id')
                    ->nullable(false)
                    ->change();
            }
        );
    }
};
