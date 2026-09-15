<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Limpia el nombre técnico para forzar minúsculas y reemplazar espacios por guiones bajos
        if ($this->has('name')) {
            $this->merge([
                'name' => strtolower(trim(str_replace(' ', '_', $this->name))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 
                'string', 
                'max:50', 
                'regex:/^[a-z0-9._-]+$/', 
                'unique:roles,name'
            ],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            // Permite validar si lo que llega del frontend son IDs numéricos O los nombres de los permisos
            'permissions.*' => ['required'], 
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'El identificador del rol solo puede contener letras minúsculas, números, puntos y guiones.',
            'name.unique' => 'El identificador del rol ya se encuentra registrado.',
        ];
    }
}