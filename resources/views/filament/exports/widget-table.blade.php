<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #0A1228; background: #fff; }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 2px solid #0A1228;
            margin-bottom: 16px;
        }
        .brand { font-size: 14px; font-weight: 700; color: #0A1228; letter-spacing: -0.02em; }
        .brand span { color: #F59E0B; }
        .meta { font-size: 9px; color: #718096; text-align: right; }

        h1 { font-size: 13px; font-weight: 700; color: #0A1228; margin: 0 20px 12px; }

        table { width: calc(100% - 40px); margin: 0 20px; border-collapse: collapse; font-size: 10px; }
        thead tr { background: #0A1228; color: #FAF8F4; }
        thead th { padding: 6px 8px; text-align: left; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; font-size: 9px; }
        tbody tr:nth-child(even) { background: #F4F2ED; }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #EDE9E0; color: #2D3748; vertical-align: top; }

        .footer {
            margin: 20px 20px 0;
            padding-top: 8px;
            border-top: 1px solid #EDE9E0;
            font-size: 9px;
            color: #A0AEC0;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Oe<span>Parts</span></div>
        <div class="meta">
            Generated: {{ now()->format('d M Y, H:i') }}<br>
            OeParts Admin Export
        </div>
    </div>

    <h1>{{ $title }}</h1>

    <table>
        @if (!empty($headers))
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <span>OeParts — Confidential</span>
        <span>{{ $rows->count() }} rows exported</span>
    </div>
</body>
</html>
