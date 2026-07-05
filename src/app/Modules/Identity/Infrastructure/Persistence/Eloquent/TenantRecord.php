<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TenantRecord extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'legal_name',
        'document',
        'status',
        'notes',
    ];

    public function organizations(): HasMany
    {
        return $this->hasMany(OrganizationRecord::class, 'tenant_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembershipRecord::class, 'tenant_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id')
            ->withPivot([
                'role',
                'is_owner',
                'is_active',
                'joined_at',
                'left_at',
            ])
            ->withTimestamps();
    }
}
