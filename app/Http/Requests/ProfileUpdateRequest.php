<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Usuário autenticado pode atualizar o próprio perfil
        return true;
    }

    /**
     * Normaliza dados antes da validação.
     * - CPF: mantém apenas dígitos ou null se vazio
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cpf' => $this->filled('cpf')
                ? preg_replace('/\D+/', '', (string) $this->input('cpf'))
                : null,
        ]);
    }

    /**
     * Regras de validação.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($userId),
            ],
            'cpf'   => [
                'nullable', 'digits:11',
                Rule::unique(User::class, 'cpf')->ignore($userId),
            ],
        ];
    }

    /**
     * Mensagens personalizadas.
     */
    public function messages(): array
    {
        return [
            'cpf.digits' => 'O CPF deve conter exatamente 11 dígitos.',
            'cpf.unique' => 'Este CPF já está em uso.',
            'email.unique' => 'Este e-mail já está em uso.',
        ];
    }
}
