<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // aqui você pode controlar permissões
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'     => ['required', 'string', 'max:255'],
            'email'    => ['nullable', 'email', 'unique:clientes,email'],
            'cpf'      => ['required', 'string', 'unique:clientes,cpf'],
            'whatsapp' => ['nullable', 'string', 'unique:clientes,whatsapp'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf.unique'      => 'Já existe um cliente cadastrado com este CPF.',
            'email.unique'    => 'Este e-mail já está sendo usado por outro cliente.',
            'whatsapp.unique' => 'Este WhatsApp já está em uso.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cpf'      => 'CPF',
            'email'    => 'E-mail',
            'whatsapp' => 'WhatsApp',
        ];
    }
}
