<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'facial_photos',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);

                $table->string('subject_type');
                $table->uuid('subject_id');

                $table->unsignedBigInteger('created_by')->nullable();

                $table->string('source', 40);
                $table->string('status', 40)
                    ->default('pending_validation');

                $table->dateTime('captured_at')->nullable();
                $table->dateTime('analyzed_at')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->dateTime('rejected_at')->nullable();
                $table->dateTime('outdated_at')->nullable();

                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();

                $table->char('sha256', 64)->nullable();

                $table->string(
                    'validation_version',
                    50
                )->nullable();

                $table->json('validation_result')->nullable();
                $table->json('rejection_reasons')->nullable();

                $table->timestamps();

                $table->foreign(
                    'tenant_id',
                    'facial_photos_tenant_fk'
                )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table->foreign(
                    'organization_id',
                    'facial_photos_org_fk'
                )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table->foreign(
                    'created_by',
                    'facial_photos_creator_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'subject_type',
                        'subject_id',
                        'status',
                        'created_at',
                    ],
                    'facial_photos_subject_status_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'organization_id',
                        'status',
                    ],
                    'facial_photos_scope_status_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'sha256',
                    ],
                    'facial_photos_tenant_hash_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('facial_photos');
    }
};
