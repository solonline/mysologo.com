@php
    $config = match ($status) {
        'success' => [
            'title' => 'Email confirmado',
            'message' => $name
                ? "Olá, {$name}. O teu email foi validado. Podes continuar no app."
                : 'O teu email foi validado. Podes continuar no app.',
            'iconBg' => 'bg-green-100',
            'iconColor' => 'text-green-600',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'already_verified' => [
            'title' => 'Email já confirmado',
            'message' => $name
                ? "Olá, {$name}. Este email já estava validado."
                : 'Este email já estava validado.',
            'iconBg' => 'bg-amber-100',
            'iconColor' => 'text-amber-600',
            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        ],
        'not_found' => [
            'title' => 'Conta não encontrada',
            'message' => 'Não encontrámos uma conta associada a este link.',
            'iconBg' => 'bg-gray-100',
            'iconColor' => 'text-gray-400',
            'icon' => 'M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'invalid_hash' => [
            'title' => 'Link incorreto',
            'message' => 'O link não corresponde ao email da conta.',
            'iconBg' => 'bg-red-100',
            'iconColor' => 'text-red-600',
            'icon' => 'M6 18L18 6M6 6l12 12',
        ],
        default => [
            'title' => 'Link inválido',
            'message' => 'Este link de validação não é válido.',
            'iconBg' => 'bg-red-100',
            'iconColor' => 'text-red-600',
            'icon' => 'M6 18L18 6M6 6l12 12',
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $config['title'] }} — Yoop</title>
    @vite(['resources/css/app.css'])
    <style>
        @keyframes fade-scale-in {
            from { opacity: 0; transform: scale(.85); }
            to   { opacity: 1; transform: scale(1); }
        }
        .card-in { animation: fade-scale-in .4s ease-out both; }

        @keyframes draw-path {
            to { stroke-dashoffset: 0; }
        }
        .icon-draw path {
            stroke-dasharray: 40;
            stroke-dashoffset: 40;
            animation: draw-path .55s ease-out .1s forwards;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-sm bg-white rounded-2xl shadow-md overflow-hidden card-in">

    {{-- Header --}}
    <div class="bg-gray-900 px-6 py-5 flex items-center justify-between">
        <span class="text-xs text-gray-400 uppercase tracking-widest">Yoop</span>
        <img src="{{ asset('LOGO YOOP 2.png') }}" alt="Yoop" class="h-7 shrink-0">
    </div>

    {{-- Body --}}
    <div class="px-6 py-10 flex flex-col items-center gap-5 text-center">

        <div class="w-20 h-20 rounded-full {{ $config['iconBg'] }} flex items-center justify-center">
            <svg class="w-10 h-10 {{ $config['iconColor'] }} @if($status === 'success') icon-draw @endif" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $config['icon'] }}"/>
            </svg>
        </div>

        <div>
            <p class="text-xl font-semibold text-gray-900 mb-2">{{ $config['title'] }}</p>
            <p class="text-sm text-gray-500">{{ $config['message'] }}</p>
        </div>

        @if ($status === 'success')
            <a href="{{ config('app.url_frontend') }}"
               class="mt-2 inline-flex items-center justify-center rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-gray-800 transition-colors">
                Abrir a app
            </a>
        @endif

    </div>

    {{-- Rodapé --}}
    <div class="border-t border-gray-100 px-6 py-3 flex items-center justify-center gap-1.5">
        <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        <span class="text-xs text-gray-400">Pagamento seguro via</span>
        <img src="{{ asset('securepay.png') }}" alt="Yoop" class="h-8">
    </div>

</div>

</body>
</html>
