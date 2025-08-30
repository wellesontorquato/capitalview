{{-- resources/views/recibos/emprestimo.blade.php --}}
@php
    $fmtMoeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
    $fmtCPF   = function($cpf) {
        $cpf = preg_replace('/\D+/', '', (string)$cpf ?? '');
        return strlen($cpf)===11
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf)
            : $cpf;
    };
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Recibo de Confissão de Dívida — Empréstimo para {{ $cliente->nome }}</title>
<style>
  *{ box-sizing:border-box; }
  body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color:#111;
        margin:36px; display:flex; flex-direction:column; min-height:100vh; }
  h1{ font-size:20px; margin:0 0 16px; text-align:center; }
  p{ line-height:1.5; margin:12px 0; font-size:13px; }
  .muted{ color:#666; font-size:12px; }

  .grid{ display:grid; grid-template-columns:1fr 1fr; gap:8px 24px; margin:10px 0 20px; }
  .label{ color:#555; font-size:12px; }
  .val{ font-weight:600; }
  .box{ border:1px solid #ddd; padding:12px; border-radius:8px; margin:12px 0; }

  /* Assinaturas */
  .assinaturas{ margin-top:80px; display:flex; gap:40px; }
  .assin{ flex:1; text-align:center; }
  .assin .traco{ margin-top:80px; border-top:1px solid #000; padding-top:6px; }

  /* Footer no rodapé */
  footer{ margin-top:auto; text-align:center; font-size:11px; color:#666; padding-top:20px; }
</style>
</head>
<body>
  <h1>RECIBO DE CONFISSÃO DE DÍVIDA</h1>

  <div class="box">
    <div class="grid">
      <div><span class="label">Empréstimo:</span> <span class="val">#{{ $emprestimo->id }}</span></div>
      <div><span class="label">Data:</span> <span class="val">{{ $hoje->format('d/m/Y') }}</span></div>
      <div><span class="label">Credor(a):</span> <span class="val">{{ $credor['nome'] }}</span></div>
      @if(!empty($credor['cpf']))
        <div><span class="label">CPF credor(a):</span> <span class="val">{{ $fmtCPF($credor['cpf']) }}</span></div>
      @endif
      <div><span class="label">Devedor(a):</span> <span class="val">{{ $cliente->nome }}</span></div>
      @if(!empty($cliente->cpf))
        <div><span class="label">CPF devedor(a):</span> <span class="val">{{ $fmtCPF($cliente->cpf) }}</span></div>
      @endif
      <div><span class="label">Valor principal:</span> <span class="val">{{ $fmtMoeda($valor) }}</span></div>
      @if(!empty($emprestimo->taxa_periodo))
        <div><span class="label">Juros contratados:</span> <span class="val">{{ number_format($emprestimo->taxa_periodo*100,2,',','.') }}% a.m.</span></div>
      @endif
    </div>
  </div>

  <p>
    Pelo presente instrumento particular de confissão de dívida, {{ $credor['nome'] }}
    @if(!empty($credor['cpf'])) (CPF {{ $fmtCPF($credor['cpf']) }}) @endif,
    na qualidade de <strong>Credor(a)</strong>, declara ter emprestado a quantia de
    <strong>{{ $fmtMoeda($valor) }}</strong> a {{ $cliente->nome }}
    @if(!empty($cliente->cpf)) (CPF {{ $fmtCPF($cliente->cpf) }}) @endif,
    doravante <strong>Devedor(a)</strong>, o(a) qual reconhece e confessa a dívida,
    obrigando-se a quitá-la conforme as condições pactuadas entre as partes e registradas
    no Empréstimo #{{ $emprestimo->id }}.
  </p>

  <p class="muted">
    Observação: este recibo é emitido para fins de comprovação de relação creditícia e
    poderá ser utilizado em conjunto com o cronograma/contrato correspondente.
  </p>

  <div class="assinaturas">
    <div class="assin">
      <div class="traco">Credor(a): {{ $credor['nome'] }}</div>
      @if(!empty($credor['cpf'])) <div class="muted">CPF {{ $fmtCPF($credor['cpf']) }}</div> @endif
    </div>
    <div class="assin">
      <div class="traco">Devedor(a): {{ $cliente->nome }}</div>
      @if(!empty($cliente->cpf)) <div class="muted">CPF {{ $fmtCPF($cliente->cpf) }}</div> @endif
    </div>
    <div class="assin">
      <div class="traco">Testemunha</div>
      <div class="muted">Assinatura / CPF</div>
    </div>
  </div>

  @if(!empty($credor['cidade']) || !empty($credor['uf']))
    <p class="muted" style="margin-top:32px;">
      {{ $credor['cidade'] ?? '' }}{{ !empty($credor['cidade']) && !empty($credor['uf']) ? ' - ' : '' }}{{ $credor['uf'] ?? '' }},
      {{ $hoje->translatedFormat('d \d\e F \d\e Y') }}
    </p>
  @endif

  <footer>
    Este recibo foi gerado eletronicamente pelo sistema e não requer assinatura digital.
  </footer>
</body>
</html>
