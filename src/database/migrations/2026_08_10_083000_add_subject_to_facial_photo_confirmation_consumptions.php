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
                    ->string('subject_type')
                    ->nullable()
                    ->after('facial_photo_id');

                $table
                    ->uuid('subject_id')
                    ->nullable()
                    ->after('subject_type');
            }
        );

        DB::table('facial_photo_confirmation_consumptions')
            ->orderBy('id')
            ->chunkById(
                200,
                function ($consumptions): void {
                    $photoIds = $consumptions
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

                    foreach ($consumptions as $consumption) {
                        $photo = $photos->get(
                            $consumption->facial_photo_id
                        );

                        if (
                            $photo === null
                            || trim((string) $photo->subject_type) === ''
                            || trim((string) $photo->subject_id) === ''
                        ) {
                            throw new RuntimeException(
                                'Não foi possível determinar o sujeito '
                                .'de um consumo de confirmação facial.'
                            );
                        }

                        DB::table(
                            'facial_photo_confirmation_consumptions'
                        )
                            ->where('id', $consumption->id)
                            ->update([
                                'subject_type' => (string) $photo->subject_type,

                                'subject_id' => (string) $photo->subject_id,
                            ]);
                    }
                },
                'id'
            );

        $incompleteCount = DB::table(
            'facial_photo_confirmation_consumptions'
        )
            ->where(
                static function ($query): void {
                    $query
                        ->whereNull('subject_type')
                        ->orWhereNull('subject_id')
                        ->orWhere('subject_type', '')
                        ->orWhere('subject_id', '');
                }
            )
            ->count();

        if ($incompleteCount !== 0) {
            throw new RuntimeException(
                'Existem consumos de confirmação facial sem sujeito '
                .'após o backfill.'
            );
        }

        Schema::table(
            'facial_photo_confirmation_consumptions',
            function (Blueprint $table): void {
                $table->index(
                    [
                        'subject_type',
                        'subject_id',
                        'consumed_at',
                    ],
                    'fpcc_subject_consumed_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'facial_photo_confirmation_consumptions',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'fpcc_subject_consumed_idx'
                );

                $table->dropColumn([
                    'subject_type',
                    'subject_id',
                ]);
            }
        );
    }
};
