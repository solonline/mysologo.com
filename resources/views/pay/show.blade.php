<!DOCTYPE html>
<html lang="{{ $paymentRequest->language ?? 'pt' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('pay.title', ['merchant' => $paymentRequest->merchant->name]) }}</title>
    @vite(['resources/css/app.css'])
    @if($clientSecret)
        <script src="https://js.stripe.com/v3/" defer></script>
    @endif
    <style>
        @keyframes draw-path {
            to { stroke-dashoffset: 0; }
        }
        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            20%      { transform: rotate(-14deg); }
            40%      { transform: rotate(14deg); }
            60%      { transform: rotate(-8deg); }
            80%      { transform: rotate(8deg); }
        }
        @keyframes slow-spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        @keyframes fade-scale-in {
            from { opacity: 0; transform: scale(.6); }
            to   { opacity: 1; transform: scale(1); }
        }
        .icon-draw path {
            stroke-dasharray: 40;
            stroke-dashoffset: 40;
            animation: draw-path .55s ease-out .1s forwards;
        }
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fade-in .5s ease-out both; }
        .icon-shake { animation: shake .55s ease-out .1s both; }
        .icon-spin  { animation: slow-spin 4s linear infinite; }
        .icon-fade  { animation: fade-scale-in .4s ease-out both; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-sm bg-white rounded-2xl shadow-md overflow-hidden">

    {{-- Banner modo teste --}}
    @if($paymentRequest->merchant->mode === 'test')
        <div class="bg-amber-400 px-6 py-2 flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-900 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <p class="text-xs font-semibold text-amber-900 uppercase tracking-widest">
                {{ __('pay.test_mode') }}
            </p>
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-gray-900 px-6 py-5">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">{{ __('pay.requested_by') }}</p>
                <h1 class="text-white text-lg font-semibold">{{ $paymentRequest->merchant->name }}</h1>
            </div>
            <img src="{{ asset('LOGO YOOP 2.png') }}" alt="Yoop" class="h-7 shrink-0">
        </div>
    </div>

    <div class="px-6 py-6 space-y-5">

        {{-- Valor --}}
        <div class="text-center">
            <p class="text-4xl font-bold text-gray-900">
                {{ number_format($paymentRequest->amount / 100, 2, ',', '.') }}
                <span class="text-xl font-medium text-gray-500">{{ $paymentRequest->currency }}</span>
            </p>
        </div>

        {{-- Items da venda --}}
        @if($paymentRequest->items)
            <details class="group">
                <summary class="flex items-center justify-between cursor-pointer list-none text-xs text-gray-400 uppercase tracking-widest select-none">
                    <span>{{ __('pay.items') }}</span>
                    <div class="flex items-center gap-1.5">
                        @if($paymentRequest->description)
                            <span class="text-xs text-gray-400 animate-fade-in normal-case tracking-normal">{{ $paymentRequest->description }}</span>
                        @endif
                        <svg class="w-3.5 h-3.5 shrink-0 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </summary>
                <div class="mt-3 space-y-2">
                    @foreach($paymentRequest->items as $item)
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="shrink-0 text-xs font-medium text-gray-400 bg-gray-100 rounded px-1.5 py-0.5">{{ $item['quantity'] }}×</span>
                                <span class="text-gray-700 truncate">{{ $item['name'] }}</span>
                            </div>
                            <span class="shrink-0 text-gray-800 font-medium ml-3">
                                {{ number_format(($item['unit_price'] * ($item['quantity'] ?? 1)) / 100, 2, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        <hr class="border-gray-100">

        {{-- Dados do payer --}}
        @if($paymentRequest->payer || $paymentRequest->description)
            <div class="space-y-1 text-sm justify-center text-center">
                @if($paymentRequest->payer)
                    <p class="text-gray-400 truncate">
                        {{ collect([$paymentRequest->payer->full_name, $paymentRequest->payer->email_address, $paymentRequest->payer->phone_number])->filter()->implode(' · ') }}
                    </p>
                @endif
            </div>
            <hr class="border-gray-100">
        @endif

        {{-- Estados terminais --}}
        @if($paymentRequest->status === 'completed')
            <div class="flex flex-col items-center gap-4 py-4">
                <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-green-500 icon-draw" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-800">{{ __('pay.status.completed') }}</p>
                    @if($paymentRequest->paid_at)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $paymentRequest->paid_at->format('d/m/Y H:i') }}</p>
                    @endif
                </div>
            </div>

        @elseif($paymentRequest->status === 'failed')
            <div class="flex flex-col items-center gap-4 py-4">
                <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-red-500 icon-shake" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-800 text-center">{{ __('pay.status.failed') }}</p>
            </div>

        @elseif($paymentRequest->status === 'expired')
            <div class="flex flex-col items-center gap-4 py-4">
                <div class="w-20 h-20 rounded-full bg-amber-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-amber-500 icon-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-800 text-center">{{ __('pay.status.expired') }}</p>
            </div>

        @elseif($paymentRequest->status === 'cancelled')
            <div class="flex flex-col items-center gap-4 py-4">
                <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-gray-400 icon-fade" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-800 text-center">{{ __('pay.status.cancelled') }}</p>
            </div>

        @elseif($awaitingConfirmation)
            {{-- A aguardar confirmação do webhook --}}
            <div class="flex flex-col items-center gap-4 py-4">
                <div class="w-20 h-20 rounded-full bg-blue-50 flex items-center justify-center">
                    <svg class="w-10 h-10 text-blue-400 icon-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-700 text-center">{{ __('pay.confirming') }}</p>
            </div>
            <script>setTimeout(() => location.replace(location.pathname), 30000);</script>

        @elseif($clientSecret)
            {{-- Stripe Payment Element --}}
            <div id="form-wrapper" class="space-y-4 relative">
                {{-- Overlay durante processamento --}}
                <div id="form-overlay" class="hidden absolute inset-0 bg-white/80 rounded-xl flex items-center justify-center z-10">
                    <svg class="w-8 h-8 text-gray-400 icon-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <form id="payment-form" class="space-y-4">
                    <div id="payment-element"></div>
                    <div id="payment-message" class="hidden text-sm text-red-600 text-center"></div>
                    <button
                        id="submit-btn"
                        type="submit"
                        class="w-full bg-gray-900 hover:bg-gray-700 disabled:opacity-50 text-white text-sm font-medium rounded-xl py-3 transition-colors"
                    >
                        <span id="btn-text">
                            {{ __('pay.pay_button', [
                                'amount'   => number_format($paymentRequest->amount / 100, 2, ',', '.'),
                                'currency' => $paymentRequest->currency,
                            ]) }}
                        </span>
                        <span id="btn-spinner" class="hidden">{{ __('pay.processing') }}</span>
                    </button>
                </form>
            </div>

            @if($paymentRequest->expires_at)
                <p class="text-center text-xs text-gray-400 mb-0">
                    {{ __('pay.valid_until', ['date' => $paymentRequest->expires_at->format('d/m/Y H:i')]) }}
                </p>
            @endif

            <script>
                document.addEventListener('DOMContentLoaded', async () => {
                    const stripe   = Stripe('{{ $publishableKey }}');
                    const elements = stripe.elements({ clientSecret: '{{ $clientSecret }}' });

                    const paymentElement = elements.create('payment', {
                        fields: {
                            billingDetails: {
                                address: { country: 'never' },
                            },
                        },
                    });
                    paymentElement.mount('#payment-element');

                    const form      = document.getElementById('payment-form');
                    const overlay   = document.getElementById('form-overlay');
                    const submitBtn = document.getElementById('submit-btn');
                    const btnText   = document.getElementById('btn-text');
                    const btnSpinner = document.getElementById('btn-spinner');
                    const msgBox    = document.getElementById('payment-message');

                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();

                        overlay.classList.remove('hidden');
                        submitBtn.disabled = true;
                        btnText.classList.add('hidden');
                        btnSpinner.classList.remove('hidden');
                        msgBox.classList.add('hidden');

                        const { error } = await stripe.confirmPayment({
                            elements,
                            confirmParams: {
                                return_url: window.location.href,
                                payment_method_data: {
                                    billing_details: {
                                        address: { country: '{{ $billingCountry }}' },
                                    },
                                },
                            },
                        });

                        if (error) {
                            overlay.classList.add('hidden');
                            msgBox.textContent = error.message;
                            msgBox.classList.remove('hidden');
                            submitBtn.disabled = false;
                            btnText.classList.remove('hidden');
                            btnSpinner.classList.add('hidden');
                        }
                    });
                });
            </script>
        @endif

    </div>

    {{-- Rodapé --}}
    <div class="border-t border-gray-100 px-6 py-3 flex items-center justify-center">
        <span class="text-xs text-gray-400 mr-1">{{ __('pay.secure') }}</span>
        <a href="https://www.securepay.com" target="_blank" rel="noopener noreferrer">
            <img src="{{ asset('securepay1.png') }}" alt="SecurePay" class="h-8">
        </a>
    </div>

</div>

</body>
</html>
