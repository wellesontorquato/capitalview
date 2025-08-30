<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /* ============================ LISTA ============================ */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $clientes = Cliente::query()
            ->search($q)               // usa o scope do modelo
            ->orderBy('nome')
            ->paginate(12)
            ->withQueryString();       // preserva ?q=

        return view('clientes.index', compact('clientes'));
    }

    /* ============================ CREATE =========================== */
    public function create()
    {
        return view('clientes.create');
    }

    /* ============================ STORE ============================ */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nome'        => ['required','string','max:255'],
            'apelido'     => ['nullable','string','max:255'],
            'whatsapp'    => ['nullable','string','max:30'],
            'email'       => ['nullable','email','max:255'],
            'cpf'         => ['nullable','string','max:20'],   // aceita com/sem máscara; o Model normaliza
            'rg'          => ['nullable','string','max:30'],

            'cep'         => ['nullable','string','max:12'],
            'logradouro'  => ['nullable','string','max:255'],
            'numero'      => ['nullable','string','max:20'],
            'complemento' => ['nullable','string','max:255'],
            'bairro'      => ['nullable','string','max:255'],
            'cidade'      => ['nullable','string','max:255'],
            'uf'          => ['nullable','string','size:2'],

            'observacoes' => ['nullable','string'],
        ]);

        Cliente::create($data);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente criado.');
    }

    /* ============================ SHOW ============================= */
    public function show(Cliente $cliente)
    {
        // carrega empréstimos e parcelas para o painel do cliente
        $cliente->load([
            'emprestimos' => fn($q) => $q->latest('id'),
            'emprestimos.parcelas',
        ]);

        return view('clientes.show', compact('cliente'));
    }

    /* ============================ EDIT ============================= */
    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    /* ============================ UPDATE =========================== */
    public function update(Request $request, Cliente $cliente)
    {
        $data = $request->validate([
            'nome'        => ['required','string','max:255'],
            'apelido'     => ['nullable','string','max:255'],
            'whatsapp'    => ['nullable','string','max:30'],
            'email'       => ['nullable','email','max:255'],
            'cpf'         => ['nullable','string','max:20'],
            'rg'          => ['nullable','string','max:30'],

            'cep'         => ['nullable','string','max:12'],
            'logradouro'  => ['nullable','string','max:255'],
            'numero'      => ['nullable','string','max:20'],
            'complemento' => ['nullable','string','max:255'],
            'bairro'      => ['nullable','string','max:255'],
            'cidade'      => ['nullable','string','max:255'],
            'uf'          => ['nullable','string','size:2'],

            'observacoes' => ['nullable','string'],
        ]);

        $cliente->update($data);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente atualizado.');
    }

    /* ============================ DESTROY ========================== */
    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return back()->with('success', 'Cliente removido.');
    }
}
