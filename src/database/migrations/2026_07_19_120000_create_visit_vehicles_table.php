<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_vehicles', function (Blueprint $table): void {
            $table->id();

            $table->string('visit_id', 36)->unique();

            $table->string('plate', 10);
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();

            $table->boolean('entry_authorized')->default(false);

            $table->foreignId('entry_authorized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('entry_authorized_at')->nullable();

            $table->timestamps();

            $table->foreign('visit_id', 'visit_vehicles_visit_fk')
                ->references('id')
                ->on('visits')
                ->cascadeOnDelete();

            $table->index(
                ['plate', 'entry_authorized'],
                'visit_vehicles_plate_auth_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_vehicles');
    }
};
