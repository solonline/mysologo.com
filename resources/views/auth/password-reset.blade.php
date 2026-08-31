@php
    $config = match ($status) {
        'success' => [
            'title' => 'Senha atualizada',
            'message' => 'A tua senha foi redefinida. Podes continuar no app.',
            'iconBg' => 'bg-green-100',
            'iconColor' => 'text-green-600',
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'invalid' => [
            'title' => 'Link inválido',
            'message' => 'Este link de redefinição não é válido ou já foi utilizado. Pede um novo a partir da app.',
            'iconBg' => 'bg-red-100',
            'iconColor' => 'text-red-600',
            'icon' => 'M6 18L18 6M6 6l12 12',
        ],
        default => [
            'title' => 'Redefinir senha',
            'message' => 'Escolhe uma nova senha para a tua conta.',
            'iconBg' => 'bg-indigo-100',
            'iconColor' => 'text-indigo-600',
            'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
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
        [x-cloak] { display: none !important; }
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

    <div class="bg-gray-900 px-6 py-5 flex items-center justify-between">
        <span class="text-xs text-gray-400 uppercase tracking-widest">Yoop</span>
        <img src="{{ asset('LOGO YOOP 2.png') }}" alt="Yoop" class="h-7 shrink-0">
    </div>

    <div class="px-6 {{ $status === 'form' ? 'pt-8 pb-8' : 'py-10' }} flex flex-col {{ $status === 'form' ? 'items-stretch' : 'items-center' }} gap-5 {{ $status === 'form' ? '' : 'text-center' }}">

        <div class="w-20 h-20 rounded-full {{ $config['iconBg'] }} flex items-center justify-center {{ $status === 'form' ? 'mx-auto' : '' }}">
            <svg class="w-10 h-10 {{ $config['iconColor'] }} @if($status === 'success') icon-draw @endif" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $config['icon'] }}"/>
            </svg>
        </div>

        <div class="{{ $status === 'form' ? 'text-center' : '' }}">
            <p class="text-xl font-semibold text-gray-900 mb-2">{{ $config['title'] }}</p>
            <p class="text-sm text-gray-500">{{ $config['message'] }}</p>
        </div>

        @if ($status === 'form')
            @if ($errors->any())
                <div class="px-4 py-3 bg-red-50 border border-red-100 rounded-xl">
                    <p class="text-sm text-red-600">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                        Email
                    </label>
                    <input type="email" value="{{ $email }}" readonly
                           class="w-full px-3.5 py-2.5 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-xl">
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                        Nova senha
                    </label>
                    <div class="relative" x-data="{ show: false }">
                        <input id="password" :type="show ? 'text' : 'password'" name="password"
                               autocomplete="new-password" required autofocus minlength="8"
                               class="w-full px-3.5 py-2.5 pr-10 text-sm text-gray-900 bg-white border border-gray-200 rounded-xl
                                      placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500
                                      transition @error('password') border-red-400 @enderror"
                               placeholder="Mínimo 8 caracteres">
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                        Confirmar senha
                    </label>
                    <div class="relative" x-data="{ show: false }">
                        <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation"
                               autocomplete="new-password" required minlength="8"
                               class="w-full px-3.5 py-2.5 pr-10 text-sm text-gray-900 bg-white border border-gray-200 rounded-xl
                                      placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500
                                      transition"
                               placeholder="Repete a nova senha">
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-2.5 bg-gray-900 hover:bg-gray-800 text-white text-sm font-medium rounded-xl transition">
                    Redefinir senha
                </button>
            </form>
        @endif

    </div>

    <div class="border-t border-gray-100 px-6 py-3 flex items-center justify-center gap-1.5">
        <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        <span class="text-xs text-gray-400">Pagamento seguro via</span>
        <img src="{{ asset('securepay.png') }}" alt="Yoop" class="h-8">
    </div>

</div>

@if ($status === 'form')
    <script src="//unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
@endif

</body>
</html>
