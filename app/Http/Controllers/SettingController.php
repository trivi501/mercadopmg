<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración general del sistema (datos del municipio y del ticket).
 */
class SettingController extends Controller
{
    public const array KEYS = ['municipality_name', 'municipality_state', 'treasury_name', 'ticket_footer'];

    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->can(Permission::SettingsManage->value), 403);

        return Inertia::render('admin/settings', [
            'settings' => Setting::query()->whereIn('key', self::KEYS)->get(['key', 'value', 'description'])->keyBy('key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can(Permission::SettingsManage->value), 403);

        $data = $request->validate([
            'municipality_name' => ['required', 'string', 'max:150'],
            'municipality_state' => ['nullable', 'string', 'max:100'],
            'treasury_name' => ['nullable', 'string', 'max:150'],
            'ticket_footer' => ['nullable', 'string', 'max:200'],
        ]);

        foreach ($data as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configuración guardada.']);

        return back();
    }
}
