@extends('emails.layouts.base')

@section('title', 'Transferência recebida')

@section('content')
    @php
        $senderName = $transaction->bank_transfer_sender_name;
        $hasSender = filled($senderName);
        $creditedAt = $transaction->partner_funded_at ?? $transaction->stripe_created_at ?? $transaction->created_at;
    @endphp

    <h1>Boas notícias, {{ $customer->name }}!</h1>

    <p style="font-size:1.1rem;">
        Chegou uma transferência de
        <strong>{{ $formattedAmount }} {{ $currency }}</strong>
        à sua conta Yoop
        @if ($hasSender)
            , enviada por <strong>{{ $senderName }}</strong>.
        @else
            .
        @endif
    </p>

    <p style="font-size:1.05rem;">
        O valor já está disponível — pode usar o saldo na app, quando quiser!
    </p>

    <div class="info-block">
        <strong>Resumo da transferência</strong><br>
        Montante creditado: {{ $formattedAmount }} {{ $currency }}<br>
        @if ($hasSender)
        Remetente: {{ $senderName }}<br>
        @endif
        @if ($transaction->bank_transfer_reference)
        Referência: {{ $transaction->bank_transfer_reference }}<br>
        @endif
        @if ($creditedAt)
        Creditado em: {{ $creditedAt->format('d/m/Y') }} às {{ $creditedAt->format('H:i') }}<br>
        @endif
    </div>

    <p style="font-size:1.05rem; color:#3730a3; font-weight:500;">
        Obrigado por confiar na Yoop. Estamos aqui para tornar tua gestão de pagamentos mais simples.
    </p>

    <p style="color:#64748b; font-size:0.97rem; text-align:center; margin-top:18px;">
        Este é um e-mail automático. Se não reconhece esta transferência, contacte o nosso suporte.
    </p>
@endsection
