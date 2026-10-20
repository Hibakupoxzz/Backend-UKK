<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h2 { margin-bottom: 4px; }
        .meta { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h2>{{ $title }}</h2>
    <div class="meta">Dicetak {{ now()->format('d-m-Y H:i') }} oleh {{ $user }}</div>

    <table>
        <thead>
            <tr>@foreach ($headings as $h)<th>{{ $h }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>

    @if (! empty($footer))
        <p><strong>{{ $footer }}</strong></p>
    @endif
</body>
</html>
