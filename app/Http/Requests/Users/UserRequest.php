<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User
            ? (bool) $this->user()?->can('update', $user)
            : (bool) $this->user()?->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => is_string($this->input('username')) ? strtolower(trim($this->input('username'))) : $this->input('username'),
            'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $ignore = $user instanceof User ? $user->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users')->ignore($ignore)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($ignore)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['boolean'],
            'password' => $ignore ? ['nullable', 'string', Password::default(), 'confirmed'] : $this->passwordRules(),
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'username' => 'usuario', 'email' => 'correo electrónico', 'phone' => 'teléfono', 'role' => 'rol', 'password' => 'contraseña'];
    }
}
