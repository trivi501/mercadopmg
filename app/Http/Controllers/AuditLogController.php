<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Models\AuditLog;
use App\Support\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta de la bitácora de auditoría (solo lectura).
 */
class AuditLogController extends Controller
{
    private const array RECORD_LABELS = [
        'Merchant' => 'Comerciante',
        'Receipt' => 'Recibo',
        'User' => 'Usuario',
        'BusinessType' => 'Giro',
        'Tianguis' => 'Tianguis',
        'MarketRoute' => 'Ruta',
        'Concept' => 'Concepto',
    ];

    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->can(Permission::AuditView->value), 403);

        $filters = $request->only(['date_from', 'date_to', 'user_id', 'action', 'module', 'search']);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['module'] ?? null, fn ($q, $v) => $q->where('module', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where('description', 'like', "%{$v}%"))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user->name ?? 'Sistema',
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'module' => $log->module,
                'record' => $log->auditable_type ? (self::RECORD_LABELS[class_basename($log->auditable_type)] ?? class_basename($log->auditable_type)).' #'.$log->auditable_id : null,
                'description' => $log->description,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'device' => $log->device,
                'date' => $log->created_at?->format('d/m/Y'),
                'time' => $log->created_at?->format('H:i:s'),
            ]);

        return Inertia::render('audit/index', [
            'logs' => $logs,
            'filters' => $filters,
            'options' => [
                ...Options::for(['users']),
                'actions' => AuditAction::options(),
                'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            ],
        ]);
    }
}
