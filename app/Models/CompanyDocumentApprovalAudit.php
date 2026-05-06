<?php

namespace App\Models;

use Database\Factories\CompanyDocumentApprovalAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDocumentApprovalAudit extends Model
{
    /** @use HasFactory<CompanyDocumentApprovalAuditFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_document_id',
        'action',
        'actor_user_id',
        'note',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CompanyDocument, $this>
     */
    public function companyDocument(): BelongsTo
    {
        return $this->belongsTo(CompanyDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
