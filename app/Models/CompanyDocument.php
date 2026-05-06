<?php

namespace App\Models;

use Database\Factories\CompanyDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocument extends Model
{
    /** @use HasFactory<CompanyDocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'folder_id',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'uploaded_by_user_id',
        'access_mode',
        'status',
        'submitted_at',
        'decided_at',
        'decided_by_user_id',
        'decision_note',
        'tags',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'size_bytes' => 'integer',
            'tags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<CompanyDocumentFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * @return HasMany<CompanyDocumentApprovalAudit, $this>
     */
    public function approvalAudits(): HasMany
    {
        return $this->hasMany(CompanyDocumentApprovalAudit::class);
    }
}
