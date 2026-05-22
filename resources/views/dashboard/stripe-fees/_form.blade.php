@php $fee = $fee ?? null; @endphp

{{-- Identificação --}}
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Identificação</h2>
    </div>
    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Identificador <span class="text-red-400">*</span>
                <button type="button" onclick="document.getElementById('modal_payment_method_info').classList.remove('hidden')"
                        class="text-indigo-400 hover:text-indigo-600 transition" title="Ver identificadores Stripe">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </label>
                   <input type="text" name="payment_method" id="payment_method"
                          value="{{ old('payment_method', $fee?->payment_method ?? '') }}"
                          placeholder="Clique para selecionar um método..."
                          {{ $fee ? 'disabled' : '' }}
                          @unless($fee) onclick="document.getElementById('modal_payment_method_info').classList.remove('hidden')" @endunless
                          class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('payment_method') border-red-400 @enderror {{ $fee ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' }}">
                   @if($fee)
                       <input type="hidden" name="payment_method" value="{{ $fee->payment_method }}">
                   @else
                       <p class="mt-1.5 text-xs text-slate-400">Clique para selecionar da lista, ou escreva diretamente um identificador personalizado.</p>
                   @endif
            @error('payment_method')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Nome Amigável <span class="text-red-400">*</span>
            </label>
            <input type="text" name="label"
                   value="{{ old('label', $fee?->label) }}"
                   placeholder="ex: Cartão EEA, MB WAY"
                   class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('label') border-red-400 @enderror">
            @error('label')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Moeda <span class="text-red-400">*</span>
            </label>
            <select name="currency" id="currency_select"
                    class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl font-mono uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('currency') border-red-400 @enderror">
                @foreach($currencies as $currency)
                    <option value="{{ $currency }}"
                            {{ old('currency', $fee?->currency ?? 'EUR') === $currency ? 'selected' : '' }}>
                        {{ strtoupper($currency) }}
                    </option>
                @endforeach
            </select>
            @error('currency')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3 pt-5">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       {{ old('is_active', $fee?->is_active ?? true) ? 'checked' : '' }}
                       class="sr-only peer">
                <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                <span class="ml-2 text-sm font-medium text-slate-700">Ativo</span>
            </label>
        </div>
    </div>
</div>

{{-- Taxa Stripe (Referência) --}}
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Taxa Stripe <span class="normal-case font-normal text-slate-400">(referência)</span></h2>
        <p class="text-xs text-slate-400 mt-1">Usada apenas para estimativas no dashboard e simulação. O valor real vem do <code class="text-indigo-500">balance_transaction</code> após pagamento.</p>
    </div>
    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Percentagem
            </label>
            <div class="relative">
                <input type="number" name="stripe_fee_percentage" step="0.01" min="0" max="100"
                       value="{{ old('stripe_fee_percentage', $fee ? round($fee->stripe_fee_percentage * 100, 4) : '') }}"
                       placeholder="1.50"
                       class="w-full px-3 py-2 pr-8 text-sm bg-slate-50 border border-slate-200 rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('stripe_fee_percentage') border-red-400 @enderror">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">%</span>
            </div>
            @error('stripe_fee_percentage')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Valor Fixo
            </label>
            <div class="relative">
                <span id="currency_symbol_stripe" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">€</span>
                <input type="number" name="stripe_fee_fixed" step="0.01" min="0"
                       value="{{ old('stripe_fee_fixed', $fee?->stripe_fee_fixed ?? '') }}"
                       placeholder="0.25"
                       class="w-full pl-7 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('stripe_fee_fixed') border-red-400 @enderror">
            </div>
            @error('stripe_fee_fixed')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

{{-- Taxa Plataforma --}}
<div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">Taxa Plataforma</h2>
        <p class="text-xs text-slate-400 mt-1">Taxa adicional cobrada pela Yoop.</p>
    </div>
    <div class="px-6 py-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Percentagem <span class="text-red-400">*</span>
            </label>
            <div class="relative">
                <input type="number" name="platform_fee_percentage" step="0.01" min="0" max="100"
                       value="{{ old('platform_fee_percentage', $fee ? round($fee->platform_fee_percentage * 100, 4) : '') }}"
                       placeholder="1.00"
                       class="w-full px-3 py-2 pr-8 text-sm bg-slate-50 border border-slate-200 rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('platform_fee_percentage') border-red-400 @enderror">
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">%</span>
            </div>
            @error('platform_fee_percentage')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">
                Valor Fixo <span class="text-red-400">*</span>
            </label>
            <div class="relative">
                <span id="currency_symbol_platform" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">€</span>
                <input type="number" name="platform_fee_fixed" step="0.01" min="0"
                       value="{{ old('platform_fee_fixed', $fee?->platform_fee_fixed ?? '') }}"
                       placeholder="0.00"
                       class="w-full pl-7 pr-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 @error('platform_fee_fixed') border-red-400 @enderror">
            </div>
            @error('platform_fee_fixed')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

{{-- Modal: Identificadores Stripe --}}
<div id="modal_payment_method_info"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">Métodos de Pagamento Stripe</h3>
                <p class="text-xs text-slate-400 mt-0.5">
                    @unless($fee)Clique numa linha para selecionar o identificador e nome amigável.@else O identificador deve corresponder exatamente ao usado pela Stripe.@endunless
                </p>
            </div>
            <button type="button" onclick="document.getElementById('modal_payment_method_info').classList.add('hidden')"
                    class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto px-6 py-4">
            @php
            $stripeAvailability = $stripeAvailability ?? [];
            // [id, label, note, group]
            // Cartões: categorias internas (classificadas automaticamente no charge.updated)
            // Outros: identificador Stripe directo
            $stripeMethods = [
                // Cartões — categorias internas
                ['card_eea',          'Cartão EEA',            'Visa/MC/Amex emitidos na EEA (AT,BE,DE,ES,FR,PT...)', 'card'],
                ['card_uk',           'Cartão Reino Unido',    'Cartões emitidos no Reino Unido (GB)',                 'card'],
                ['card_international','Cartão Internacional',  'Cartões fora da EEA e do Reino Unido',                'card'],
                // Débito bancário
                ['sepa_debit',        'SEPA Direct Debit',     'Débito bancário em euros (Europa)',     'bank'],
                ['bank_transfer',     'Transferência Bancária','Transferência SEPA via VIBAN',          'bank'],
                ['us_bank_account',   'ACH Direct Debit',      'Débito bancário (EUA)',                 'bank'],
                ['acss_debit',        'Pre-authorized Debit',  'Débito pré-autorizado (Canadá)',        'bank'],
                ['becs_debit',        'BECS Direct Debit',     'Débito bancário (Austrália)',           'bank'],
                // Portugal
                ['multibanco',        'Multibanco',            'Referência gerada no ATM',              'local'],
                ['mb_way',            'MB WAY',                'Pagamento via app móvel',               'local'],
                // Europa local
                ['ideal',             'iDEAL',                 'Países Baixos',                        'local'],
                ['bancontact',        'Bancontact',            'Bélgica',                              'local'],
                ['eps',               'EPS',                   'Áustria',                              'local'],
                ['p24',               'Przelewy24',            'Polónia',                              'local'],
                ['giropay',           'Giropay',               'Alemanha',                             'local'],
                ['sofort',            'SOFORT',                'Europa — redirect banking',            'local'],
                // Carteiras digitais
                ['link',              'Link by Stripe',        'Carteira digital do Stripe',           'wallet'],
                ['paypal',            'PayPal',                'Carteira digital',                     'wallet'],
                ['revolut_pay',       'Revolut Pay',           'Carteira digital Revolut',             'wallet'],
                ['amazon_pay',        'Amazon Pay',            'Carteira digital Amazon',              'wallet'],
                ['cashapp',           'Cash App Pay',          'EUA',                                  'wallet'],
                // BNPL
                ['klarna',            'Klarna',                'Buy now, pay later',                   'bnpl'],
                ['afterpay_clearpay', 'Afterpay / Clearpay',   'Buy now, pay later (UK/AU/US)',        'bnpl'],
                // Regional
                ['boleto',            'Boleto',                'Brasil',                               'regional'],
                ['oxxo',              'OXXO',                  'México',                               'regional'],
                ['alipay',            'Alipay',                'China',                                'regional'],
                ['wechat_pay',        'WeChat Pay',            'China',                                'regional'],
                ['grabpay',           'GrabPay',               'Sudeste Asiático',                     'regional'],
                // Interno
                ['customer_balance',  'Saldo do Cliente',      'Saldo em conta no Stripe',             'internal'],
            ];
            @endphp

            @if(empty($stripeAvailability))
            <p class="mb-3 text-xs text-amber-600 bg-amber-50 rounded-xl px-3 py-2">
                Não foi possível obter dados da conta Stripe. A disponibilidade dos métodos não está disponível.
            </p>
            @endif

            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-slate-400 uppercase tracking-wider border-b border-slate-100">
                        <th class="pb-2 font-semibold">Identificador</th>
                        <th class="pb-2 font-semibold">Nome</th>
                        <th class="pb-2 font-semibold text-center">Disponível</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($stripeMethods as [$id, $name, $note, $group])
                    @php
                        $isCardCategory = $group === 'card';
                        $available = $isCardCategory ? null : ($stripeAvailability[$id] ?? null);
                    @endphp
                    <tr @unless($fee)
                            onclick="selectPaymentMethod('{{ $id }}', '{{ $name }}')"
                            class="hover:bg-indigo-50 cursor-pointer group"
                        @else
                            class="hover:bg-slate-50/50"
                        @endunless
                        title="{{ $note }}">
                        <td class="py-2.5 pr-3 font-mono font-medium {{ $isCardCategory ? 'text-violet-600' : 'text-indigo-600' }}">
                            {{ $id }}
                            @if($isCardCategory)
                                <span class="ml-1 text-[10px] font-sans text-violet-400 uppercase tracking-wide">interno</span>
                            @endif
                        </td>
                        <td class="py-2.5 pr-3 text-slate-700">{{ $name }}</td>
                        <td class="py-2.5 text-center">
                            @if($isCardCategory)
                                <span class="text-slate-300" title="Classificado automaticamente pelo país do cartão">auto</span>
                            @elseif($available === true)
                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400" title="Ativo na conta Stripe"></span>
                            @elseif($available === false)
                                <span class="inline-block w-2 h-2 rounded-full bg-slate-200" title="Inativo na conta Stripe"></span>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const symbols = { EUR: '€', USD: '$', GBP: '£', CHF: 'Fr', BRL: 'R$', JPY: '¥', CNY: '¥', CAD: '$', AUD: '$' };

    function updateSymbols(currency) {
        const symbol = symbols[currency.toUpperCase()] ?? currency.toUpperCase();
        document.getElementById('currency_symbol_stripe').textContent   = symbol;
        document.getElementById('currency_symbol_platform').textContent = symbol;
    }

    const select = document.getElementById('currency_select');
    if (select) {
        select.addEventListener('change', () => updateSymbols(select.value));
        updateSymbols(select.value);
    }

    window.selectPaymentMethod = function (id, name) {
        const methodInput = document.getElementById('payment_method');
        const labelInput  = document.querySelector('input[name="label"]');

        if (methodInput) methodInput.value = id;
        if (labelInput && !labelInput.value) labelInput.value = name;

        document.getElementById('modal_payment_method_info').classList.add('hidden');
    };
})();
</script>
