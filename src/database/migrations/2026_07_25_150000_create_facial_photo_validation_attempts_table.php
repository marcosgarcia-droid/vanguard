<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'facial_photo_validation_attempts',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid('facial_photo_id');
                $table->string('tenant_id', 36);
                $table->string('organization_id', 36);

                /*
                 * Validações automáticas não possuem necessariamente
                 * um usuário humano responsável.
                 */
                $table
                    ->unsignedBigInteger('operator_user_id')
                    ->nullable();

                /*
                 * Snapshot do nome do operador, preservado mesmo se
                 * o cadastro do usuário for posteriormente alterado.
                 */
                $table
                    ->string('operator_name')
                    ->nullable();

                /*
                 * Cada execução do validador é uma tentativa
                 * independente e auditável.
                 */
                $table->unsignedSmallInteger('attempt_number');

                $table->string('validator', 100);
                $table->string('validator_version', 50);
                $table->string('decision', 30);

                $table->unsignedSmallInteger('face_count');

                /*
                 * Somente métricas escalares e diagnósticos técnicos.
                 * Não armazena representações faciais derivadas.
                 */
                $table->json('metrics');

                /*
                 * Lista de códigos definidos por
                 * FacialPhotoValidationIssue.
                 */
                $table->json('issues');

                /*
                 * Snapshots da política de transição. A criação deste
                 * ledger não altera por si só o registro facial.
                 */
                $table->string('status_before', 40);
                $table->string('status_after', 40);

                $table->dateTime('validated_at');
                $table->timestamps();

                /*
                 * Nomes explícitos e curtos preservam compatibilidade
                 * com o limite de 64 caracteres do MySQL.
                 */
                $table
                    ->foreign(
                        'facial_photo_id',
                        'fpva_photo_fk'
                    )
                    ->references('id')
                    ->on('facial_photos')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'tenant_id',
                        'fpva_tenant_fk'
                    )
                    ->references('id')
                    ->on('tenants')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'organization_id',
                        'fpva_org_fk'
                    )
                    ->references('id')
                    ->on('organizations')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'operator_user_id',
                        'fpva_operator_fk'
                    )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->unique(
                    [
                        'facial_photo_id',
                        'attempt_number',
                    ],
                    'fpva_photo_attempt_unique'
                );

                $table->index(
                    [
                        'facial_photo_id',
                        'validated_at',
                    ],
                    'fpva_photo_validated_idx'
                );

                $table->index(
                    [
                        'tenant_id',
                        'organization_id',
                        'decision',
                        'validated_at',
                    ],
                    'fpva_scope_decision_idx'
                );

                $table->index(
                    [
                        'operator_user_id',
                        'validated_at',
                    ],
                    'fpva_operator_validated_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'facial_photo_validation_attempts'
        );
    }
};
