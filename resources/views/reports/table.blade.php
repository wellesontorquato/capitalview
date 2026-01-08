<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Relatório' }}</title>
    <style>
        body{ font-family: DejaVu Sans, sans-serif; font-size:12px; color:#111; }
        h1{ font-size:18px; margin:0 0 8px; }
        .muted{ color:#555; }
        table{ width:100%; border-collapse:collapse; }
        th,td{ border:1px solid #ddd; padding:6px 8px; }
        th{ background:#f3f4f6; text-align:left; }
        .right{ text-align:right; }
        .center{ text-align:center; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Relatório' }}</h1>
    @isset($subtitle)
        <div class="muted" style="margin-bottom:10px">{{ $subtitle }}</div>
    @endisset

    <table>
        <thead>
            <tr>
                @foreach($columns as $c)
                    <th class="{{ $c['align'] ?? '' }}">{{ $c['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
                <tr>
                    @foreach($columns as $c)
                        @php $k = $c['key']; @endphp
                        <td class="{{ $c['align'] ?? '' }}">{{ $r[$k] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
