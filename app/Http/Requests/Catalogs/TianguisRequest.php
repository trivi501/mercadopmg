<?php

namespace App\Http\Requests\Catalogs;

use App\Models\Tianguis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TianguisRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150', Rule::unique('tianguis')->ignore($this->route('tianguis'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:150'],
            'municipality' => ['nullable', 'string', 'max:150'],
            'operating_day' => ['nullable', Rule::in(Tianguis::DAYS)],
            'schedule' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'operating_day' => 'día de operación', 'schedule' => 'horario'];
    }
}
