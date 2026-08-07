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
                    ->string('subject_type')
                    ->nullable()
                    ->after('organization_id');

                $table
                    ->uuid('subject_id')
                    ->nullable()
                    ->after('subject_type');
            }
        );

        DB::table('facial_credential_syncs')
            ->orderBy('id')
            ->chunkById(
                200,
                function ($synchronizations): void {
                    $photoIds = collect($synchronizations)
                        ->pluck('facial_photo_id')
                        ->filter()
                        ->unique()
                        ->values();

                    $photos = DB::table('facial_photos')
                        ->whereIn('id', $photoIds)
                        ->get([
                            'id',
                            'subject_type',
                            'subject_id',
                        ])
                        ->keyBy('id');

                    foreach ($synchronizations as $synchronization) {
                        $photo = $photos->get(
                            $synchronization->facial_photo_id
                        );

                        if (
                            $photo === null
                            || blank($photo->subject_type)
                            || blank($photo->subject_id)
                        ) {
                            throw new RuntimeException(
                                'Não foi possível determinar o sujeito '
                                .'da sincronização facial existente.'
                            );
                        }

                        DB::table('facial_credential_syncs')
                            ->where('id', $synchronization->id)
                            ->update([
                                'subject_type' => (string) $photo->subject_type,

                                'subject_id' => (string) $photo->subject_id,
                            ]);
                    }
                },
                'id'
            );

        $incompleteCount = DB::table(
            'facial_credential_syncs'
        )
            ->where(
                static function ($query): void {
                    $query
                        ->whereNull('subject_type')
                        ->orWhereNull('subject_id');
                }
            )
            ->count();

        if ($incompleteCount !== 0) {
            throw new RuntimeException(
                'Existem sincronizações faciais sem sujeito '
                .'após o backfill.'
            );
        }

        Schema::table(
            'facial_credential_syncs',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'subject_type',
                        'subject_id',
                        'access_device_id',
                        'operation',
                        'version',
                    ],
                    'fcs_subject_device_op_version_uq'
                );

                $table->index(
                    [
                        'subject_type',
                        'subject_id',
                        'status',
                    ],
                    'fcs_subject_status_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'facial_credential_syncs',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'fcs_subject_device_op_version_uq'
                );

                $table->dropIndex(
                    'fcs_subject_status_idx'
                );

                $table->dropColumn([
                    'subject_type',
                    'subject_id',
                ]);
            }
        );
    }
};
