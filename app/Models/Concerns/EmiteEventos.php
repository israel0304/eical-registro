<?php

namespace App\Models\Concerns;

use App\Services\EventAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Habilita la auditoría automática de eventos para un modelo.
 *
 * Emite eventos {modelo}.created / .updated / .deleted hacia el catálogo
 * de config/events.php. Para que `updated` se registre solo cuando cambian
 * ciertas columnas, define en el modelo:
 *
 *     protected static array $auditUpdatedAttributes = ['name', 'title'];
 *
 * Si el arreglo está vacío, `updated` se emite en cualquier cambio.
 */
trait EmiteEventos
{
    protected static array $auditUpdatedAttributes = [];

    public static function bootEmiteEventos(): void
    {
        static::created(function ($model) {
            $model->emitEvent('created');
        });

        static::updated(function ($model) {
            if (static::auditTracksUpdated($model)) {
                $model->emitEvent('updated');
            }
        });

        static::deleted(function ($model) {
            $model->emitEvent('deleted');
        });
    }

    public function emitEvent(string $action, array $payload = []): void
    {
        EventAudit::emit(
            Str::snake(class_basename($this)).'.'.$action,
            $this,
            Auth::user(),
            $payload ?: $this->toAuditPayload(),
        );
    }

    protected static function auditTracksUpdated($model): bool
    {
        if (empty(static::$auditUpdatedAttributes)) {
            return true;
        }

        return $model->wasChanged(static::$auditUpdatedAttributes);
    }

    public function toAuditPayload(): array
    {
        return $this->getAttributes();
    }
}
