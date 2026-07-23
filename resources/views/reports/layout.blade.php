<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
    .header h1 { font-size: 16px; margin: 0 0 2px; }
    .header p { margin: 0; font-size: 11px; color: #444; }
    .report-title { text-align: center; font-size: 14px; font-weight: bold; margin: 10px 0 16px; text-decoration: underline; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; font-size: 11px; }
    th { background: #eee; }
    .text-center { text-align: center; }
    .signatures td { border: none; text-align: center; padding-top: 30px; }
    .sig-line { border-top: 1px solid #333; display: inline-block; width: 140px; margin-bottom: 4px; }
</style>
</head>
<body>
    <div class="header">
        <h1>{{ $data['branch']->name ?? 'School' }}</h1>
        @if(($data['branch']->address ?? null))<p>{{ $data['branch']->address }}</p>@endif
    </div>
    <div class="report-title">{{ $title }}</div>
    @yield('content')
</body>
</html>
