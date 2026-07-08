@if ($route = \App\Domains\Wallet\Enums\TopupSettlementRoute::resolve($transaction))
<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium border {{ $route->badgeClasses($transaction) }}">
    {{ $route->label() }}
</span>
@if ($detail = $route->detail($transaction))
<p class="text-[10px] mt-1 {{ $route === \App\Domains\Wallet\Enums\TopupSettlementRoute::StripeDeferred && ! $transaction->balance_settled_at ? 'text-amber-600' : 'text-slate-400' }}">
    {{ $detail }}
</p>
@endif
@endif
