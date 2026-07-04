<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('trade_name')->index();
            $table->string('unit_code')->nullable()->after('display_name')->unique();
        });

        DB::statement("
            UPDATE organizations
            SET display_name = COALESCE(NULLIF(trade_name, ''), legal_name)
            WHERE display_name IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique('organizations_unit_code_unique');
            $table->dropIndex('organizations_display_name_index');
            $table->dropColumn(['display_name', 'unit_code']);
        });
    }
};
