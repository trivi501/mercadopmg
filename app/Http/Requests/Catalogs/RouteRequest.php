<?php

namespace App\Http\Requests\Catalogs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('catalogs.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('routes')->ignore($this->route('route'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'zone' => ['nullable', 'string', 'max:150'],
            'itinerary' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'zone' => 'zona', 'itinerary' => 'recorrido'];
    }
}
