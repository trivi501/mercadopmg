<?php

namespace App\Http\Requests\Catalogs;

use App\Enums\ConceptType;
use App\Enums\Periodicity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('catalogs.manage');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('concepts')->ignore($this->route('concept'))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(ConceptType::class)],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'periodicity' => ['required', Rule::enum(Periodicity::class)],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'clave', 'name' => 'nombre', 'amount' => 'importe', 'periodicity' => 'periodicidad', 'type' => 'tipo'];
    }
}
