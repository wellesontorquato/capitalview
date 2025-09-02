{{-- resources/views/recibos/emprestimo.blade.php --}}
@php
    $fmtMoeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
    $fmtCPF   = function($cpf) {
        $cpf = preg_replace('/\D+/', '', (string)$cpf ?? '');
        return strlen($cpf)===11
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf)
            : $cpf;
    };

    // Total de quitação (juros simples sobre principal)
    $totalQuitacao = (float) $valor;
    if(!empty($emprestimo->taxa_periodo) && !empty($emprestimo->qtd_parcelas)) {
        $totalQuitacao = (float) $valor * (1 + (float)$emprestimo->taxa_periodo * (int)$emprestimo->qtd_parcelas);
    }
    $totalJuros = max(0, $totalQuitacao - (float)$valor);
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Recibo de Confissão de Dívida — Empréstimo para {{ $cliente->nome }}</title>
<style>
  /* Dompdf: 1 página A4, margens compactas */
  @page { size: A4 portrait; margin: 22px 26px 24px 26px; }

  * { box-sizing: border-box; }
  body {
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    color:#111; margin:0;
  }

  /* Cabeçalho */
  .header {
    text-align:center; padding:8px 6px 10px;
    border-bottom: 1px solid #dcdcdc;
  }
  .header h1 {
    font-size:18px; margin:0; letter-spacing:0.2px;
  }
  .subinfo {
    font-size:11.2px; color:#666; margin-top:4px;
  }

  /* Quadro resumo (valores) */
  .summary {
    margin:10px 0 8px; padding:8px;
    background:#f6f8ff; border:1px solid #dfe3ff; border-radius:6px;
  }
  .summary-row { width:100%; border-collapse:collapse; }
  .summary-row td {
    padding:8px 10px; vertical-align:top; border-right:1px dashed #ccd3ff;
    font-size:12px;
  }
  .summary-row td:last-child { border-right:none; }
  .k { color:#555; font-size:11.5px; display:block; }
  .v { font-weight:700; font-size:13px; margin-top:2px; }

  /* Seções de partes (2 colunas) */
  .panel {
    border:1px solid #e3e3e3; border-radius:6px; padding:8px 10px; margin:8px 0;
  }
  .panel-title {
    font-weight:700; font-size:12.6px; margin:0 0 6px; color:#333;
  }
  .twocol { width:100%; border-collapse:separate; border-spacing:0 4px; }
  .twocol td { vertical-align:top; font-size:12px; padding:2px 4px; }
  .label { color:#666; font-size:11.4px; display:inline-block; min-width:98px; }
  .val { font-weight:600; }

  /* Texto principal */
  p { line-height:1.38; margin:8px 0; font-size:12.2px; }
  .muted { color:#666; font-size:11.4px; }

  /* Assinaturas */
  .sigs {
    margin-top:16px;
  }
  .sig-grid { width:100%; border-collapse:separate; border-spacing:18px 0; }
  .sig-cell { width:33.33%; text-align:center; }
  .line {
    margin-top:38px; border-top:1px solid #000; padding-top:4px; font-size:12px;
  }
  .sig-sub { color:#666; font-size:11px; margin-top:2px; }

  /* Rodapé */
  footer {
    margin-top:10px; text-align:center; font-size:11px; color:#666; padding-top:6px;
    border-top:1px solid #ededed;
  }

  /* Evitar quebras em blocos críticos */
  .no-break { page-break-inside: avoid; }
</style>
</head>
<body>

  <!-- Cabeçalho -->
  <div class="header no-break">
    <h1>RECIBO DE CONFISSÃO DE DÍVIDA</h1>
    <div class="subinfo">
      Data: {{ $hoje->format('d/m/Y') }}
      @if(!empty($credor['cidade']) || !empty($credor['uf']))
        • {{ $credor['cidade'] ?? '' }}{{ !empty($credor['cidade']) && !empty($credor['uf']) ? ' - ' : '' }}{{ $credor['uf'] ?? '' }}
      @endif
    </div>
  </div>

  <!-- Quadro-resumo (valores principais) -->
  <div class="summary no-break">
    <table class="summary-row">
      <tr>
        <td>
          <span class="k">Valor principal</span>
          <span class="v">{{ $fmtMoeda($valor) }}</span>
        </td>
        <td>
          <span class="k">Juros contratados</span>
          <span class="v">
            @if(!empty($emprestimo->taxa_periodo))
              {{ number_format($emprestimo->taxa_periodo*100,2,',','.') }}% a.m.
              @if(!empty($emprestimo->qtd_parcelas))
                • {{ $emprestimo->qtd_parcelas }} parcelas
              @endif
            @else
              —
            @endif
          </span>
        </td>
        <td>
          <span class="k">Total para quitação</span>
          <span class="v">{{ $fmtMoeda($totalQuitacao) }}</span>
        </td>
        <td>
          <span class="k">Juros totais (estimados)</span>
          <span class="v">{{ $fmtMoeda($totalJuros) }}</span>
        </td>
      </tr>
    </table>
  </div>

  <!-- Partes -->
  <div class="panel no-break">
    <div class="panel-title">Identificação das Partes</div>
    <table class="twocol">
      <tr>
        <td style="width:50%;">
          <div><span class="label">Credor(a):</span> <span class="val">{{ $credor['nome'] }}</span></div>
          @if(!empty($credor['cpf']))
            <div><span class="label">CPF:</span> <span class="val">{{ $fmtCPF($credor['cpf']) }}</span></div>
          @endif
        </td>
        <td style="width:50%;">
          <div><span class="label">Devedor(a):</span> <span class="val">{{ $cliente->nome }}</span></div>
          @if(!empty($cliente->cpf))
            <div><span class="label">CPF:</span> <span class="val">{{ $fmtCPF($cliente->cpf) }}</span></div>
          @endif
        </td>
      </tr>
    </table>
  </div>

  <!-- Texto principal -->
  <p class="no-break">
    Pelo presente instrumento particular de confissão de dívida, {{ $credor['nome'] }}
    @if(!empty($credor['cpf'])) (CPF {{ $fmtCPF($credor['cpf']) }}) @endif,
    na qualidade de <strong>Credor(a)</strong>, declara ter emprestado a quantia de
    <strong>{{ $fmtMoeda($valor) }}</strong> a {{ $cliente->nome }}
    @if(!empty($cliente->cpf)) (CPF {{ $fmtCPF($cliente->cpf) }}) @endif,
    doravante <strong>Devedor(a)</strong>. Este(a) reconhece e confessa a dívida,
    obrigando-se a quitá-la conforme as condições pactuadas, sendo o valor final
    devido para quitação integral de <strong>{{ $fmtMoeda($totalQuitacao) }}</strong>,
    estimando-se juros totais de <strong>{{ $fmtMoeda($totalJuros) }}</strong>
    conforme taxa e quantidade de parcelas indicadas.
  </p>

  <p class="muted no-break">
    Observação: este recibo é emitido para fins de comprovação de relação creditícia e
    poderá ser utilizado em conjunto com o cronograma/contrato correspondente.
  </p>

  <!-- Assinaturas -->
  <div class="sigs no-break">
    <table class="sig-grid">
      <tr>
        <td class="sig-cell">
          <div class="line">Credor(a): {{ $credor['nome'] }}</div>
          @if(!empty($credor['cpf'])) <div class="sig-sub">CPF {{ $fmtCPF($credor['cpf']) }}</div> @endif
        </td>
        <td class="sig-cell">
          <div class="line">Devedor(a): {{ $cliente->nome }}</div>
          @if(!empty($cliente->cpf)) <div class="sig-sub">CPF {{ $fmtCPF($cliente->cpf) }}</div> @endif
        </td>
        <td class="sig-cell">
          <div class="line">Testemunha</div>
          <div class="sig-sub">Assinatura / CPF</div>
        </td>
      </tr>
    </table>
  </div>

  <footer class="no-break">
    Este recibo foi gerado eletronicamente. Para assinatura digital, utilize o serviço oficial do
    <a href="https://www.gov.br/pt-br/servicos/assinatura-eletronica" target="_blank" style="text-decoration:none;">
    <img src="https://barra.sistema.gov.br/v1/assets/govbr.webp" alt="GOV.BR" style="height:18px; vertical-align:middle; margin-left:4px;">
  </a>
</footer>
</body>
</html>
