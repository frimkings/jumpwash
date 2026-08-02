<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { color: #111; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p { margin: 0 0 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-weight: 700; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <p>{{ $report['start']->format('M d, Y') }} - {{ $report['end']->format('M d, Y') }} | Records: {{ $report['summary']['records'] }}</p>
    <table>
        <thead>
            <tr>
                @foreach ($report['headings'] as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['headings']) }}">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
