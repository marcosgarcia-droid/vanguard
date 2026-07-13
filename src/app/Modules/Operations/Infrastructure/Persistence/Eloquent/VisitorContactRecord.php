<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VisitorContactRecord extends Model
{
    use LogsVanguardActivity;

    protected $table = 'visitor_contacts';

    protected $fillable = [
        'visitor_id',
        'type',
        'label',
        'value',
        'normalized_value',
        'is_primary',
        'notes',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $contact): void {
            $normalized = self::normalizeValue(
                $contact->type,
                $contact->value
            );

            $contact->value = $normalized;
            $contact->normalized_value = $normalized;
        });
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(VisitorRecord::class, 'visitor_id');
    }

    public static function normalizeValue(?string $type, ?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return match ($type) {
            'mobile', 'phone', 'whatsapp' => self::digits($value),
            'email' => mb_strtolower($value),
            default => $value,
        };
    }

    private static function digits(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }
}
