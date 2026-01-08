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
        // (opcional) trava por idempotency_key por 20s
        if ($key = $request->input('idempotency_key')) {
            $lockKey = 'clientes:create:' . $key;
            if (! Cache::add($lockKey, 1, now()->addSeconds(20))) {
                return redirect()
                    ->route('clientes.index')
                    ->with('error', 'Requisição repetida detectada. O envio anterior já está sendo processado.');
            }
        }

        // --- validação (unicidade por usuário)
        $uid = auth()->id();

        $rules = [
            'nome'        => ['required','string','max:255'],
            'apelido'     => ['nullable','string','max:255'],

            'whatsapp'    => [
                'nullable','string','max:30',
                Rule::unique('clientes','whatsapp')
                    ->where(fn($q) => $q->where('user_id', $uid)),
            ],
            'email'       => [
                'nullable','email','max:255',
                Rule::unique('clientes','email')
                    ->where(fn($q) => $q->where('user_id', $uid)),
            ],
            'cpf'         => [
                'required','string','max:20',
                Rule::unique('clientes','cpf')
                    ->where(fn($q) => $q->where('user_id', $uid)),
            ],
            'rg'          => ['nullable','string','max:30'],

            'cep'         => ['nullable','string','max:12'],
            'logradouro'  => ['nullable','string','max:255'],
            'numero'      => ['nullable','string','max:20'],
            'complemento' => ['nullable','string','max:255'],
            'bairro'      => ['nullable','string','max:255'],
            'cidade'      => ['nullable','string','max:255'],
            'uf'          => ['nullable','string','size:2'],

            'observacoes' => ['nullable','string'],
        ];

        // mensagens de apoio (caso o lang/validation.php ainda não esteja com os rótulos)
        $messages = [
            'cpf.unique'      => 'Já existe um cliente cadastrado com este CPF.',
            'email.unique'    => 'Este e-mail já está sendo usado por outro cliente.',
            'whatsapp.unique' => 'Este WhatsApp já está em uso.',
        ];

        $data = $request->validate($rules, $messages);

        // normalização e vínculo ao dono
        $data = $this->normalize($data);
        $data['user_id'] = $uid;

        try {
            // chave natural (scopada por user) para evitar duplicidade simultânea
            if ($naturalKey = $this->pickNaturalKey($data)) {
                Cliente::updateOrCreate(
                    ['user_id' => $uid, $naturalKey => $data[$naturalKey]],
                    $data
                );
            } else {
                Cliente::create($data);
            }
        } catch (QueryException $e) {
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
        $uid = auth()->id();

        $rules = [
            'nome'        => ['required','string','max:255'],
            'apelido'     => ['nullable','string','max:255'],

            'whatsapp'    => [
                'nullable','string','max:30',
                Rule::unique('clientes','whatsapp')
                    ->where(fn($q) => $q->where('user_id', $uid))
                    ->ignore($cliente->id),
            ],
            'email'       => [
                'nullable','email','max:255',
                Rule::unique('clientes','email')
                    ->where(fn($q) => $q->where('user_id', $uid))
                    ->ignore($cliente->id),
            ],
            'cpf'         => [
                'required','string','max:20',
                Rule::unique('clientes','cpf')
                    ->where(fn($q) => $q->where('user_id', $uid))
                    ->ignore($cliente->id),
            ],
            'rg'          => ['nullable','string','max:30'],

            'cep'         => ['nullable','string','max:12'],
            'logradouro'  => ['nullable','string','max:255'],
            'numero'      => ['nullable','string','max:20'],
            'complemento' => ['nullable','string','max:255'],
            'bairro'      => ['nullable','string','max:255'],
            'cidade'      => ['nullable','string','max:255'],
            'uf'          => ['nullable','string','size:2'],

            'observacoes' => ['nullable','string'],
        ];

        $messages = [
            'cpf.unique'      => 'Já existe um cliente cadastrado com este CPF.',
            'email.unique'    => 'Este e-mail já está sendo usado por outro cliente.',
            'whatsapp.unique' => 'Este WhatsApp já está em uso.',
        ];

        $data = $request->validate($rules, $messages);
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

        $data['cpf']      = array_key_exists('cpf', $data)      ? $digits($data['cpf']) : null;
        $data['whatsapp'] = array_key_exists('whatsapp', $data) ? $digits($data['whatsapp']) : null;
        $data['cep']      = array_key_exists('cep', $data)      ? $digits($data['cep']) : null;

        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }
        if (array_key_exists('uf', $data) && $data['uf'] !== null) {
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
        $sqlState   = $e->errorInfo[0] ?? null;
        $driverCode = $e->errorInfo[1] ?? null;

        if ($sqlState === '23000' && in_array($driverCode, [1062, 1586, 1022])) {
            $text = strtolower($e->getMessage());
            if (str_contains($text, 'clientes_cpf'))      return 'Já existe um cliente com este CPF.';
            if (str_contains($text, 'clientes_email'))    return 'Este e-mail já está sendo usado por outro cliente.';
            if (str_contains($text, 'clientes_whatsapp')) return 'Este WhatsApp já está em uso.';
            return 'Registro duplicado. Verifique CPF, e-mail ou WhatsApp.';
        }
        return null;
    }
}
