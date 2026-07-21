<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class VisitVehicleRecord extends Model
{
    use LogsVanguardActivity;

    protected $table = 'visit_vehicles';

    protected $fillable = [
        'visit_id',
        'plate',
        'brand',
        'model',
        'color',
        'entry_authorized',
        'entry_authorized_by',
        'entry_authorized_at',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $vehicle): void {
            $vehicle->plate = self::normalizePlate(
                $vehicle->plate
            );

            foreach (['brand', 'model', 'color'] as $field) {
                $vehicle->{$field} = self::normalizeText(
                    $vehicle->{$field}
                );
            }

            if (! $vehicle->entry_authorized) {
                $vehicle->entry_authorized_by = null;
                $vehicle->entry_authorized_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'entry_authorized' => 'boolean',
            'entry_authorized_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(
            VisitRecord::class,
            'visit_id'
        );
    }

    public function entryAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'entry_authorized_by'
        );
    }

    public function authorizationRequests(): HasMany
    {
        return $this->hasMany(
            VisitVehicleAuthorizationRequestRecord::class,
            'visit_vehicle_id'
        )->latest('requested_at');
    }

    public function latestAuthorizationRequest(): HasOne
    {
        return $this->hasOne(
            VisitVehicleAuthorizationRequestRecord::class,
            'visit_vehicle_id'
        )->latestOfMany('requested_at');
    }

    public function pendingAuthorizationRequest(): HasOne
    {
        return $this->hasOne(
            VisitVehicleAuthorizationRequestRecord::class,
            'visit_vehicle_id'
        )->whereNotNull('pending_marker');
    }

    public static function normalizePlate(
        mixed $value
    ): ?string {
        $plate = strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '',
                trim((string) $value)
            ) ?? ''
        );

        return $plate !== '' ? $plate : null;
    }

    private static function normalizeText(
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
