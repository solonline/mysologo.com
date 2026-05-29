<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Yoop</title>
    @vite(['resources/css/app.css'])
    <style>
        @keyframes fade-scale-in {
            from { opacity: 0; transform: scale(.85); }
            to   { opacity: 1; transform: scale(1); }
        }
        .card-in { animation: fade-scale-in .4s ease-out both; }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-6px); }
        }
        .icon-float { animation: float 3s ease-in-out infinite; }
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

        <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
            <svg class="w-10 h-10 text-gray-400 icon-float" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <div>
            <p class="text-5xl font-bold text-gray-900 mb-2">404</p>
            <p class="text-sm text-gray-500">Este link não existe ou já não está disponível.</p>
        </div>

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
