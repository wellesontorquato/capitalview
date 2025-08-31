<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;

class ClienteController extends Controller
{
    /* ============================ LISTA ============================ */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $clientes = Cliente::query()
            ->search($q)
            ->orderBy('nome')
            ->paginate(12)
            ->withQueryString();

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
        // --- (opcional) trava simples por idempotency_key por 20s
        if ($key = $request->input('idempotency_key')) {
            $lockKey = 'clientes:create:' . $key;
            if (! Cache::add($lockKey, 1, now()->addSeconds(20))) {
                return redirect()
                    ->route('clientes.index')
                    ->with('error', 'Requisição repetida detectada. O envio anterior já está sendo processado.');
            }
        }

        // validação com unicidade
        $data = $request->validate([
            'nome'        => ['required','string','max:255'],
            'apelido'     => ['nullable','string','max:255'],
            'whatsapp'    => ['nullable','string','max:30','unique:clientes,whatsapp'],
            'email'       => ['nullable','email','max:255','unique:clientes,email'],
            'cpf'         => ['required','string','max:20','unique:clientes,cpf'],
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

        // normalização antes de persistir
        $data = $this->normalize($data);

        try {
            // Chave natural para evitar duplicidade simultânea
            $naturalKey = $this->pickNaturalKey($data); // cpf > email > whatsapp
            if ($naturalKey) {
                Cliente::updateOrCreate([$naturalKey => $data[$naturalKey]], $data);
            } else {
                Cliente::create($data);
            }
        } catch (QueryException $e) {
            // Erros de índice único -> mensagem amigável
            $msg = $this->uniqueErrorMessage($e);
            return back()->withInput()->with('error', $msg ?: 'Não foi possível salvar o cliente.');
        }

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente criado.');
    }

    /* ============================ SHOW ============================= */
    public function show(Cliente $cliente)
    {
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
            'whatsapp'    => ['nullable','string','max:30', Rule::unique('clientes','whatsapp')->ignore($cliente->id)],
            'email'       => ['nullable','email','max:255', Rule::unique('clientes','email')->ignore($cliente->id)],
            'cpf'         => ['required','string','max:20', Rule::unique('clientes','cpf')->ignore($cliente->id)],
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

        $data = $this->normalize($data);

        try {
            $cliente->update($data);
        } catch (QueryException $e) {
            $msg = $this->uniqueErrorMessage($e);
            return back()->withInput()->with('error', $msg ?: 'Não foi possível atualizar o cliente.');
        }

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

    /* ============================ HELPERS ========================== */

    /** Normaliza CPF/CEP/WhatsApp (só dígitos), e-mail minúsculo, UF maiúsculo */
    private function normalize(array $data): array
    {
        $digits = fn($v) => $v !== null ? preg_replace('/\D+/', '', (string)$v) : null;

        $data['cpf']      = isset($data['cpf'])      ? $digits($data['cpf']) : null;
        $data['whatsapp'] = isset($data['whatsapp']) ? $digits($data['whatsapp']) : null;
        $data['cep']      = isset($data['cep'])      ? $digits($data['cep']) : null;

        if (isset($data['email']) && $data['email'] !== null) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }
        if (isset($data['uf']) && $data['uf'] !== null) {
            $data['uf'] = mb_strtoupper(trim($data['uf']));
        }

        return $data;
    }

    /** Escolhe a melhor chave natural disponível (prioridade: cpf > email > whatsapp) */
    private function pickNaturalKey(array $data): ?string
    {
        foreach (['cpf','email','whatsapp'] as $k) {
            if (!empty($data[$k])) return $k;
        }
        return null;
    }

    /** Mensagem amigável para violação de unique index */
    private function uniqueErrorMessage(QueryException $e): ?string
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        // MySQL/MariaDB duplicate
        if ($sqlState === '23000' && in_array($driverCode, [1062, 1586, 1022])) {
            $msg = 'Já existe um cliente com ';
            $text = strtolower($e->getMessage());

            if (str_contains($text, 'clientes_cpf_unique') || str_contains($text, 'clientes_cpf')) {
                return $msg . 'este CPF.';
            }
            if (str_contains($text, 'clientes_email_unique') || str_contains($text, 'clientes_email')) {
                return $msg . 'este e-mail.';
            }
            if (str_contains($text, 'clientes_whatsapp_unique') || str_contains($text, 'clientes_whatsapp')) {
                return $msg . 'este WhatsApp.';
            }
            return 'Registro duplicado. Verifique CPF, e-mail ou WhatsApp.';
        }

        return null;
    }
}
