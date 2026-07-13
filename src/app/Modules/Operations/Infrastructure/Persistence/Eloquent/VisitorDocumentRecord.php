<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

final class VisitorDocumentRecord extends Model
{
    use LogsVanguardActivity;

    protected $table = 'visitor_documents';

    protected $fillable = [
        'visitor_id',
        'type',
        'number',
        'normalized_number',
        'state',
        'issuing_authority',
        'issued_at',
        'expires_at',
        'is_primary',
        'notes',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $document): void {
            $normalized = VisitorRecord::normalizeDocumentNumber(
                $document->type,
                $document->number
            );

            $document->number = $normalized;
            $document->normalized_number = $normalized;
            $document->state = filled($document->state)
                ? strtoupper(trim((string) $document->state))
                : null;

            $document->validateDocumentUniqueness();
        });
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
            'is_primary' => 'boolean',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(VisitorRecord::class, 'visitor_id');
    }

    private function validateDocumentUniqueness(): void
    {
        if (
            blank($this->visitor_id)
            || blank($this->type)
            || blank($this->normalized_number)
        ) {
            return;
        }

        $visitor = $this->visitor()->first();

        if (! $visitor instanceof VisitorRecord) {
            return;
        }

        $duplicateExists = self::query()
            ->where('type', $this->type)
            ->where('normalized_number', $this->normalized_number)
            ->when(
                $this->exists,
                fn ($query) => $query->whereKeyNot($this->getKey())
            )
            ->whereHas(
                'visitor',
                fn ($query) => $query
                    ->where('tenant_id', $visitor->tenant_id)
                    ->where('organization_id', $visitor->organization_id)
            )
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'documents' => 'Já existe um visitante com este documento nesta unidade.',
            ]);
        }
    }
}
