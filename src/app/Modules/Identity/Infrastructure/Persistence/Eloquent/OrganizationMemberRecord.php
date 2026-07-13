<?php

namespace App\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Support\ActivityLog\LogsVanguardActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class OrganizationMemberRecord extends Model
{
    use LogsVanguardActivity, SoftDeletes;

    protected $table = 'organization_members';

    protected $fillable = [
        'organization_id',
        'name',
        'document_type',
        'document_number',
        'member_type',
        'qualification_code',
        'qualification_name',
        'role',
        'is_legal_representative',
        'joined_at',
        'age_range',
        'country_code',
        'representative_name',
        'representative_document_type',
        'representative_document_number',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_legal_representative' => 'boolean',
            'joined_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationRecord::class, 'organization_id');
    }
}
