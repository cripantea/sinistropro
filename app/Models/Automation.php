<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Automation extends Model
{
    use BelongsToTenant;

    protected $table = 'automations';

    protected $fillable = [
        'tenant_id',
        'name',
        'tenant_status_id',
        'channel',
        'recipient',
        'message_template',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TenantStatus::class, 'tenant_status_id');
    }

    public function documentCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentCategory::class,
            'automation_document_categories',
            'automation_id',
            'document_category_id'
        );
    }
}
