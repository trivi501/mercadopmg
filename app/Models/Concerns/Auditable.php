<?php

namespace App\Models\Concerns;

use App\Enums\AuditAction;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra automáticamente en la bitácora de auditoría las altas,
 * modificaciones y bajas del modelo.
 *
 * @mixin Model
 */
trait Auditable
{
    /**
     * Atributos que nunca deben quedar en la bitácora.
     *
     * @var list<string>
     */
    protected static array $auditExcept = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'updated_at', 'created_at'];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(AuditLogger::class)->log(AuditAction::Create, static::auditModule(), $model, null, static::auditFilter($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = static::auditFilter($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            app(AuditLogger::class)->log(AuditAction::Update, static::auditModule(), $model, static::auditFilter($original), $changes);
        });

        static::deleted(function (Model $model) {
            app(AuditLogger::class)->log(AuditAction::Delete, static::auditModule(), $model, static::auditFilter($model->getOriginal()), null);
        });
    }

    public static function auditModule(): string
    {
        return class_basename(static::class);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function auditFilter(array $values): array
    {
        return array_diff_key($values, array_flip(static::$auditExcept));
    }
}
