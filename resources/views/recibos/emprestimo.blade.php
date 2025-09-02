{{-- resources/views/recibos/emprestimo.blade.php --}}
@php
    $fmtMoeda = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
    $fmtCPF   = function($cpf) {
        $cpf = preg_replace('/\D+/', '', (string)$cpf ?? '');
        return strlen($cpf) === 11
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf)
            : $cpf;
    };

    $totalQuitacao = $valor;
    if(!empty($emprestimo->taxa_periodo) && !empty($emprestimo->qtd_parcelas)) {
        // juros simples sobre principal
        $totalQuitacao = $valor * (1 + $emprestimo->taxa_periodo * $emprestimo->qtd_parcelas);
    }
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Recibo de Confissão de Dívida — {{ $cliente->nome }}</title>
<style>
    @page {
        size: A4 portrait;
        margin: 2.5cm; /* Margens mais tradicionais para documentos */
    }
    body {
        font-family: 'Helvetica Neue', Arial, sans-serif;
        color: #333;
        margin: 0;
        font-size: 12px;
        display: flex;
        flex-direction: column;
        min-height: 100vh; /* Ocupa a altura total da página */
    }
    .container {
        width: 100%;
        margin: 0 auto;
    }
    header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 15px;
    }
    h1 {
        font-size: 22px;
        margin: 0;
        font-weight: 500;
        letter-spacing: 0.5px;
    }
    main {
        flex-grow: 1; /* Faz o conteúdo principal expandir e empurrar o rodapé */
    }
    p {
        line-height: 1.6;
        margin: 15px 0;
        text-align: justify;
    }
    .muted {
        color: #777;
        font-size: 11px;
    }

    /* Caixa de informações */
    .info-box {
        background-color: #f9f9f9;
        border: 1px solid #e0e0e0;
        padding: 18px;
        border-radius: 8px;
        margin: 25px 0;
    }
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 24px;
    }
    .info-grid div {
        display: flex;
        flex-direction: column;
    }
    .label {
        color: #555;
        font-size: 10px;
        text-transform: uppercase;
        margin-bottom: 2px;
    }
    .value {
        font-weight: bold;
        font-size: 14px;
    }

    /* Texto da declaração */
    .declaration-text {
        margin: 30px 0;
    }

    /* Local e Data */
    .date-location {
        text-align: right;
        margin-top: 40px;
        font-size: 12px;
    }

    /* Assinaturas */
    .signatures-container {
        display: flex;
        justify-content: space-between;
        gap: 50px; /* AUMENTADO: Mais espaço horizontal entre as assinaturas */
        margin-top: 100px; /* AUMENTADO: Mais espaço vertical acima das assinaturas */
    }
    .signature-block {
        flex: 1;
        text-align: center;
    }
    .signature-line {
        border-top: 1px solid #333;
        padding-top: 8px;
        font-size: 12px;
    }
    .signature-label {
        font-size: 11px;
        color: #555;
        line-height: 1.4; /* ADICIONADO: Melhora o espaçamento do texto abaixo da linha */
    }

    /* Rodapé */
    footer {
        margin-top: 40px;
        text-align: center;
        font-size: 10px;
        color: #888;
        padding-top: 15px;
        border-top: 1px solid #e0e0e0;
    }
    footer a {
        color: #555;
        text-decoration: none;
        font-weight: bold;
    }
    footer img {
        height: 16px;
        vertical-align: middle;
        margin-left: 3px;
    }
</style>
</head>
<body>
<div class="container">
    <header>
        <h1>RECIBO E INSTRUMENTO DE CONFISSÃO DE DÍVIDA</h1>
    </header>

    <main>
        <div class="info-box">
            <div class="info-grid">
                <div><span class="label">Credor(a):</span> <span class="value">{{ $credor['nome'] }}</span></div>
                @if(!empty($credor['cpf']))
                    <div><span class="label">CPF do(a) Credor(a):</span> <span class="value">{{ $fmtCPF($credor['cpf']) }}</span></div>
                @endif

                <div><span class="label">Devedor(a):</span> <span class="value">{{ $cliente->nome }}</span></div>
                @if(!empty($cliente->cpf))
                    <div><span class="label">CPF do(a) Devedor(a):</span> <span class="value">{{ $fmtCPF($cliente->cpf) }}</span></div>
                @endif

                <div><span class="label">Valor Principal:</span> <span class="value">{{ $fmtMoeda($valor) }}</span></div>
                @if(!empty($emprestimo->taxa_periodo))
                    <div><span class="label">Juros Contratados:</span>
                        <span class="value">{{ number_format($emprestimo->taxa_periodo*100,2,',','.') }}% a.m.</span>
                    </div>
                @endif

                <div><span class="label">Valor Total para Quitação:</span> <span class="value">{{ $fmtMoeda($totalQuitacao) }}</span></div>
                <div><span class="label">Data de Emissão:</span> <span class="value">{{ $hoje->format('d/m/Y') }}</span></div>
            </div>
        </div>

        <div class="declaration-text">
            <p>
                Pelo presente instrumento particular, <strong>{{ $credor['nome'] }}</strong>,
                @if(!empty($credor['cpf'])) inscrito(a) no CPF sob o nº {{ $fmtCPF($credor['cpf']) }}, @endif
                doravante denominado(a) <strong>CREDEOR(A)</strong>, e de outro lado, <strong>{{ $cliente->nome }}</strong>,
                @if(!empty($cliente->cpf)) inscrito(a) no CPF sob o nº {{ $fmtCPF($cliente->cpf) }}, @endif
                doravante denominado(a) <strong>DEVEDOR(A)</strong>, declaram para os devidos fins que o(a) DEVEDOR(A) reconhece e confessa ser devedor(a) da quantia de <strong>{{ $fmtMoeda($valor) }}</strong>, recebida a título de empréstimo.
            </p>
            <p>
                A dívida ora confessada, acrescida dos encargos contratuais, totaliza o montante de <strong>{{ $fmtMoeda($totalQuitacao) }}</strong>, valor este que o(a) <strong>DEVEDOR(A)</strong> se compromete a pagar nas condições pactuadas entre as partes.
            </p>
            <p class="muted">
                Este instrumento serve como prova da relação creditícia e poderá ser utilizado para fins legais de cobrança em caso de inadimplemento, em conjunto com o contrato ou cronograma de pagamentos correspondente.
            </p>
        </div>

        @if(!empty($credor['cidade']) || !empty($credor['uf']))
            <p class="date-location">
                {{ $credor['cidade'] ?? '' }}{{ !empty($credor['cidade']) && !empty($credor['uf']) ? ' - ' : '' }}{{ $credor['uf'] ?? '' }},
                {{ $hoje->translatedFormat('d \d\e F \d\e Y') }}.
            </p>
        @endif

        <div class="signatures-container">
            <div class="signature-block">
                <div class="signature-line">{{ $credor['nome'] }}</div>
                <div class="signature-label">CREDEOR(A) @if(!empty($credor['cpf'])) <br>CPF {{ $fmtCPF($credor['cpf']) }} @endif</div>
            </div>
            <div class="signature-block">
                <div class="signature-line">{{ $cliente->nome }}</div>
                <div class="signature-label">DEVEDOR(A) @if(!empty($cliente->cpf)) <br>CPF {{ $fmtCPF($cliente->cpf) }} @endif</div>
            </div>
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-label">TESTEMUNHA<br>Nome e CPF</div>
            </div>
        </div>
    </main>

    <footer>
        Este recibo foi gerado eletronicamente. Para validade legal, deve ser assinado pelas partes.
        Recomenda-se a assinatura digital via
        <a href="https://www.gov.br/pt-br/servicos/assinatura-eletronica" target="_blank">
            Assinador ITI
            <img src="https://barra.sistema.gov.br/v1/assets/govbr.webp" alt="GOV.BR">
        </a>
    </footer>
</div>
</body>
</html>