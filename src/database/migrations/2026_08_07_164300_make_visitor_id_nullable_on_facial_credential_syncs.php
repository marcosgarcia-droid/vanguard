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
            'facial_credential_syncs',
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
        $polymorphicSubjectCount = DB::table(
            'facial_credential_syncs'
        )
            ->whereNull('visitor_id')
            ->count();

        if ($polymorphicSubjectCount !== 0) {
            throw new RuntimeException(
                'Não é seguro restaurar visitor_id como obrigatório: '
                .'existem sincronizações faciais sem visitante legado.'
            );
        }

        Schema::table(
            'facial_credential_syncs',
            function (Blueprint $table): void {
                $table
                    ->uuid('visitor_id')
                    ->nullable(false)
                    ->change();
            }
        );
    }
};
