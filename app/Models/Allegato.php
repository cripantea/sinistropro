<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Allegato extends Model
{
    use HasFactory, BelongsToTenant, HasAuditLog;

    protected $table = 'allegati';

    protected $fillable = [
        'pratica_id',
        'tenant_id',
        'nome_file',
        's3_key',
        'document_category_id',
        'source',
        'module_template_id',
    ];

    // s3_key è un dato tecnico/interno: non include nel log.
    protected array $auditExclude = ['s3_key'];

    public function pratica(): BelongsTo
    {
        return $this->belongsTo(Pratica::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
