<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Aggancia automaticamente gli Eloquent Events created/updated/deleted
 * e scrive il registro di audit tramite AuditLogger.
 *
 * Proprietà opzionale sul modello:
 *   protected array $auditExclude = ['campo_da_ignorare'];
 */
trait HasAuditLog
{
    // Buffer statico: cattura i valori "prima" durante l'evento `updating`,
    // li usa nell'evento `updated`. Usa spl_object_id per isolare le istanze.
    private static array $auditBuffer = [];

    public static function bootHasAuditLog(): void
    {
        // --- CREATE ---
        static::created(function (self $model): void {
            $exclude    = array_merge(['created_at', 'updated_at'], $model->auditExclude ?? []);
            $newValues  = array_diff_key($model->getAttributes(), array_flip($exclude));

            app(AuditLogger::class)->log('create', $model, null, $newValues);
        });

        // --- UPDATE (cattura i valori prima del salvataggio) ---
        static::updating(function (self $model): void {
            $exclude  = array_merge(['created_at', 'updated_at'], $model->auditExclude ?? []);
            $dirty    = array_diff_key($model->getDirty(), array_flip($exclude));

            if (empty($dirty)) {
                return;
            }

            self::$auditBuffer[spl_object_id($model)] = [
                'old' => array_intersect_key($model->getOriginal(), $dirty),
                'new' => $dirty,
            ];
        });

        static::updated(function (self $model): void {
            $oid  = spl_object_id($model);
            $data = self::$auditBuffer[$oid] ?? null;

            if ($data === null) {
                return; // nessun campo rilevante è cambiato
            }

            unset(self::$auditBuffer[$oid]);

            app(AuditLogger::class)->log('update', $model, $data['old'], $data['new']);
        });

        // --- DELETE ---
        static::deleted(function (self $model): void {
            $exclude   = array_merge(['created_at', 'updated_at'], $model->auditExclude ?? []);
            $oldValues = array_diff_key($model->getAttributes(), array_flip($exclude));

            app(AuditLogger::class)->log('delete', $model, $oldValues, null);
        });
    }

    /**
     * Logs a manual 'view' action for this model instance.
     * Da chiamare esplicitamente nel controller (es. PraticaController::show).
     */
    public function logView(): void
    {
        app(AuditLogger::class)->log('view', $this);
    }
}
