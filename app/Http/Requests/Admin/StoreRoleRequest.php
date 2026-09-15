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
            'permissions.*' => ['required'],
        ];
    }
}