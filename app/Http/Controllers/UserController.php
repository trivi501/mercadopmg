<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\Users\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with('roles')
            ->withCount('tokens')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')->toString()))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => UserResource::collection($users),
            'filters' => $request->only(['search', 'role']),
            'options' => Options::for(['roles']),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->except(['role', 'password_confirmation']));
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$request->input('role')]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario creado.']);

        return back();
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['role', 'password', 'password_confirmation']);

        if ($request->user()?->is($user) && (! ($data['is_active'] ?? true) || ($request->input('role') !== Role::Admin->value && $user->isAdmin()))) {
            return back()->withErrors(['role' => 'No puede desactivarse ni quitarse el rol de administrador a sí mismo.']);
        }

        DB::transaction(function () use ($request, $user, $data) {
            $user->fill($data);
            if ($request->filled('password')) {
                $user->password = (string) $request->input('password');
            }
            $user->save();
            $user->syncRoles([$request->input('role')]);

            // Un usuario desactivado pierde sus tokens de la app móvil.
            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado.']);

        return back();
    }

    /**
     * Revoca todas las sesiones de la app móvil del usuario.
     */
    public function revokeTokens(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $count = $user->tokens()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se revocaron {$count} sesiones móviles."]);

        return back();
    }
}
