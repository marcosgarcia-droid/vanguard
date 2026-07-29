<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'facial_photo_confirmation_consumptions',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('facial_photo_id');
                $table->uuid('visitor_id');

                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);

                $table
                    ->unsignedBigInteger('confirmed_by')
                    ->nullable();

                /*
                 * SHA-256 do recibo criptografado apresentado
                 * na confirmação. O recibo original não é salvo.
                 */
                $table->char(
                    'confirmation_key',
                    64
                );

                /*
                 * Contexto funcional que originou a confirmação.
                 * Não deve ser apresentado na interface.
                 */
                $table->string(
                    'confirmation_context',
                    255
                );

                /*
                 * Fingerprint da mídia definitiva registrada.
                 * Permanece oculto e restrito ao backend.
                 */
                $table->char(
                    'photo_sha256',
                    64
                );

                $table->timestamp('consumed_at');
                $table->timestamps();

                /*
                 * O mesmo recibo pode confirmar exatamente uma
                 * única operação.
                 */
                $table->unique(
                    'confirmation_key',
                    'fpcc_confirmation_unique'
                );

                /*
                 * Cada foto definitiva corresponde a um único
                 * consumo de confirmação.
                 */
                $table->unique(
                    'facial_photo_id',
                    'fpcc_photo_unique'
                );

                $table->index(
                    [
                        'visitor_id',
                        'consumed_at',
                    ],
                    'fpcc_visitor_consumed_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'organization_id',
                        'consumed_at',
                    ],
                    'fpcc_scope_consumed_idx'
                );

                /*
                 * Nomes explícitos e curtos preservam a
                 * compatibilidade com o limite do MySQL.
                 */
                $table
                    ->foreign(
                        'facial_photo_id',
                        'fpcc_photo_fk'
                    )
                    ->references('id')
                    ->on('facial_photos')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'visitor_id',
                        'fpcc_visitor_fk'
                    )
                    ->references('id')
                    ->on('visitors')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'tenant_id',
                        'fpcc_tenant_fk'
                    )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'organization_id',
                        'fpcc_organization_fk'
                    )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'confirmed_by',
                        'fpcc_confirmer_fk'
                    )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'facial_photo_confirmation_consumptions'
        );
    }
};
