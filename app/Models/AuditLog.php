<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Registro de auditoría. Solo se insertan; no se modifican ni eliminan.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string $module
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string|null $description
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $device
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'action', 'module', 'auditable_type', 'auditable_id', 'description', 'old_values', 'new_values', 'ip_address', 'device'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La bitácora de auditoría es de solo lectura.'));
        static::deleting(fn () => throw new LogicException('La bitácora de auditoría es de solo lectura.'));
    }

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
