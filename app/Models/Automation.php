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
        'trigger_type',
        'tenant_status_id',
        'watched_field',
        'days_before',
        'channel',
        'recipient',
        'recipients_to',
        'recipients_cc',
        'message_template',
        'is_active',
        'requires_confirmation',
    ];

    protected $casts = [
        'is_active'             => 'boolean',
        'requires_confirmation' => 'boolean',
        'days_before'           => 'integer',
        'recipients_to'         => 'array',
        'recipients_cc'         => 'array',
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
