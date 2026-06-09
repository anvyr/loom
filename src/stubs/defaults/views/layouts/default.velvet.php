<!DOCTYPE html>
<html lang="{{ config('app.locale', 'en') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ $asset('css/app.css') }}">
</head>
<body>
    <main>
        {!! $content !!}
    </main>

    <footer>
        <span>&copy; {{ date('Y') }} {{ config('app.name', 'Anvyr Loom') }}</span>
        <span>Powered by Anvyr Loom</span>
    </footer>
</body>
</html>
