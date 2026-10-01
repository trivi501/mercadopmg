<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Punto único para escribir en la bitácora de auditoría.
 */
class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  User|null  $actor  Usuario que realiza la operación cuando no es el de la petición (consola, colas, seeders).
     */
    public function log(AuditAction $action, string $module, ?Model $subject = null, ?array $old = null, ?array $new = null, ?string $description = null, ?User $actor = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : $this->request;

        return AuditLog::create([
            'user_id' => $actor->id ?? $request?->user()?->getAuthIdentifier() ?? auth()->id(),
            'action' => $action,
            'module' => $module,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'device' => $request ? $this->device($request) : 'console',
        ]);
    }

    private function device(Request $request): ?string
    {
        $device = $request->header('X-Device-Name') ?: $request->userAgent();

        return $device ? Str::limit($device, 250, '') : null;
    }
}
