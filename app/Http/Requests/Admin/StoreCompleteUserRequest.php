<?php

namespace App\Http\Requests\Admin;

use App\Support\PersonData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** "1.070.622" y "1070622" son el mismo documento: se normaliza antes de validar unicidad. */
    protected function prepareForValidation(): void
    {
        PersonData::normalizeRequest($this);
    }

    public function rules(): array
    {
        return [
            'document_type_id' => ['required', 'exists:document_types,id'],
            // Un documento ya registrado no se reutiliza en silencio (antes se tomaba
            // la persona existente e ignoraba los nombres escritos).
            'document_number' => [
                'required', 'string', 'max:30',
                Rule::unique('persons', 'document_number')->where('document_type_id', $this->input('document_type_id')),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            // La regla "barrio obligatorio para Jurado" NO vive aquí: con `nullable` en la mezcla,
            // Laravel se salta cualquier closure cuando el valor llega vacío, que es justo el caso
            // a bloquear. Se valida de forma imperativa en el controlador (ver store()).
            'commune_id' => ['nullable', 'exists:communes,id'],
            'neighborhood_id' => ['nullable', 'active_exists:neighborhoods,id'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Un solo rol por usuario.
            'roles' => ['required', 'array', 'size:1'],
            'roles.*' => ['integer', 'distinct'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.size' => 'Un usuario solo puede tener un rol.',
            'document_number.unique' => 'Ya hay una persona registrada con este documento. Créale la cuenta desde la pestaña Usuarios.',
        ];
    }
}
