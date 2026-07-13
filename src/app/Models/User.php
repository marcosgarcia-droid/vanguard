<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantMembershipRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Support\ActivityLog\LogsVanguardActivity;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsVanguardActivity, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([
            config('filament-shield.super_admin.name', 'super_admin'),
            config('filament-shield.panel_user.name', 'panel_user'),
        ]);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(TenantRecord::class, 'tenant_user', 'user_id', 'tenant_id')
            ->withPivot([
                'role',
                'is_owner',
                'is_active',
                'joined_at',
                'left_at',
            ])
            ->withTimestamps();
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembershipRecord::class, 'user_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationRecord::class, 'organization_user', 'user_id', 'organization_id')
            ->withPivot([
                'role',
                'is_active',
                'granted_at',
                'revoked_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
