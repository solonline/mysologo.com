<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Yoop Docs</title>
    <link rel="icon" href="{{ asset('yoop.jpeg') }}" type="image/jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @include('docs.partials.styles')
    @stack('head')
</head>
<body>
    @include('docs.partials.header', [
        'title' => $title,
        'description' => $description ?? null,
        'navActive' => $navActive ?? null,
    ])

    @yield('content')

    @stack('scripts')
</body>
</html>
