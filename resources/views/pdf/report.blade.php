<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf._styles')
    <style>
        .lines th, .lines td { font-size: 9px; padding: 5px 6px; }
    </style>
</head>
<body>
    <div class="footer">{{ $title }} · Generated {{ now()->format('d M Y H:i') }}</div>

    <h1>{{ $title }}</h1>
    <div class="muted" style="margin: 4px 0 14px;">{{ $subtitle }}</div>

    <table class="lines">
        <thead>
            <tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($headings) }}" class="muted">No records for this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($truncated)
        <div class="note muted">Only the first {{ number_format(count($rows)) }} rows are shown. Use the Excel or CSV export for the full list.</div>
    @endif
</body>
</html>
