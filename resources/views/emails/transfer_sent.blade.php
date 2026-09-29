@extends('emails.layouts.base')

@section('title', 'Transferência enviada')

@section('content')
    @php
        $hasRecipient = filled($recipient);
        $hasDescription = filled($description);
    @endphp

    <h1>Transferência confirmada, {{ $customer->name }}!</h1>

    <p style="font-size:1.1rem;">
        Enviaste
        <strong>{{ $formattedAmount }} {{ $currency }}</strong>
        @if ($hasRecipient)
            para <strong>{{ $recipient }}</strong>.
        @else
            .
        @endif
    </p>

    <p style="font-size:1.05rem;">
        A transferência foi concluída com sucesso.
    </p>

    <div class="info-block">
        <strong>Resumo da transferência</strong><br>
        Montante enviado: {{ $formattedAmount }} {{ $currency }}<br>
        @if ($hasRecipient)
        Destinatário: {{ $recipient }}<br>
        @endif
        @if ($hasDescription)
        Descrição: {{ $description }}<br>
        @endif
        @if ($sentAt)
        Enviado em: {{ $sentAt->format('d/m/Y') }} às {{ $sentAt->format('H:i') }}<br>
        @endif
    </div>

    <p style="font-size:1.05rem; color:#3730a3; font-weight:500;">
        Obrigado por confiar na Yoop. Estamos aqui para tornar tua gestão de pagamentos mais simples.
    </p>

    <p style="color:#64748b; font-size:0.97rem; text-align:center; margin-top:18px;">
        Este é um e-mail automático. Se não reconhece esta transferência, contacte o nosso suporte.
    </p>
@endsection
